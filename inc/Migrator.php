<?php

include_once 'MigratorResponse.php';

define('MIGRATOR_DEBUG', false);

class Migrator {
	/** Configuration. Must have two entries: 'db' and 'authors' */
	private $config = array(
		'db' => array(
			'host' => 'localhost',
			'dbname' => 'wordpress',
			'user' => 'root',
			'password' => 'root',
			'table_prefix' => 'wp_'
		),
		'authors' => array(),
		'approve_comments' => true
	);

	/**
	 * Last added items
	 */
	private $last = array(
		'post' => NULL,
		'comment' => NULL,
		'term' => NULL,
		'term_taxonomy' => NULL
	);

	/** Database instance */
	private $db = NULL;

	/**
	 * Pretty standard configuration method...
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 * @return mixed $value if passed or the value associated to the key
	 */
	public function config($key, $value = NULL) {
		if ( func_num_args() > 1 ) {
			$this->config[$key] = $value;
			return $value;
		}

		return isset($this->config[$key]) ? $this->config[$key]: NULL;
	}

	/**
	 * Some persistent vars, last post/tag/etc inserted
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return mixed $value if passed or the value associated to the key
	 */
	public function last($key, $value = NULL) {
		if ( func_num_args() > 1 ) {
			$this->last[$key] = $value;
			return $value;
		}

		return isset($this->last[$key]) ? $this->last[$key]: NULL;
	}



	/**
	 * Init this shit
	 */
	public function __construct(array $config) {
		$this->config('db', $config['db']);
		$this->config('authors', $config['authors']);
		$this->connect();
	}

	/**
	 * Connect to database, throwing appropiate exceptions
	 *
	 * @return void
	 */
	private function connect() {
		if ( ! $config = $this->config('db') ) {
			throw new Exception("Database configuration not found", 1);
		}

		if ( ! class_exists('PDO') ) {
			throw new Exception("PDO module must be installed", 1);
		}

		if ( ! in_array('mysql', PDO::getAvailableDrivers()) ) {
			throw new Exception("PDO MySQL must be installed", 1);
		}

		$this->db = new PDO("mysql:host={$config['host']};dbname={$config['dbname']}", $config['user'], $config['password']);
		$this->db->query("SET NAMES 'utf8'");
	}

	/**
	 * Get the correct table name
	 *
	 * @param string $table
	 *
	 * @return string table name using prefix
	 */
	private function table($table) {
		$config = $this->config('db');
		return (isset($config['table_prefix']) ? $config['table_prefix'] : 'wp_') . $table;
	}

	/**
	 * Init the migration... check if connection works and all that stuff
	 */
	public function initMigration() {
		$this->db->query('ALTER TABLE `' . $this->table('comments') . '` ADD `comment_author_image` VARCHAR(200) NOT NULL');

		return new MigratorResponse();
	}

	/**
	 * End the migration... Calculate fields and that things
	 */
	public function finishMigration() {
		$this->db->query(sprintf(
			'UPDATE `%1$s` SET `%1$s`.`comment_count` = SELECT COUNT(*) FROM `%2$s` WHERE `%2$s`.`post_id` = `%1s`.`ID`',
				$this->table('posts'),
				$this->table('comments')
			)
		);

		$this->db->query(sprintf(
			'UPDATE `%1$s` SET `%1$s`.`count` = SELECT COUNT(*) FROM `%$2s` WHERE `%$2s`.`term_taxonomy_id` = `%$1s``term_taxonomy_id`',
				$this->table('term_taxonomy'),
				$this->table('term_relationships')
		));
	}

	/**
	 * Add a post
	 *
	 * @param stdClass $post
	 *
	 * $post can have tags and comments, but it's not mandatory
	 *
	 */
	public function addPost($post) {
		$response = new MigratorResponse();
		$authors_config = $this->config('authors');
		$stmt = $this->db->prepare('INSERT INTO `' . $this->table('posts') . '` (`ID`, `post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`, `post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`, `post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`, `post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`, `post_mime_type`, `comment_count`) VALUES
																	  (NULL, :post_author,   :fecha,      :fecha,          :content,       :title,     \'\',             :status,       \'open\',           \'open\',        \'\',              :name,       \'\',        \'\',       :modified,       :modified,           \'\',                      \'0\',           \'\',     \'0\',          \'post\',      \'\',               0)');

		$args = array(
			':post_author' => isset($authors_config[$post->author->name]) ? $authors_config[$post->author->name]['wp_id'] : 1,
			':fecha' => date("Y-m-d H:i:s", strtotime($post->published)),
			':content' => $post->content,
			':title' => $post->title,
			':modified' => date("Y-m-d H:i:s", strtotime($post->updated)),
			':name' => is_string($post->name) ? $post->name : '',
			':status' => $post->status
		);

		if ( ! $stmt->execute($args) ) {
			return $response->addError('Post couldn\'t be created');
		}

		$post_id = $this->last('post', $this->db->lastInsertId());

		$response->addParam('id', $post_id);

		if ( isset($post->tags) ) {
			foreach ( $post->tags as $tag ) {
				if ( ! $this->addTag($tag) ) {
					$response->addError(array(
						'fatal' => false,
						'message' => "Tag {$tag} could'nt be set for post {$post_id}, add manually if neccessary"
					));
				}
			}
		}

		if ( isset($post->comments) ) {
			foreach ( $post->comments as $comment ) {
				if ( ! $this->addComment($comment) ) {
					$response->addError(array(
						'fatal' => false,
						'message' => "Comment couldn't be added to {$post_id}, skipping new comments"
					));
					break;
				}
			}
		}

		return $response;
	}

	/**
	 * Create a term passing the name
	 *
	 * @param string $tag
	 *
	 * @return stdClass $term
	 */
	public function createTag($tag) {
		$ret = new stdClass();
		$stmt = $this->db->prepare(sprintf('INSERT INTO `%s` (`name`, `slug`) VALUES (:name, :slug)', $this->table('terms')));
		$term_taxonomy_stmt = $this->db->prepare(sprintf('INSERT INTO `%s` (`term_id`, `taxonomy`, `count`) VALUES (:id, \'post_tag\', \'0\')', $this->table('term_taxonomy')));
		$slug = strtolower(str_replace(' ', '-', $tag));

		$args = array(
			':name' => $tag,
			':slug' => $slug
		);

		if ( ! $stmt->execute($args) ) {
			throw new Exception("Insert term {$tag} failed: " . print_r($stmt->errorInfo(), true), 1);
		}

		$ret->name = $tag;
		$ret->slug = $slug;
		$ret->id = $this->last('term', $this->db->lastInsertId());

		if ( ! $term_taxonomy_stmt->execute(array(':id' => $this->last('term'))) ) {
			throw new Exception("Insert term taxonomy for {$this->last('term')} failed: " . print_r($term_taxonomy_stmt->errorInfo(), true), 1);
		}

		$this->last('term_taxonomy', $this->db->lastInsertId());

		return $ret;
	}

	/**
	 * Get or create tag by name
	 *
	 * @param string $tag
	 *
	 * @return stdClass
	 */
	public function getOrCreateTag($tag) {
		$tag = trim($tag);
		$stmt = $this->db->prepare(sprintf('SELECT `term_id` FROM `%s` WHERE `name` = :name', $this->table('terms')));
		$term_taxonomy_stmt = $this->db->prepare(sprintf('SELECT `term_taxonomy_id` FROM `%s` WHERE `term_id` = :term_id', $this->table('term_taxonomy')));

		// Assume correct execution
		// TODO: refactor this
		$stmt->execute(array(
			':name' => $tag
		));

		$term = $stmt->fetchObject('stdClass');

		if ( ! $term ) {
			$this->createTag($tag);
			$term_taxonomy = new stdClass();
			$term_taxonomy->term_taxonomy_id = $this->last('term_taxonomy');
		} else {
			// Assume correct
			$term_taxonomy_stmt->execute(array(
				':term_id' => $term->term_id
			));

			$term_taxonomy = $term_taxonomy_stmt->fetchObject('stdClass');
		}

		return $term_taxonomy;
	}

	/**
	 * Add a tag to a post
	 *
	 * If p_id is set we assume we must return a response, else we return a boolean to check if creation succeded
	 *
	 * @param string $tag
	 * @param int $p_id
	 *
	 * @return boolean|MigratorResponse
	 */
	public function addTag($tag, $p_id = NULL) {
		$stmt = $this->db->prepare(sprintf('INSERT INTO `%s` (`object_id`, `term_taxonomy_id`) VALUES (:post_id, :term_taxonomy_id)', $this->table('term_relationships')));
		$post_id = $p_id ? $p_id : $this->last('post');
		$term_taxonomy = $this->getOrCreateTag($tag);

		$args = array(
			':post_id' => $post_id,
			':term_taxonomy_id' => $term_taxonomy->term_taxonomy_id
		);

		if ( $p_id === NULL ) {
			$ret = $stmt->execute($args);

			if ( MIGRATOR_DEBUG && ! $ret ) {
				var_dump($tag, $post_id, $term_taxonomy, $stmt->errorInfo());
			}

			return $ret;
		}

		$response = new MigratorResponse();

		if ( ! $stmt->execute($args) ) {
			$response->addError("Tag {$tag} couldn't be added to post {$p_id}: " . print_r($stmt->errorInfo(), true));
		}

		return $response;
	}

	/**
	 * Add a comment to a post
	 *
	 * If p_id is set we assume we must return a response, else we return a boolean to check if creation succeded
	 *
	 * @param stdClass $comment
	 * @param int $p_id
	 * @param int $parent_id
	 *
	 * @return boolean|MigratorResponse
	 */
	public function addComment($comment, $p_id = NULL, $parent_id = 0) {
		$post_id = $p_id ? $p_id : $this->last('post');
		$authors_config = $this->config('authors');
		$stmt = $this->db->prepare('INSERT INTO `' . $this->table('comments') . '` (`comment_ID`,`comment_post_ID`, `comment_author`, `comment_author_email`, `comment_author_url`, `comment_author_IP`, `comment_date`, `comment_date_gmt`, `comment_content`, `comment_karma`, `comment_approved`, `comment_agent`, `comment_type`, `comment_parent`, `user_id`, `comment_author_image`) VALUES (
																					NULL,        :post_id,           :author_name,      :author_email,           :author_url,           \'\',                  :fecha,         :fecha,             :content,          \'0\',             :approved,          \'\',              \'\',             :parent,          :user_id,   :author_image)');

		$author_name = $comment->author->name;

		$user_id = 0;

		if ( isset($authors_config[$author_name]) ) {
			$author = $authors_config[$author_name];
			$user_id = $author['wp_id'];
			$comment->author->image = '';
			$comment->author->email = $author['email'];
			$comment->author->uri = $author['url'];
		}

		$args = array(
			':post_id' => $post_id,
			':author_name' => $comment->author->name,
			':author_email' => $comment->author->email,
			':author_url' => $comment->author->uri,
			':fecha' => date("Y-m-d H:i:s", strtotime($comment->published)),
			':content' => $comment->content,
			':approved' => $this->config('approve_comments'),
			':parent' => $parent_id,
			':user_id' => $user_id,
			':author_image' => $comment->author->image
		);

		$response = new MigratorResponse();

		if ( ! $stmt->execute($args) ) {
			$response->addError("Comment couldn't be added: " . print_r($stmt->errorInfo(), true));
		} else {
			$this->last('comment', $this->db->lastInsertId());
			if ( isset($comment->replies) ) {
				foreach ( $comment->replies as $reply ) {
					$parent = $this->last('comment');
					if ( ! $this->addComment($reply, $p_id, $parent) ) {
						$response->addError(array(
							'fatal' => false,
							'message' => "Error inserting reply to comment: {$parent}"
						));
					}
				}
			}
		}
		if ( $p_id !== NULL && $parent_id === 0 ) {
			return $response;
		}

		if ( MIGRATOR_DEBUG ) {
			var_dump($stmt->errorInfo());
		}
		return ! $response->hasError();
	}
}

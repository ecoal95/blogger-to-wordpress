(function(window, undefined) {
	/** @const */ var ID_REG = /\.post-([0-9]+)/;

	Array.prototype.findBy = function(key, val) {
		var i = 0,
			len = this.length;

		for ( ; i < len; i++ ) {
			if ( typeof(this[i]) === "object" && this[i] !== null ) {
				if ( this[i][key] === val ) {
					return this[i];
				}
			}
		}

		return null;
	}


	Date.prototype.toWPGMT = (function() {
		var pad = function(num){
			return num < 10 ? "0" + num : num.toString();
		}

		return function() {
			var yyyy = this.getFullYear().toString(),
				mm = pad(this.getMonth()+1),
				dd  = pad(this.getDate()),
				hh = pad(this.getHours()),
				mns = pad(this.getMinutes()),
				ss = pad(this.getSeconds());
			return [yyyy, mm, dd].join("-") + ' ' + [hh, mns, ss].join(":");
		};
	}());


	var getAuthor = function(el){
		var aut = el.querySelector('author');
		return {
			name: aut.querySelector('name').textContent,
			uri: aut.querySelector('uri') ? aut.querySelector('uri').textContent : '',
			email: aut.querySelector('email').textContent,
			image: aut.querySelector('[src]').getAttribute('src')
		}
	}

	var getTags = function getTags(cat){
		var tags = []
		// La primera es el tipo (post)
		for( var i = 1; i < cat.length; i++){
			tags.push(cat[i].getAttribute('term'))
		}
		return tags;
	}

	var BloggerParser = function(doc) {
		this.document = doc;
	}

	// [blogger_id] => post
	BloggerParser.prototype.posts = []

	BloggerParser.prototype.parse = function(){
		var els = this.document.getElementsByTagName('entry'),
			len = els.length,
			i = 0,
			term;

		this.posts = [];

		// Escribir primero los posts y luego los comentarios, para evitar errores
		for ( ; i < len; i++){
			term = els[i].querySelector('category').getAttribute('term');
			if( /\#post$/.test(term) ) {
				this.parsePost(els[i]);
			}
		}

		for( i = 0; i < len; j++ ) {
			term = els[i].querySelector('category').getAttribute('term');
			if( /\#comment$/.test(term) ) {
				this.parseComment(els[i]);
			}
		}

		return this.posts;
	}

	/**
	 * Parse a post and add it to the posts array
	 */
	BloggerParser.prototype.parsePost = function(el){
		var post = {
			id: el.querySelector('id').textContent.match(ID_REG)[1],
			link: el.querySelector('link[rel="alternate"]'),
			published: new Date(el.querySelector('published').textContent).toWPGMT(),
			updated: new Date(el.querySelector('updated').textContent).toWPGMT(),
			tags: getTags(el.querySelectorAll('category')),
			title: el.querySelector('title').textContent,
			image: el.querySelector('[url]') ? el.querySelector('[url]').getAttribute('url') : null,
			author: getAuthor(el),
			content: el.querySelector('content').textContent,
			status: "publish",
			comments: []
		};

		if( post.link ){
			post.link = post.link.getAttribute('href');
			post.name = post.link.match(/\/([^\/]+)\.html/)[1];
		} else {
			post.status = "draft";
			post.name = post.title.toLowerCase().replace(/\s/g, "-");
		}

		this.posts.push(post);
	}

	/**
	 * Parse a comment and add it to the post
	 */
	BloggerParser.prototype.parseComment = function(el) {
		var postId = el.querySelector('link[rel=edit]').getAttribute('href').match(/\/([0-9]+)\/comments\/default/)[1],
			id = el.querySelector('id').textContent.match(ID_REG)[1],
			comment = {
				id: id,
				title: el.querySelector('title').textContent,
				content: el.querySelector('content').textContent,
				author: getAuthor(el),
				published: new Date(el.querySelector('published').textContent).towpGMT(),
				updated: new Date(el.querySelector('updated').textContent).towpGMT(),
			},
			post,
			href;

		post = this.posts.findBy('id', postId);

		if( ! post ) {
			console.log("El post " + postId + " no existe. Saltando comentario " + id );
			return;
		}

		if ( el.querySelector('link[rel=related]') ) {
			try{
				post.comments
					.findBy('id', el.querySelector('link[rel=related]').getAttribute('href').match(/([0-9]+)$/)[1])
					.replies.push(comment);
			} catch(e) {
				console.log("No se encontró el comentario padre de " + id)
			}
			return;
		} else {
			comment.replies = [];
			post.comments.push(comment);
		}
	}


	window.BloggerParser = BloggerParser;
} (window));

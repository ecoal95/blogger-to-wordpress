<?php
class MigratorResponse {
	/** Errors array */
	private $messages = array();

	/** Response params */
	private $params = array();

	public function __construct() {}

	/**
	 * Add a param to the response
	 */
	public function addParam($key, $value) {
		$this->params[$key] = $value;
	}

	/**
	 * Return if any of the errors is fatal
	 *
	 * @return boolean
	 */
	public function isFatalError() {
		foreach ( $this->messages as $message ) {
			if ( $message['type'] === 'error' && $message['fatal'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return if response has any errors
	 *
	 * @return boolean
	 */
	public function hasError() {
		foreach ( $this->messages as $msg ) {
			if ( $msg['type'] === 'error' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add a message
	 *
	 * @param string type
	 * @param string|array $params
	 */
	public function addMessage($type, $params) {
		$msg = array(
			'type' => $type
		);

		if ( is_array($params) ) {
			$msg = array_merge($msg, $params);
		} else {
			$msg['message'] = $params;
		}

		$this->messages[] = $msg;
		return $this;
	}

	/**
	 * Add an error
	 *
	 * @param string|array $message
	 */
	public function addError($message) {
		return $this->addMessage('error', $message);
	}

	public function __toString() {
		$ret = $this->params;

		if ( ! count($this->messages) ) {
			$this->addMessage('success', 'Operation completed');
		}

		$ret['messages'] = $this->messages;

		$ret['fatal'] = $this->isFatalError();

		return json_encode($ret);
	}

}

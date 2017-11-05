<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

class BoResponseData {
	private $jsonMode = false;
	private $needRender = false;
	private $headers = ['Vary' => 'Accept-Encoding'];
	private $content = '';
	private $status;
	private $params;

	private $status_map = [
		200 => 'OK',
		204 => 'No Content',

		301 => 'Moved Permanently',
		302 => 'Found',
		304 => 'Not Modified',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',

		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		410 => 'Gone',
		429 => 'Too Many Requests',
		451 => 'Unavailable For Legal Reasons',

		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		503 => 'Service Unavailable',
	];

	public static function createDefault() {
		$data = new self();
		return $data;
	}

	public function content($content) {
		$this->content = strval($content);
		$this->params = null;
		$this->needRender = false;
		return $this;
	}

	/**
	 * @param array|Params $model
	 * @param bool $convert
	 * @return ResponseData
	 */
	public function params($model = [], $convert = false) {
		if ($convert && is_array($model)) {
			$this->params = new Params($model);
		} else {
			$this->params = $model;
		}

		$this->needRender = true;
		return $this;
	}

	public function getParam($key) {
		if ($this->params == null) {
			$this->params = new Params();
		}
		return isset($this->params[$key]) ? $this->params[$key] : null;
	}

	public function appendParam($key, $value) {
		if ($this->params == null) {
			$this->params = new Params();
		}
		$this->params[$key] = $value;
		$this->needRender = true;
		return $this;
	}

	/**
	 * set status
	 * @param int $status short for usual code "200", "404" etc. or full as "503 Service Unavailable"
	 * @return $this for chaining
	 * @throws Exception when set wrong status code (as not in $status_map).
	 */
	public function status($status) {
		if (!isset($this->status_map[$status])) {
			throw new Exception("Unknown Status: {$status}");
		}
		$this->status = "{$status} {$this->status_map[$status]}";
		return $this;
	}

	/**
	 * set header
	 * @param string $key both key and value will be trim
	 * @param string $value
	 * @return $this for chaining
	 * @throws Exception when any param contains new line.
	 */
	public function header($key, $value) {
		if (preg_match("/[\n\r]/", $key . $value)) {
			throw new Exception('Header should not contains new line (\r,\n)');
		}
		//by default override the prev set.
		$this->headers[trim($key)] = trim($value);
		return $this;
	}

	/*
	 * set Content-Type header
	 * @param string $type
	 */
	public function contentType($type) {
		$this->header('Content-Type', $type);
		return $this;
	}

	public function jsonmode($mode = true) {
		$this->jsonMode = $mode;
		return $this;
	}

	private function renderContent() {
		if (!$this->needRender) {
			return $this;
		}

		if ($this->jsonMode) {
			return $this->content(json_stringify($this->params));
		}

		return $this;
	}

	public function __get($name) {
		if ($name === 'content' && $this->needRender) {
			$this->renderContent();
		}

		return $this->$name;
	}

	public function prepareForSend($content = null, $status = '') {
		if ($this->jsonMode) {
			$this->contentType('application/json; charset=utf-8');
			$content = isset($content) ? json_stringify($content) : null;
		}

		if (isset($content)) {
			$this->content($content);
		} else {
			$this->renderContent();
		}

		if ($status) $this->status($status);

		//set default header
		if (!$this->status) $this->status(200);
		if (!isset($this->headers['Content-Type'])) $this->header('Content-Type', 'text/html');

		if (!isset($this->headers['Content-Length'])) {
			// assert: empty($this->headers["Transfer-Encoding"])
			// assert: $this->status is not 204 or 1xx
			$this->header('Content-Length', strlen($this->content));
		}

		return $this;
	}

	public function prepareForRedirect($url, $status = 302) {
		if (!in_array($status, [301, 302, 307, 308])) throw new Exception('Can not redirect with status:' . $status);
		$this->status($status);
		if ($url == 'back') $url = isset($_SERVER['HTTP_REFERER']) ? trim($_SERVER['HTTP_REFERER']) : '/';
		if (strpos($url, '//') === 0) $url = request_url_scheme(WITH_COLON) . $url;
		$this->header('Location', $url);
		$this->content("<html><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></html>");
		return $this;
	}
}

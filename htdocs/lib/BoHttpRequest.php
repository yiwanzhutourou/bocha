<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

class BoHttpRequest {
	private static $requestId = null;
	private static $headers = null;

	public static function init() {
		$_REQUEST = [];

		$_GET = self::formatInput($_GET);
		$_POST = self::formatInput($_POST);

		$_SERVER['QUERY_STRING'] = http_build_query($_GET);
		$uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
		$uri_part = $uri_parts[0];
		$_SERVER['REQUEST_URL'] = request_url_scheme(true) . "//{$_SERVER['HTTP_HOST']}{$uri_part}";
		$_SERVER['REQUEST_URI'] = $uri_part;
		if (isset($_SERVER['HTTP_REFERER']))
			$_SERVER['HTTP_REFERER'] = self::formatInput($_SERVER['HTTP_REFERER']);

		self::$requestId = uniqid(). mt_rand(10000, 99999);
	}

	public static function formatInput($data) {
		if (is_array($data)) {
			$arr = [];
			foreach ($data as $k => $v) {
				$arr[self::formatInput($k)] = self::formatInput($v);
			}
			unset($arr['']);
			return $arr;
		} else {
			return trim(stripslashes($data));
		}
	}

	public static function header($key = null) {
		if (is_null(self::$headers)) {
			foreach ($_SERVER as $name => $value) {
				if (substr($name, 0, 5) == 'HTTP_') {
					self::$headers[str_replace('_', '-', substr($name, 5))] = $value;
				}
			}
		}
		$key = str_replace('_', '-', strtoupper($key));

		return $key
			? (isset(self::$headers[$key]) ? self::$headers[$key] : '')
			: self::$headers;
	}

	public static function scheme() {
		return request_url_scheme();
	}

	public static function host() {
		return $_SERVER['HTTP_HOST'];
	}

	public static function referrer() {
		return isset($_SERVER['HTTP_REFERER']) ? to_utf8($_SERVER['HTTP_REFERER']) : '';
	}

	public static function ip() {
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	public static function uri() {
		return $_SERVER['REQUEST_URI'];
	}

	public static function url() {
		return $_SERVER['REQUEST_URL'];
	}

	public static function full_url() {
		return "{$_SERVER['REQUEST_URL']}?{$_SERVER['QUERY_STRING']}";
	}

	/**
	 * @return string Raw post data
	 * @see http://www.php.net/manual/en/wrappers.php.php
	 */
	public static function rawPost() {
		return file_get_contents('php://input');
	}

	public static function post($name = null, $default = null) {
		return is_null($name) ? $_POST : (isset($_POST[$name]) ? $_POST[$name] : $default);
	}

	public static function get($name = null, $default = null) {
		return is_null($name) ? $_GET : (isset($_GET[$name]) ? $_GET[$name] : $default);
	}

	public static function getOrPost($name = null, $default = null) {
		$method = self::method();
		if (!in_array($method, ['get', 'post', 'head'])) {
			throw new Exception('Bad request method');
		}

		return self::$method($name, $default);
	}

	public static function cookie($name = null, $default = null) {
		return is_null($name) ? $_COOKIE : (isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default);
	}

	public static function method() {
		return isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : '';
	}

	public static function isGet() {
		return self::method() == 'get';
	}

	public static function isPost() {
		return self::method() == 'post';
	}

	public static function requestId() {
		return self::$requestId;
	}
}
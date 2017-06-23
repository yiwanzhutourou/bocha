<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

abstract class BoRequestData {
	/** @var array url 和 pattern match 的部分 */
	public $matches = [];

	/** @var string index by default */
	public $action;

	/** @var Visitor */
	public $visitor;

	/** @var bool */
	public $jsonMode;

	protected function init() {
		$this->visitor = Visitor::instance();
	}

	public static function getUrlMatches($rule, $url, $path = null) {
		if (strpos($rule, '//') === 0) $rule = request_url_scheme(WITH_COLON) . $rule;
		$req_str = strpos($rule, 'http') === 0 ? $url : (empty($path) ? parse_url($url, PHP_URL_PATH) : $path);
		if (empty($req_str)) {
			return null;
		}

		$rule = str_replace(['\{', '\}'], ['{', '}'], preg_quote($rule, '/'));
		$reg_exp = preg_replace('/\{([^\{\}]+)\}/', '(?<$1>[\.\%\-\_0-9a-zA-Z\x7f-\xff ]+)', $rule);
		if (preg_match('/^' . $reg_exp . '/', $req_str, $m)) {
			return $m;
		} else {
			return null;
		}
	}

	abstract public function host();
	abstract public function scheme();
	abstract public function fullUrl();
	abstract public function uri();
	abstract public function get($name = null, $default = null);
	abstract public function post($name = null, $default = null);
	abstract public function referrer();
	abstract public function method();
}

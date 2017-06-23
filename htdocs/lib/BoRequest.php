<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

final class BoRequest extends BoHttpRequest {

	use Singleton;

	private static $params = [];

	public function __construct() {
		$this->foreverAlone();
		foreach (parent::get() as $k => $v) {
			$this->$k = $v;
		}
	}

	public function __set($name, $value) {
		self::$params[$name] = $value;
	}

	public function __get($name) {
		return isset(self::$params[$name]) ? self::$params[$name] : null;
	}
}

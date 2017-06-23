<?php
/**
 * Created by Cui Yi
 * 2017/5/24
 */

trait Singleton {

	private static $instance;

	/**
	 * @return static
	 */
	final public static function instance() {
		if (empty(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	final private function foreverAlone() {
		if (self::$instance !== null) throw new SingletonException(__CLASS__);
	}

	final function __construct() {
		$this->foreverAlone();
		if (get_parent_class()) parent::__construct();
	}

	final function __clone() { throw new SingletonException(__CLASS__, __METHOD__); }
	final function __wakeup() { throw new SingletonException(__CLASS__, __METHOD__); }

}

class SingletonException extends LogicException {
	function __construct($class, $method = null) {
		parent::__construct($class . ' is singleton' . ($method ? ' and can not call ' . $method : ''));
	}
}

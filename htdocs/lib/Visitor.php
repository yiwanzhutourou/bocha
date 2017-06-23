<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

use \Graph\MUser;

class Visitor {

	private static $instance = null;

	/**
	 * @var MUser
	 */
	private $user = null;

	private function __construct() {}

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new Visitor();
		}
		return self::$instance;
	}

	public function setUser($user) {
		if (!$user) return;
		$this->user = $user;
	}

	/**
	 * @return MUser
	 */
	public function getUser() {
		return $this->user;
	}

	public function isLogin() {
		return $this->user != null;
	}
}
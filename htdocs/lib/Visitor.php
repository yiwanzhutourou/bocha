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

	public function isMe($userId) {
		return $this->user != null && $this->user->id === $userId;
	}

	public function isLogin() {
		return $this->user != null;
	}

	public function hasMobile() {
		return $this->user != null && !empty($this->user->mobile);
	}
}
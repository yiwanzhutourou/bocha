<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

use \Graph\MUser;
use \Api\Exception;

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

	/*
	 * 不判空,调用前需要检查是否登录,见checkAuth
	 */
	public function getUserId() {
		return $this->user->id;
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

	public function checkAuth($skipMobile = false) {
		if (!$this->isLogin())
			throw new Exception(Exception::AUTH_FAILED, '未登录');
		if (!$skipMobile) {
			if (!$this->hasMobile())
				throw new Exception(Exception::AUTH_FAILED_NO_MOBILE, '未绑定手机号');
		}
	}
}
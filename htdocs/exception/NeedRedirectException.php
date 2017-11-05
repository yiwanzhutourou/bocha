<?php
/**
 * Created by Cui Yi
 * 2017/11/5
 */

class NeedRedirectException extends Exception {

	/**
	 * @var string
	 */
	public $url;

	/**
	 * @var int
	 */
	public $status;

	public function __construct($redirectUrl, $status = 302, $message = '') {
		// 很多场景下，跳转前要跟用户说明情况，需要设置message
		parent::__construct($message);

		$this->url = $redirectUrl;
		$this->status = $status;
	}
}
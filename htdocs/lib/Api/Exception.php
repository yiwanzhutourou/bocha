<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

namespace Api;

class Exception extends \Exception {

	const INTERNAL_ERROR = 1001;
	const INVALID_COMMAND = 2003;
	const PARAMETERS_MISSING = 2004;

	const BAD_REQUEST = 2007;
	const AUTH_FAILED = 2008;
	const AUTH_FAILED_NO_MOBILE = 2009;

	const WEIXIN_AUTH_FAILED = 3001;

	const RESOURCE_NOT_FOUND = 4001;
	const RESOURCE_ALREADY_ADDED = 4002;

	const REQUEST_TOO_MUCH = 5001;
	const VERIFY_CODE_EXPIRED = 5002;

	public $ext;

	public function httpCode() {
		return 404;
	}

	public function __construct($code = 0, $message = "", $ext = null) {
		$this->ext = $ext;
		parent::__construct($message, $code);
	}

	public function output() {
		return json_stringify([
			'error' => $this->getCode(),
			'message' => $this->getMessage(),
			'ext' => $this->ext,
		]);
	}
}


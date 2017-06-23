<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

use Api\Exception;

class ApiCmd {

	private $name, $requestData;

	public $result = null;
	public static $resultCacheTime = 0;

	public function __construct($uri, $requestData) {
		$this->parseApiName($uri);
		$this->requestData = $requestData;
	}

	private function parseApiName($uri) {
		$paths = array_slice(explode('/', ltrim($uri, '/')), 1);
		if (count($paths) == 1) { //uri api/User.addBook/ -> paths mobile.config/
			if (count(explode('.', $paths[0])) == 2) {
				$name = $paths[0];
				$this->name = $name;
				return;
			}
		}
		throw new Exception(Exception::INVALID_COMMAND);
	}

	public function run() {
		self::setCacheTime(0);
		$this->result = $this->runApi();
		return $this->result;
	}

	private function runApi() {
		$callContext = [
			'method' => \BoHttpRequest::method(),
		];
		return api($this->name, $this->requestData, $callContext);
	}

	public static function setCacheTime($seconds) {
		self::$resultCacheTime = $seconds;
	}
}

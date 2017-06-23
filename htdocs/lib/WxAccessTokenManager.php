<?php
/**
 * Created by Cui Yi
 * 2017/6/3
 */

use Api\Exception;

class WxAccessTokenManager {
	private static $instance = null;

	const TOKEN_KEY = 'wx_access_token';
	
	private $access_token = '';
	private $expire_time = -1;

	private function __construct() {}

	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new WxAccessTokenManager();
		}
		return self::$instance;
	}

	public function getAccessToken() {
		if (!empty($this->access_token) && $this->tokenValid()) {
			return $this->access_token;
		}

		/** @var \Graph\MXu $xu */
		$xu = \Graph\Graph::findXu(self::TOKEN_KEY);
		if ($xu !== false) {
			$this->access_token = $xu->value;
			$this->expire_time = $xu->expireTime;
			if (!empty($this->access_token) && $this->tokenValid()) {
				return $this->access_token;
			}
		}

		$url = "https://api.weixin.qq.com/cgi-bin/token?" . http_build_query([
					'grant_type' => 'client_credential',
					'appid'      => WX_APPID,
					'secret'     => WX_SECRET
				]);
		$response = json_decode(file_get_contents($url));
		if (!empty($response->errcode)) {
			throw new Exception(Exception::WEIXIN_AUTH_FAILED, '微信验证出错:'.$response->errmsg);
		}

		if (!empty($response->access_token)) {
			$this->access_token = $response->access_token;
			$this->expire_time = time() + ($response->expires_in - 300) * 1000;

			$newXu = new \Graph\MXu();
			$newXu->name = self::TOKEN_KEY;
			$newXu->value = $this->access_token;
			$newXu->createTime = time();
			$newXu->expireTime = $this->expire_time;
			if ($xu === false) {
				$newXu->insert();
			} else {
				$newXu->id = $xu->id;
				$newXu->update();
			}

			return $this->access_token;
		}

		return false;
	}

	private function tokenValid() {
		return $this->expire_time != -1 && $this->expire_time < time();
	}
}

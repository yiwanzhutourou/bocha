<?php
/**
 * Created by Cui Yi
 * 2017/8/16
 */

namespace Api;

require ROOT . '/Qiniu/autoload.php';

use Qiniu\Auth;

class Qiniu extends ApiBase {

	public function getUploadToken() {
		$this->checkAuth();

		$auth = new Auth(QINIU_AK, QINIU_AKS);
		// 空间名 https://developer.qiniu.io/kodo/manual/concepts
		$bucket = 'bocha';
		// 生成上传Token
		$token = $auth->uploadToken($bucket);

		return $token;
	}

	private function checkAuth($skipMobile = false) {
		if (!\Visitor::instance()->isLogin())
			throw new Exception(Exception::AUTH_FAILED, '未登录');
		if (!$skipMobile) {
			if (!\Visitor::instance()->hasMobile())
				throw new Exception(Exception::AUTH_FAILED_NO_MOBILE, '未绑定手机号');
		}
	}
}

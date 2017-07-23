<?php
/**
 * Created by Cui Yi
 * 2017/7/23
 */

namespace Api;

use Graph\MUserAddress;
use Graph\MUserInfo;

/**
 * Class Haribo
 * 测试用,只上传到测试服务器
 *
 * @package Api
 */
class Haribo extends ApiBase {

	/**
	 * 给地下城用的测试接口
	 *
	 * 会清空用户的简介,所有地址,绑定的手机号,联系方式
	 */
	public function clearUser() {
		$this->checkAuth(true);

		$currentUser = \Visitor::instance()->getUser();

		// clear all addresses
		$addressList = $currentUser->getAddressList();
		if ($addressList !== false) {
			/** @var MUserAddress $address */
			foreach ($addressList as $address) {
				$address->delete();
			}
		}
		
		// clear intro
		$userInfo = new MUserInfo();
		$userInfo->userId = $currentUser->id;
		$userInfo->delete();

		// clear mobile, contact
		$currentUser->clear("contact='',mobile=''");
		$currentUser->mobile = '';
		$currentUser->contact = '';

		return 'ok';
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
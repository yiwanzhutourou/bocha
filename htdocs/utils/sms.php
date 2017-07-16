<?php
/**
 * Created by Cui Yi
 * 2017/7/11
 */

include ROOT  . '/aliyun-php-sdk-core/Config.php';

include_once ROOT . '/Dysmsapi/Request/V20170525/SendSmsRequest.php';
include_once ROOT . '/Dysmsapi/Request/V20170525/QuerySendDetailsRequest.php';

function sendBorrowBookSms($phoneNumber, $fromUserName, $bookName) {

	// AK信息
	$accessKeyId = ALIYUN_AK;
	$accessKeySecret = ALIYUN_AKS;
	// 短信API产品名
	$product = "Dysmsapi";
	// 短信API产品域名
	$domain = "dysmsapi.aliyuncs.com";
	// 暂时不支持多Region
	$region = "cn-hangzhou";

	// 初始化访问的acsCleint
	$profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
	DefaultProfile::addEndpoint("cn-hangzhou", "cn-hangzhou", $product, $domain);
	$acsClient= new DefaultAcsClient($profile);

	$request = new Dysmsapi\Request\V20170525\SendSmsRequest;
	// 必填-短信接收号码
	$request->setPhoneNumbers($phoneNumber);
	// 必填-短信签名
	$request->setSignName("有读书房");
	// 必填-短信模板Code
	$request->setTemplateCode("SMS_76385138");
	// 选填-假如模板中存在变量需要替换则为必填(JSON格式)
	$params = [
		'from_user_name'   => $fromUserName,
		'borrow_book_name' => $bookName
	];
	$request->setTemplateParam(json_stringify($params));
	// 选填-发送短信流水号
	// $request->setOutId("1234");

	// 发起访问请求
	$acsResponse = $acsClient->getAcsResponse($request);
	if (!empty($acsResponse) && $acsResponse->Code === 'OK') {
		return true;
	}
	// TODO 短信发送失败要不要打点记一下啊?
	// var_dump($acsResponse);
	return false;
}

function sendVeriCodeSms($phoneNumber, $code) {

	// AK信息
	$accessKeyId = ALIYUN_AK;
	$accessKeySecret = ALIYUN_AKS;
	// 短信API产品名
	$product = "Dysmsapi";
	// 短信API产品域名
	$domain = "dysmsapi.aliyuncs.com";
	// 暂时不支持多Region
	$region = "cn-hangzhou";

	// 初始化访问的acsCleint
	$profile = DefaultProfile::getProfile($region, $accessKeyId, $accessKeySecret);
	DefaultProfile::addEndpoint("cn-hangzhou", "cn-hangzhou", $product, $domain);
	$acsClient= new DefaultAcsClient($profile);

	$request = new Dysmsapi\Request\V20170525\SendSmsRequest;
	// 必填-短信接收号码
	$request->setPhoneNumbers($phoneNumber);
	// 必填-短信签名
	$request->setSignName("有读书房");
	// 必填-短信模板Code
	$request->setTemplateCode("SMS_76440167");
	// 选填-假如模板中存在变量需要替换则为必填(JSON格式)
	$params = [
		'verify_code'   => $code
	];
	$request->setTemplateParam(json_stringify($params));
	// 选填-发送短信流水号
	// $request->setOutId("1234");

	// 发起访问请求
	$acsResponse = $acsClient->getAcsResponse($request);
	if (!empty($acsResponse) && $acsResponse->Code === 'OK') {
		return true;
	}
	// 阿里后台可以看失败,应该不需要打点了
	return false;
}

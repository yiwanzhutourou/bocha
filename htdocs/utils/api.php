<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

use Api\Exception;

/**
 * 调用方法 api('User.addBook', ['isbn' => 'xxx'])
 *
 * @param string $api_name Api的名称
 * @param array $data 传给Api的参数
 * @param array $callContext 上下文，如http method用以检查
 * @return mixed result
 * @throws Exception
 * @throws \Api\Exception
 */
function api($api_name, $data, $callContext = null) {
	list($className, $method) = explode('.', trim($api_name));
	$className = ucfirst($className);
	$class = "Api\\{$className}";

	// check class
	$api = apiCheckClass($class);
	if (!$api) throw new Exception(Exception::INVALID_COMMAND, "非api接口，不能调用：{$className}");

	// check method
	$method = apiCheckMethod($api, $method);
	if (!$method) throw new Exception(Exception::INVALID_COMMAND, "api接口不存在此方法，不能调用：{$className}.{$method}");

	apiCheckRequestMethod($api, $method, $callContext);
	$callPars = apiCheckParams($api, $method, $data);

	try {
		$result = call_user_func_array([$api, $method], $callPars);
		return $result;
	} catch (Exception $e) {
		throw new Exception($e->getCode(), $e->getMessage());
	}
}

function apiCheckClass($className) {
	if (!class_exists($className)) return false;
	$api = new $className();
	if (!$api instanceof \Api\ApiBase) return false;
	return $api;
}

function apiCheckMethod($api, $method) {
	if (!is_callable([$api, $method])) return false;
	return $method;
}

function apiCheckRequestMethod($api, $method, $callContext = null) {
	if (isset($callContext['method'])) {
		$annotates = \Annotate::get($api, $method);
		if (!empty($annotates['method'])) {
			$expectMethod = strtoupper($annotates['method'][0]);
			if (strtoupper($callContext['method']) != $expectMethod) {
				throw new \Exception("api接口" . get_class($api) . ".{$method}请使用{$expectMethod}请求");
			}
		}
	}
	return true;
}


function apiCheckParams($api, $method, $data) {
	$pars = (new \ReflectionMethod($api, $method))->getParameters();
	$callPars = [];
	foreach ($pars as $eachPar) {
		$key = $eachPar->getName();
		if (isset($data[$key])) {
			$callPars[] = $data[$key];
		} elseif ($key == 'otherArgs') {
			$callPars[] = &$data;
		} elseif ($eachPar->isDefaultValueAvailable()) {
			$callPars[] = $eachPar->getDefaultValue();
		} else {
			throw new Exception(Exception::PARAMETERS_MISSING, $key);
		}
		unset($data[$key]);
	}
	return $callPars;
}

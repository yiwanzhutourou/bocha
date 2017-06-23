<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

use Api\Exception;

class ApiController extends BaseController {
	public function index(BoRequestData $request) {
		$response = BoResponseData::createDefault()->jsonmode();

		try {
			$this->handleRequest($request, $response);
		} catch (Exception $e) {
			$response->status($e->httpCode())->content($e->output());
		}

		return $response;
	}

	private function handleRequest(BoRequestData $request, BoResponseData $response) {
		$this->initEnv();
		return $this->handle($request, $response);
	}

	protected function handleException(\Exception $e, BoRequestData $request) {
		$response = BoResponseData::createDefault()->jsonmode();
		$content = json_stringify([
			'error' => 500,
			'message' => $e->getMessage(),
			'ext' => '',
		]);
		$response->status(500)->content($content);
		return $response;
	}

	/**
	 * 设置验证token -> 设置当前用户
	 *
	 * @throws \Api\Exception
	 */
	private function initEnv() {
		// 验证token
		$token = BoHttpRequest::header('BOCHA-USER-TOKEN');
		if ($token) {
			$user = new \Graph\MUser();
			$user->token = $token;
			Visitor::instance()->setUser($user->findOne());
		}
	}

	private function handle(BoRequestData $request, BoResponseData $response) {
		$this->checkParams($request);
		$uri = $request->uri();
		$requestData = $this->rawRequestData($request);
		$cmd = new ApiCmd($uri, $requestData);
		$cmd->run();

		$output = [
			'result' => $cmd->result
		];

		return $response->content(json_stringify($output));
	}

	private function checkParams(BoRequestData $request) {
	}

	private function rawRequestData(BoRequestData $request) {
		switch ($request->method()) {
			case 'get' :
				$data = $request->get();
				break;
			case 'post' :
				$data = json_decode(BoHttpRequest::rawPost(), true) ?: [];
				try {
					array_walk_recursive($data, function($value, $key) {
						utf8_filter($value. $key, UTF8_ERR_EXCEPTION);
					});
				} catch (\Exception $e) {
					throw new Exception(Exception::BAD_REQUEST, '含有非法字符');
				}

				$data += $request->get();
				break;
			default :
				$data = [];
		}
		return $data;
	}
}
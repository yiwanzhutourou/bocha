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
		if ($e instanceof NeedRedirectException) {
			throw $e;
		} else {
			$response = BoResponseData::createDefault()->jsonmode();
			$content = json_stringify([
										  'error' => 500,
										  // TODO 报错
										  // 'message' => $e->getMessage(),
										  'message' => '服务器发生错误了~',
										  'ext' => '',
									  ]);
			$response->status(500)->content($content);
			return $response;
		}
	}

	/**
	 * 设置验证token -> 设置当前用户
	 *
	 * @throws \Api\Exception
	 */
	private function initEnv() {
		// 检查平台
		$platform = BoHttpRequest::header('BOCHA-PLATFORM');
		// 目前仅支持微信小程序
		if (empty($platform) || $platform !== 'wx-mp') {
			throw new NeedRedirectException('//www.youdushufang.com/');
		}
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

		//----- hard code 转发一下豆瓣的几个接口
        $isDoubanApi = false;
        $paths = explode('/', ltrim($uri, '/'));
        if (count($paths) > 1 && $paths[0] === 'v2') {
            if (count($paths) === 4 && $paths[1] === 'book'
                    && $paths[2] === 'isbn') {
                $uri = '/api/Book.getBookByIsbn';
                $requestData = array_merge($requestData, [
                    'isbn' => $paths[3],
                ]);
                $isDoubanApi = true;
            } else if (count($paths) === 3 && $paths[1] === 'book'
                && $paths[2] === 'search') {
                $uri = '/api/Book.search';
                $isDoubanApi = true;
            } else if (count($paths) === 3 && $paths[1] === 'book') {
                $uri = '/api/Book.getBook';
                $requestData = array_merge($requestData, [
                    'isbn' => $paths[2],
                ]);
                $isDoubanApi = true;
            }
        }
        // -----

		$cmd = new ApiCmd($uri, $requestData);
		$cmd->run();

		if ($isDoubanApi) {
            $output = $cmd->result;
        } else {
            $output = [
                'result' => $cmd->result
            ];
        }

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
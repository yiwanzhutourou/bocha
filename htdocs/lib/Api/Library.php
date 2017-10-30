<?php
/**
 * Created by Cui Yi
 * 2017/10/29
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MLibrary;
use Graph\MLibraryAddress;
use Graph\MLibraryAdmin;
use Graph\MLibraryBook;

class Library extends ApiBase {

	// ----- Public

	public function getPageData($id) {
		/** @var MLibrary $library */
		$library = Graph::findLibraryById($id);
		if ($library === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '图书馆不存在~');
		}

		return [
			'name'       => $library->name,
			'avatar'     => getListThumbnailUrl($library->avatar),
			'defaultPic' => $library->defaultPic,
			'info'       => $library->info,
		];
	}


	// ----- 需要权限

	

	// 服务器打豆瓣接口可能会炸,可能需要把获取图书信息的逻辑放在客户端
	public function addBook($id, $isbn) {
		$this->checkAuth();

		$userId = \Visitor::instance()->getUserId();
		$this->checkAdmin($id, $userId);

		/** @var MLibrary $library */
		$library = Graph::findLibraryById($id);
		if ($library === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '图书馆不存在~');
		}

		// check book in Douban
		$url = "https://api.douban.com/v2/book/{$isbn}";
		$response = file_get_contents($url);

		$doubanBook = json_decode($response);
		if ($doubanBook === null || empty($doubanBook->id)) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '无法获取图书信息');
		}

		$book = new MBook();
		$book->updateBook($doubanBook);

		$libBook = new MLibraryBook();
		$libBook->libId = $id;
		$libBook->isbn = $isbn;

		if ($libBook->findOne() !== false) {
			throw new Exception(Exception::RESOURCE_ALREADY_ADDED , '不可以添加重复的图书哦~');
		} else {
			$libBook->totalCount = 1;
			$libBook->leftCount = 1; // 暂时默认都是 1 本书
			// 图书馆新增图书要做什么推荐逻辑吗?
			$libBook->insert();
		}
		return 'ok';
	}

	public function getSettingData($id) {
		$this->checkAuth();

		$userId = \Visitor::instance()->getUserId();
		$this->checkAdmin($id, $userId);

		/** @var MLibrary $library */
		$library = Graph::findLibraryById($id);
		if ($library === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '图书馆不存在~');
		}

		/** @var MLibraryAddress $libAddress */
		$retAddr = '';
		$libAddress = Graph::getLibAddress($id);
		if ($libAddress !== false) {
			$retAddr = [
				'name'      => $libAddress->name,
				'detail'    => $libAddress->detail,
				'latitude'  => $libAddress->latitude,
				'longitude' => $libAddress->longitude,
				'city'      => json_decode($libAddress->city)
			];
		}

		return [
			'name'       => $library->name,
			'avatar'     => getListThumbnailUrl($library->avatar),
			'defaultPic' => $library->defaultPic,
			'info'       => $library->info,
			'address'    => $retAddr,
		];
	}

	public function updateLibInfo($id, $name, $info) {
		$this->checkAuth();

		$userId = \Visitor::instance()->getUserId();
		$this->checkAdmin($id, $userId);

		/** @var MLibrary $library */
		$library = Graph::findLibraryById($id);
		if ($library === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '图书馆不存在~');
		}

		$library->name = $name;
		$library->info = $info;
		$library->update();
		return 'ok';
	}

	public function updateLibAvatar($id, $avatar) {
		$this->checkAuth();

		$userId = \Visitor::instance()->getUserId();
		$this->checkAdmin($id, $userId);

		/** @var MLibrary $library */
		$library = Graph::findLibraryById($id);
		if ($library === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '图书馆不存在~');
		}

		$library->avatar = $avatar;
		$library->update();
		return 'ok';
	}

	public function updateLibAddress($id, $name, $detail, $latitude, $longitude) {
		$this->checkAuth();

		$userId = \Visitor::instance()->getUserId();
		$this->checkAdmin($id, $userId);

		/** @var MLibrary $library */
		$library = Graph::findLibraryById($id);
		if ($library === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '图书馆不存在~');
		}

		$query = new MLibraryAddress();
		$query->libId = $id;

		// 一个图书馆只支持一个地址
		/** @var MLibraryAddress $libAddress */
		$libAddress = $query->findOne();
		$query->name = $name;
		$query->detail = $detail;
		$query->latitude = $latitude;
		$query->longitude = $longitude;
		$query->city = reversePoi($latitude, $longitude);
		if ($libAddress === false) {
			$query->insert();
		} else {
			$query->id = $libAddress->id;
			$query->update();
		}

		return 'ok';
	}

	// 获取所有我管理的图书馆
	public function getMyLibs() {
		$this->checkAuth();
		
		$userId = \Visitor::instance()->getUserId();

		$libList = Graph::findLibsByUser($userId);

		if ($libList === false) {
			return [];
		}

		$libList = array_map(function($admin) {
			/** @var MLibraryAdmin $admin */
			if (!$admin) {
				return false;
			}
			/** @var MLibrary $lib */
			$lib = Graph::findLibraryById($admin->libId);
			if ($lib === false) {
				return false;
			}

			/** @var MLibraryAddress $libAddress */
			$retAddr = '';
			$libAddress = Graph::getLibAddress($lib->id);
			if ($libAddress !== false) {
				$retAddr = [
					'name'      => $libAddress->name,
					'detail'    => $libAddress->detail,
					'latitude'  => $libAddress->latitude,
					'longitude' => $libAddress->longitude,
					'city'      => json_decode($libAddress->city)
				];
			}
			return [
				'id'         => $lib->id,
				'name'       => $lib->name,
				'avatar'     => getListThumbnailUrl($lib->avatar),
				'defaultPic' => $lib->defaultPic,
				'info'       => $lib->info,
				'address'    => $retAddr,
			];
		}, $libList);

		$libList = array_values(array_filter($libList, function($item) {
			return $item !== false;
		}));

		return $libList;
	}

	// 创建一个新图书馆,目前用于测试
	public function create($name) {
		$this->checkAuth();
		
		$name = Graph::escape($name);
		
		if (count($name) > 30) {
			throw new Exception(Exception::INVALID_PARAMETERS, '图书馆名字不能超过 30 个字');
		}

		$library = new MLibrary();
		$library->name = $name;

		// 补默认值进去,创建的时候暂时不需要提供
		$library->avatar = '';
		$library->defaultPic = '';
		$library->info = '';

		$libId = $library->insert();
		if ($libId > 0) {
			// 默认把创建者加为管理员
			$libAdmin = new MLibraryAdmin();
			$libAdmin->libId = $libId;
			$libAdmin->userId = \Visitor::instance()->getUserId();
			$libAdmin->insert();
			// 这里如果插入失败应该回滚的
			return 'ok';
		}

		throw new Exception(Exception::INTERNAL_ERROR, '服务器出错了，请稍后再试');
	}

	private function checkAdmin($libId, $userId) {
		$libAdmin = new MLibraryAdmin();
		$libAdmin->libId = $libId;
		$libAdmin->userId = $userId;

		if ($libAdmin->findOne() === false) {
			throw new Exception(Exception::AUTH_FAILED, '你不是这个图书馆的管理员~'.$libId.'&'.$userId);
		}
	}

	// 暂时先只给部分人试用
	private function checkAuth() {
		if (!\Visitor::instance()->isLogin())
			throw new Exception(Exception::AUTH_FAILED, '未登录');

		$userId = \Visitor::instance()->getUserId();

		// TODO 测试服上我的 ID 是 398,记得最后删掉
		$authUsers = [34, 398];
		if (!in_array(intval($userId), $authUsers)) {
			throw new Exception(Exception::AUTH_FAILED, '没有权限');
		}
	}
}
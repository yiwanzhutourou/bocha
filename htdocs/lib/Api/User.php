<?php
/**
 * Created by Cui Yi
 * 2017/5/28
 */

namespace Api;

use Graph\Graph;
use Graph\MBook;
use Graph\MBorrowHistory;
use Graph\MCard;
use Graph\MCardPulp;
use Graph\MFollow;
use Graph\MSmsCode;
use Graph\MUser;
use Graph\MUserAddress;
use Graph\MUserBook;
use Graph\MUserInfo;

class User extends ApiBase {
	public function login($code, $nickname, $avatar) {
		$url = "https://api.weixin.qq.com/sns/jscode2session?" . http_build_query([
			'appid'      => WX_APPID,
			'secret'     => WX_SECRET,
			'js_code'    => $code,
			'grant_type' => 'authorization_code'
		]);

		$response = json_decode(file_get_contents($url));
		if (!empty($response->errcode)) {
			throw new Exception(Exception::WEIXIN_AUTH_FAILED, '微信验证出错:'.$response->errmsg);
		}

		$openid = $response->openid;
		$session = $response->session_key;

		if (!empty($openid) && !empty($session)) {
			$user = new MUser();
			$user->openId = $openid;

			/** @var MUser $one */
			$one = $user->findOne();

			if ($one !== false && !empty($one->token)) {
				$token = $one->token;
				$user->id = $one->id;
				$user->session = $session;
				// 假的,暂时token不超时
				$user->createTime = strtotime('now');
				$user->expireTime = strtotime('now + 30 days');
				$user->nickname = empty($one->nickname) ? $nickname : $one->nickname;
				$user->avatar = empty($one->avatar) ? $avatar : $one->avatar;
				$user->update();
			} else {
				$token = 'bocha' . uniqid('', true);
				$user->token = $token;
				$user->session = $session;
				// 假的,暂时token不超时
				$user->createTime = strtotime('now');
				$user->expireTime = strtotime('now + 30 days');
				$user->nickname = $nickname;
				$user->avatar = $avatar;
				$user->contact = "";
				$user->mobile = "";
				$user->insert();
			}
			return [
				'token'     => $token,
				'hasMobile' => $one !== false && !empty($one->mobile)
			];
		}

		throw new Exception(Exception::WEIXIN_AUTH_FAILED, '无法获取openid');
	}

	public function getSettingsData() {
		$this->checkAuth();

		$user = \Visitor::instance()->getUser();
		$contactJson = $user->contact;
		$contact = json_decode($contactJson);
		$mobile = $user->mobile;

		$addressList = array_map(function($address) {
				return json_decode($address->city);
		}, $user->getAddressList());

		return [
			'contact'     => $contact,
			'mobileTail'  => substr($mobile, strlen($mobile) - 4, strlen($mobile)),
			'address'     => $addressList,
		];
	}

	public function getMinePageData() {
		$this->checkAuth();

		$user = \Visitor::instance()->getUser();
		$bookCount = $user->getBookListCount();
		$cardCount = $user->getCardListCount();

		return [
			'nickname'       => $user->nickname,
			'avatar'         => $user->avatar,
			'bookCount' => $bookCount,
			'cardCount' => $cardCount,
			'followerCount'  => Graph::getFollowerCount($user->id),
			'followingCount' => Graph::getFollowingCount($user->id),
		];
	}

	public function getUserContact() {
		$this->checkAuth();
		$contactJson = \Visitor::instance()->getUser()->contact;
		$contact = json_decode($contactJson);
		if (isset($contact->name) && isset($contact->contact)) {
			if (in_array($contact->name, ['微信', 'QQ', '邮箱'])
				&& !empty($contact->contact)) {
				return [
					'name'    => $contact->name,
					'contact' => $contact->contact
				];
			}
		}
		return [
			'name'    => '',
			'contact' => ''
		];
	}

	public function getUserContactByRequest($requestId) {
		$this->checkAuth();

		$history = new MBorrowHistory();
		$history->id = $requestId;
		/** @var MBorrowHistory $one */
		$one = $history->findOne();
		if ($one !== false) {
			if ($one->requestStatus == 1) {
				/** @var MUser $bookHoster */
				$bookHoster = Graph::findUserById($one->to);
				if ($bookHoster !== false) {
					$contactJson = $bookHoster->contact;
					$contact = json_decode($contactJson);
					if (isset($contact->name) && isset($contact->contact)) {
						if (in_array($contact->name, ['微信', 'QQ', '邮箱'])
							&& !empty($contact->contact)) {
							return [
								'name'    => $contact->name,
								'contact' => $contact->contact
							];
						}
					}
					return [
						'name'    => '',
						'contact' => ''
					];
				}
				throw new Exception(Exception::RESOURCE_NOT_FOUND, '用户不存在');
			}
			throw new Exception(Exception::BAD_REQUEST, '书房主人还未同意请求~');
		}
		throw new Exception(Exception::RESOURCE_NOT_FOUND, '借书请求不存在');
	}

	public function setUserContact($name, $contact) {
		$this->checkAuth();
		$user = \Visitor::instance()->getUser();
		$userContact = [
			'name'    => $name,
			'contact' => $contact
		];
		$user->contact = json_stringify($userContact);
		$user->update();
		return $userContact;
	}

	public function getGuideInfo() {
		$this->checkAuth();
		$userId = \Visitor::instance()->getUser()->id;

		$user = new MUser();
		$user->id = $userId;
		/** @var MUser $one */
		$one = $user->findOne();
		if ($one === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '用户不存在');
		}

		// 用户简介
		/** @var MUserInfo $info */
		$info = $one->getInfo();

		// 地址列表
		// user address
		$addressList = array_map(function($address) {
			return [
				'name'      => $address->name,
				'detail'    => $address->detail,
				'latitude'  => $address->latitude,
				'longitude' => $address->longitude,
				'city'      => json_decode($address->city)
			];
		}, $one->getAddressList());

		// 联系方式
		$contactData = [];
		$contactJson = \Visitor::instance()->getUser()->contact;
		$contact = json_decode($contactJson);
		if (isset($contact->name) && isset($contact->contact)) {
			if (in_array($contact->name, ['微信', 'QQ', '邮箱'])
				&& !empty($contact->contact)
			) {
				$contactData = [
					'name'    => $contact->name,
					'contact' => $contact->contact
				];
			}
		}

		return [
			'info'     => $info === false ? '' : $info->info,
			'address'  => $addressList,
			'contact'  => $contactData
		];
	}

	public function getHomepageData($userId = '') {
		$isFollowing = false;
		if ($userId === '') {
			$this->checkAuth();
			$userId = \Visitor::instance()->getUser()->id;
			$isMe = true;
		} else {
			$isMe = \Visitor::instance()->isMe($userId);
			if (\Visitor::instance()->getUser() !== null) {
				$isFollowing = Graph::isFollowing(\Visitor::instance()->getUser()->id, $userId);
			}
		}

		$user = new MUser();
		$user->id = $userId;
		/** @var MUser $one */
		$one = $user->findOne();
		if ($one === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '用户不存在');
		}

		// 用户简介
		/** @var MUserInfo $info */
		$info = $one->getInfo();

		// 地址列表
		// user address
		$addressList = array_map(function($address) {
			return [
				'name'      => $address->name,
				'detail'    => $address->detail,
				'latitude'  => $address->latitude,
				'longitude' => $address->longitude,
				'city'      => json_decode($address->city)
			];
		}, $one->getAddressList());

		// 最新的三条读书卡片
		$card = new MCard();
		$card->userId = $userId;
		$cardList = $card->query("status = '0'", 'ORDER BY create_time DESC LIMIT 0,3');
		$cards = array_map(function($card) {
			/** @var MBook $book */
			$book = Graph::findBook($card->bookIsbn);

			/** @var MCard $card */
			return [
				'id'         => $card->id,
				'title'      => $card->title,
				'content'    => mb_substr($card->content, 0, 48, 'utf-8'),
				'picUrl'     => getListThumbnailUrl($card->picUrl),
				'bookTitle'  => $book->title,
				'createTime' => $card->createTime,
			];
		}, $cardList);

		// 读书卡片总数
		$cardCount = $user->getCardListCount();

		// 图书列表
//		$userBooks = $one->getBooks(3);
		$books = [];
//		/** @var MUserBook $userBook */
//		foreach ($userBooks as $userBook) {
//			$book = new MBook();
//			$book->isbn = $userBook->isbn;
//			/** @var MBook $bookOne */
//			$bookOne = $book->findOne();
//			if ($bookOne !== false) {
//				$books[] = [
//					'isbn'      => $bookOne->isbn,
//					'title'     => $bookOne->title,
//					'author'    => json_decode($bookOne->author),
//					'cover'     => $bookOne->cover,
//					'publisher' => $bookOne->publisher,
//					'canBorrow' => false,
//				];
//			}
//		}
		$bookCount = $one->getBookListCount();

		$borrowBooks = array_map(function($userBook) use ($isMe) {
			/** @var MUserBook $userBook */
			$book = new MBook();
			$book->isbn = $userBook->isbn;
			/** @var MBook $bookOne */
			$bookOne = $book->findOne();
			if ($bookOne === false) {
				return false;
			}
			return [
				'isbn'      => $bookOne->isbn,
				'title'     => $bookOne->title,
				'author'    => json_decode($bookOne->author),
				'cover'     => $bookOne->cover,
				'publisher' => $bookOne->publisher,
				'canBorrow' => !$isMe,
			];
		}, $one->getBorrowBooksLimit(5));

		$borrowBooks = array_filter($borrowBooks, function($book) {
			return $book !== false;
		});
		$borrowBookCount = $one->getBorrowBookCount();


		return [
			'userId'          => $userId,
			'info'            => $info === false ? '' : $info->info,
			'nickname'        => $one->nickname,
			'avatar'          => $one->avatar,
			'address'         => $addressList,
			'cards'           => $cards,
			'cardCount'       => $cardCount,
			'borrowBooks'     => $borrowBooks,
			'borrowBookCount' => $borrowBookCount,
			'books'           => $books,
			'bookCount'       => $bookCount,
			'isMe'            => $isMe,
			'followed'        => $isFollowing,
			'followerCount'   => Graph::getFollowerCount($userId),
			'followingCount'  => Graph::getFollowingCount($userId),
		];
	}

	public function getUserInfo($userId = '') {
		if ($userId === '') {
			$this->checkAuth();
			$userId = \Visitor::instance()->getUser()->id;
		}
		$user = new MUser();
		$user->id = $userId;
		/** @var MUser $one */
		$one = $user->findOne();

		if ($one !== false) {
			return [
				'nickname' => $one->nickname,
				'avatar'   => $one->avatar
			];
		}
		throw new Exception(Exception::RESOURCE_NOT_FOUND, '用户不存在');
	}

	public function info($userId = '') {
		if ($userId !== '') {
			$userInfo = new MUserInfo();
			$userInfo->userId = $userId;
			/** @var MUserInfo $info */
			$info = $userInfo->findOne();
		} else {
			$this->checkAuth();
			/** @var MUserInfo $info */
			$info = \Visitor::instance()->getUser()->getInfo();
		}

		return empty($info) ? '' : $info->info;
	}

	public function setInfo($info) {
		$this->checkAuth();
		$info = Graph::escape($info);
		$user = \Visitor::instance()->getUser();
		$user->updateInfo($info);
		return $info;
	}

	public function updateHomeData($nickname, $intro, $avatar) {
		$this->checkAuth();
		$userId = \Visitor::instance()->getUserId();

		if (!empty($avatar)) {
			// 鉴黄
			$url = $avatar . '?pulp';
			$response = file_get_contents($url);
			$pulp = json_decode($response);
			// 没解出数据认为是正常的
			if (empty($pulp)) {
				$picIsNormal = true;
			} else if ($pulp->code === 0
					   && $pulp->pulp->label === 2) {
				$picIsNormal = true;
			} else {
				$picIsNormal = false;
			}

			$query = new MCardPulp();
			$query->userId = $userId;
			$query->title = '设置头像';
			$query->content = '';
			$query->picUrl = $avatar;
			$query->createTime = strtotime('now');
			$query->pulpRate = empty($pulp) ? -1 : $pulp->pulp->rate;
			$query->pulpLabel = empty($pulp) ? -1 : $pulp->pulp->label;
			$query->pulpReview = empty($pulp) ? 'empty' : $pulp->pulp->review;
			$query->insert();

			if ($picIsNormal === false) {
				throw new Exception(Exception::RESOURCE_IS_PULP, '你的图片不符合规范，不可以在有读书房使用');
			}
		}

		$nickname = Graph::escape($nickname);
		$intro = Graph::escape($intro);

		$user = \Visitor::instance()->getUser();
		$user->updateInfo($intro);

		$user->nickname = $nickname;
		if (!empty($avatar)) {
			$user->avatar = $avatar . '?imageView2/1/w/640/h/640/format/jpg/q/75|imageslim';
		}
		$user->update();

		return 'ok';
	}

	public function getUserBooks($userId, $all) {
		$user = new MUser();
		$user->id = $userId;
		/** @var MUser $one */
		$one = $user->findOne();

		if ($one === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$isMe = \Visitor::instance()->isMe($userId);

		if (intval($all) === 0) {
			$userBooks = $one->getBookList();
		} else {
			$userBooks = $one->getBorrowBooks();
		}

		$result = [];
		/** @var MUserBook $userBook */
		foreach ($userBooks as $userBook) {
			$book = new MBook();
			$book->isbn = $userBook->isbn;
			/** @var MBook $one */
			$one = $book->findOne();
			if ($one !== false) {
				$result[] = [
					'isbn'      => $one->isbn,
					'title'     => $one->title,
					'author'    => json_decode($one->author),
					'cover'     => $one->cover,
					'publisher' => $one->publisher,
					'canBorrow' => !$isMe && intval($userBook->canBeBorrowed) === BOOK_CAN_BE_BORROWED,
				];
			}
		}
		return $result;
	}

	// 这个接口只用于设置界面的我的书,可否借阅的字段仅用来标识闲置图书状态,不用于显示借阅按钮
	public function getMyBooks($all) {
		$this->checkAuth();
		if (intval($all) === 0) {
			$userBooks = \Visitor::instance()->getUser()->getBookList();
		} else {
			$userBooks = \Visitor::instance()->getUser()->getBorrowBooks();
		}
		$result = [];
		/** @var MUserBook $userBook */
		foreach ($userBooks as $userBook) {
			$book = new MBook();
			$book->isbn = $userBook->isbn;
			/** @var MBook $one */
			$one = $book->findOne();
			if ($one !== false) {
				$result[] = [
					'isbn'      => $one->isbn,
					'title'     => $one->title,
					'author'    => json_decode($one->author),
					'cover'     => $one->cover,
					'publisher' => $one->publisher,
					'canBorrow' => intval($userBook->canBeBorrowed) === BOOK_CAN_BE_BORROWED,
				];
			}
		}
		return $result;
	}

	public function addBook($isbn) {
		$this->checkAuth();

		// check book in Douban
		$url = "https://api.douban.com/v2/book/{$isbn}";
		$response = file_get_contents($url);

		$doubanBook = json_decode($response);
		if ($doubanBook === null || empty($doubanBook->id)) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '无法获取图书信息');
		}

		$book = new MBook();
		$book->updateBook($doubanBook);

		$user = \Visitor::instance()->getUser();
		$userBook = new MUserBook();
		$userBook->userId = $user->id;
		$userBook->isbn = $isbn;
		if ($userBook->findOne() !== false) {
			throw new Exception(Exception::RESOURCE_ALREADY_ADDED , '不可以添加重复的图书哦~');
		} else {
			$userBook->createTime = strtotime('now');
			$userBook->canBeBorrowed = BOOK_CAN_BE_BORROWED;
			if ($userBook->insert() > 0) {
				// 检查并添加新图书到发现流
				Graph::addNewBookToDiscoverFlow($book, $userBook);
			}
		}
		return $isbn;
	}

	public function markBookAs($isbn, $canBeBorrowed) {
		$this->checkAuth();

		$user = \Visitor::instance()->getUser();

		$userBook = new MUserBook();
		$userBook->userId = $user->id;
		$userBook->isbn = $isbn;

		$one = $userBook->findOne();

		if ($one === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '图书不存在');
		} else {
			$canBeBorrowedInt = intval($canBeBorrowed);
			if ($canBeBorrowedInt !== BOOK_CAN_BE_BORROWED
					&& $canBeBorrowedInt !== BOOK_CANNOT_BE_BORROWED) {
				$canBeBorrowedInt = BOOK_CAN_BE_BORROWED;
			}
			$one->canBeBorrowed = $canBeBorrowedInt;
			$one->update();

			return 'ok';
		}
	}

	public function removeBook($isbn) {
		$this->checkAuth();

		$user = \Visitor::instance()->getUser();
		if ($user->removeBook($isbn) > 0) {
			// 图书从发现流中删除
			Graph::removeBookFromDiscoverFlow($isbn, $user->id);
			return $isbn;
		} else {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '无法删除未添加的图书~');
		}
	}

	public function getMyAddress() {
		$this->checkAuth();
		$addresses = \Visitor::instance()->getUser()->getAddressList();
		$result = [];
		/** @var MUserAddress $address */
		foreach ($addresses as $address) {
			$result[] = [
				'id'        => $address->id,
				'name'      => $address->name,
				'detail'    => $address->detail,
				'latitude'  => $address->latitude,
				'longitude' => $address->longitude,
			];
		}
		return $result;
	}

	public function getAddressCities() {
		$this->checkAuth();

		// 地址列表
		// user address
		$addressList = array_map(function($address) {
			return [
				'name'      => $address->name,
				'detail'    => $address->detail,
				'latitude'  => $address->latitude,
				'longitude' => $address->longitude,
				'city'      => json_decode($address->city)
			];
		}, \Visitor::instance()->getUser()->getAddressList());

		return $addressList;
	}

	public function removeAddress($id) {
		$this->checkAuth();

		$user = \Visitor::instance()->getUser();
		if ($user->removeAddress($id) > 0) {
			return $id;
		} else {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '无法删除未添加的地址~');
		}
	}

	public function addAddress($name, $detail, $latitude, $longitude) {
		$this->checkAuth();

		$user = \Visitor::instance()->getUser();
		$addressList = $user->getAddressList();

		if ($addressList !== false && count($addressList) >= 3) {
			throw new Exception(Exception::BAD_REQUEST , '最多添加三个地址~');
		}

		// 判断一下 2 公里内不能添加多个地址
		foreach ($addressList as $addressItem) {
			/** @var MUserAddress $addressItem */
			if (abs($latitude - $addressItem->latitude) <= 0.0038
					&& abs($longitude - $addressItem->longitude) <= 0.0034) {
				throw new Exception(Exception::BAD_REQUEST , '你已经在附近添加过一个地址了');
			}
		}

		$userAddress = new MUserAddress();
		$userAddress->userId = $user->id;
		$userAddress->name = $name;
		$userAddress->detail = $detail;
		$userAddress->latitude = $latitude;
		$userAddress->longitude = $longitude;
		$userAddress->city = reversePoi($latitude, $longitude);
		$userAddress->insert();
		return $name;
	}

	/**
	 * 0 - 未处理
	 * 1 - 已同意
	 * 2 - 已拒绝
	 * 3 - 已忽略
	 */
	public function getMyApprovedRequest() {
		$this->checkAuth();

		$history = new MBorrowHistory();
		$history->from = \Visitor::instance()->getUser()->id;
		$history->requestStatus = 1;
		$list = $history->find();

		/** @var MBorrowHistory $one */
		return array_map(function ($one) {
			$toUserId = $one->to;
			/** @var MUser $toUser */
			$toUser = Graph::findUserById($toUserId);
			return [
				'requestId'   => $one->id,
				'userId'    => $toUserId,
				'user'      => $toUser->nickname,
				'bookTitle' => $one->bookTitle,
				'bookCover' => $one->bookCover,
				'date'      => $one->date,
				'status'    => $one->requestStatus,
			];
		}, $list);
	}

	public function getBorrowHistory() {
		$this->checkAuth();

		$history = new MBorrowHistory();
		$history->from = \Visitor::instance()->getUser()->id;
		$list = $history->find();

		/** @var MBorrowHistory $one */
		return array_map(function ($one) {
			$toUserId = $one->to;
			/** @var MUser $toUser */
			$toUser = Graph::findUserById($toUserId);
			return [
				'requestId'   => $one->id,
				'userId'    => $toUserId,
				'user'      => $toUser->nickname . '的书房',
				'bookTitle' => $one->bookTitle,
				'bookCover' => $one->bookCover,
				'date'      => $one->date,
				'status'    => $one->requestStatus,
			];
		}, $list);
	}

	public function getBorrowRequestCount() {
		$this->checkAuth();

		$history = new MBorrowHistory();
		$history->to = \Visitor::instance()->getUser()->id;
		$list = $history->find();

		$count = 0;
		foreach ($list as $item) {
			if ($item->requestStatus === '0') {
				$count++;
			}
		}

		return $count;
	}

	public function getBorrowRequest() {
		$this->checkAuth();

		$history = new MBorrowHistory();
		$history->to = \Visitor::instance()->getUser()->id;
		$list = $history->query('status < 3', 'ORDER BY _id DESC');

		/** @var MBorrowHistory $one */
		return array_map(function ($one) {
			$fromUserId = $one->from;
			/** @var MUser $toUser */
			$fromUser = Graph::findUserById($fromUserId);
			return [
				'requestId'   => $one->id,
				'fromUser'    => $fromUser === false ? '' : $fromUser->nickname,
				'fromUserId'  => $one->from,
				'bookTitle'   => $one->bookTitle,
				'bookCover'   => $one->bookCover,
				'date'        => $one->date,
				'status'      => $one->requestStatus,
			];
		}, $list);
	}

	public function borrowBook($toUser, $isbn, $formId) {
		$this->checkAuth();

		// check user exist
		/** @var MUser $user */
		$user = Graph::findUserById($toUser);
		if ($user === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$selfId = \Visitor::instance()->getUser()->id;
		if ($toUser === $selfId) {
			throw new Exception(Exception::BAD_REQUEST , '不可以借自己的书哦~');
		}

		// check book exist
		/** @var MBook $book */
		$book = Graph::findBook($isbn);
		if ($book === false) {
			throw new Exception(Exception::WEIXIN_AUTH_FAILED, '无法获取图书信息');
		}

		$history = new MBorrowHistory();
		$history->from = $selfId;
		$history->to = $toUser;
		/** @var MBorrowHistory $one */
		$list = $history->find();
		if (!empty($list)) {
			$one = current($list);
		}
		if (isset($one) && $one->date === date('Y-m-d')) {
			throw new Exception(Exception::REQUEST_TOO_MUCH, '你今天已经在他的书房里借阅了一本书~');
		}

		// 这个当时为什么只存了个日期字符串,算了将错就错吧
		$date = date('Y-m-d');

		$history->bookIsbn = $book->isbn;
		$history->bookTitle = $book->title;
		$history->bookCover = $book->cover;
		$history->date = date('Y-m-d');
		$history->formId = $formId;
		$history->requestStatus = 0;
		$history->insert();

		// 插一条消息到聊天记录
		$requestExtra = [
			'isbn'  => $book->isbn,
			'title' => $book->title,
			'cover' => $book->cover,
			'date'  => $date,
		];
		Graph::sendRequest($selfId, $toUser, json_stringify($requestExtra));

		// 发通知短信
		/** @var MUser $sendSmsUser */
		$sendSmsUser = Graph::findUserById($toUser);
		if ($sendSmsUser !== false && !empty($sendSmsUser->mobile)) {
			sendBorrowBookSms(
				$sendSmsUser->mobile, \Visitor::instance()->getUser()->nickname, $book->title);
		}

		return 'ok';
	}

	public function updateBorrowRequest($requestId, $status) {
		$this->checkAuth();

		$history = new MBorrowHistory();
		$history->id = $requestId;

		/** @var MBorrowHistory $one */
		$one = $history->findOne();
		if ($one === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '借书请求不存在~');
		}

		$user = \Visitor::instance()->getUser();
		if ($one->to !== $user->id) {
			throw new Exception(Exception::BAD_REQUEST , '不属于你的请求不能处理~');
		}

		if ($one->requestStatus !== '0') {
			throw new Exception(Exception::BAD_REQUEST , '重复请求~');
		}

		// 检查联系方式是不是设置了
		if ($status === 1) {
			$hasContact = false;
			$contactJson = $user->contact;
			$contact = json_decode($contactJson);
			if (isset($contact->name) && isset($contact->contact)) {
				if (in_array($contact->name, ['微信', 'QQ', '邮箱'])
					&& !empty($contact->contact)) {
					$hasContact = true;
				}
			}

			if (!$hasContact) {
				return 'no_contact';
			}
		}

		$one->requestStatus = $status;
		$one->update();

		// 发模板消息
		/** @var MUser $fromUser */
		$fromUser = Graph::findUserById($one->from);
		if ($fromUser !== false) {
			if ($status === 1) {
				$this->sendAgreeBorrowBookMessage(
					$fromUser->openId, $one->formId, $one->bookTitle,
					$user->nickname, $one->date
				);
			} else if ($status === 2) {
				$this->sendDeclineBorrowBookMessage(
					$fromUser->openId, $one->formId, $one->bookTitle,
					$user->nickname, $one->date
				);
			}
		}

		return 'success';
	}

	public function follow($toUser) {
		$this->checkAuth();

		// check user exist
		/** @var MUser $user */
		$user = Graph::findUserById($toUser);
		if ($user === false) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND , '用户不存在~');
		}

		$self = \Visitor::instance()->getUser();
		$selfId = $self->id;
		if ($toUser === $selfId) {
			throw new Exception(Exception::BAD_REQUEST , '不可以关注自己哦~');
		}

		Graph::addFollower($selfId, $toUser);

		$extra = [
			'router' => 'follower',
		];
		// 给被关注的同志发一条系统消息
		Graph::sendSystemMessage(BOCHA_SYSTEM_USER_ID, $toUser,
								 "书友 {$self->nickname} 关注了你~",
								 json_stringify($extra));

		return 'ok';
	}

	public function unfollow($toUser) {
		$this->checkAuth();

		$selfId = \Visitor::instance()->getUser()->id;
		Graph::removeFollower($selfId, $toUser);

		return 'ok';
	}

	public function getMyFollowings() {
		$this->checkAuth();

		$selfId = \Visitor::instance()->getUser()->id;
		$followings = Graph::getFollowings($selfId);
		$result = [];
		if ($followings !== false) {
			$result = array_map(function($following) {
				/** @var MFollow $following */
				$toId = $following->toId;
				/** @var MUser $user */
				$user = Graph::findUserById($toId);
				$addresses = array_map(function($address) {
					return [
						'name'      => $address->name,
						'detail'    => $address->detail,
						'city'      => json_decode($address->city),
					];
				}, $user->getAddressList());
				$bookCount = $user->getBookListCount();
				return [
					'id'        => $user->id,
					'nickname'  => $user->nickname,
					'avatar'    => $user->avatar,
					'address'   => $addresses,
					'bookCount' => $bookCount
				];
			}, $followings);
		}

		return $result;
	}

	public function getMyFollowers() {
		$this->checkAuth();

		$selfId = \Visitor::instance()->getUser()->id;
		$followers = Graph::getFollowers($selfId);
		$result = [];
		if ($followers !== false) {
			$result = array_map(function($follower) {
				/** @var MFollow $follower */
				$fromId = $follower->fromId;
				/** @var MUser $user */
				$user = Graph::findUserById($fromId);
				$addresses = array_map(function($address) {
					return [
						'name'      => $address->name,
						'detail'    => $address->detail,
						'city'      => json_decode($address->city),
					];
				}, $user->getAddressList());
				$bookCount = $user->getBookListCount();
				return [
					'id'        => $user->id,
					'nickname'  => $user->nickname,
					'avatar'    => $user->avatar,
					'address'   => $addresses,
					'bookCount' => $bookCount
				];
			}, $followers);
		}

		return $result;
	}

	public function requestVerifyCode($mobile) {
		$this->checkAuth(true/* 验证短信当然不能要求人家已经绑了手机啦,这简直就是跟着客户端一起二 */);

		$user = \Visitor::instance()->getUser();
		if (!empty($user->mobile) && $user->mobile === $mobile) {
			throw new Exception(Exception::RESOURCE_ALREADY_ADDED, '你已经绑定过这个手机号了~');
		}

		/** @var MSmsCode $verifyCode */
		$verifyCode = Graph::findCodeByUser($user->id, $mobile);
		if ($verifyCode !== false && (strtotime('now') - intval($verifyCode->createTime)) < 60) {
			throw new Exception(Exception::REQUEST_TOO_MUCH, '请求过于频繁,请稍后再试~');
		}
		
		$codeNum = randCode(6, 1); // 6位数字
		if (sendVeriCodeSms($mobile, $codeNum)) {
			Graph::insertSmsCode($user->id, $mobile, $codeNum);
			return 'ok';
		}

		throw new Exception(Exception::INTERNAL_ERROR, '验证码发送失败,请稍后再试~');
	}
	
	public function verifyCode($mobile, $code) {
		$this->checkAuth(true);

		$user = \Visitor::instance()->getUser();
		/** @var MSmsCode $verifyCode */
		$verifyCode = Graph::findCode($user->id, $mobile, $code);

		if ($verifyCode !== false
			&& ((intval($verifyCode->createTime) + 300) > strtotime('now'))) {
			// TODO 要不要从数据库里删除?
			$user->mobile = $mobile;
			$user->update();
			return 'ok';
		}

		throw new Exception(Exception::VERIFY_CODE_EXPIRED, '验证码错误或者已经过期,请重新发送验证码~');
	}

	private function sendDeclineBorrowBookMessage(
		$toUserOpenId, $formId, $bookTitle, $hoster, $date) {
		return $this->sendWxTemplateMessage(
			$toUserOpenId,
			'Sp_-WuvoHxYBzxAJMXtH2gop8AwDHuwnzREOr-QkTr4',
			'pages/user/history',
			$formId,
			[
				'keyword1' => ['value' => $bookTitle],
				'keyword2' => ['value' => $date],
				'keyword3' => ['value' => "书友 {$hoster} 拒绝了你借阅《{$bookTitle}》的请求,点击查看详情"]
			],
			'keyword1.DATA'
		);
	}

	private function sendAgreeBorrowBookMessage(
		$toUserOpenId, $formId, $bookTitle, $hoster, $date) {
		return $this->sendWxTemplateMessage(
			$toUserOpenId,
			'Sp_-WuvoHxYBzxAJMXtH2gop8AwDHuwnzREOr-QkTr4',
			'pages/message/approved',
			$formId,
			[
				'keyword1' => ['value' => $bookTitle],
				'keyword2' => ['value' => $date],
				'keyword3' => ['value' => "书友 {$hoster} 同意了你借阅《{$bookTitle}》的请求,点击查看详情"]
			],
			'keyword1.DATA'
		);
	}

	private function sendBorrowBookMessage(
				$toUserOpenId, $formId, $bookTitle, $fromUserNick) {
		return $this->sendWxTemplateMessage(
			$toUserOpenId,
			'Sp_-WuvoHxYBzxAJMXtH2uPu7Iw-AtY2fS-zWRuroU4',
			'pages/user/request',
			$formId,
			[
				'keyword1' => ['value' => $bookTitle],
				'keyword2' => ['value' => "书友 {$fromUserNick} 想借阅你书房里的《{$bookTitle}》,点击查看详情"],
				'keyword3' => ['value' => $fromUserNick],
				'keyword4' => ['value' => date('Y-m-d H:m')]
			],
			'keyword1.DATA'
		);
	}

	private function sendWxTemplateMessage(
					$toUserOpenId, $templateId,
					$page, $formId, $data = [], $keyword) {
		// TODO 微信模板消息发失败显然不能往客户端抛错,记录下Log?
		$access_token = \WxAccessTokenManager::instance()->getAccessToken();
		if ($access_token === false) {
			//throw new Exception(Exception::INTERNAL_ERROR, '无法获取微信access token');
		}
		$url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token={$access_token}";
		$data = array(
			'touser'           => $toUserOpenId,
			'template_id'      => $templateId,
			'page'             => $page,
			'form_id'          => $formId,
			'data'             => $data,
			'emphasis_keyword' => $keyword
		);

		$options = array(
			'http' => array(
				'header'  => "Content-type:application/json",
				'method'  => 'POST',
				'content' => json_encode($data, true),
				'timeout' => 60
			)
		);

		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);

		if ($result === false) {
			//throw new Exception(Exception::INTERNAL_ERROR, '无法发送微信模板消息');
		}

		$json = json_decode($result);
		if (!empty($json->errcode) || $json->errcode > 0) {
			//throw new Exception(Exception::INTERNAL_ERROR, '发送微信模板消息失败:'.$json->errcode.", ".$json->errmsg);
		}

		return true;
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

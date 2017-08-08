<?php
/**
 * Created by Cui Yi
 * 17/5/20
 */

namespace Graph;

define ('MSG_TYPE_TEXT', 0);
define ('MSG_TYPE_BORROW', 1);
define ('MSG_TYPE_CONTACT', 2);

define ('MSG_STATUS_NORMAL', 0);
define ('MSG_STATUS_DEL_BY_SENDER', 1);
define ('MSG_STATUS_DEL_BY_RECEIVER', 2);
define ('MSG_STATUS_DEL_BY_BOTH', 2);

class Graph {

	/**
	 * 先这样简单防一下注入,后面赶紧改用框架写吧
	 * @param $escapestr
	 * @return string
	 * @throws \Exception
	 */
	public static function escape($escapestr) {
		$mysqli = DataConnection::getConnection();
		if ($mysqli == null) {
			return '';
		}
		return $mysqli->escape_string($escapestr);
	}

	public static function findUser($token) {
		$user = new MUser();
		$user->token = $token;
		return $user->findOne();
	}

	public static function findUserById($id) {
		$user = new MUser();
		$user->id = $id;
		return $user->load();
	}

	public static function findMobileByUserId($id) {
		$query = new MUser();
		$query->id = $id;
		/** @var MUser $user */
		$user = $query->load();

		if ($user !== false) {
			return $user->mobile;
		}
		return null;
	}

	public static function findBook($isbn) {
		$book = new MBook();
		$book->isbn = $isbn;
		return $book->findOne();
	}

	public static function findUserAddress($userId) {
		$address = new MUserAddress();
		$address->userId = $userId;
		return $address->findOne();
	}

	public static function findXu($name) {
		$xu = new \Graph\MXu();
		$xu->name = $name;
		return $xu->findOne();
	}

	public static function insertSmsCode($userId, $mobile, $code) {
		$query = new MSmsCode();
		$query->userId = $userId;
		$query->mobile = $mobile;

		/** @var MSmsCode $smsCode */
		$smsCode = $query->findOne();
		if ($smsCode === false) {
			$query->code = $code;
			$query->createTime = strtotime('now');
			$query->insert();
		} else {
			// 直接覆盖
			$smsCode ->code = $code;
			$smsCode->createTime = strtotime('now');
			$smsCode->update();
		}
	}

	public static function addFollower($fromId, $toId) {
		$query = new MFollow();
		$query->fromId = $fromId;
		$query->toId = $toId;

		$follow = $query->findOne();
		if ($follow === false) {
			$query->createTime = strtotime('now');
			$query->insert();
		}
	}

	public static function removeFollower($fromId, $toId) {
		$query = new MFollow();
		$query->fromId = $fromId;
		$query->toId = $toId;
		$query->delete();
	}

	public static function getFollowings($fromId) {
		$query = new MFollow();
		$query->fromId = $fromId;
		return $query->find();
	}

	public static function getFollowers($toId) {
		$query = new MFollow();
		$query->toId = $toId;
		return $query->find();
	}

	public static function isFollowing($fromId, $toId) {
		$query = new MFollow();
		$query->fromId = $fromId;
		$query->toId = $toId;
		return $query->findOne() !== false;
	}

	public static function getFollowerCount($toId) {
		$query = new MFollow();
		$query->toId = $toId;
		return $query->count();
	}

	public static function getFollowingCount($fromId) {
		$query = new MFollow();
		$query->fromId = $fromId;
		return $query->count();
	}

	public static function findCode($userId, $mobile, $code) {
		$query = new MSmsCode();
		$query->userId = $userId;
		$query->mobile = $mobile;
		$query->code = $code;

		return $query->findOne();
	}

	public static function findCodeByUser($userId, $mobile) {
		$query = new MSmsCode();
		$query->userId = $userId;
		$query->mobile = $mobile;

		return $query->findOne();
	}

	public static function sendRequest($from, $to, $request) {
		$timestamp = strtotime('now');

		// update both user in chat table
		self::updateChat($from, $to, $from, '', MSG_TYPE_BORROW, $timestamp, $request);
		self::updateChat($to, $from, $from, '', MSG_TYPE_BORROW, $timestamp, $request);

		// insert a new message
		self::insertNewMessage($from, $to, '', MSG_TYPE_BORROW, $timestamp, $request);
	}

	public static function sendContact($from, $to, $contact) {
		$timestamp = strtotime('now');

		// update both user in chat table
		self::updateChat($from, $to, $from, '', MSG_TYPE_CONTACT, $timestamp, $contact);
		self::updateChat($to, $from, $from, '', MSG_TYPE_CONTACT, $timestamp, $contact);

		// insert a new message
		self::insertNewMessage($from, $to, '', MSG_TYPE_CONTACT, $timestamp, $contact);
	}

	public static function sendMessage($from, $to, $message) {

		$message = self::escape($message);
		$timestamp = strtotime('now');

		// update both user in chat table
		self::updateChat($from, $to, $from, $message, MSG_TYPE_TEXT, $timestamp, '');
		self::updateChat($to, $from, $from, $message, MSG_TYPE_TEXT, $timestamp, '');

		// insert a new message
		self::insertNewMessage($from, $to, $message, MSG_TYPE_TEXT, $timestamp, '');
	}

	public static function insertNewMessage($from, $to, $message, $msgType, $timestamp, $extra) {
		$query = new MChatMessage();
		$query->user1 = $from;
		$query->user2 = $to;
		$query->msgContent = $message;
		$query->msgType = $msgType;
		$query->status = MSG_STATUS_NORMAL;
		$query->timestamp = $timestamp;
		$query->extra = $extra;
		$query->insert();
	}

	public static function updateChat(
		$user1, $user2, $sender, $message, $msgType, $timestamp, $extra) {
		$query = new MChat();
		$query->user1 = $user1;
		$query->user2 = $user2;
		$isReceiver = ($user1 !== $sender);

		/** @var MChat $chat */
		$chat = $query->findOne();
		if ($chat === false) {
			$query->msgSender = $sender;
			$query->msgContent = $message;
			$query->msgType = $msgType;
			$query->status = MSG_STATUS_NORMAL;
			$query->timestamp = $timestamp;
			$query->extra = $extra;
			if ($isReceiver) {
				$query->unreadCount = 1;
			} else {
				$query->unreadCount = 0;
			}
			$query->insert();
		} else {
			$chat->msgSender = $sender;
			$chat->msgContent = $message;
			$chat->msgType = $msgType;
			$chat->status = MSG_STATUS_NORMAL;
			$chat->timestamp = $timestamp;
			$chat->extra = $extra;
			if ($isReceiver) {
				$chat->update('unread_count = unread_count + 1');
			} else {
				$chat->update();
			}
		}
	}

	public static function clearUnread($user1, $user2) {
		$query = new MChat();
		$query->unreadCount = 0;
		$query->modify("user_1 = {$user1} and user_2 = {$user2}", '');
	}
}

class DataConnection {
	/** @var \mysqli */
	private static $connection = null;

	public static function getConnection() {
		if (self::$connection == null) {
			self::$connection = mysqli_connect(DB_HOST, DB_USER, DB_PWD, DB_DATABASE, DB_PORT);
			if (mysqli_connect_errno()) {
				throw new \Exception('Connect db error: ' . mysqli_connect_error());
			}
			if (!self::$connection->set_charset('utf8mb4')) {
				throw new \Exception('Set charset error: ' . mysqli_connect_error());
			}
		}
		return self::$connection;
	}
}

class Data {
	public $key, $table, $columns;

	public function init($options) {
		$this->key = $options['key'];
		$this->table = $options['table'];
		$this->columns = $options['columns'];
	}

	public function reset() {
		foreach ($this->columns as $objCol => $dbCol) {
			$this->$objCol = null;
		}
	}

	public function insert() {
		$columns = '';
		$values = '';
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->columns[$this->key] === $dbCol && !isset($this->$objCol)) {
				continue;
			}
			if (!isset($this->$objCol)) {
				throw new \Exception("insert into table {$this->table}, column {$dbCol} is missing");
			}
			$columns .= "{$dbCol},";
			$values .= "'{$this->$objCol}',";
		}

		// 去掉最后的逗号
		if (strlen($columns) > 0) {
			$columns = substr($columns, 0, strlen($columns) - 1);
		}
		if (strlen($values) > 0) {
			$values = substr($values, 0, strlen($values) - 1);
		}

		$sql = "insert into {$this->table} ({$columns}) values ({$values})";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$connection->query($sql);
		if (!empty($connection->error)) {
			throw new \Exception('Insert error: ' . $connection->error);
		}
		return $connection->insert_id;
	}

	public function delete() {
		$where = 'where 1=1 ';
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->$objCol) {
				$where .= " and $dbCol = '{$this->$objCol}'";
			}
		}
		$sql = "delete from {$this->table} $where";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$connection->query($sql);
		if (!empty($connection->error)) {
			throw new \Exception('Delete error: ' . $connection->error);
		}
		return $connection->affected_rows;
	}

	public function update($update = '') {
		$key = $this->key;
		$id = $this->$key;
		if ($id == null) {
			throw new \Exception('Update error, id must be specified');
		}

		$updates = '';
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->columns[$this->key] === $dbCol) {
				continue;
			}
			if (isset($this->$objCol)) {
				$updates .= "$dbCol = '{$this->$objCol}',";
			}
		}

		if ($update === '') {
			// 去掉最后的逗号
			if (strlen($updates) > 0) {
				$updates = substr($updates, 0, strlen($updates) - 1);
			}
		} else {
			$updates .= $update;
		}

		$sql = "update {$this->table} set {$updates} where {$this->columns[$key]} = {$id}";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$connection->query($sql);
		if (!empty($connection->error)) {
			throw new \Exception('Update error: ' . $connection->error);
		}
		return $connection->affected_rows;
	}

	public function modify($where, $update) {
		$updates = '';
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->columns[$this->key] === $dbCol) {
				continue;
			}
			if (isset($this->$objCol)) {
				$updates .= "$dbCol = '{$this->$objCol}',";
			}
		}

		if ($update === '') {
			// 去掉最后的逗号
			if (strlen($updates) > 0) {
				$updates = substr($updates, 0, strlen($updates) - 1);
			}
		} else {
			$updates .= $update;
		}

		$sql = "update {$this->table} set {$updates} where {$where}";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$connection->query($sql);
		if (!empty($connection->error)) {
			throw new \Exception('Modify error: ' . $connection->error);
		}
		return $connection->affected_rows;
	}

	public function clear($query) {
		$key = $this->key;
		$id = $this->$key;
		if ($id == null) {
			throw new \Exception('Update error, id must be specified');
		}

		$sql = "update {$this->table} set {$query} where {$this->columns[$key]} = {$id}";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$connection->query($sql);
		if (!empty($connection->error)) {
			throw new \Exception('Clear error: ' . $connection->error);
		}
		return $connection->affected_rows;
	}

	public function load() {
		$key = $this->key;
		$id = $this->$key;
		if ($id == null) {
			throw new \Exception('Load error, id must be specified');
		}
		$sql = "select * from {$this->table} where {$this->columns[$key]} = {$id}";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return false;
		}
		$rs = $connection->query($sql);
		if ($rs === false) {
			throw new \Exception('Query error: ' . $connection->error);
		} else {
			$row = $rs->fetch_assoc();
			if ($row) {
				foreach ($this->columns as $objCol => $dbCol) {
					$this->$objCol = $row["$dbCol"];
				}
				return $this;
			} else {
				return false;
			}
		}
	}

	public function find() {
		$result = array();
		$where = 'where 1=1 ';
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->$objCol) {
				$where .= " and $dbCol = '{$this->$objCol}'";
			}
		}
		$key = $this->key;
		$sql = "select * from {$this->table} $where ORDER BY {$this->columns[$key]} DESC";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$rs = $connection->query($sql);
		if ($rs === false) {
			throw new \Exception('Query error: ' . $connection->error);
		} else {
			$row = $rs->fetch_assoc();
			while ($row) {
				$o = clone $this;
				foreach ($o->columns as $objCol => $dbCol) {
					$o->$objCol = $row[$dbCol];
				}
				$result[] = $o;
				$row = $rs->fetch_assoc();
			}
			return $result;
		}
	}

	public function count() {
		$where = 'where 1=1 ';
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->$objCol) {
				$where .= " and $dbCol = '{$this->$objCol}'";
			}
		}
		$key = $this->key;
		$sql = "select count(*) from {$this->table} $where ORDER BY {$this->columns[$key]} DESC";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$rs = $connection->query($sql);
		if ($rs === false) {
			throw new \Exception('Query count error: ' . $connection->error);
		} else {
			$row = $rs->fetch_row();
			return intval($row[0]);
		}
	}

	/**
	 * 先凑合着用吧
	 */
	public function query($query, $orderBy = '') {
		$result = array();
		$where = 'where 1=1';
		if (!empty($query)) {
			$where .= ' and ' . $query;
		}
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->$objCol) {
				$where .= " and $dbCol = '{$this->$objCol}'";
			}
		}
		$sql = "select * from {$this->table} {$where} {$orderBy}";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$rs = $connection->query($sql);
		if ($rs === false) {
			throw new \Exception('Query error: ' . $connection->error);
		} else {
			$row = $rs->fetch_assoc();
			while ($row) {
				$o = clone $this;
				foreach ($o->columns as $objCol => $dbCol) {
					$o->$objCol = $row[$dbCol];
				}
				$result[] = $o;
				$row = $rs->fetch_assoc();
			}
			return $result;
		}
	}

	public function findOne() {
		$where = 'where 1=1 ';
		foreach ($this->columns as $objCol => $dbCol) {
			if ($this->$objCol) {
				$where .= " and $dbCol = '{$this->$objCol}'";
			}
		}
		$sql = "select * from {$this->table} $where";
		$connection = DataConnection::getConnection();
		if ($connection == null) {
			return null;
		}
		$rs = $connection->query($sql);
		if ($rs === false) {
			throw new \Exception('Query error: ' . $connection->error);
		} else {
			$row = $rs->fetch_assoc();
			if ($row) {
				$o = clone $this;
				foreach ($o->columns as $objCol => $dbCol) {
					$o->$objCol = $row[$dbCol];
				}
				return $o;
			}
		}
		return false;
	}
}

/**
 * Class MUser
 * @property mixed id
 * @property mixed token
 * @property mixed openId
 * @property mixed session
 * @property mixed createTime
 * @property mixed expireTime
 * @property mixed nickname
 * @property mixed avatar
 * @property mixed contact
 * @property mixed mobile
 */
class MUser extends Data {
	public function __construct() {
		$options = array(
			'key' => 'id',
			'table' => 'bocha_user',
			'columns' => array(
				'id'         => 'user_id',
				'token'      => 'token',
				'openId'     => 'wechat_open_id',
				'session'    => 'wechat_session',
				'createTime' => 'create_time',
				'expireTime' => 'expire_time',
				'nickname'   => 'nickname',
				'avatar'     => 'avatar',
				'contact'    => 'contact',
				'mobile'     => 'mobile'
			)
		);
		parent::init($options);
	}

	//---------- User info

	public function getInfo() {
		$userInfo = new MUserInfo();
		$userInfo->userId = $this->id;
		return $userInfo->findOne();
	}

	public function updateInfo($newInfo) {
		$userInfo = new MUserInfo();
		$userInfo->userId = $this->id;
		/** @var MUserInfo $info */
		$info = $userInfo->findOne();
		if ($info !== false) {
			$info->info = $newInfo;
			$info->update();
		} else {
			$userInfo->info = $newInfo;
			$userInfo->insert();
		}
	}

	//---------- User info end

	//---------- User books

	public function removeBook($isbn) {
		$userBook = new MUserBook();
		$userBook->userId = $this->id;
		$userBook->isbn = $isbn;
		return $userBook->delete();
	}

	public function getBookList() {
		$userBook = new MUserBook();
		$userBook->userId = $this->id;
		return $userBook->find();
	}

	public function getBookListCount() {
		$userBook = new MUserBook();
		$userBook->userId = $this->id;
		return $userBook->count();
	}

	public function isBookAdded($isbn) {
		$userBook = new MUserBook();
		$userBook->userId = $this->id;
		$userBook->isbn = $isbn;
		return $userBook->findOne() !== false;
	}

	//---------- User books end

	//---------- User addresses

	public function addAddress($address) {
		$userAddress = new MUserAddress();
		$userAddress->userId = $this->id;
		$userAddress->name = $address['name'];
		$userAddress->detail = $address['detail'];
		$userAddress->longitude = $address['longitude'];
		$userAddress->latitude = $address['latitude'];
		$userAddressList = $userAddress->find();
		if (count($userAddressList) > 0) {
			throw new \Exception('不可以添加重复的地址哦~');
		} else {
			$userAddress->insert();
		}
	}

	public function removeAddress($addressId) {
		$userAddress = new MUserAddress();
		$userAddress->userId = $this->id;
		$userAddress->id = $addressId;
		return $userAddress->delete();
	}

	public function getAddressList() {
		$userAddress = new MUserAddress();
		$userAddress->userId = $this->id;
		return $userAddress->find();
	}
	//---------- User addresses end
}

/**
 * Class MUserInfo
 * @property mixed id
 * @property mixed userId
 * @property mixed info
 */
class MUserInfo extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_info',
			'columns' => [
				'id'          => '_id',
				'userId'     => 'user_id',
				'info'     => 'info'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MUserAddress
 * @property mixed id
 * @property mixed userId
 * @property mixed name
 * @property mixed detail
 * @property mixed longitude
 * @property mixed latitude
 * @property mixed city
 */
class MUserAddress extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_user_address',
			'columns' => [
				'id'        => '_id',
				'userId'    => 'user_id',
				'name'      => 'address',
				'detail'    => 'detail',
				'longitude' => 'longitude',
				'latitude'  => 'latitude',
				'city'      => 'city'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MUserBook
 * @property mixed id
 * @property mixed userId
 * @property mixed isbn
 */
class MUserBook extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_user_book',
			'columns' => [
				'id'      => '_id',
				'userId'  => 'user_id',
				'isbn'    => 'isbn'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MBook
 * @property mixed isbn
 * @property mixed title
 * @property mixed author
 * @property mixed cover
 * @property mixed publisher
 * @property mixed trueIsbn
 */
class MBook extends Data {
	public function __construct() {
		$options = [
			'key'     => 'isbn',
			'table'   => 'bocha_book',
			'columns' => [
				'isbn'      => 'isbn',
				'title'     => 'title',
				'author'    => 'author',
				'cover'     => 'cover',
				'publisher' => 'publisher',
				'trueIsbn'  => 'true_isbn',
			]
		];
		parent::init($options);
	}

	public function updateBook($doubanBook) {
		$this->isbn = $doubanBook->id;
		$one = $this->findOne();
		if ($one === false) {
			$this->title = $doubanBook->title;
			$this->author = json_stringify($doubanBook->author);
			$this->cover = $doubanBook->image;
			$this->publisher = $doubanBook->publisher;
			$this->trueIsbn = empty($doubanBook->isbn13) ? 'fake_isbn' : $doubanBook->isbn13;
			$this->insert();
		}
	}
}

/**
 * Class MBorrowHistory
 * @property mixed id
 * @property mixed from
 * @property mixed to
 * @property mixed bookIsbn
 * @property mixed bookTitle
 * @property mixed bookCover
 * @property mixed date
 * @property mixed requestStatus
 * @property mixed formId
 */
class MBorrowHistory extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_borrow_history',
			'columns' => [
				'id'        => '_id',
				'from'      => 'from_user',
				'to'        => 'to_user',
				'bookIsbn'  => 'book_isbn',
				'bookTitle' => 'book_title',
				'bookCover' => 'book_cover',
				'date'      => 'date',
				'requestStatus'    => 'status',
				'formId'    => 'form_id'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MSmsCode
 * @property mixed id
 * @property mixed userId
 * @property mixed mobile
 * @property mixed code
 * @property mixed createTime
 */
class MSmsCode extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_sms_code',
			'columns' => [
				'id'         => '_id',
				'userId'     => 'user_id',
				'mobile'     => 'mobile',
				'code'       => 'code',
				'createTime' => 'create_time'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MFollow
 * @property mixed id
 * @property mixed fromId
 * @property mixed toId
 * @property mixed createTime
 */
class MFollow extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_follow',
			'columns' => [
				'id'         => '_id',
				'fromId'     => 'from_id',
				'toId'     => 'to_id',
				'createTime' => 'create_time'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MChat
 * @property mixed id
 * @property mixed user1
 * @property mixed user2
 * @property mixed msgContent
 * @property mixed msgSender
 * @property mixed msgType
 * @property mixed status
 * @property mixed timestamp
 * @property mixed extra
 * @property mixed unreadCount
 */
class MChat extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_chat',
			'columns' => [
				'id'          => '_id',
				'user1'       => 'user_1',
				'user2'       => 'user_2',
				'msgContent'  => 'msg_content',
				'msgSender'   => 'msg_sender',
				'msgType'     => 'msg_type',
				'status'      => 'status',
				'timestamp'   => 'timestamp',
				'extra'       => 'extra',
				'unreadCount' => 'unread_count'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MChatMessage
 * @property mixed id
 * @property mixed user1
 * @property mixed user2
 * @property mixed msgContent
 * @property mixed msgType
 * @property mixed status
 * @property mixed timestamp
 * @property mixed extra
 */
class MChatMessage extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_chat_message',
			'columns' => [
				'id'         => '_id',
				'user1'      => 'user_1',
				'user2'      => 'user_2',
				'msgContent' => 'msg_content',
				'msgType'    => 'msg_type',
				'status'     => 'status',
				'timestamp'  => 'timestamp',
				'extra'      => 'extra'
			]
		];
		parent::init($options);
	}
}

/**
 * Class MXu
 * @property mixed id
 * @property mixed name
 * @property mixed value
 * @property mixed createTime
 * @property mixed expireTime
 */
class MXu extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_xu',
			'columns' => [
				'id'         => '_id',
				'name'       => 'name',
				'value'      => 'value',
				'createTime' => 'create_time',
				'expireTime' => 'expire_time',
			]
		];
		parent::init($options);
	}
}

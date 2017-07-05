<?php
/**
 * Created by Cui Yi
 * 17/5/20
 */

namespace Graph;

class Graph {
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

	public static function findBook($isbn) {
		$book = new MBook();
		$book->isbn = $isbn;
		return $book->findOne();
	}

	public static function findXu($name) {
		$xu = new \Graph\MXu();
		$xu->name = $name;
		return $xu->findOne();
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

	public function update() {
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
			if ($this->$objCol) {
				$updates .= "$dbCol = '{$this->$objCol}',";
			}
		}
		// 去掉最后的逗号
		if (strlen($updates) > 0) {
			$updates = substr($updates, 0, strlen($updates) - 1);
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

	/**
	 * 先凑合着用吧
	 */
	public function query($query) {
		$result = array();
		$where = 'where 1=1 and ' . $query;
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
 */
class MUser extends Data {
	public function __construct() {
		$options = array(
			'key' => 'id',
			'table' => 'bocha_user',
			'columns' => array(
				'id' => 'user_id',
				'token' => 'token',
				'openId' => 'wechat_open_id',
				'session' => 'wechat_session',
				'createTime' => 'create_time',
				'expireTime' => 'expire_time',
				'nickname' => 'nickname',
				'avatar' => 'avatar',
				'contact' => 'contact'
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
 */
class MUserAddress extends Data {
	public function __construct() {
		$options = [
			'key'     => 'id',
			'table'   => 'bocha_user_address',
			'columns' => [
				'id'          => '_id',
				'userId'     => 'user_id',
				'name'     => 'address',
				'detail'     => 'detail',
				'longitude'     => 'longitude',
				'latitude'     => 'latitude'
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
			$this->trueIsbn = $doubanBook->isbn13;
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

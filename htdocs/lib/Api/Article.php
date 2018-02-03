<?php
/**
 * Created by Cui Yi
 * 2018/2/3
 */

namespace Api;

use Graph\Graph;
use Graph\MArticle;
use Graph\MAuthor;

class Article extends ApiBase {

	public function getArticle($id) {
		$query = new MArticle();
		$query->id = $id;

		/** @var MArticle $article */
		$article = $query->findOne();
		if ($article === false || intval($article->status) === CARD_STATUS_DELETED) {
			throw new Exception(Exception::RESOURCE_NOT_FOUND, '文章已被删除');
		} else {
			// find author
			/** @var MAuthor $author */
			$author = Graph::findAuthorById($article->authorId);
			if ($author === false) {
				$author = new MAuthor();
				$author->id = 1;
				$author->nickname = '有读书房';
				$author->avatar = 'http://othb16dht.bkt.clouddn.com/Fm3qYpsmNFGRDbWeTOQDRDfiJz9l?imageView2/1/w/640/h/640/format/jpg/q/75|imageslim';
			}

			// 增加一次浏览
			$query->update('read_count = read_count + 1');

			return [
				'id'            => $article->id,
				'user'          => [
					'id'       => $author->id,
					'nickname' => $author->nickname,
					'avatar'   => $author->avatar,
				],
				'title'         => $article->title,
				'content'       => $article->content,
				'picUrl'        => $article->picUrl,
				'createTime'    => $article->createTime,
				'readCount'     => intval($article->readCount),
			];
		}
	}
}
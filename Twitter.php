<?php

require_once 'HTTP/OAuth/Consumer.php';

/**
 * Description of Twitter
 *
 * @author matsui
 */
class RPL_Util_Service_Twitter {

    public $noSession;
    public $unqKey;
    public $consumer;
    public $consumerKey;
    public $consumerSecret;
    public $requestToken;
    public $requestTokenSecret;
    public $accessToken;
    public $accessTokenSecret;
    public $userData;
    public $lastResponse;

    /**
     * コンストラクタ
     */
    public function __construct($params, $noSession = FALSE) {
        if (!$noSession) {
            if (!session_id()) {
                session_start();
            }
        }
        $this->lastResponse = NULL;
        $this->requestToken = NULL;
        $this->requestTokenSecret = NULL;
        $this->consumerKey = $params['key'];
        $this->setAppId($params['key']);
        $this->consumerSecret = $params['secret'];
        $this->consumer = new HTTP_OAuth_Consumer($this->consumerKey, $this->consumerSecret);
        $http_request = new HTTP_Request2();
        $http_request->setConfig('ssl_verify_peer', false);
        $consumer_request = new HTTP_OAuth_Consumer_Request;
        $consumer_request->accept($http_request);
        $this->consumer->accept($consumer_request);

        $this->userData = NULL;
    }

    /**
     * 認証用URL(Authrize)取得
     * @param type $params callback
     * @return string 認証URL
     */
    public function getAuthrizeUrl($params) {
        $this->consumer->getRequestToken('https://api.twitter.com/oauth/request_token', $params['callback']);
        $this->requestToken = $this->consumer->getToken();
        $this->requestTokenSecret = $this->consumer->getTokenSecret();

        $authUrl = $this->consumer->getAuthorizeUrl('https://api.twitter.com/oauth/authorize');

        $this->setSession('REQUEST_TOKEN', $this->requestToken);
        $this->setSession('REQUEST_TOKEN_SECRET', $this->requestTokenSecret);

        $this->clearSession('ACCESS_TOKEN');
        $this->clearSession('ACCESS_TOKEN_SECRET');

        return $authUrl;
    }

    /**
     * 認証用URL(Authenticate)取得
     * @param type $params callback
     * @return string 認証URL
     */
    public function getAuthenticateUrl($params) {
        $this->consumer->getRequestToken('https://api.twitter.com/oauth/request_token', $params['callback']);
        $this->requestToken = $this->consumer->getToken();
        $this->requestTokenSecret = $this->consumer->getTokenSecret();

        $authUrl = $this->consumer->getAuthorizeUrl('https://api.twitter.com/oauth/authenticate');

        $this->setSession('REQUEST_TOKEN', $this->requestToken);
        $this->setSession('REQUEST_TOKEN_SECRET', $this->requestTokenSecret);

        $this->clearSession('ACCESS_TOKEN');
        $this->clearSession('ACCESS_TOKEN_SECRET');

        return $authUrl;
    }

    /**
     * アクセストークンをTwitterサーバーから取得
     * @param type $verifier
     */
    public function getAccessTokenFromTwitter($verifier) {
        $requestToken = $this->getSession('REQUEST_TOKEN');
        $requestTokenSecret = $this->getSession('REQUEST_TOKEN_SECRET');
        $this->consumer->setToken($requestToken);
        $this->consumer->setTokenSecret($requestTokenSecret);
        $this->consumer->getAccessToken('https://twitter.com/oauth/access_token', $verifier);
        $this->accessToken = $this->consumer->getToken();
        $this->accessTokenSecret = $this->consumer->getTokenSecret();

        $this->setSession('ACCESS_TOKEN', $this->accessToken);
        $this->setSession('ACCESS_TOKEN_SECRET', $this->accessTokenSecret);

        return array($this->accessToken, $this->accessTokenSecret);
    }

    /**
     * アクセストークンを取得
     */
    public function getAccessToken() {
        $this->accessToken = $this->getSession('ACCESS_TOKEN');
        return $this->accessToken;
    }

    /**
     * アクセストークンシークレットを取得
     */
    public function getAccessTokenSecret() {
        $this->accessTokenSecret = $this->getSession('ACCESS_TOKEN_SECRET');
        return $this->accessTokenSecret;
    }

    /**
     * アクセストークンを設定
     */
    public function setAccessToken($accessToken) {
        $this->accessToken = $accessToken;
        $this->setSession('ACCESS_TOKEN', $accessToken);
    }

    /**
     * アクセストークンシークレットを設定
     */
    public function setAccessTokenSecret($accessTokenSecret) {
        $this->accessTokenSecret = $accessTokenSecret;
        $this->setSession('ACCESS_TOKEN_SECRET', $accessTokenSecret);
    }

    /**
     * ツイートサービスを取得
     */
    public function getTweetService() {
        return (new RPL_Util_Service_Twitter_Tweet($this));
    }

    /**
     * ダイレクトメッセージサービスを取得
     */
    public function getDirectMessageService() {
        return (new RPL_Util_Service_Twitter_DirectMessage($this));
    }

    /**
     * タイムラインサービスを取得
     */
    public function getTimelineService() {
        return (new RPL_Util_Service_Twitter_Timeline($this));
    }

    /**
     * ユーザーサービスを取得
     */
    public function getUserService() {
        return (new RPL_Util_Service_Twitter_User($this));
    }

    /**
     * API制限情報を取得
     * @param type $params
     * @return type
     * @throws Exception
     */
    public function getRateLimit($params = NULL) {
        // パラメータをクエリに変換
        $query = self::hash2query($params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/application/rate_limit_status.json' . ((strlen($query)) ? '?' . $query : '');

        // リクエスト
        $retArray = $this->sendRequest($url);

        return $retArray;
    }

    /**
     * Twitterへのリクエスト送信
     */
    public function sendRequest($url, $params = array(), $method = 'GET') {
        $this->consumer->setToken($this->getAccessToken());
        $this->consumer->setTokenSecret($this->getAccessTokenSecret());

        // リクエスト
        $this->lastResponse = $this->consumer->sendRequest($url, $params, $method);

        // レスポンスコードを取得
        $status = $this->lastResponse->getResponse()->getStatus();
        if ($status != 200) {
            throw new RPL_Util_Service_Twitter_Exception('', $status, NULL);
        }

        // 戻り値を変換
        $retArray = json_decode($this->lastResponse->getBody(), TRUE);

        return $retArray;
    }

    public function getLastResponse() {
        return $this->lastResponse;
    }

    /**
     * ハッシュからクエリ文字列に変換
     * @param type $params
     * @return string
     */
    public static function hash2query($params) {
        $query = '';
        if (!is_null($params)) {
            foreach ($params as $key => $val) {
                if (strlen($query)) {
                    $query .= '&';
                }
                $query .= $key . '=' . $val;
            }
        }
        return $query;
    }

    /**
     * APPIDの生成
     * @param string $consumerKey 
     */
    protected function setAppId($consumerKey) {
        $this->unqKey = substr($consumerKey, 0, 5);
    }

    /**
     * APPIDの取得
     * @return string AppID 
     */
    protected function getAppId() {
        return $this->unqKey;
    }

    /**
     * セッションのデータ削除
     * @param string $key  　データキー
     */
    protected function clearSession($key) {
        if ($this->getSession($key)) {
            unset($_SESSION[$this->constructSessionVariableName($key)]);
        }
    }

    /**
     * セッションへのデータ保存
     * @param string $key  　データキー
     * @param string $value  値
     */
    protected function setSession($key, $value) {
        $_SESSION[$this->constructSessionVariableName($key)] = $value;
    }

    /**
     * セッションからのデータ取得
     * @param string $key  　データキー
     * @param string $default デフォルト値
     */
    protected function getSession($key, $default = false) {
        return isset($_SESSION[$this->constructSessionVariableName($key)]) ? $_SESSION[$this->constructSessionVariableName($key)] : $default;
    }

    /**
     * セッションキーの生成
     * @param type $key
     * @return type 
     */
    protected function constructSessionVariableName($key) {
        return implode('_', array('twit',
                    $this->getAppId(),
                    $key));
    }

}

/**
 * ユーザーを扱う
 */
class RPL_Util_Service_Twitter_User {

    public $twitter;

    /**
     * コンストラクタ
     */
    public function __construct($twitter) {
        $this->twitter = $twitter;
    }

    /**
     * 認証ユーザーのユーザーデータ取得
     * 
     * @param mixed $params その他パラメータ
     * @return mixed ユーザーデータ
     */
    public function getOAuthUser($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/account/verify_credentials.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 指定されたスクリーンネームのユーザー情報を取得
     * 
     * @param mixed $screenNames 取得すべきユーザースクリーンネーム
     * @param mixed $params その他パラメータ
     * @return mixed 取得したユーザーデータ
     */
    public function getUser($screenNames, $params = array()) {
        // 単一指定に対応
        if (!is_array($screenNames)) {
            $screenNames = array($screenNames);
        }

        // パラメータ
        $params = array_merge(array('screen_name' => implode(',', $screenNames)), $params);

        // URL生成
        $url = 'http://api.twitter.com/1.1/users/lookup.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 指定されたユーザーIDのユーザー情報を取得
     * 
     * @param mixed $userIds 取得すべきユーザーID
     * @param mixed $params その他パラメータ
     * @return mixed 取得したユーザーデータ
     */
    public function getUserFromId($userIds, $params = array()) {
        // 単一指定に対応
        if (!is_array($userIds)) {
            $userIds = array($userIds);
        }

        // パラメータ
        $params = array_merge(array('user_id' => implode(',', $userIds)), $params);

        // URL生成
        $url = 'http://api.twitter.com/1.1/users/lookup.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * フォロワーリスト取得
     * 
     * @param mixed $params その他パラメータ
     * @return mixed 取得したユーザーデータ
     */
    public function getFollowers($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/followers/list.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

}

/**
 * タイムラインを扱う
 */
class RPL_Util_Service_Twitter_Timeline {

    public $twitter;

    /**
     * コンストラクタ
     */
    public function __construct($twitter) {
        $this->twitter = $twitter;
    }

    /**
     * 自分がメンションに含まれているタイムラインの取得
     * 
     * @param mixed $params
     * @return mixed メンションタイムライン
     */
    public function getMentionTimeline($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/mentions_timeline.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 指定ユーザーの発言タイムラインを取得
     * 
     * @param mixed $params その他パラメータ
     * @return mixed ユーザーの発言タイムライン
     */
    public function getUserTimeline($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 認証ユーザーのタイムラインを取得
     * 
     * @param mixed $params その他パラメータ
     * @return mixed 認証ユーザーのタイムライン
     */
    public function getHomeTimeline($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/home_timeline.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * フォロワーによってリツイートされたつぶやき一覧を取得
     * 
     * @param mixed $params その他パラメータ
     * @return mixed リツイートされたつぶやき一覧を取得
     */
    public function getRetweetedTweets($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/retweets_of_me.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 検索
     * 
     * @param string $searchString ハッシュタグなどの検索文字
     * @param mixed $params その他のパラメータ
     * @return mixed 検索でヒットしたツイート一覧
     */
    public function search($searchString, $params = array()) {
        // パラメータ
        $params = array_merge(array('q' => $searchString), $params);

        $url = 'https://api.twitter.com/1.1/search/tweets.json';
        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

}

/**
 * ツイートサービスを扱う
 */
class RPL_Util_Service_Twitter_Friend {

    public $twitter;

    /**
     * コンストラクタ
     */
    public function __construct($twitter) {
        $this->twitter = $twitter;
    }

    /**
     * フレンド(フォロー)リスト取得
     * 
     * @param mixed $params その他パラメータ
     * @return mixed 取得したユーザーデータ
     */
    public function getFriends($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/friends/list.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     */
    public function getNoReTweetUserIds($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/friendships/no_retweets/ids.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     */
    public function getFriendIds($params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/friends/ids.json';

        // リクエスト
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

}

/**
 * ツイートサービスを扱う
 */
class RPL_Util_Service_Twitter_Tweet {

    public $twitter;

    /**
     * コンストラクタ
     */
    public function __construct($twitter) {
        $this->twitter = $twitter;
    }

    /**
     * 指定したツイートへのリツイート取得
     * @param type $tweetId
     * @param type $params
     * @return type
     * @throws Exception
     */
    public function getRetweets($tweetId, $params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/retweets/' . $tweetId . '.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 指定したツイートを取得
     * @param type $tweetId
     * @param type $params
     * @return type
     * @throws Exception
     */
    public function getTweet($tweetId, $params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/show/' . $tweetId . '.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * oEmbedフォーマットのツイートを取得
     * @param type $tweetId
     * @param type $params
     * @return type
     * @throws Exception
     */
    public function getOEmbedTweet($tweetId, $params = array()) {
        // パラメータ
        $params = array_merge(array('id' => $tweetId), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/oembed.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 指定したツイートを削除
     * @param type $tweetId
     * @param type $params
     * @return type
     * @throws Exception
     */
    public function deleteTweet($tweetId, $params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/destroy/' . $tweetId . '.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

    /**
     * リツイート
     * @param type $tweetId
     * @param type $params
     * @return type
     * @throws Exception
     */
    public function reTweet($tweetId, $params = array()) {
        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/retweet/' . $tweetId . '.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

    /**
     * メッセージツイート
     * @param type $message
     * @param type $options
     * @return boolean
     */
    public function tweet($status, $params = array()) {

        // パラメータを作成
        $params = array_merge(array('status' => $status), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/update.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

    /**
     * メディアファイル付きツイート
     * @param type $message
     * @param type $mediaPath
     * @param type $options
     * @return boolean
     */
    public function tweetWithMedia($status, $mediaPath, $params = array()) {
        // パラメータを作成
        $params = array_merge(array('status' => $status, 'media[]' => file_get_contents($mediaPath)), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/update_with_media.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

    /**
     * メディアファイル付きツイート
     * @param type $message
     * @param type $mediaBin
     * @param type $options
     * @return boolean
     */
    public function tweetWithMediaBinary($status, $mediaBin, $params = array()) {
        // パラメータを作成
        $params = array_merge(array('status' => $status, 'media[]' => $mediaBin), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/statuses/update_with_media.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

}

/**
 * ダイレクトメッセージサービスを扱う
 */
class RPL_Util_Service_Twitter_DirectMessage {

    public $twitter;

    /**
     * コンストラクタ
     */
    public function __construct($twitter) {
        $this->twitter = $twitter;
    }

    /**
     * 認証ユーザーに届いたダイレクトメッセージを取得
     * 
     * @param type $params
     * @return type
     */
    public function getRecieveMessages($params = array()) {

        // URL生成
        $url = 'https://api.twitter.com/1.1/direct_messages.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 認証ユーザーが送信したダイレクトメッセージを取得
     * 
     * @param type $params
     * @return type
     */
    public function getSentMessages($params = array()) {

        // URL生成
        $url = 'https://api.twitter.com/1.1/direct_messages/sent.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 指定したIDのメッセージを取得
     * 
     * @param type $params
     * @return type
     */
    public function getMessage($id, $params = array()) {

        // パラメータ
        $params = array_merge(array('id' => $id), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/direct_messages/show.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params);

        return $retArray;
    }

    /**
     * 指定したIDのメッセージを削除(削除できるのは受信者のみ)
     * 
     * @param type $params
     * @return type
     */
    public function deleteMessage($id, $params = array()) {

        // パラメータ
        $params = array_merge(array('id' => $id), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/direct_messages/destroy.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

    /**
     * 新規ダイレクトメッセージを送信
     * 
     * @param string $screenName 送信先ユーザースクリーンネーム
     * @param string $text メッセージ
     * @param mixed $params その他パラメータ
     * @return mixed 送信されたメッセージ
     */
    public function sendMessage($screenName, $text, $params = array()) {

        // パラメータ
        $params = array_merge(array('screen_name' => $screenName, 'text' => $text), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/direct_messages/new.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

    /**
     * 新規ダイレクトメッセージを送信
     * 
     * @param string $userId 送信先ユーザーID
     * @param string $text メッセージ
     * @param mixed $params その他パラメータ
     * @return mixed 送信されたメッセージ
     */
    public function sendMessageAsId($userId, $text, $params = array()) {

        // パラメータ
        $params = array_merge(array('user_id' => $userId, 'text' => $text), $params);

        // URL生成
        $url = 'https://api.twitter.com/1.1/direct_messages/new.json';

        // リクエスト送信
        $retArray = $this->twitter->sendRequest($url, $params, 'POST');

        return $retArray;
    }

}

/**
 * 例外
 */
class RPL_Util_Service_Twitter_Exception extends Exception {

    const STATUS_NOT_MODIFIED = 304;
    const STATUS_BAD_REQUEST = 400;
    const STATUS_UNAUTHORIZED = 401;
    const STATUS_FORBIDDEN = 403;
    const STATUS_NOT_FOUND = 404;
    const STATUS_NOT_ACCEPTABLE = 406;
    const STATUS_ENCHACE_YOUR_CALM = 420;
    const STATUS_INTERNAL_SERVER_ERROR = 500;
    const STATUS_BAD_GATEWAY = 502;
    const STATUS_SERVICE_UNAVAILABLE = 503;

    public $errorMessages = array(
        // (GETに対して)返すべき新しいデータはない
        304 => 'Not Modified',
        // リクエストがAPI制限により無効になった(エラーメッセージあり)
        400 => 'Bad Request',
        // 認証が正しくない、または認証データが見つからない
        401 => 'Unauthorized',
        // API制限によるリクエスト拒否(エラーメッセージあり)
        403 => 'Forbidden',
        // 不正なURLリクエストが送信された、またはリソースが見つからない(ユーザーが見つからないなど)
        404 => 'Not Found',
        // Search APIへのリクエストが不正なフォーマット
        406 => 'Not Acceptable',
        // Search APIまたはTrends APIへのリクエストがAPI制限により無効になった
        420 => 'Enhance Your Calm',
        // Twitterの故障が起こっている
        500 => 'Internal Server Error',
        // Twitterサーバーが停止している、またはメンテナンス中
        502 => 'Bad Gateway',
        // Twitterサーバーが高負荷状態(オーバーロード)
        503 => 'Service Unavailable',
    );

    public function __construct($message, $code, $previous) {

        $message = $this->errorMessages[$code];

        parent::__construct($message, $code, $previous);
    }

}

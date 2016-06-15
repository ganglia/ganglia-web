<?php
class GangliaAuth {
  private static $auth;

  private $user;
  private $group;
  private $tokenIsValid;

  public static function getInstance() {
    if(is_null(self::$auth)) {
      self::$auth = new GangliaAuth();
    }
    return self::$auth;
  }

  public static function destroyInstance() {
    self::$auth = null;
  }

  public function __construct() {
    // separated for testability. need to disable constructor in some tests & run init later.
    $this->init();
  }

  public function init() {
    if(!$this->environmentIsValid()) {
      return false;
    }

    $this->user = null;
    $this->group = null;
    $this->tokenIsValid = false;

    if(isset($_COOKIE['ganglia_auth'])) {
      $cookie = $_COOKIE['ganglia_auth'];
      // magic quotes will break unserialization
      if($this->getMagicQuotesGpc()) {
        $cookie = stripslashes($cookie);
      }
      $data = json_decode($cookie, TRUE);

      if(array_keys($data) != array('user','group','token')) {
        return false;
      }

      if($this->getAuthToken($data['user']) === $data['token']) {
        $this->tokenIsValid = true;
        $this->user = $data['user'];
        $this->group = $data['group'];
      }
    }
  }

  public function getUser() {
    return $this->user;
  }

  public function getGroup() {
    return $this->group;
  }

  public function isAuthenticated() {
    return $this->tokenIsValid;
  }

  public function getEnvironmentErrors() {
    $errors = array();
    if(!isset($_SERVER['ganglia_secret'])) {
      $errors[] = "No ganglia_secret set in the server environment.  If you are using Apache, try adding 'SetEnv ganglia_secret ".sha1(mt_rand().microtime())."' to your configuration.";
    }
    return $errors;
  }

  public function environmentIsValid() {
    return count($this->getEnvironmentErrors())==0;
  }

  public function getAuthToken($user) {
    $secret = $_SERVER['ganglia_secret'];
    return sha1( $user.$secret );
  }

  // this is how a user 'logs in'.
  public function setAuthCookie($user, $group=null) {
    setcookie('ganglia_auth', json_encode( array('user'=>$user, 'group'=>$group, 'token'=>$this->getAuthToken($user)) ) );
    $this->user = $user;
    $this->group = $group;
    $this->tokenIsValid = true;
  }

  public function destroyAuthCookie() {
    setcookie('ganglia_auth', '', time());
    self::$auth = null;
  }

  protected function getMagicQuotesGpc() {
    return get_magic_quotes_gpc();
  }
}
?>

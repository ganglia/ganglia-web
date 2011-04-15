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
  
  private function __construct() {
    $this->user = null;
    $this->group = null;
    $this->tokenIsValid = false;
    
    if(isSet($_COOKIE['ganglia_auth'])) {
      $data = unserialize($_COOKIE['ganglia_auth']);
      
      if(array_keys($data) != array('user','group','token')) {
        return false;
      }
      
      if($this->getAuthToken($data['user']) == $data['token']) {
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
  
  public function getAuthToken($user) {
    if(isSet($_SERVER['ganglia_secret'])) {
      $secret = $_SERVER['ganglia_secret'];
    } else {
      $secret = "";
      error_log("No ganglia_secret set in the Apache environment.  Try 'SetEnv ganglia_secret your-secret-here'.");
    }
    return sha1( $user.$secret );
  }
  
  // this is how a user 'logs in'.
  public function setAuthCookie($user, $group=null) {
    setcookie('ganglia_auth', serialize( array('user'=>$user, 'group'=>$group, 'token'=>$this->getAuthToken($user)) ) );
    $this->user = $user;
    $this->group = $group;
    $this->tokenIsValid = true;
  }
  
  public function destroyAuthCookie() {
    setcookie('ganglia_auth', '', time());
    self::$auth = null;
  }
}
?>
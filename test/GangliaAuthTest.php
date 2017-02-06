<?php

$base_dir = dirname(__FILE__);
ini_set( 'include_path', ini_get('include_path').":$base_dir/../lib");
require_once 'GangliaAuth.php';

require_once 'TestCase.php';
class GangliaAuthTest extends TestCase {

  public function setUp() {
    $_SERVER['ganglia_secret'] = 'really really secret';

    $user = 'foo';
    $this->cookie_data = array('user'=>$user, 'group'=>null, 'token'=>sha1($user.$_SERVER['ganglia_secret']));
  }

  public function tearDown() {
    GangliaAuth::destroyInstance();
  }

  // This is the normal way to access the Auth instance.
  public function testGetInstance() {
    $obj1 = GangliaAuth::getInstance();
    $obj2 = GangliaAuth::getInstance();

    $this->assertEquals( $obj1, $obj2 );
  }

  public function testEnvironmentIsInvalidWithoutGangliaSecret() {
    unset($_SERVER['ganglia_secret']);
    $auth = GangliaAuth::getInstance();
    $errors = $auth->getEnvironmentErrors();
    $this->assertEquals("No ganglia_secret set in the server environment.", substr($errors[0], 0, 48));
  }

  public function testGetAuthTokenHashesUsernameAndSecret() {
    $auth = GangliaAuth::getInstance();
    $expected = sha1($this->cookie_data['user'] . $_SERVER['ganglia_secret']);
    $this->assertEquals($expected, $auth->getAuthToken($this->cookie_data['user']));
  }

  public function testAuthUnserializesCookieInformation() {
    $_COOKIE['ganglia_auth'] = json_encode( $this->cookie_data );

    $auth = GangliaAuth::getInstance();
    $this->assertTrue($auth->isAuthenticated());
    $this->assertEquals('foo', $auth->getUser());
  }

  public function testIncorrectHashCausesAuthFailure() {
    $this->cookie_data['token'] = 'xxxxxxx';
    $_COOKIE['ganglia_auth'] = json_encode( $this->cookie_data );

    $auth = GangliaAuth::getInstance();
    $this->assertFalse($auth->isAuthenticated());
    $this->assertNull($auth->getUser());
  }

  public function testSlashesAddedByMagicQuotesDontCauseUnserializationFailure() {
    $_COOKIE['ganglia_auth'] = addslashes(json_encode( $this->cookie_data ));

    // we can't enable real magic_quotes_gpc at runtime, so we mock it.
    $auth = $this->getMockBuilder('GangliaAuth')
      ->disableOriginalConstructor()
      ->setMethods(array('getMagicQuotesGpc'))
      ->getMock();

    $auth->expects($this->once())
      ->method('getMagicQuotesGpc')
      ->will($this->returnValue(true));

    // this is normally part of the constructor, but we have to get our mock getMagicQuotesGpc in place first
    $auth->init();

    $this->assertTrue($auth->isAuthenticated());
    $this->assertEquals('foo', $auth->getUser());
  }
}
?>

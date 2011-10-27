<?php
require_once 'Zend/Acl.php';

class GangliaAcl extends Zend_Acl {
  private static $acl;
  
  public static function getInstance() {
    if(is_null(self::$acl)) {
      self::$acl = new GangliaAcl();
    }
    return self::$acl;
  }
  
  public function __construct() {
    // define default groups
    $this->addRole( new Zend_Acl_Role('guests'))
         ->addRole( new Zend_Acl_Role('admins'));
    
    // define default resources
    // all clusters should be children of GangliaAcl::ALL_CLUSTERS
    $this->add( new Zend_Acl_Resource('*') );
    $this->add( new Zend_Acl_Resource('clusters/*'), '*');
    $this->add( new Zend_Acl_Resource('views/*'), '*');
    
    // guest can view everything and edit nothing.
    $this->allow('guests', '*', 'view');
    $this->deny('guests', '*', 'edit');
    
    $this->allow('admins', '*', 'edit');
    $this->allow('admins', '*', 'view');
  }
  
  public function addPrivateCluster($cluster) {
    $resource = "clusters/$cluster";
    $this->add( new Zend_Acl_Resource($resource), "clusters/*" );
    //$this->allow(self::ADMIN, $cluster, 'edit');
    $this->deny('guests', $resource);
  }
  
  public function add($name) {
    $parts = explode('/', $name);
    if( count($parts) != 2 || $parts[0] != 'clusters' || $parts[0] != 'views' ) {
      throw new InvalidArgumentException("'$name' is invalid.  Please specify 'clusters/<name>' or 'views/<name>'.");
    }
    $resource = $parts[0].'/'.$parts[1];
    parent::add( new Zend_Acl_Resource($resource, $parts[0].'/*') );
  }
  
}
?>
<?php
require_once 'Zend/Acl.php';

class GangliaAcl extends Zend_Acl {
  private static $acl;
  
  const ALL   = 'all';
  const VIEW  = 'view';
  const EDIT  = 'edit';
  const ADMIN = 'admin';
  const GUEST = 'guest';
  
  public static function getInstance() {
    if(is_null(self::$acl)) {
      self::$acl = new GangliaAcl();
    }
    return self::$acl;
  }
  
  private function __construct() {
    // define roles for any groups
    $this->addRole( new Zend_Acl_Role(GangliaAcl::GUEST))
        ->addRole( new Zend_Acl_Role(GangliaAcl::ADMIN));

    // define resources you want to protect.
    // all params after the 1st one define 'parent' resources, so privileges may be inherited.
    $this->add( new Zend_Acl_Resource(GangliaAcl::ALL) );

    // specify who can do what.
    // role, resource, action
    // we support actions 'view' and 'edit'
    $this->allow(GangliaAcl::GUEST, GangliaAcl::ALL, GangliaAcl::VIEW);
    $this->allow(GangliaAcl::ADMIN, GangliaAcl::ALL, GangliaAcl::VIEW);
    $this->allow(GangliaAcl::ADMIN, GangliaAcl::ALL, GangliaAcl::EDIT);
  }
  
  public function addPrivateCluster($cluster) {
    
    $this->add( new Zend_Acl_Resource($cluster), self::ALL );
    $this->deny(self::GUEST, $cluster);
  }
}
?>
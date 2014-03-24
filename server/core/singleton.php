<?php

namespace API\Core;

class Single {
  protected static $instances = array();
  private function __clone() {}
  private function __wakeup() {}
  private function __construct($params = null) { $this->init($params); }
  protected function init($params){}

  public final static function getInstance($params = null)
  {
    $class = get_called_class();
    if (!isset(self::$instances[$class])) {
      self::$instances[$class] = new $class($params);
    }
    return self::$instances[$class];
  }
}
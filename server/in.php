<?php

namespace API {

  use API\Core\Loader;

  // \mb_internal_encoding("UTF-8");
  error_reporting(-1);

  require_once "core/Loader.php";
  Loader::getInstance();

  $db = Core\DB\DB::getInstance(array('username'=>'om_messenger', 'password'=>'om_messenger', 'dsn'=>'mysql:host=localhost;dbname=om_messenger'));
  $cache = Core\DB\Cache::getInstance();

  $api = new API();
  $api->addService(new Messenger());
  $api->addService(new AllowOrigin($api));
  $api->run();
}

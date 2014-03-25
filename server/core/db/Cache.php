<?php

namespace API\Core\DB;

use API\Core\Single;

class Cache extends Single
{
  private $memcache = null;
  private $queryCount = array('get'=>0, 'set'=>0, 'del'=>0);
  private $queryTime = array('get'=>0, 'set'=>0, 'del'=>0);

  protected function init($params)
  {
    $host = isset($params['host']) ? $params['host'] : 'localhost';
    $port = isset($params['port']) ? $params['port'] : 11211;
    $this->memcache = new \Memcache;
    $this->memcache->pconnect($host, $port);
  }

  public function getQCount() { return $this->queryCount; }
  public function getQTime() { return $this->queryTime; }

  public function set($key, $data, $time = 0)
  {
    echo "Cache: set $key\n";
    $start = microtime(true);
    if ($this->memcache) {
      $time += rand(0, $time/2); // some cache expiring deviation 1-1.5 times
      $this->memcache->set($key, $data, false, $time);
    }
    $this->queryCount['set']++;
    $this->queryTime['set'] += microtime(true) - $start;
    return $this;
  }

  public function get($key)
  {
    echo "Cache: get $key\n";
    $start = microtime(true);
    $res = false;
    if ($this->memcache) {
      $res = $this->memcache->get($key);
    }
    $this->queryCount['get']++;
    $this->queryTime['get'] += microtime(true) - $start;
    return $res;
  }

  public function del($key)
  {
    echo "Cache: del $key\n";
    $start = microtime(true);
    $res = false;
    if ($this->memcache) {
      $res = $this->memcache->delete($key);
    }
    $this->queryCount['del']++;
    $this->queryTime['del'] += microtime(true) - $start;
    return $this;
  }
}

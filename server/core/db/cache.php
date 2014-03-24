<?php

namespace API\Core\DB;

use API\Core\Single;

class Cache extends Single
{
  private $m = null;
  private $queryCount = array('get'=>0, 'set'=>0, 'del'=>0);
  private $queryTime = array('get'=>0, 'set'=>0, 'del'=>0);

  protected function init($params)
  {
    $host = isset($params['host']) ? $params['host'] : 'localhost';
    $port = isset($params['port']) ? $params['port'] : 11211;
    $this->m = new \Memcache;
    $this->m->connect('localhost', 11211);
  }

  public function getQCount() { return $this->queryCount; }
  public function getQTime()  { return $this->queryTime; }

  public function set($key, $data, $time = 0)
  {
    echo "Cache: set $key\n";
    $start = microtime(true);
    if ($this->m) {
      $this->m->set($key, $data, false, $time);
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
    if ($this->m) {
      $res = $this->m->get($key);
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
    if ($this->m) {
      $res = $this->m->delete($key);
    }
    $this->queryCount['del']++;
    $this->queryTime['del'] += microtime(true) - $start;
    return $this;
  }
}
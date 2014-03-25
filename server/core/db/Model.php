<?php
namespace API\Core\DB;

abstract class Model implements \ArrayAccess
{
  const CACHE_TIME = 60;
  const CACHE_PREFIX = 'cache_users_';
  protected static $table = '';

  protected $data = array();
  protected $fields = array();

  public function offsetSet($offset, $value) { if (is_null($offset)) $this->data[] = $value; else $this->data[$offset] = $value; }
  public function offsetExists($offset) { return isset($this->data[$offset]); }
  public function offsetUnset($offset) { unset($this->data[$offset]); }
  public function offsetGet($offset) { return isset($this->data[$offset]) ? $this->data[$offset] : null; }

  public function __construct($data)
  {
    $this->setData($data);
  }
  public function setData($data)
  {
    $this->data = $data;
    return $this;
  }
  public function getData()
  {
    return $this->data;
  }
  public function save()
  {
    $set = array();
    $cols = array();
    $vals = array();
    $data = array();
    foreach ($this->fields as $field) {
      echo "$field, ";
      $set[]  = "`{$field}`=:{$field}";
      $cols[] = "`{$field}`";
      $vals[] = ":{$field}";
      $data[":{$field}"] = isset($this->data[$field]) ? $this->data[$field] : null;
    }
    $set = implode(',', $set);
    $cols = implode(',', $cols);
    $vals = implode(',', $vals);

    $sql = isset($this->data['id'])
      ? "UPDATE `".static::$table."` SET $set WHERE `id` = :id"
      : "INSERT INTO `".static::$table."` ({$cols}) VALUES ({$vals})";
    $dbh = DB::getInstance();
    $dbh->exec($sql, $data);
    if(!isset($this->data['id'])) $this->data['id'] = $dbh->getLastId();

    $key = static::CACHE_PREFIX.'_id_'.$this->data['id'];
    Cache::getInstance()->set($key, $this->data, static::CACHE_TIME);

    return $this->data['id'];
  }

  public static function getById($pkId)
  {
    if (!$pkId) return false;
    $key = static::CACHE_PREFIX.'_id_'.$pkId;
    $data = Cache::getInstance()->get($key);
    if (!$data) {
      $sql = "SELECT * FROM `".static::$table."` WHERE `id` = :id";
      $data = DB::getInstance()->row($sql, array(':id'=>$pkId));
      if (!$data) return false;
      Cache::getInstance()->set($key, $data, self::CACHE_TIME); //store only fresh data
    }
    return new static($data);
  }

  protected static function widgetDef(array &$params = array(), array $add = array())
  {
    $params = $params + array(
        'fields' => array('*'),
        'page' => 0,
        'size' => 5,
        'order' => 't.id ASC'
    ) + $add;
  }

  public static function widget(array $params = array())
  {
      static::widgetDef($params);
      $options = array();

      $options['page']  = (int)$params['page'];
      $options['size']  = (int)$params['size'];
      $options['offset'] = $options['page'] * $options['size'];

      $options['table'] = static::$table . " t";

      $options['joins']   = '';
      $options['rjoins'] = '';
      $options['fields']   = implode(',', $params['fields']);

      $where = array();
      $phs = array();
      if(isset($params['exclude'])) {
        $where[] = "id != :exclude";
        $phs[':exclude'] = $params['exclude'];
      }

      $options['where'] = $where ? implode(' AND ', $where) : 1; // 'WHERE 1', if there isn't restrictions
      $options['phs'] = $phs;

      $options['order'] = $params['order'];
      $data = static::datalist($options);
      return $data;
  }

  protected static function datalist($params)
  {
      //separate rows/count queries caching(may cause empty pages or invisible rows for short time)
      $keyrows = static::CACHE_PREFIX.'_datarows_'.md5(json_encode($params));
      $keycnt  = static::CACHE_PREFIX.'_data_cnt_'.md5(json_encode($params));

      $rows = Cache::getInstance()->get($keyrows);
      $count = Cache::getInstance()->get($keycnt);

      if ($rows===false || $count===false) {
          $dbh   = DB::getInstance();

          if($rows===false){
            $rowsSQL  =
            "SELECT {$params['fields']} FROM {$params['table']} {$params['joins']} {$params['rjoins']}
            WHERE {$params['where']} ORDER BY {$params['order']} LIMIT {$params['offset']}, {$params['size']};";
            $rows  = $dbh->rows($rowsSQL, $params['phs']);
            Cache::getInstance()->set($keyrows, $rows?:-1, 10);
          }

          if($count===false){
            $countSQL = "SELECT COUNT(id) FROM {$params['table']} {$params['rjoins']} WHERE {$params['where']};";
            $count = $dbh->cell($countSQL, $params['phs']);
            Cache::getInstance()->set($keycnt, $count, 10);
          }
      }
      return array(
          'data' => $rows == -1 ? array() : $rows,
          'count'=> (int)$count,
          'size' => $params['size'],
          'page' => $params['page']
      );
  }

}

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
    $db = DB::getInstance();
    $db->exec($sql, $data);
    if(!isset($this->data['id'])) $this->data['id'] = $db->getLastId();

    $key = static::CACHE_PREFIX.'_id_'.$this->data['id'];
    Cache::getInstance()->set($key, $this->data, static::CACHE_TIME);

    return $this->data['id'];
  }

  static function getById($id)
  {
    if (!$id) return false;
    $key = static::CACHE_PREFIX.'_id_'.$id;
    $data = Cache::getInstance()->get($key);
    if (!$data) {
      $sql = "SELECT * FROM `".static::$table."` WHERE `id` = :id";
      $data = DB::getInstance()->row($sql, array(':id'=>$id));
      if (!$data) return false;
      Cache::getInstance()->set($key, $data, self::CACHE_TIME); //store only fresh data
    }
    return new static($data);
  }

  static function widget(array $params = array())
  {
    $key = static::CACHE_PREFIX.'_widget_'.md5(json_encode($params));

    $data = Cache::getInstance()->get($key);
    if(!$data) {
      $fields = isset($params['fields']) ? implode(',',$params['fields']) : '*';
      $size   = isset($params['size']) ? (int)$params['size'] : 5;
      $offset = isset($params['page']) ? (int)$params['page']*$size : 0;

      $table = static::$table;
      $where = array();
      $phs = array();
      if(isset($params['exclude'])) {
        $where[] = "id != :exclude";
        $phs[':exclude'] = $params['exclude'];
      }
      $where = $where ? implode(' AND ', $where) : 1;
      $order = isset($params['order']) ? $params['order'] : 'id ASC';
      $rowsSQL  = "SELECT $fields FROM $table WHERE $where ORDER BY $order LIMIT $offset, $size;";
      $countSQL = "SELECT COUNT(id) FROM $table WHERE $where;";

      $db = DB::getInstance();
      $rows = $db->rows($rowsSQL, $phs);
      $count = $db->cell($countSQL, $phs);
      $data = array(
        'data' => $rows,
        'count'=> $count
      );
    }
    Cache::getInstance()->set($key, $data, self::CACHE_TIME);
    return $data;
  }

}

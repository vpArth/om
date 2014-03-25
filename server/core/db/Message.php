<?php

namespace API\Core\DB;

class Message extends Model
{
    const CACHE_PREFIX = 'cache_message_';
    protected static $table = 'messages';

    protected $fields = array('id', 'from_id', 'to_id', 'text', 'created');

    public static function data(array $params = array())
    {
        static::widgetDef($params, array('cut' => 0));

        $options = array();

        $options['page']  = (int)$params['page'];
        $options['size']  = (int)$params['size'];
        $options['offset'] = $options['page'] * $options['size'];

        $options['table'] = static::$table . " t";

        $joins   = array();
        $joins[] = "INNER JOIN users uf ON uf.id = t.from_id";
        $options['joins']   = implode(' ', $joins);
        $options['rjoins'] = '';

        $fields   = array('t.id', 't.created', 't.from_id', 't.to_id');
        $fields[] = 'uf.username `from`';

        $cut    = isset($params[ 'cut']) ? (int)$params[ 'cut'] : 0;
        $fields[] = $cut
            ? "IF(".
                    "LENGTH(t.`text`)<{$cut},".
                    "t.`text`,".
                    "CONCAT(TRIM(SUBSTR(t.`text`,1,{$cut})),'...')".
                ") `text`"
            : 't.`text`';
        $options['fields']   = implode(',', $fields);

        $where = array();
        $phs   = array();
        if (isset($params['self'])) {
            $where[] = "t.from_id != :self"; //hide own messages
            $where[] = "t.to_id    = :self"; //show messages to me
            $phs[':self'] = $params['self'];
        }
        $options['where'] = $where ? implode(' AND ', $where) : 1; // 'WHERE 1', if there isn't restrictions
        $options['phs'] = $phs;

        $options['order'] = $params['order'];
        $data = static::datalist($options);
        return $data;
    }
}

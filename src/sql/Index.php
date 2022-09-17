<?php
namespace obray\data\sql;

class Index
{
    const UNIQUE = 'UNIQUE';
    const INDEX = '';

    static public function createSQL(mixed $columns, string $type=self::INDEX)
    {
        if(gettype($columns) === 'string') $columns = [$columns];
        $columnSQL = '`' . implode('`,`', $columns) . '`';
        $keyName = hash('sha256', $columnSQL . '_index');
        if(!empty($type)) $type = $type . ' ';
        $sql = $type . 'KEY `' . $keyName . '` (' . $columnSQL . ') USING BTREE';
        return $sql;
    }
}
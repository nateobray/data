<?php
namespace obray\data\types;

use obray\data\DBConn;

class Text extends BaseType
{
    const TYPE = 'TEXT';
    const NULLABLE = true;
    const LENGTH = null;
    const UNSIGNED = null;

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        return $conn->quote($this->value, \PDO::PARAM_STR);
    }
}
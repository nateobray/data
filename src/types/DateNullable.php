<?php
namespace obray\data\types;

use obray\data\DBConn;

class Date extends BaseType
{
    const TYPE = 'DATE';
    const NULLABLE = true;
    const DEFAULT = null;

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        return $conn->quote($this->value, \PDO::PARAM_STR);
    }
}
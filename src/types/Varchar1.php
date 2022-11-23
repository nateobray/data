<?php
namespace obray\data\types;

use obray\data\DBConn;

class Varchar1 extends BaseType
{
    const TYPE = 'VARCHAR';
    const LENGTH = 1;
    const NULLABLE = false;
    const UNSIGNED = null;

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        print_r($this->value . "\n");
        
        return $conn->quote($this->value, \PDO::PARAM_STR);
    }
}
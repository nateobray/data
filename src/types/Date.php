<?php
namespace obray\data\types;

use obray\data\DBConn;

class Date extends BaseType
{
    const IS_PRIMARY = false;
    const TYPE = 'DATE';
    const LENGTH = null;
    const UNSIGNED = false;
    const NULLABLE = true;
    const DEFAULT = '0000-00-00';
    const AUTO_INCEMENT = false;

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        return $conn->quote($this->value, \PDO::PARAM_STR);
    }
}
<?php
namespace obray\data\types;

use obray\data\DBConn;

class DateNullable extends BaseType
{
    const IS_PRIMARY = false;
    const TYPE = 'DATE';
    const LENGTH = null;
    const UNSIGNED = false;
    const NULLABLE = true;
    const DEFAULT = null;
    const AUTO_INCEMENT = false;

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        return $conn->quote($this->value, \PDO::PARAM_STR);
    }
}
<?php
namespace obray\data\types;

use obray\data\DBConn;

class DateTime extends BaseType implements \JsonSerializable 
{
    const IS_PRIMARY = false;
    const TYPE = 'DATETIME';
    const LENGTH = null;
    const UNSIGNED = false;
    const NULLABLE = false;
    const DEFAULT = '0000-00-00 00:00:00';
    const AUTO_INCEMENT = false;

    public function __construct(mixed $value=self::DEFAULT)
    {
        if($value === null){
            $this->value = $value;
        }  else {
            $this->value = new \DateTime($value);
        }
    }

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        return $conn->quote($this->value->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
    }

    public function __toString()
    {
        if($this->value === null) return '';
        return $this->value->format('c');
    }

    public function getValue()
    {
        if($this->value === null) return '';
        return $this->value->format('c');
    }

    public function jsonSerialize(): mixed 
    {
        if($this->value === null) return null;
        return $this->value->format('c');
    }
}
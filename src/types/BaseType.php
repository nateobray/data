<?php
namespace obray\data\types;

use obray\data\DBConn;

class BaseType
{
    const IS_PRIMARY = false;
    const TYPE = 'INT';
    const LENGTH = 11;
    const UNSIGNED = true;
    const NULLABLE = false;
    const DEFAULT = null;
    const AUTO_INCEMENT = false;
    const ON_UPDATE = null;

    protected $value;

    public function __construct(mixed $value=self::DEFAULT)
    {
        $this->value = $value;
    }

    static public function createSQL($column)
    {
        $sql = '`' . $column . '`';
        $sql .= ' ';
        $sql .= static::TYPE;
        if(static::LENGTH !== null) $sql .= '(' . (string)static::LENGTH . ')';
        $sql .= ' ';
        if(static::UNSIGNED === true) $sql .= 'UNSIGNED ';
        if(static::NULLABLE === false) $sql .= 'NOT NULL ';

        // hande default definitions
        if(static::AUTO_INCEMENT === true){
            $sql .= 'AUTO_INCREMENT';
        } else if(static::DEFAULT === null && static::NULLABLE !== false){
            $sql .= 'DEFAULT NULL';
        } else if(static::DEFAULT === true){
            $sql .= 'DEFAULT TRUE';
        } else if(static::DEFAULT === false){
            $sql .= 'DEFAULT FALSE';
        } else if(static::DEFAULT === 'CURRENT_TIMESTAMP'){
            $sql .= 'DEFAULT CURRENT_TIMESTAMP';
        } else if(in_array(static::TYPE, ['DATE', 'TIME', 'DATETIME', 'TIMESTAMP', 'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', ]) && static::DEFAULT !== null) {
            $sql .= 'DEFAULT \'' . static::DEFAULT . '\'';
        } else if(in_array(static::TYPE, ['INT', 'FLOAT', 'DECIMAL']) && static::DEFAULT !== null) {
            $sql .= 'DEFAULT ' . static::DEFAULT;
        }

        // handle on update definition
        if(static::ON_UPDATE === 'CURRENT_TIMESTAMP'){
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        } else if(!empty(static::ON_UPDATE)){
            $sql .= ' ON UPDATE \'' . static::ON_UPDATE . '\'';
        }
        return $sql;
    }

    public function empty()
    {
        return empty($this->value);
    }

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        if($this->value === false) return 'FALSE';
        if($this->value === true) return 'TRUE';
        return $this->value;
    }

    public function __toString()
    {
        return (string)$this->value;
    }

    public function getValue()
    {
        return $this->value;
    }

    <?php
namespace obray\data\types;

use JsonSerializable;
use obray\data\DBConn;

class BaseType implements JsonSerializable
{
    const IS_PRIMARY = false;
    const TYPE = 'INT';
    const LENGTH = 11;
    const UNSIGNED = true;
    const NULLABLE = false;
    const DEFAULT = null;
    const AUTO_INCEMENT = false;
    const ON_UPDATE = null;

    protected $value;

    public function __construct(mixed $value=self::DEFAULT)
    {
        $this->value = $value;
    }

    static public function createSQL($column)
    {
        $sql = '`' . $column . '`';
        $sql .= ' ';
        $sql .= static::TYPE;
        if(static::LENGTH !== null) $sql .= '(' . (string)static::LENGTH . ')';
        $sql .= ' ';
        if(static::UNSIGNED === true) $sql .= 'UNSIGNED ';
        if(static::NULLABLE === false) $sql .= 'NOT NULL ';

        // hande default definitions
        if(static::AUTO_INCEMENT === true){
            $sql .= 'AUTO_INCREMENT';
        } else if(static::DEFAULT === null && static::NULLABLE !== false){
            $sql .= 'DEFAULT NULL';
        } else if(static::DEFAULT === true){
            $sql .= 'DEFAULT TRUE';
        } else if(static::DEFAULT === false){
            $sql .= 'DEFAULT FALSE';
        } else if(static::DEFAULT === 'CURRENT_TIMESTAMP'){
            $sql .= 'DEFAULT CURRENT_TIMESTAMP';
        } else if(in_array(static::TYPE, ['DATE', 'TIME', 'DATETIME', 'TIMESTAMP', 'CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', ]) && static::DEFAULT !== null) {
            $sql .= 'DEFAULT \'' . static::DEFAULT . '\'';
        } else if(in_array(static::TYPE, ['INT', 'FLOAT', 'DECIMAL']) && static::DEFAULT !== null) {
            $sql .= 'DEFAULT ' . static::DEFAULT;
        }

        // handle on update definition
        if(static::ON_UPDATE === 'CURRENT_TIMESTAMP'){
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        } else if(!empty(static::ON_UPDATE)){
            $sql .= ' ON UPDATE \'' . static::ON_UPDATE . '\'';
        }
        return $sql;
    }

    public function empty()
    {
        return empty($this->value);
    }

    public function insertSQL(?DBConn $conn=null)
    {
        if($this->value === null) return 'null';
        if($this->value === false) return 'FALSE';
        if($this->value === true) return 'TRUE';
        return $this->value;
    }

    public function __toString()
    {
        return (string)$this->value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function jsonSerialize(): mixed 
    {
        return $this->value;
    }
}
}
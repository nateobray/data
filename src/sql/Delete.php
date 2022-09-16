<?php
namespace obray\data\sql;

use obray\data\DBConn;
use obray\data\Table;

class Delete
{
    private $instance;
    private DBConn $DBConn;

    public function __construct(mixed $instance, DBConn $DBConn)
    {
        $this->instance = $instance;
        $this->DBConn = $DBConn;
    }

    public function toSQL()
    {
        if(!is_array($this->instance)) $this->instance = [$this->instance];
        $instance = $this->instance[0];
        $table = Table::getTable($instance::class);
        $columns = Table::getColumns($instance::class);
        
        $sql =  "DELETE FROM " . $table . "\n";

        if($instance->getPrimaryKey() && !empty($instance->{$instance->getPrimaryKey()})){
            $sql .=  "      WHERE " . $instance->getPrimaryKey() . ' = ' . $instance->{$instance->getPrimaryKey()} . "\n";
        }
        return $sql;

    }
}
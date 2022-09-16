<?php
namespace obray\data\sql;

use obray\data\DBConn;
use obray\data\Querier;
use obray\data\Table;

class Insert
{
    private $instance;
    private DBConn $DBConn;

    public function __construct(mixed $instance, DBConn $DBConn)
    {
        $this->instance = $instance;
        $this->DBConn = $DBConn;
    }

    public function onBeforeInsert(Querier $querier)
    {
        $this->instance->onBeforeInsert($querier);
    }

    public function onAfterInsert(Querier $querier, $lastId)
    {
        $this->instance->onAfterInsert($querier, $lastId);
    }

    public function toSQL()
    {
        if(!is_array($this->instance)) $this->instance = [$this->instance];
        $instance = $this->instance[0];
        $table = Table::getTable($instance::class);
        $columns = Table::getColumns($instance::class);
        
        $columnSQL = [];
        forEach($columns as $column){
            if(strpos($column->propertyClass, 'PrimaryKey') && empty($instance->{$column->propertyName})) continue; 
            if(strpos($column->propertyClass, 'DateTimeCreated')) continue;
            if(strpos($column->propertyClass, 'DateTimeModified')) continue;
            $columnSQL[] = $column->propertyName;
        }

        $instanceSQL = [];
        forEach($this->instance as $instance){
            $this->instance = $instance;
            $valueSQL = [];
            forEach($columns as $column){
                if(strpos($column->propertyClass, 'PrimaryKey') && empty($this->instance->{$column->propertyName})) continue;
                if(strpos($column->propertyClass, 'DateTimeCreated')) continue;
                if(strpos($column->propertyClass, 'DateTimeModified')) continue;
                $valueSQL[] = $instance->{$column->name}->insertSQL($this->DBConn);
            }
            $instanceSQL[] = '('.implode(',', $valueSQL).')';
        }
        return "INSERT INTO " . $table . ' (' .implode(',', $columnSQL). ")\n     VALUES\n\t" . implode(",\n\t", $instanceSQL);
    }
}
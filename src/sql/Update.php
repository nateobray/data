<?php
namespace obray\data\sql;

use obray\data\DBConn;
use obray\data\Querier;
use obray\data\Table;

class Update
{
    private $instance;
    private DBConn $DBConn;
    private $values = [];

    public function __construct(mixed $instance, DBConn $DBConn)
    {
        $this->instance = $instance;
        $this->DBConn = $DBConn;
    }

    public function onBeforeUpdate(Querier $querier)
    {
        $this->instance->onBeforeUpdate($querier);
    }

    public function onAfterUpdate(Querier $querier)
    {
        $this->instance->onAfterUpdate($querier);
    }

    public function toSQL()
    {
        $table = Table::getTable($this->instance::class);
        $columns = Table::getColumns($this->instance::class);

        $columnSQL = []; $whereSQL = [];
        forEach($columns as $column){
            if(strpos($column->propertyClass, 'PrimaryKey') ){
                if($this->instance->{$column->name}->empty()) continue;
                $whereSQL[] = "`" . $column->propertyName . "` = " . $this->instance->{$column->name}->insertSQL($this->DBConn);
                continue;
            }
            if(strpos($column->propertyClass, 'DateTimeCreated')) continue;
            if(strpos($column->propertyClass, 'DateTimeModified')) continue;
            $columnSQL[] = "`" . $column->propertyName . "` = :" . $column->name;
            $this->values[$column->name] = $this->instance->{$column->name}->getValue();
        }

        $sql = "UPDATE ";
        $sql .= $table . "\n   SET "; 
        $sql .= implode(",\n", $columnSQL);

        if(!empty($whereSQL)){
            $sql .= "\n WHERE " . implode("\n  AND ", $whereSQL);
        }
        return $sql;
    }

    public function values()
    {
        return $this->values;
    }
}
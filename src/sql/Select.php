<?php
namespace obray\data\sql;

use obray\data\Table;

class Select
{
    private $classes = [];

    public function __construct(string $class)
    {
        $this->classes[$class::TABLE] = $class;
    }

    public function add(string $name, string $class)
    {
        $this->classes[$name] = $class;
    }

    public function toSQL()
    {
        $columnSQL = [];
        forEach($this->classes as $name => $class){
            $columns = Table::getColumns($class);
            foreach($columns as $column){
                $columnSQL[] = "`".$name.'`.`'.$column->propertyName.'` AS `' . $name . '_' . $column->propertyName . '`';
            }
        }
        return "  SELECT\n\t" . implode(",\n\t",$columnSQL) . "\n";
    }
}
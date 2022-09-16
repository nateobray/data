<?php
namespace obray\data;

use obray\Obj;

class Table extends Obj
{
    private DBConn $DBConn;
    public function __construct(DBConn $DBConn)
    {
        $this->DBConn = $DBConn;
    }

    static public function getTable($class)
    {
        $reflection = new \ReflectionClass($class);
        try {
            $table = $class::TABLE;
        } catch (\Exception $e) {
            throw new \Exception("Class does not have a table property, not compatible with data class.");
        }
        return $table;
    }

    static public function getPrimaryKey(string $class)
    {
        $reflection = new \ReflectionClass($class);
        $properties = $reflection->getProperties();
        forEach($properties as $property){
            $propertyType = $property->getType();
            if($propertyType === null) continue;
            $propertyClass = $propertyType->getName();
            if(strpos($propertyClass, 'PrimaryKey') !== false){
                return substr($property->name, 4);
            }
        }
        throw new \Exception("No primary key found.");
    }

    static public function getColumns($class)
    {
        $reflection = new \ReflectionClass($class);
        $properties = $reflection->getProperties();

        $columns = [];
        forEach($properties as $property){
            $propertyType = $property->getType();
            if($propertyType === null) continue;
            $propertyClass = $propertyType->getName();
            if(strpos($propertyClass, 'obray\\dataTypes\\') === false && strpos($property->name, 'col_') !== 0) continue;
            $property->propertyClass = $propertyClass;
            $property->propertyName = substr($property->name, 4);
            $columns[] = $property;
        }
        return $columns;
    }

    public function create($class)
    {
        $reflection = new \ReflectionClass($class);
        $properties = $reflection->getProperties();

        $keys = [];
        $constraints = [];

        $table = self::getTable($class);

        $sql = $this->disableConstraints() . "\nCREATE TABLE `" . $table . '`' . "(\n";

        $this->console("%s","*** Scripting Table " . $table . " ***\n","GreenBold");
        
        $columnSQL = [];
        $columns = self::getColumns($class);
        forEach($columns as $column){
            if(strpos($column->propertyClass, 'PrimaryKey')) $primaryKey = $column->propertyName;
            $columnSQL[] = "\t" . ($column->propertyClass)::createSQL($column->propertyName);
        }
        $sql .= implode(",\n", $columnSQL);

        // build indexes
        if(defined($class . '::INDEXES')){
            forEach($class::INDEXES as $index){
                $keys[] = Index::createSQL(...$index);
            }
        }
        
        // build Foreign Keys
        if(defined($class . '::FOREIGN_KEYS')){
            forEach($class::FOREIGN_KEYS as $key){
                $foreign = ForeignKey::createSQL(...$key);
                $keys[] = $foreign[0];
                $constraints[] = $foreign[1];
            }
        }
        
        if(!empty($primaryKey)){
            $sql .= ",\n\n\tPRIMARY KEY (`" . $primaryKey . "`)";
        }
        if(!empty($keys)){
            $sql .= ",\n\t";
            $sql .= implode(",\n\t", $keys);
        }
        if(!empty($constraints)){
            $sql .= ",\n\t";
            $sql .= implode(",\n\t", $constraints);
        }

        $sql .= "\n".') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;' . "\n\n";

        $sql .= $this->enableConstraints();

        $this->console("%s","\n" . $sql . "\n","White");

        $this->DBConn->query($sql);

        if(defined($class . '::SEED_CONSTANTS')){
            $this->seedConstants($class, $columns);
        }
    }

    private function seedConstants($class, $columns)
    {
        $reflection = new \ReflectionClass($class);
        $constants = $reflection->getConstants();
        $querier = new Querier($this->DBConn);
        forEach($constants as $key => $value){
            if(in_array($key, ['SEED_CONSTANTS', 'TABLE', 'FOREIGN_KEYS', 'INDEXES'])) continue;
            $key = ucwords(strtolower(str_replace('_', ' ', $key)));
            $obj = new $class(...[
                $columns[0]->propertyName => $value,
                $columns[1]->propertyName => $key
            ]);
            $querier->insert($obj)->run();
        }
    }

    private function disableConstraints()
    {
        $sql = "
            SET @ORIG_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
            SET FOREIGN_KEY_CHECKS = 0;
            
            SET @ORIG_UNIQUE_CHECKS = @@UNIQUE_CHECKS;
            SET UNIQUE_CHECKS = 0;
            
            SET @ORIG_TIME_ZONE = @@TIME_ZONE;
            SET TIME_ZONE = '+00:00';
            
            SET @ORIG_SQL_MODE = @@SQL_MODE;
            SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
        ";
        return $sql;
    }

    private function enableConstraints()
    {
        $sql = "
            SET FOREIGN_KEY_CHECKS = @ORIG_FOREIGN_KEY_CHECKS;
            SET UNIQUE_CHECKS = @ORIG_UNIQUE_CHECKS;
            SET @ORIG_TIME_ZONE = @@TIME_ZONE;
            SET TIME_ZONE = @ORIG_TIME_ZONE;
            SET SQL_MODE = @ORIG_SQL_MODE;
        ";
        $sql;
    }
}
<?php
namespace obray\data;

use JsonSerializable;
use obray\data\types\BaseType;
use obray\data\types\Password;
use ReflectionProperty;

class DBO implements JsonSerializable
{
    private $primaryKeyValue;

    public function __construct(...$params)
    {
        $columns = Table::getColumns(static::class);
        forEach($columns as $index => $column){
            // if neither property exists get default value
            if(!array_key_exists($column->propertyName, $params) && !array_key_exists($index, $params)) {
                $reflection = new \ReflectionClass($column->propertyClass);
                $value = $reflection->getConstant('DEFAULT');
            } 
            // if named assoc array was passed get that way
            if(array_key_exists($column->propertyName, $params)) $value = $params[$column->propertyName];    
            // if numerically indexed array get value that way
            if(array_key_exists($index, $params)) $value = $params[$index];
            $this->{$column->name} = new ($column->propertyClass)($value);
            if(strpos($column->propertyClass, 'PrimaryKey') !== false){
                $this->primaryKeyColumn = $column->propertyName;
                $this->primaryKeyValue = $value;
            }
        }
    }

    public function getPrimaryKeyValue()
    {
        return $this->primaryKeyValue;
    }

    static public function getPrimaryKey()
    {
        $reflection = new \ReflectionClass(static::class);
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

    public function __set($key, $value)
    {
        $reflection = new \ReflectionClass(static::class);
        try {
            $property = $reflection->getProperty('col_' . $key);

            $propertyType = $property->getType();
            if($propertyType === null) throw new \Exception("Invalid property: " . $key . "\n");
            $propertyClass = $propertyType->getName();
            $this->{'col_' . $key} = new $propertyClass($value);
        } catch (\Exception $e) {
            $this->{$key} = $value;
        }
    }

    public function __get($key)
    {
        if(isSet($this->{'col_' . $key})){
            return $this->{'col_' . $key}->getValue();
        } 
        if(isSet($this->{'cust_' . $key})){
            return $this->{'cust_' . $key};
        }
        $value = $this->{$key};
        return $value;
    }

    public function __isSet($key)
    {
        try{
            $reflection = new \ReflectionClass(static::class);
            $property = $reflection->getProperty('col_' . $key);
        } catch (\Exception $e){
            return isSet($this->{$key});
        }
        return isset($this->{'col_' . $key});
    }

    public function onBeforeInsert(Querier $querier)
    {
        return;
    }

    public function onAfterInsert(Querier $querier, int $lastId)
    {
        return;
    }

    public function onBeforeUpdate(Querier $querier)
    {
        return;
    }

    public function onAfterUpdate(Querier $querier)
    {
        return;
    }

    public function jsonSerialize(): mixed
    {
        $obj = new \stdClass();
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties();
        
        forEach($this as $key => $prop){
            if(strpos($key, 'col_') !== false && $prop instanceof BaseType) {
                if($prop instanceof Password) continue;
                $obj->{str_replace('col_', '', $key)} = $prop->getValue();
            }

            if(strpos($key, 'cust_') !== false) {
                $obj->{str_replace('cust_', '', $key)} = $this->{$key};
            }
        }
        return $obj;
    }

    public function empty()
    {
        return empty($this->primaryKeyValue);
    }

}
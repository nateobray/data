<?php
namespace obray\data;

use obray\core\Helpers;
use obray\data\sql\Delete;
use obray\data\sql\From;
use obray\data\sql\Insert;
use obray\data\sql\Limit;
use obray\data\sql\OrderBy;
use obray\data\sql\Select;
use obray\data\sql\Update;
use obray\data\sql\Where;
use obray\core\Obj;

class Statement
{
    private string $class;
    private string $action = 'selecting';
    private \obray\data\sql\Insert $insert;
    private \obray\data\sql\Update $update;
    private \obray\data\sql\Delete $delete;
    private \obray\data\sql\Select $select;
    private \obray\data\sql\From $from;
    private \obray\data\sql\Where $where;
    private \obray\data\sql\Limit $limit;
    private \obray\data\sql\OrderBy $orderBy;

    private bool $returnSingleRow = false;

    public function __construct(DBConn $conn)
    {
        $this->conn = $conn;
    }

    public function insert(mixed $instance)
    {
        $this->action = 'inserting';
        $this->insert = new Insert($instance, $this->conn);
        return $this;
    }

    public function update(mixed $instance)
    {
        $this->action = 'updating';
        $this->update = new Update($instance, $this->conn);
        return $this;
    }

    public function delete(mixed $instance)
    {
        $this->action = 'deleting';
        $this->delete = new Delete($instance, $this->conn);
        return $this;
    }
    
    public function select(string $class)
    {
        $this->action = 'selecting';
        $this->class = $class;
        $this->select = new Select($class);
        $this->from = new From($class);
        return $this;
    }

    public function leftJoin(string $name, string $toClass, mixed $fromClass=null, string $toColumn=null, string $fromColumn=null)
    {
        $this->from->leftJoin($name, $toClass, $fromClass, $toColumn, $fromColumn);
        if(!empty($this->select)) $this->select->add($name, $toClass);
        return $this;
    }

    public function join(string $name, string $toClass, mixed $fromClass=null, string $toColumn=null, string $fromColumn=null)
    {
        $this->from->join($name, $toClass, $fromClass, $toColumn, $fromColumn);
        if(!empty($this->select)) $this->select->add($name, $toClass);
        return $this;
    }

    public function where(array $where)
    {
        $this->where = new Where($this->class, $where);
        return $this;
    }

    public function limit(int $rows, int $offset=0)
    {
        if($rows === 1) $this->returnSingleRow = true;
        $this->limit = new Limit($rows, $offset);
        return $this;
    }

    public function orderBy($orderBy)
    {
        $this->orderBy = new OrderBy($orderBy);
        return $this;
    }

    public function out()
    {
        Helpers::console("%s", "\n\t**** SQL STATEMENT ****\n\n", "YellowBold");
        $sql = '';
        if(!empty($this->insert)) $sql .= $this->insert->toSQL();
        if(!empty($this->update)) $sql .= $this->update->toSQL();
        if(!empty($this->delete)) $sql .= $this->delete->toSQL();
        if(!empty($this->select)) $sql .= $this->select->toSQL();
        if(!empty($this->from)) $sql .= $this->from->toSQL();
        if(!empty($this->where)) $sql .= $this->where->toSQL();
        if(!empty($this->orderBy)) $sql .= $this->orderBy->toSQL();
        if(!empty($this->limit)) $sql .= $this->limit->toSQL();
        Helpers::console($sql."\n");
        return $this;
    }

    public function run($sql='')
    {

        if($this->action === 'updating'){
            $this->update->onBeforeUpdate($this->newQuerier());
        }

        if($this->action === 'inserting'){
            $this->insert->onBeforeInsert($this->newQuerier());
        }

        $values = [];
        if(!empty($this->insert)){
            $sql .= $this->insert->toSQL();
            $values = $this->insert->values();
        } 
        if(!empty($this->update)){
            $sql .= $this->update->toSQL();
            $values = $this->update->values();
        } 
        if(!empty($this->delete)) $sql .= $this->delete->toSQL();
        if(!empty($this->select)) $sql .= $this->select->toSQL();
        if(!empty($this->from)) $sql .= $this->from->toSQL();
        if(!empty($this->where)){
            $sql .= $this->where->toSQL();
            $values = array_merge($values, $this->where->values());
        }
        if(!empty($this->orderBy)) $sql .= $this->orderBy->toSQL();
        if(!empty($this->limit)) $sql .= $this->limit->toSQL();
        
        $data = $this->conn->run($sql, $values, \PDO::FETCH_ASSOC);

        $results = [];

        foreach ($data[0] as $i => $row) {

            if(empty($row)) continue;

            $objProps = [];
            forEach($row as $name => $prop){
                if(strpos($name, $this->class::TABLE.'_') !== false){
                    $objProps[substr($name, strlen($this->class::TABLE.'_'))] = $prop;
                }
            }
            
            $result = new ($this->class)(...$objProps);
            if(empty($results[$result->getPrimaryKeyValue()])){
                $results[$result->getPrimaryKeyValue()] = $result;
            }
            
            //handle joins
            forEach($this->from->getJoins() as $class => $join){
                // populate data from row into our join object
                $joinResult = $this->populateJoin($row, $join);
                // if object does not already contain the join, then add it as an empty array
                if(!isSet($results[$result->getPrimaryKeyValue()]->{$join->getName()})){
                    $results[$result->getPrimaryKeyValue()]->{$join->getName()} = array();
                }
                // if an object with the joins primary key does not exist, then added it to the join
                if(!empty($joinResult) && empty($results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()])){
                    $results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()] = $joinResult;
                } else if (!empty($joinResult) && !empty($results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()])){
                    $originalObject = $results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()];
                    $resultsObj = $joinResult;
                    $results[$result->getPrimaryKeyValue()]->{$join->getName()}[$joinResult->getPrimaryKeyValue()] = $this->merge($originalObject, $resultsObj);
                }
            }
        }

        $this->removePrimaryKeys($results);

        $this->sql = '';
        if($this->returnSingleRow){
            $results = array_values($results);
            if(empty($results[0])) return [];
            return $results[0];
        } 

        if($this->action === 'updating'){
            $this->update->onAfterUpdate($this->newQuerier());
        }

        if($this->action === 'inserting'){ 
            $lastId = $this->conn->lastInsertId();
            $this->insert->onAfterInsert($this->newQuerier(), $lastId);
            return $lastId;
        }
        if(is_array($results)) array_values($results);
        return $results;
    }

    private function merge($obj1, $obj2)
    {
        $merged = clone $obj1;
        forEach($obj2 as $key => $value){
            if(is_array($value)){
                $merged->{$key} = $this->mergeArray($merged->{$key}, $value);
            } else if (is_object($value)){
                $merged->{$key} = $this->merge($merged->{$key}, $value);
            }
        }
        return $merged;
    }

    private function mergeArray($arr1, $arr2)
    {
        $merged = $arr1;
        forEach($arr2 as $key => $value){
            if(array_key_exists($key, $merged)){
                $merged[$key] = $this->merge($merged[$key], $value);    
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    private function populateJoin($row, $join)
    {
        $objProps = [];
        forEach($row as $n => $prop){
            if(strpos($n, $join->getName().'_') === 0){
                $objProps[substr($n, strlen($join->getName().'_'))] = $prop;
            }
        }
        $joinResult = new ($join->getToClass())(...$objProps);
        if($joinResult->empty()){
            $joinResult = null;
            return $joinResult;
        } 
        forEach($join->joins as $j){
            $result = $this->populateJoin($row, $j);
            if(empty($result)) continue;
            if(empty($joinResult->{$j->getName()})) $joinResult->{$j->getName()} = [];
            if(empty($joinResult->{$j->getName()}[$result->getPrimaryKeyValue()])){
                $joinResult->{$j->getName()}[$result->getPrimaryKeyValue()] = $result;
            }
        }
        return $joinResult;
    }

    private function removePrimaryKeys(mixed &$results)
    {
        if(is_array($results)) $results = array_values($results);
        forEach($results as $key => &$value){
            if(is_array($value) || is_object($value)) $this->removePrimaryKeys($value);
        }
    }

    private function newQuerier()
    {
        return new Querier($this->conn);
    }

}
<?php
namespace obray\data;

use obray\data\Statement;
use obray\Obj;

class Querier extends Obj
{
    private string $class;
    private string $sql;
    private array $values = [];
    private $returnSingleRow = false;
    private $action = null;

    public $DBConn;

    public function __construct(DBConn $DBConn)
    {
        $this->DBConn = $DBConn;
    }

    public function insert(mixed $instances)
    {
        return (new Statement($this->DBConn))->insert($instances);
    }

    public function update($instance, $action='updating')
    {
        return (new Statement($this->DBConn))->update($instance);
    }

    public function delete($instance, $action='deleting')
    {
        return (new Statement($this->DBConn))->delete($instance);
    }

    public function select($class)
    {
        return (new Statement($this->DBConn))->select($class);
    }

    public function orderBy(mixed $orderBy)
    {
        if(!is_array($orderBy)) $orderBy = [$orderBy];
        
        $this->sql .= "\nORDER BY " . implode(', ', $orderBy) . "\n";
        return $this;
    }

    private function newQuerier()
    {
        return new Querier($this->DBConn);
    }
}
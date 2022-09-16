<?php
namespace obray\data\sql;

class Join
{
    private $name;
    private $fromAlias;
    private $fromClass;
    private $fromColumn;
    private $toClass;
    private $toColumn;
    public $joins = [];
    
    public function __construct($name, $fromClass, $fromColumn, $toClass, $toColumn)
    {
        $this->name = $name;
        $this->fromClass = $fromClass;
        $this->fromColumn = $fromColumn;
        $this->toClass = $toClass;
        $this->toColumn = $toColumn;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFromTable()
    {
        return $this->fromClass::TABLE;
    }

    public function getToTable()
    {
        return $this->toClass::TABLE;
    }

    public function getToClass()
    {
        return $this->toClass;
    }

    public function getFromColumn()
    {
        return $this->fromColumn;
    }

    public function getToColumn()
    {
        return $this->toColumn;
    }

    public function addFromAlias($alias)
    {
        $this->fromAlias = $alias;
    }

    public function toSQL()
    {
        $fromTable = (empty($this->fromAlias)?$this->getFromTable():$this->fromAlias);
        $sql = '   JOIN `' . $this->getToTable() . '` `'.$this->name.'` ON `'. $this->name . '`.`' . $this->getToColumn() . '` = `' . $fromTable . '`.`' . $this->getFromColumn() . "`\n";
        forEach($this->joins as $join){
            $sql .= $join->toSQL();
        }
        return $sql;
    }
}

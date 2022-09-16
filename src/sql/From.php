<?php
namespace obray\data\sql;

class From
{
    private string $table;
    private string $class;
    private array $joins = [];
    private array $joinMap = [];

    public function __construct(string $class)
    {
        $this->class = $class;
        $this->table = $class::TABLE;
    }

    public function getJoins()
    {
        return $this->joins;
    }

    public function getJoinMap()
    {
        return $this->joinMap;
    }

    public function join(string $name, string $toClass, $fromClass=null, $toColumn=null, $fromColumn=null)
    {
        $alias = '';
        if(is_array($fromClass)){
            if(!empty($fromClass[1])) $alias = $fromClass[1];
            $fromClass = $fromClass[0];
        }
        if(empty($fromClass)) $fromClass = $this->class;
        if(empty($fromColumn)) $fromColumn = $toClass::getPrimaryKey();
        if(empty($toColumn)) $toColumn = $toClass::getPrimaryKey();
        
        $aliasAttachement = (!empty($alias)?'-'.$alias:'');
        if(class_exists($fromClass) && empty($alias) && $fromClass::TABLE === $this->table){
            $this->joins[$fromClass.$aliasAttachement] = new Join($name, $fromClass, $fromColumn, $toClass, $toColumn);
            if(!empty($alias)) $this->joins[$fromClass.$aliasAttachement]->addFromAlias($alias);
            $this->joinMap[$toClass.'-'.$name] = &$this->joins[$fromClass.$aliasAttachement];
        } elseif(!empty($this->joinMap[$fromClass.$aliasAttachement])){
            $this->joinMap[$fromClass.$aliasAttachement]->joins[$toClass.'-'.$name] = new Join($name, $fromClass, $fromColumn, $toClass, $toColumn);
            if(!empty($alias)) $this->joinMap[$fromClass.$aliasAttachement]->joins[$toClass.'-'.$name]->addFromAlias($alias);
            $this->joinMap[$toClass.'-'.$name] = &$this->joinMap[$fromClass.$aliasAttachement]->joins[$toClass.'-'.$name];
        }
    }

    public function toSQL()
    {
        $sql = '   FROM `' . $this->table . "`\n";
        forEach($this->joins as $join){
            $sql .= $join->toSQL();
        }
        return $sql;
    }
}
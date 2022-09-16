<?php
namespace obray\data\sql;

class OrderBy
{
    private $orderBy;
    public function __construct($orderBy)
    {
        $this->orderBy = $orderBy;
    }

    public function toSQL()
    {
        if(!is_array($this->orderBy)) $this->orderBy = [$this->orderBy];
        $sql = "\nORDER BY " . implode(', ', $this->orderBy) . "\n";
        return $sql;
    }
}
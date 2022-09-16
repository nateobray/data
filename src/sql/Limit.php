<?php
namespace obray\data\sql;

class Limit
{
    private int $rows;
    private int $offset = 0;


    public function __construct($rows, $offset=0)
    {
        $this->rows = $rows;
        $this->offset = $offset;
    }

    public function toSQL()
    {
        $sql = '  LIMIT ' . $this->offset . ',' . $this->rows . "\n";
        return $sql;
    }
}
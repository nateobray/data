<?php
namespace obray\data\sql;

class Where
{
    private $class;
    private $where;
    private $values;

    public function __construct(string $class, $where=[])
    {
        $this->class = $class;
        $this->where = $where;
    }

    public function toSQL()
    {
        $sql = '  WHERE ';
        $columnSQL = [];
        $this->values = [];
        forEach($this->where as $column => $value){
            if($value === null){
                $columnSQL[] = $column . ' IS NULL';
                continue;
            }
            if(is_array($value)){
                $ors = [];
                forEach($value as $index => $v){
                    $columnKey = $column . '_' . $index;
                    if(strpos($column, '.') !== false) $columnKey = str_replace('.', '', strstr($column, '.')) . '_' . $index;
                    $ors[] = $column . ' = :' . $columnKey;
                    $this->values[':' . $columnKey] = $v;
                }
                $columnSQL[] = '( ' . implode(' OR ', $ors) . ' )';
                continue;
            }

            $columnKey = $column;
            if(strpos($column, '.') !== false) $columnKey = str_replace('.', '', strstr($column, '.'));

            $columnSQL[] = $column . ' = :' . $columnKey;
            $this->values[':' . $columnKey] = $value; 
        }
        $sql = '  WHERE ' . implode("\n    AND ", $columnSQL) . "\n";
        return $sql;
    }



    public function values(): array
    {
        return $this->values;
    }
}
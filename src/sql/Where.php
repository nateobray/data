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
                    if($v instanceof Not){
                        if($v->getValue() === null){
                            $ors[] = $column . ' != :' . $columnKey;
                        } else {
                            $ors[] = $column . ' IS NOT :' . $columnKey;
                        }
                        $this->values[':' . $columnKey] = $v->getValue();
                    } else if ($v instanceof GT){
                        $ors[] = $column . ' > :' . $columnKey;
                        $this->values[':' . $columnKey] = $v->getValue();
                    } else if ($v instanceof GTE){
                        $ors[] = $column . ' >= :' . $columnKey;
                        $this->values[':' . $columnKey] = $v->getValue();
                    } else if ($v instanceof LT){
                        $ors[] = $column . ' < :' . $columnKey;
                        $this->values[':' . $columnKey] = $v->getValue();
                    } else if ($v instanceof LTE){
                        $ors[] = $column . ' <= :' . $columnKey;
                        $this->values[':' . $columnKey] = $v->getValue();
                    } else {
                        if($v === null){
                            $ors[] = $column . ' IS NULL';
                        } else {
                            $ors[] = $column . ' = :' . $columnKey;
                            $this->values[':' . $columnKey] = $v;
                        }
                        
                    }
                    
                    
                }
                $columnSQL[] = '( ' . implode(' OR ', $ors) . ' )';
                continue;
            }

            $columnKey = $column;
            if(strpos($column, '.') !== false) $columnKey = str_replace('.', '', strstr($column, '.'));

            if($value instanceof Not){
                if($value->getValue() === null){
                    $columnSQL[] = $column . ' IS NOT NULL';
                } else {
                    $columnSQL[] = $column . ' != :' . $columnKey;
                    $this->values[':' . $columnKey] = $value->getValue();
                }
            } else if ($value instanceof GT){
                $columnSQL[] = $column . ' > :' . $columnKey;
                $this->values[':' . $columnKey] = $value->getValue();
            } else if ($value instanceof GTE){
                $columnSQL[] = $column . ' >= :' . $columnKey;
                $this->values[':' . $columnKey] = $value->getValue();
            } else if ($value instanceof LT){
                $columnSQL[] = $column . ' < :' . $columnKey;
                $this->values[':' . $columnKey] = $value->getValue();
            } else if ($value instanceof LTE){
                $columnSQL[] = $column . ' <= :' . $columnKey;
                $this->values[':' . $columnKey] = $value->getValue();
            } else {
                $columnSQL[] = $column . ' = :' . $columnKey;
                $this->values[':' . $columnKey] = $value; 
            }

        }
        $sql = '  WHERE ' . implode("\n    AND ", $columnSQL) . "\n";
        return $sql;
    }

    public function values(): array
    {
        return $this->values;
    }
}
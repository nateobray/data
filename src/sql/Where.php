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
                    $ors[] = $this->getExpression($column, $index, $v);
                }
                $columnSQL[] = '( ' . implode(' OR ', $ors) . ' )';
                continue;
            }

            if($value instanceof AndOp){
                $ands = [];
                forEach($value->getValue() as $index => $v){
                    $ands[] = $this->getExpression($column, $index, $v);
                }
                $columnSQL[] = '( ' . implode(' AND ', $ands) . ' )';
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

    private function getExpression($column, $index, $v)
    {
        $columnKey = $column . '_' . $index . '_';
        if(strpos($column, '.') !== false) $columnKey = str_replace('.', '', strstr($column, '.')) . '_' . $index . '_';
        if($v instanceof Not){
            $this->values[':' . $columnKey] = $v->getValue();
            if($v->getValue() === null){
                return $column . ' != :' . $columnKey;
            } else {
                return $column . ' IS NOT :' . $columnKey;
            }
        } else if ($v instanceof GT){
            $this->values[':' . $columnKey] = $v->getValue();
            return $column . ' > :' . $columnKey;
        } else if ($v instanceof GTE){
            $this->values[':' . $columnKey] = $v->getValue();
            return $column . ' >= :' . $columnKey;
        } else if ($v instanceof LT){
            $this->values[':' . $columnKey] = $v->getValue();
            return $column . ' < :' . $columnKey;
        } else if ($v instanceof LTE){
            $this->values[':' . $columnKey] = $v->getValue();
            return $column . ' <= :' . $columnKey;
        } else {
            if($v === null){
                return $column . ' IS NULL';
            } else {
                $this->values[':' . $columnKey] = $v;
                return $column . ' = :' . $columnKey;
            }
        }
    }

    public function values(): array
    {
        return $this->values;
    }
}
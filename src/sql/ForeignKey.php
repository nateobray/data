<?php
namespace obray\data\sql;

class ForeignKey
{
    const CASCADE = 'CASCADE';
    const RESTRICT = 'RESTRICT';
    const SET_NULL = 'SET NULL';
    const NO_ACTION = 'NO ACTION';
    const SET_DEFAULT = 'SET DEFAULT';

    static public function createSQL(mixed $localColumn, string $foreignTable, mixed $foreignColumn, string $onDelete = self::CASCADE, string $onUpdate = self::CASCADE)
    {
        if(!is_array($localColumn)) $localColumn = [$localColumn];
        if(!is_array($foreignColumn)) $foreignColumn = [$foreignColumn];
        $keyName = hash('sha256', implode(',',$localColumn) . '_' . $foreignTable.'_' . implode(',',$foreignColumn) . '_'.strtotime('now').'_foreign');
        $keySQL = 'KEY `' . $keyName . '` (`' . implode('`,`',$localColumn) . '`)';
        $constraintSQL = 'CONSTRAINT `' . $keyName . '` FOREIGN KEY (`' . implode('`,`',$localColumn) . '`) REFERENCES `' . $foreignTable . '` (`' . implode('`,`',$foreignColumn) . '`) ON DELETE ' . $onDelete . ' ON UPDATE ' . $onUpdate;
        return [$keySQL, $constraintSQL];
    }
}
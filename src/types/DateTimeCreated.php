<?php
namespace obray\data\types;

class DateTimeCreated extends DateTime
{
    const TYPE = 'DATETIME';
    const LENGTH = null;
    const UNSIGNED = false;
    const NULLABLE = false;
    const DEFAULT = 'CURRENT_TIMESTAMP';   

    public function __construct(mixed $value=self::DEFAULT)
    {
        if($value === 'CURRENT_TIMESTAMP' || $value === null){
            $this->value = new \DateTime();
        }  else {
            $this->value = new \DateTime($value);
        }
    }
}
<?php
namespace obray\data\sql;

class AndOp
{
    private $value = '';

    public function __construct(array $value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
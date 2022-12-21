<?php
namespace obray\data\sql;

class LTE
{
    private $value = '';

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
<?php
namespace obray\data\types;

class Bit extends BaseType
{
    protected $isPrimary = false;
    protected $type = 'BIT';
    protected $length = 1;
    protected $unsigned;
    protected $nullable = true;
    protected $default = null;
    protected $autoIncement;
}
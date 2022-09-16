<?php
namespace obray\data;

class DBParam
{
    /**
     * @var Parameter name to be replaced in the prepared SQL
     */
    public $name;
    /**
     * @var Parameter value to be bound to the above name
     */
    public $param;
    /**
     * @var int Parameter type (\PDO::PARAM_*)
     */
    public $type;

    public function __construct($name, $param, $type = \PDO::PARAM_STR)
    {
        $this->name = $name;
        $this->param = $param;
        $this->type = $type;
    }
}
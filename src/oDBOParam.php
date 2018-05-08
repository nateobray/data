<?php
/**
 * Created by PhpStorm.
 * User: cameronbird
 * Date: 5/7/18
 * Time: 3:08 PM
 */

namespace obray;


/**
 * Class oDBOParam
 * @package obray
 */
class oDBOParam
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
<?php
/**
 * Created by PhpStorm.
 * User: cameronbird
 * Date: 5/7/18
 * Time: 2:31 PM
 */

namespace obray;


/**
 * Class oDBOStatement
 * @package obray
 */

use obray\exceptions\SqlArrayFailedToLoad;

/**
 * Class oDBOStatement
 * @package obray
 */
class oDBOStatement
{
    const VALID_SORT_DIRECTIONS = ['ASC', 'DESC'];

    /**
     * @var oDBOConnection
     */
    protected $oDBOConnection;

    /**
     * @var string
     */
    protected $queryString;
    /**
     * @var string
     */
    protected $orderString = '';
    /**
     * @var string
     */
    protected $limitString = '';
    /**
     * @var oDBOParam[]
     */
    protected $queryParams = [];
    /**
     * @var oDBOParam[]
     */
    protected $preparedParams = [];
    /**
     * @var \PDOStatement
     */
    protected $stmt = false;

    /**
     * oDBOStatement constructor.
     * @param oDBOConnection $oDBOConnection
     */
    public function __construct(\obray\oDBOConnection $oDBOConnection)
    {
        $this->oDBOConnection = $oDBOConnection;
    }

    /**
     * @param $sql
     * @return $this
     * @throws SqlFailedToLoad
     */
    public function loadSql($sql)
    {
        try {
            $this->loadSqlFile($sql);
            return $this;
        } catch (\obray\exceptions\SqlFileNotFound $e) {
        }

        try {
            $this->loadSqlArray($sql);
            return $this;
        } catch (\obray\exceptions\SqlArrayFailedToLoad $e) {
        }

        try {
            $this->loadSqlString($sql);
            return $this;
        } catch (\obray\exceptions\SqlStringFailedToLoad $e) {
        }

        throw new \obray\exceptions\SqlFailedToLoad();
    }

    /**
     * @param oDBOParam[] $params
     * @return $this
     * @throws \Exception
     */
    public function bindValues($params)
    {
        foreach ($params as $key => $param) {
            if (is_array($param)) {
                $this->bindArray($key, $param);
            } else {
                $this->bindValues($key, $param);
            }
        }
    }

    /**
     * @param $name
     * @param $param
     * @param int $paramType
     * @return $this
     * @throws \Exception
     */
    public function bindParam($name, &$param, $paramType = \PDO::PARAM_STR)
    {
        $oDBOParam = new oDBOParam($name, $param, $paramType);
        $this->addParam($name, $oDBOParam);
        return $this;
    }

    /**
     * @param $name
     * @param $param
     * @param int $paramType
     * @return $this
     * @throws \Exception
     */
    public function bindValue($name, $param, $paramType = \PDO::PARAM_STR)
    {
        $oDBOParam = new oDBOParam($name, $param, $paramType);
        $this->addParam($name, $oDBOParam);
        return $this;
    }

    /**
     * @param $name
     * @param $params
     * @param int $paramType
     * @return $this
     * @throws \Exception
     */
    public function bindArray($name, $params, $paramType = \PDO::PARAM_STR)
    {
        if (!is_array($params)) {
            throw new \Exception('Non-array value param received, array expected.');
        }

        $paramArray = [];
        foreach ($params as $param) {
            $paramArray[] = new oDBOParam($name, $param, $paramType);
        }
        $this->addParam($name, $paramArray);
        return $this;
    }

    public function orderBy($sortColumn, $sortDirection = 'ASC')
    {
        $sortDirection = strtoupper(trim($sortDirection));
        if (!in_array($sortDirection, self::VALID_SORT_DIRECTIONS)) {
            throw new \Exception('Invalid Sort Direction Passed');
        }
        if (preg_match('/\s/', $sortColumn)) {
            throw new \Exception('Invalid Sort Column; Cannot contain spaces');
        }

        if (empty($this->orderString)) {
            $this->orderString = "ORDER BY {$sortColumn} {$sortDirection}";
        } else {
            $this->orderString .= ", {$sortColumn} {$sortDirection}";
        }
    }

    public function limit($limit, $offset = 0)
    {
        if (!empty($this->limitString)) {
            throw new \Exception('Limit already set for query');
        }
        if (!self::isInteger($limit) || !self::isInteger($offset)) {
            throw new \Exception('Limit and Offset must be integers');
        }
        $this->limitString = "LIMIT {$limit} OFFSET {$offset}";
    }

    /**
     * @param $debug Variable to assign the debug data to
     */
    public function execute(&$debug = null)
    {
        // Setup debug object
        $debug = new \stdClass();
        $debug->query = null;
        $debug->params = null;

        $this->prepare($debug->query, $debug->params);
        return $this->stmt->execute();
    }

    /**
     * @param $query Variable to assign the executed query to
     */
    protected function prepare(&$query = null, &$params = null)
    {
        $params = $this->prepareParams();
        $conn = $this->oDBOConnection->getConnection();
        $query = implode("\r\n", [$this->queryString, $this->orderString, $this->limitString]);
        $this->stmt = $conn->prepare($query);
        foreach ($params as $param) {
            $this->stmt->bindParam($param->name, $param->param, $param->type);
        }
    }

    /**
     * @param $sqlString
     * @return void
     * @throws SqlStringFailedToLoad
     */
    protected function loadSqlString($sqlString)
    {
        // Attempt to load SQL from string
        try {
            // Check if string is prepare-able
            $dbh = $this->oDBOConnection->getConnection();
            $pdoStatement = $dbh->prepare($sqlString);
            if ($pdoStatement !== false) {
                $this->queryString = $sqlString;
                return;
            }
        } catch (\PDOException $e) {
        }
        throw new \obray\exceptions\SqlStringFailedToLoad();
    }

    /**
     * @param $sqlArray
     * @return void
     * @throws SqlArrayFailedToLoad
     */
    protected function loadSqlArray($sqlArray)
    {
        // Attempt to load SQL from array
        try {
            if (is_array($sqlArray) && !empty($sqlArray['sql'])) {
                $this->queryString = $sqlArray['sql'];
                return;
            }
        } catch (\Exception $e) {
        }
        throw new \obray\exceptions\SqlArrayFailedToLoad();
    }

    /**
     * Load SQL
     *  Loads an SQL File from the data layer directory (/data/your/filepath/here.sql)
     *
     * @param string $file sql file path relative to the root 'data' directory (i.e. 'orders/oShipments/select_shipments.sql')
     *
     * @throws SqlFileNotFoundException
     * @throws SqlFileFailedToLoadException
     *
     * @return void
     **/
    protected function loadSqlFile($file)
    {
        $file = preg_replace('#/+#', '/', 'data/' . $file);

        /*
        -- TODO --
        // check if file is cached, if so, load from cache
        if( $this->isSqlCached($file) ) {
            $sql = $this->loadSqlFromCache($file);
        }
        -- TODO --
        */

        if (($path = realpath($file)) === false) {
            throw new \obray\exceptions\SqlFileNotFound();
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \obray\exceptions\SqlFileFailedToLoad();
        }

        $this->queryString = $contents;
    }

    /**
     * @param $key
     * @param $value
     * @throws \Exception
     */
    protected function addParam($key, $value)
    {
        $key = trim($key, ';\t\n\r\0\x0B');
        if (isset($this->queryParams[$key])) {
            throw new \Exception("A param for key '{$key}' already exists.");
        }
        $this->queryParams[$key] = $value;
    }

    protected function prepareParams()
    {
        $this->preparedParams = [];
        foreach ($this->queryParams as $name => $param) {
            if (is_array($param)) {
                $this->prepareArrayParam($name, $param);
            } else {
                $this->preparedParams[$name] = $param;
            }
        }
        return $this->preparedParams;
    }

    protected function prepareArrayParam($name, $param)
    {
        $arrayParamNames = [];
        $index = 0;
        foreach ($param as $arrayParam) {
            $arrayParam->name = $arrayParamNames[] = "{$name}_{$index}";
            $this->preparedParams[$arrayParam->name] = $arrayParam;
            $index++;
        }

        $replacementParam = implode(', ', $arrayParamNames);
        $this->queryString = str_replace($name, $replacementParam, $this->queryString);
    }

    protected static function isInteger($input)
    {
        return (ctype_digit(strval($input)));
    }

    /**
     * @param $name
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    public function __call($name, $arguments = array())
    {
        if (!$this->stmt) {
            throw new \Exception('oDBOStatement::execute() must be called before calling PDOStatement methods.');
        }
        if (!method_exists($this->stmt, $name)) {
            throw new \Exception("Method ({$name}) does not exist on oDBOStatement or PDOStatement");
        }
        return $this->stmt->$name(...$arguments);
    }

}
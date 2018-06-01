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
     * @var string[]
     */
    protected $queryStrings;
    /**
     * @var oDBOParam[]
     */
    protected $queryParams = [];
    /**
     * @var \PDOStatement[]
     */
    protected $pdoStatements = [];
    /**
     * @var mixed[]
     */
    protected $results = [];

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
            $this->bindValue($key, $param);
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
     * @param int $paramType (Default \PDO::PARAM_STR)
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
     * prepare
     *  Prepares queries to be run and bind params to first query
     */
    private function prepare()
    {
        $pdoConnection = $this->oDBOConnection->getConnection();
        $this->pdoStatements = [];
        foreach ($this->queryStrings as $index => $queryString) {
            $this->pdoStatements[$index] = $pdoConnection->prepare($queryString);

            // If first query, bind parameters
            if ($index === 0) {
                foreach ($this->queryParams as $param) {
                    $this->pdoStatements[$index]->bindParam(
                        $param->name,
                        $param->param,
                        $param->type
                    );
                }
            }
        }
    }

    /**
     * execute
     *  Executes the prepared queries
     */
    public function execute()
    {
        $this->prepare();
        if( empty($this->pdoStatements) ) {
            throw new \Exception('No PDOStatements have been prepared. Failed to execute');
        }

        foreach ($this->pdoStatements as $index => $pdoStatement) {
            $this->results[$index] = $pdoStatement->execute();
        }
        return $this->results;
    }

    /**
     * FetchResults
     *  Gets the results of each executed query and returns them in an array indexed by query number
     * @param int $fetch_style PDO Fetch Style (defaults to FETCH_OBJ)
     *
     */
    public function fetchResults($fetchStyle = \PDO::FETCH_OBJ)
    {
        foreach ($this->pdoStatements as $index => $pdoStatement) {
            $resultCount = $pdoStatement->rowCount();
            if ($this->results[$index] != true) {
                continue;
            }
            $this->results[$index] = [];
            if ($resultCount > 0) {
                $this->results[$index] = $pdoStatement->fetchAll($fetchStyle);
            }
        }
        return $this->results;
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
                $this->parseQueries($sqlString);
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
                $this->parseQueries($sqlArray['sql']);
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
     * @param string $file absolute sql file path
     *
     * @throws SqlFileNotFoundException
     * @throws SqlFileFailedToLoadException
     *
     * @return void
     **/
    protected function loadSqlFile($file)
    {
        $file = preg_replace('#/+#', '/', $file);

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

        $this->parseQueries($contents);
    }

    protected function parseQueries($sqlString)
    {
        $queries = explode(';', $sqlString);
        $this->queryStrings = array_map(function ($queryString) {
            $queryString = trim($queryString);
            return $queryString;
        }, $queries);
        // remove empty queries
        $this->queryStrings = array_filter($this->queryStrings);        
    }

    /**
     * @param $key
     * @param $value
     * @throws \Exception
     */
    protected function addParam($key, $value)
    {
        $key = trim($key, ';\t\n\r\x0B');
        if (isset($this->queryParams[$key])) {
            throw new \Exception("A param for key '{$key}' already exists.");
        }
        $this->queryParams[$key] = $value;
    }

    protected static function isInteger($input)
    {
        return (ctype_digit(strval($input)));
    }

}
<?php
namespace obray\data;

use obray\data\exceptions\SqlArrayFailedToLoad;
use obray\data\DBConn;
use obray\data\exceptions\SqlFailedToLoad;
use obray\data\exceptions\SqlFileFailedToLoad;
use obray\data\exceptions\SqlFileNotFound;
use obray\data\exceptions\SqlStringFailedToLoad;

class DBStatement
{
    const VALID_SORT_DIRECTIONS = ['ASC', 'DESC'];

    protected $DBConn;
    protected $queryStrings;
    protected $queryParams = [];
    protected $pdoStatements = [];
    protected $results = [];

    public function __construct(DBConn $DBConn)
    {
        $this->DBConn = $DBConn;
    }

    public function loadSql($sql)
    {
        try {
            $this->loadSqlFile($sql);
            return $this;
        } catch (SqlFileNotFound $e) {
        }

        try {
            $this->loadSqlArray($sql);
            return $this;
        } catch (SqlArrayFailedToLoad $e) {
        }

        try {
            $this->loadSqlString($sql);
            return $this;
        } catch (SqlStringFailedToLoad $e) {
        }

        throw new SqlFailedToLoad();
    }

    public function bindValues($params)
    {
        foreach ($params as $key => $param) {
            $this->bindValue($key, $param);
        }
    }

    public function bindParam($name, &$param, $paramType = \PDO::PARAM_STR)
    {
        $DBParam = new DBParam($name, $param, $paramType);
        $this->addParam($name, $DBParam);
        return $this;
    }

    public function bindValue($name, $param, $paramType = \PDO::PARAM_STR)
    {
        $DBParam = new DBParam($name, $param, $paramType);
        $this->addParam($name, $DBParam);
        return $this;
    }

    private function prepare()
    {
        $pdoConnection = $this->DBConn->getConnection();
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

    protected function loadSqlString($sqlString)
    {
        // Attempt to load SQL from string
        try {
            // Check if string is prepare-able
            $dbh = $this->DBConn->getConnection();
            $pdoStatement = $dbh->prepare($sqlString);
            if ($pdoStatement !== false) {
                $this->parseQueries($sqlString);
                return;
            }
        } catch (\PDOException $e) {
        }
        throw new SqlStringFailedToLoad();
    }

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
        throw new SqlArrayFailedToLoad();
    }

    protected function loadSqlFile($file)
    {
        $file = preg_replace('#/+#', '/', $file);

        if (($path = realpath($file)) === false) {
            throw new SqlFileNotFound();
        }

        $contents = file_get_contents($path);
        
        if ($contents === false) {
            throw new SqlFileFailedToLoad();
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
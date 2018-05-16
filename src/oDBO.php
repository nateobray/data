<?php

namespace obray;

if (!class_exists(\obray\oObject::class)) {
    die();
}

/********************************************************************************************************************
 *
 * ODBO:    This is the database interface object built specifically for MySQL and MariaDB.
 ********************************************************************************************************************/
Class oDBO extends \obray\oObject
{

    protected $table = '';
    protected $table_definition = array();
    protected $primary_key_column = '';
    protected $data_types = array(
        'varchar' => array(
            'sql' => ' VARCHAR(size) COLLATE utf8_general_ci ',
            'my_sql_type' => 'varchar(size)',
            'validation_regex' => ''
        ),
        'mediumtext' => array(
            'sql' => ' MEDIUMTEXT COLLATE utf8_general_ci ',
            'my_sql_type' => 'mediumtext',
            'validation_regex' => ''
        ),
        'text' => array('sql' => ' TEXT COLLATE utf8_general_ci ', 'my_sql_type' => 'text', 'validation_regex' => ''),
        'integer' => array('sql' => ' int ', 'my_sql_type' => 'int(11)', 'validation_regex' => '/^([+,-]?[0-9])*$/'),
        'uninteger' => array(
            'sql' => ' int(11) unsigned NOT NULL DEFAULT \'0\'  ',
            'my_sql_type' => 'int(11) unsigned',
            'validation_regex' => '/^([+,-]?[0-9])*$/'
        ),
        'float' => array('sql' => ' float ', 'my_sql_type' => 'float', 'validation_regex' => '/[0-9\.]*/'),
        'boolean' => array('sql' => ' tinyint(1) ', 'my_sql_type' => 'tinyint(1)', 'validation_regex' => ''),
        'datetime' => array('sql' => ' datetime ', 'my_sql_type' => 'datetime', 'validation_regex' => ''),
        'password' => array('sql' => ' varchar(255) ', 'my_sql_type' => 'varchar(255)', 'validation_regex' => '')
    );
    protected $enable_column_additions = true;
    protected $enable_column_removal = true;
    protected $enable_data_type_changes = true;
    protected $enable_system_columns = true;

    public function __construct(\obray\oDBOConnection $oDBOConnection)
    {
        $this->oDBOConnection = $oDBOConnection;

    }

    public function startTransaction()
    {
        $this->oDBOConnection->beginTransaction();
        $this->is_transaction = true;
    }

    public function commitTransaction()
    {
        if (!$this->is_transaction) {
            return;  //This likely means that the transaction was rolled back and should therefore not be committed. (that or there was never a transaction to begin with).
        }
        $this->oDBOConnection->commit();
        $this->is_transaction = false;
    }

    public function rollbackTransaction()
    {
        $this->oDBOConnection->rollBack();
        $this->is_transaction = false;
    }

    public function getOptions($params = array())
    {
        $this->data = false;
        if (!empty($this->table_definition[$params["column"]]["options"])) {
            if (isset($params['key']) && strlen(trim($params['key']))) {
                if (!empty($this->table_definition[$params["column"]]["options"][$params["key"]])) {
                    $this->data = $this->table_definition[$params["column"]]["options"][$params["key"]];
                } else {
                    $this->data = false;
                }
            } else {
                if (isset($params['value']) && strlen(trim($params['value']))) {
                    $key = array_search($params["value"], $this->table_definition[$params["column"]]["options"]);
                    if ($key !== false) {
                        $this->data = $key;
                    } else {
                        $this->data = false;
                    }
                } else {
                    $this->data = $this->table_definition[$params["column"]]["options"];
                }
            }
        }
    }

    public function setDatabaseConnection($oDBOConnection)
    {

        $this->oDBOConnection = $oDBOConnection;
        if (!isSet($this->table) || $this->table == '') {
            return;
        }
        if ($this->debug_mode) {
            $this->scriptTable();
            $this->alterTable();
        }

    }

    /*************************************************************************************************************
     *
     * SCRIPT TABLE
     *************************************************************************************************************/

    public function scriptTable($params = array())
    {

        // script database if it does't exist
        $sql = 'CREATE DATABASE IF NOT EXISTS ' . $this->oDBOConnection->getDBName() . ';';
        $statement = $this->oDBOConnection->prepare($sql);
        $statement->execute();

        $sql = '';
        $data_types = $this->data_types;

        forEach ($this->table_definition as $name => $def) {

            if (in_array($name, ["OCU", "OMU", "OCDT", "OMDT"])) {
                continue;
            }
            if (isSet($def['data_type']) && $def['data_type'] == "filter") {
                continue;
            }
            if (array_key_exists('store', $def) == false || (array_key_exists('store',
                        $def) == true && $def['store'] == true)) {

                if (!empty($sql)) {
                    $sql .= ',';
                }
                if (isSet($def['data_type'])) {
                    $data_type = $this->getDataType($def);
                    $sql .= $name . str_replace('size', str_replace(')', '', $data_type['size']),
                            $data_types[$data_type['data_type']]['sql']);
                }

                if (array_key_exists('primary_key', $def) && $def['primary_key'] === true) {
                    $this->primary_key_column = $name;
                    $sql .= $name . ' INT UNSIGNED NOT NULL AUTO_INCREMENT ';
                }
            }
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table . ' ( ' . $sql;
        if ($this->enable_system_columns) {
            $sql .= ', OCDT DATETIME, OCU INT UNSIGNED, OMDT DATETIME, OMU INT UNSIGNED ';
        }
        if (!empty($this->primary_key_column)) {
            $sql .= ', PRIMARY KEY (' . $this->primary_key_column . ') ) ENGINE=' . $this->oDBOConnection->getDBEngine() . ' DEFAULT CHARSET=' . $this->oDBOConnection->getDBCharSet() . '; ';
        }

        $this->sql = $sql;
        $statement = $this->oDBOConnection->prepare($sql);
        $this->script = $statement->execute();

    }

    public function scriptOnMissingTable($e)
    {

        $matches = array();
        print_r($e);
        print_r($this);
        preg_match('/([\'][a-zA-Z0-9]+[\.])([a-zA-Z0-9]+)/', $e->errorInfo[2], $matches);
        if (!empty($matches[2])) {
            $this->scriptTable();
            //$this->getRolesAndPermissions();
        }

    }

    /*************************************************************************************************************
     *
     * ALTERTABLE
     *************************************************************************************************************/

    public function alterTable()
    {
        if (empty($this->oDBOConnection)) {
            return $this;
        }

        $sql = 'DESCRIBE ' . $this->table . ';';
        $statement = $this->oDBOConnection->prepare($sql);
        $statement->execute();

        $statement->setFetchMode(\PDO::FETCH_OBJ);
        $data = $statement->fetchAll();

        $temp_def = $this->table_definition;
        $obray_fields = array(3 => 'OCDT', 4 => 'OCU', 5 => 'OMDT', 6 => 'OMU');
        forEach ($obray_fields as $of) {
            unset($this->table_definition[$of]);
        }

        forEach ($data as $def) {
            if (isSet($def->data_type) && $def->data_type == "filter") {
                continue;
            }
            if (array_key_exists('store', $def) == false || (array_key_exists('store',
                        $def) == true && $def['store'] == true)) {

                if (array_search($def->Field, $obray_fields) === false) {
                    if (isSet($this->table_definition[$def->Field])) {

                        if ($this->enable_data_type_changes && isSet($this->table_definition[$def->Field]['data_type'])) {
                            $data_type = $this->getDataType($this->table_definition[$def->Field]);
                            if (str_replace('size', $data_type['size'],
                                    $data_types[$data_type['data_type']]['my_sql_type']) != $def->Type) {
                                if (!isSet($this->table_alterations)) {
                                    $this->table_alterations = array();
                                }
                                $sql = 'ALTER TABLE ' . $this->table . ' MODIFY COLUMN ' . $def->Field . ' ' . str_replace('size',
                                        $data_type['size'], $data_types[$data_type['data_type']]['sql']);
                                $statement = $this->oDBOConnection->prepare($sql);
                                $this->table_alterations[] = $statement->execute();
                            }
                        }
                        unset($this->table_definition[$def->Field]);

                    } else {
                        if ($this->enable_column_removal && isSet($_REQUEST['enableDrop'])) {
                            if (!isSet($this->table_alterations)) {
                                $this->table_alterations = array();
                            }
                            $sql = 'ALTER TABLE ' . $this->table . ' DROP COLUMN ' . $def->Field . ' ';
                            $statement = $this->oDBOConnection->prepare($sql);
                            $this->table_alterations[] = $statement->execute();
                        }
                    }
                }

            }
        }

        if ($this->enable_column_additions) {
            forEach ($this->table_definition as $key => $def) {
                if (isSet($def['data_type']) && $def['data_type'] == "filter") {
                    continue;
                }
                if (array_key_exists('store', $def) == false || (array_key_exists('store',
                            $def) == true && $def['store'] == true)) {
                    if (!isSet($this->table_alterations)) {
                        $this->table_alterations = array();
                    }
                    $data_type = $this->getDataType($def);
                    $sql = 'ALTER TABLE ' . $this->table . ' ADD (' . $key . ' ' . str_replace('size',
                            $data_type['size'], $data_types[$data_type['data_type']]['sql']) . ')';
                    $statement = $this->oDBOConnection->prepare($sql);
                    $this->table_alterations[] = $statement->execute();
                }
            }
        }

        $this->table_definition = $temp_def;

    }

    /********************************************************************
     *
     * GETTABLEDEFINITION
     ********************************************************************/

    public function getTableDefinition()
    {
        $this->data = $this->table_definition;
    }

    private function getWorkingDef()
    {
        $this->required = array();
        forEach ($this->table_definition as $key => $def) {
            if (isSet($def['required']) && $def['required'] == true) {
                $this->required[$key] = true;
            }
            if (isSet($def['primary_key'])) {
                $this->primary_key_column = $key;
            }
            if (isSet($def['parent']) && $def['parent'] == true) {
                $this->parent_column = $key;
            }
            if (isSet($def['slug_key']) && $def['slug_key'] == true) {
                $this->slug_key_column = $key;
            }
            if (isSet($def['slug_value']) && $def['slug_value'] == true) {
                $this->slug_value_column = $key;
            }
        }
    }

    /********************************************************************
     *
     * ADD function
     ********************************************************************/

    public function add($params = array())
    {

        if (empty($this->oDBOConnection)) {
            return $this;
        }

        $sql = '';
        $sql_values = '';
        $data = array();
        $this->getWorkingDef();

        if (isSet($this->slug_key_column) && isSet($this->slug_value_column) && isSet($params[$this->slug_key_column])) {
            if (isSet($this->parent_column) && isSet($params[$this->parent_column])) {
                $parent = $params[$this->parent_column];
            } else {
                $parent = null;
            }
            $params[$this->slug_value_column] = $this->getSlug($params[$this->slug_key_column],
                $this->slug_value_column, $parent);
        }

        forEach ($params as $key => $param) {

            if (isSet($this->table_definition[$key])) {

                $def = $this->table_definition[$key];
                if (!empty($def["options"])) {
                    $options = array_change_key_case($def["options"], CASE_LOWER);
                    if (!empty($options[strtolower($param)]) && !is_array($options[strtolower($param)])) {
                        $data[$key] = $options[strtolower($param)];
                        $option_is_set = true;
                    } else {
                        $data[$key] = $param;
                    }
                } else {
                    $data[$key] = $param;
                }
                $data_type = $this->getDataType($def);

                if (isSet($this->required[$key])) {
                    unset($this->required[$key]);
                }
                if (isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],
                        $params[$key]) && $params[$key] == null) {
                    $this->throwError(isSet($def['error_message']) ? $def['error_message'] : isSet($def['label']) ? $def['label'] . ' is invalid.' : $key . ' is invalid.',
                        '500', $key);
                }

                if (isSet($def['data_type']) && $def['data_type'] == 'password') {
                    $salt = '$2a$12$' . $this->generateToken();
                    $data[$key] = crypt($params[$key], $salt);
                }

                if (isSet($params[$key])) {
                    if (!empty($sql)) {
                        $sql .= ',';
                        $sql_values .= ',';
                    }
                    $sql .= $key;
                    $sql_values .= ':' . $key;
                }
            }
        }

        if (!empty($this->required)) {
            forEach ($this->required as $key => $value) {
                $def = $this->table_definition[$key];
                $this->throwError(isSet($def['error_message']) ? $def['error_message'] : isSet($def['label']) ? $key . ' is required.' : $key . ' is required.',
                    '500', $key);
            }
        }

        if ($this->isError()) {
            $this->throwError(isSet($this->general_error) ? $this->general_error : 'There was an error on this form, please make sure the below fields were completed correclty: ');
            return $this;
        }

        if ($this->enable_system_columns) {
            if (isSet($_SESSION['ouser']->ouser_id)) {
                $ocu = $_SESSION['ouser']->ouser_id;
            } else {
                $ocu = 0;
            }
            $system_columns = ", OCDT, OCU ";
            $system_values = ', \'' . date('Y-m-d H:i:s') . '\', ' . $ocu;
        } else {
            $system_columns = "";
            $system_values = "";
        }

        $this->sql = ' INSERT INTO ' . $this->table . ' ( ' . $sql . $system_columns . ' ) VALUES ( ' . $sql_values . $system_values . ' ) ';
        $statement = $this->oDBOConnection->prepare($this->sql);
        forEach ($data as $key => $dati) {
            if ($dati === 'NULL') {
                $statement->bindValue($key, null, \PDO::PARAM_NULL);
            } else {
                $statement->bindValue($key, $dati);
            }
        }
        try {

            $this->script = $statement->execute();

        } catch (\PDOException $e) {

            if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1146) {
                $this->scriptOnMissingTable($e);
                $this->add($params);
                return $this;
            }

            $this->throwError("Database error occurred.");
            return $this;

        }

        if (empty($this->is_transaction)) {
            $get_params = array($this->primary_key_column => $this->oDBOConnection->lastInsertId());
            if (!empty($option_is_set)) {
                $get_params["with"] = "options";
            }
            $this->get($get_params);
            
        }
        return $this;

    }

    /********************************************************************
     * UPDATE function
     ********************************************************************/

    public function update($params = array())
    {

        if (empty($this->oDBOConnection)) {
            return $this;
        }

        $sql = '';
        $sql_values = '';
        $data = array();

        $this->getWorkingDef();

        /*
        if( isSet($this->slug_key_column) && isSet($this->slug_value_column) && isSet($params[$this->slug_key_column]) ){
            if( isSet($this->parent_column) && isSet($params[$this->parent_column]) ){ $parent = $params[$this->parent_column];  } else { $parent = null; }
            $params[$this->slug_value_column] = $this->getSlug($params[$this->slug_key_column],$this->slug_value_column,$parent);
        }
        */

        forEach ($params as $key => $param) {

            if (isSet($this->table_definition[$key])) {

                $def = $this->table_definition[$key];
                if (!empty($def["options"])) {
                    $options = array_change_key_case($def["options"], CASE_LOWER);
                    if (!empty($options[strtolower($param)]) && !is_array($options[strtolower($param)])) {
                        $data[$key] = $options[strtolower($param)];
                        $option_is_set = true;
                    } else {
                        $data[$key] = $param;
                    }
                } else {
                    $data[$key] = $param;
                }
                $data_type = $this->getDataType($def);

                if (isSet($def['required']) && $def['required'] === true && (!isSet($params[$key]) || $params[$key] === null || $params[$key] === '')) {
                    $this->throwError(isSet($def['error_message']) ? $def['error_message'] : isSet($def['label']) ? $def['label'] . ' is required.' : $key . ' is required.',
                        500, $key);
                }

                if ((isSet($def['data_type']) && !empty($this->data_types[$data_type['data_type']]['validation_regex']) && !preg_match($this->data_types[$data_type['data_type']]['validation_regex'],
                            $params[$key])) && $params[$key] == null) {
                    $this->throwError(isSet($def['error_message']) ? $def['error_message'] : isSet($def['label']) ? $def['label'] . ' is invalid.' : $key . ' is invalid.',
                        500, $key);
                }

                if (isSet($def['data_type']) && $def['data_type'] == 'password') {
                    $salt = '$2a$12$' . $this->generateToken();
                    $data[$key] = crypt($params[$key], $salt);
                }

                if (!empty($sql)) {
                    $sql .= ',';
                    $sql_values .= ',';
                }
                $sql .= $key . ' = :' . $key . ' ';

            }
        }

        if (empty($this->primary_key_column)) {
            $this->throwError('Please specify a primary key.', 'primary_key', '500');
        }
        if (!isSet($params[$this->primary_key_column])) {
            $this->throwError('Please specify a value for the primary key.', '500', $this->primary_key_column);
        }
        if ($this->isError()) {
            return $this;
        }


        if ($this->enable_system_columns) {
            if (isSet($_SESSION['ouser']->ouser_id) && !empty($_SESSION['ouser']->ouser_id)) {
                $omu = $_SESSION['ouser']->ouser_id;
            } else {
                $omu = 0;
            }
            $system_columns = ', OMDT = \'' . date('Y-m-d H:i:s') . '\', OMU = ' . $omu;

        } else {
            $system_columns = "";
        }

        $this->sql = ' UPDATE ' . $this->table . ' SET ' . $sql . $system_columns . ' WHERE ' . $this->primary_key_column . ' = :' . $this->primary_key_column . ' ';
        $statement = $this->oDBOConnection->prepare($this->sql);
        forEach ($data as $key => $dati) {
            if ($dati == 'NULL') {
                $statement->bindValue($key, null, \PDO::PARAM_NULL);
            } else {
                $statement->bindValue($key, $dati);
            }
        }
        $this->script = $statement->execute();

        if (empty($this->is_transaction)) {
            $get_params = array($this->primary_key_column => $params[$this->primary_key_column]);
            if (!empty($option_is_set)) {
                $get_params["with"] = "options";
            }
            $this->get($get_params);
        }


    }

    /********************************************************************
     *
     * DELETE function
     ********************************************************************/

    public function delete($params = array())
    {

        if (empty($this->oDBOConnection)) {
            return $this;
        }
        $original_params = $params;

        $this->where = $this->getWhere($params, $values);

        if (empty($this->where)) {
            $this->throwError('Please provide a filter for this delete statement', 500);
        }
        if (!empty($this->errors)) {
            return $this;
        }

        $this->sql = ' DELETE FROM ' . $this->table . $this->where;
        $statement = $this->oDBOConnection->prepare($this->sql);
        forEach ($values as $value) {
            if (is_integer($value)) {
                $statement->bindValue($value['key'], trim($value['value']), \PDO::PARAM_INT);
            } else {
                $statement->bindValue($value['key'], trim((string)$value['value']), \PDO::PARAM_STR);
            }
        }
        $this->script = $statement->execute();


    }

    /********************************************************************
     *
     * GET function
     ********************************************************************/

    public function get($params = array())
    {

        $original_params = $params;

        if (!empty($this->enable_system_columns)) {
            $this->table_definition['OCDT'] = array('data_type' => 'datetime');
            $this->table_definition['OMDT'] = array('data_type' => 'datetime');
            $this->table_definition['OCU'] = array('data_type' => 'integer');
            $this->table_definition['OMU'] = array('data_type' => 'integer');
        }

        $limit = '';
        $order_by = '';
        $filter = true;
        if (isSet($params['start']) && isSet($params['rows'])) {
            $limit = ' LIMIT ' . $params['start'] . ',' . $params['rows'] . '';
            unset($params['start']);
            unset($params['rows']);
            unset($original_params['start']);
            unset($original_params['rows']);
        }
        if (isSet($params['filter']) && ($params['filter'] == 'false' || !$filter)) {
            $filter = false;
            unset($params['filter']);
        }
        if (isSet($params['order_by'])) {
            $order_by = explode('|', $params['order_by']);
            $columns = array();
            forEach ($order_by as $i => &$order) {
                $order = explode(':', $order);
                if (!empty($order) && array_key_exists($order[0], $this->table_definition)) {
                    $columns[] = $order[0];
                    if (count($order) > 1) {
                        switch ($order[1]) {
                            case 'ASC':
                            case 'asc':
                                $columns[count($columns) - 1] .= ' ASC ';
                                break;
                            case 'DESC':
                            case 'desc':
                                $columns[count($columns) - 1] .= ' DESC ';
                                break;
                        }
                    }
                }
            }
            if (!empty($columns)) {
                $order_by = ' ORDER BY ' . implode(',', $columns);
            } else {
                $order_by = '';
            }
        }

        $withs = array();
        $original_withs = array();

        if (!empty($params['with'])) {
            $withs = explode('|', $params['with']);
            $original_withs = $withs;
        }

        $columns = array();
        $withs_to_pass = array();
        $filter_columns = array();

        forEach ($this->table_definition as $column => $def) {
            if (isSet($def['data_type']) && $def['data_type'] == "filter") {
                $filter_columns[] = $columns;
                continue;
            }
            if (isSet($def['data_type']) && $def['data_type'] == 'password' && isSet($params[$column])) {
                $password_column = $column;
                $password_value = $params[$column];
                unset($params[$column]);
            }
            $columns[] = $this->table . '.' . $column;
            if (array_key_exists('primary_key', $def)) {
                $primary_key = $column;
            }

            // HANDLE OPTIONS
            if (!empty($params[$column]) && !empty($def["options"])) {
                $options = $def["options"];
                $options = array_change_key_case($options, CASE_LOWER);
                if (!empty($options[strtolower($params[$column])])) {
                    $params[$column] = $options[strtolower($params[$column])];
                }
            }

            forEach ($withs as $i => &$with) {
                if (!is_array($with) && array_key_exists($with, $def)) {
                    unset($original_withs[$i]);
                    $name = $with;
                    if (!is_array($def[$with])) {
                        $with = explode(':', $def[$with]);
                        $with[] = $column;
                        $with[] = $name;
                    } else {
                        $with = array();
                        $with[] = $column;
                        $with[] = $name;
                    }
                }
            }
        }

        $filter_join = "";
        forEach ($withs as $i => $w) {
            if (!is_array($w)) {
                $withs_to_pass[] = $w;
                unset($withs[(int)$i]);
            }
        }
        $withs = array_values($withs);
        $withs_to_pass = http_build_query(array('with' => implode('|', $withs_to_pass)));
        forEach ($withs as &$with) {
            if (strpos($with[1], 'with') === false) {
                if (strpos($with[1], '?') === false) {
                    $with[1] .= '?' . $withs_to_pass;
                } else {
                    $with[1] .= '&' . $withs_to_pass;
                }
            }
        }

        if (isSet($original_params['with'])) {
            $original_params['with'] = implode('|', $original_withs);
        }
        $values = array();
        $where_str = $this->getWhere($params, $values, $original_params);

        $this->sql = 'SELECT ' . implode(',',
                $columns) . ' FROM ' . $this->table . $this->getJoin() . $filter_join . $where_str . $order_by . $limit;
        $statement = $this->oDBOConnection->prepare($this->sql);
        forEach ($values as $value) {
            if (is_integer($value)) {
                $statement->bindValue($value['key'], trim($value['value']), \PDO::PARAM_INT);
            } else {
                $statement->bindValue($value['key'], trim((string)$value['value']), \PDO::PARAM_STR);
            }
        }
        try {

            $statement->execute();
            $statement->setFetchMode(\PDO::FETCH_NUM);
            $data = $statement->fetchAll(\PDO::FETCH_OBJ);

        } catch (\PDOException $e) {

            if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1146) {
                $this->scriptOnMissingTable($e);
                $this->get($original_params);
                return $this;
            }

            $this->throwError("Database error occurred.");
            return $this;

        }


        $this->data = $data;

        if (!empty($withs) && !empty($this->data)) {

            forEach ($withs as &$with) {

                // HANDLES OPTIONS
                if (strpos($with[1], "options?with") !== false) {
                    if (!empty($this->table_definition[$with[0]]["options"])) {
                        $column = $with[0];
                        $options = $this->table_definition[$with[0]]["options"];
                        forEach ($this->data as $key => $data) {
                            $option = array_search($data->$column, $options);
                            if ($option !== false) {
                                $this->data[$key]->$column = $option;
                            }
                        }
                    }
                    continue;
                }


                $ids_to_index = array();
                if (!is_array($with)) {
                    break;
                }
                $with_key = $with[0];
                $with_column = $with[2];
                $with_name = $with[3];
                $with_components = parse_url($with[1]);
                $sub_params = array();
                forEach ($this->data as $i => $data) {
                    if (!isSet($ids_to_index[$data->$with_column])) {
                        $ids_to_index[$data->$with_column] = array();
                    }
                    $ids_to_index[$data->$with_column][] = (int)$i;
                }
                $ids = array();
                forEach ($this->data as $row) {
                    $ids[] = $row->$with_column;
                }
                $ids = implode('|', $ids);
                if (!empty($with_components['query'])) {
                    parse_str($with_components['query'], $sub_params);
                }
                if ($ids !== '') {
                    $with[0] = $with[0] . '=' . $ids;
                } else {
                    $with[0] = $with[0] . '=';
                }
                if (isSet($original_params['with']) && empty($original_params['with'])) {
                    unset($original_params['with']);
                }
                if (!empty($original_params['with']) && !empty($sub_params['with'])) {
                    $original_params['with'] = array_unique(array_merge(explode('|', $sub_params['with']),
                        explode('|', $original_params['with'])));
                    $original_params['with'] = implode('|', $original_params['with']);
                }
                $sub_params = array_replace($sub_params, $original_params);
                $new_params = array();
                parse_str($with[0], $new_params);
                $sub_params = array_replace($sub_params, $new_params);

                if (!empty($this->data) && !empty($withs) && in_array('children', $withs[0])) {
                    $sub_params['with'] = 'children';
                }
                $with = $this->route($with_components['path'] . 'get/', $sub_params)->data;
                forEach ($with as &$w) {
                    if (isSet($ids_to_index[$w->$with_key])) {
                        forEach ($ids_to_index[$w->$with_key] as $index) {
                            if (!isSet($this->data[$index]->$with_name)) {
                                $this->data[$index]->$with_name = array();
                            }
                            array_push($this->data[$index]->$with_name, $w);
                        }
                    }
                }


                if ($filter) {
                    forEach ($this->data as $i => $data) {
                        if (empty($data->$with_name)) {
                            unset($this->data[$i]);
                        }
                    }
                    $this->data = array_values((array)$this->data);
                }

            }

        }

        if ($this->table == 'ousers' || (isset($this->user_session) && $this->table == $this->user_session)) {
            forEach ($this->data as $i => &$data) {
                if (isSet($password_column) && strcmp($data->$password_column,
                        crypt($password_value, $data->$password_column)) != 0) {
                    unset($this->data[$i]);
                }
                unset($data->ouser_password);
            }
        }

        //Restructure the result set to be keyed by the column name provided
        if (!empty($original_params['keyed']) && !empty($this->data[0]->{$original_params['keyed']})) {
            $keyed_data = array();
            foreach ($this->data as $key => $data) {
                if (isset($data->{$original_params['keyed']})) {
                    $keyed_data[strtolower($data->{$original_params['keyed']})] = $data;
                }
            }

            if (count($keyed_data)) {
                $this->data = $keyed_data;
            }
        }
        $this->filter = $filter;
        $this->recordcount = count($this->data);

        return $this;

    }

    private function getJoin()
    {

        if (!empty($this->join)) {
            $obj = $this->route($this->join);
            forEach ($obj->table_definition as $key => $def) {
                if (!empty($def["primary_key"]) && $def["primary_key"] === true) {
                    $primary_key = $key;
                }
            }
            forEach ($this->table_definition as $key => $def) {
                if (!empty($def["primary_key"]) && $def["primary_key"] === true) {
                    $this->primary_key_column = $key;
                }
            }
            return ' INNER JOIN ' . strtolower($obj->table) . ' ON ' . strtolower($obj->table) . '.' . $primary_key . ' = ' . strtolower($this->table) . '.' . $this->primary_key_column . ' ';
        } else {
            return '';
        }

    }

    /********************************************************************
     *
     * GETWHERE
     ********************************************************************/

    private function getWhere(&$params = array(), &$values = array(), &$original_params = array())
    {

        if (!empty($this->enable_system_columns)) {
            $this->table_definition['OCDT'] = array('data_type' => 'datetime');
            $this->table_definition['OMDT'] = array('data_type' => 'datetime');
        }

        $where = array();
        $count = 0;
        $p = array();
        forEach ($params as $key => &$param) {
            $original_key = $key;
            $operator = '=';
            switch (substr($key, -1)) {
                case '!':
                case '<':
                case '>':
                    $operator = substr($key, -1) . '=';
                    //$p[str_replace(substr($key,-1),'',$key)] = $params[$key];
                    $key = str_replace(substr($key, -1), '', $key);
                default:
                    if (empty($params[$key])) {
                        $array = explode('~', $key);
                        if (count($array) === 2) {
                            $param = $array[1];
                            $key = $array[0];
                            unset($params[$key]);
                            $operator = 'LIKE';
                        }
                        $array = explode('>', $key);
                        if (count($array) === 2) {
                            $param = urldecode($array[1]);
                            $key = $array[0];
                            unset($params[$key]);
                            $operator = '>';
                        }
                        $array = explode('<', $key);
                        if (count($array) === 2) {
                            $param = urldecode($array[1]);
                            $key = $array[0];
                            unset($params[$key]);
                            $operator = '<';
                        }
                    }
                    break;
            }

            if (array_key_exists($key, $this->table_definition)) {

                if (!is_array($param)) {
                    $param = array(0 => $param);
                }

                forEach ($param as &$param_value) {

                    if (empty($where)) {
                        $new_key = '';
                    } else {
                        $new_key = 'AND';
                    }
                    $ors = explode('|', $param_value);

                    $where[] = array('join' => $new_key . ' (', 'key' => '', 'value' => '', 'operator' => '');
                    if ($operator == '=' && count($ors) > 1) {

                        $value_keys = array();
                        forEach ($ors as $v) {
                            ++$count;
                            $values[] = array('key' => ':' . $key . '_' . $count, 'value' => $v);
                            $value_keys[] = ':' . $key . '_' . $count;
                        }

                        $where[] = array(
                            'join' => '',
                            'key' => $key,
                            'value' => '(' . implode(',', $value_keys) . ')',
                            'operator' => 'IN'
                        );


                    } else {

                        $or_key = '';

                        forEach ($ors as $v) {

                            if ($v !== 'NULL') {
                                if ($operator == 'LIKE') {
                                    $v = '%' . $v . '%';
                                }
                                ++$count;
                                $values[] = array('key' => ':' . $key . '_' . $count, 'value' => $v);
                                $where[] = array(
                                    'join' => $or_key,
                                    'key' => $key,
                                    'value' => ':' . $key . '_' . $count,
                                    'operator' => $operator
                                );
                                $or_key = 'OR';
                            } else {
                                $where[] = array(
                                    'join' => $or_key,
                                    'key' => $key,
                                    'value' => ' IS NULL ',
                                    'operator' => ''
                                );
                            }

                        }

                    }
                    $where[] = array('join' => ')', 'key' => '', 'value' => '', 'operator' => '');
                }
            }

            if (!empty($original_params) && $key == 'OMDT') {
                unset($original_params[$original_key]);
            }
            if (!empty($original_params) && $key == 'OCDT') {
                unset($original_params[$original_key]);
            }

        }

        $where_str = '';
        if (!empty($where)) {
            $where_str = ' WHERE ';
            forEach ($where as $key => $value) {

                $val = array();
                forEach ($values as $i => $v) {
                    //if( !empty($v["value"]) && $v["value"] == 'NULL' ){ $val = &$values[$i]; break; }
                }

                if (!empty($val) && $val["value"] == 'NULL') {

                    if ($value['operator'] === '=') {
                        $where_str .= ' ' . $value['join'] . ' ' . $value['key'] . ' IS NULL ';
                    } else {
                        if ($value['operator'] === '!=') {
                            $where_str .= ' ' . $value['join'] . ' ' . $value['key'] . ' IS NOT NULL ';
                        }
                    }
                } else {
                    $where_str .= ' ' . $value['join'] . ' ' . $value['key'] . ' ' . $value['operator'] . ' ' . $value['value'] . ' ';
                }
                //if( $value['operator'] == '!=' ){ $where_str .= ' OR '.$value['key'].' IS NULL '; }
            }
        }
        return $where_str;

    }

    /********************************************************************
     *
     * DUMP
     ********************************************************************/

    public function dump($params = array())
    {

        //exec('mysqldump --user='.__OBRAY_DATABASE_USERNAME__.' --password='.__OBRAY_DATABASE_PASSWORD__.' --host='.__OBRAY_DATABASE_HOST__.' '.__OBRAY_DATABASE_NAME__.' '.$this->table.' | gzip > '.dirname(__FILE__).'backups/'.$this->table.'-'.time().'.sql.gz');

    }

    /********************************************************************
     *
     * GETDATATYPE
     ********************************************************************/

    private function getDataType($def)
    {
        if (!isSet($def['data_type'])) {
            return false;
        }                                                   // make sure datatype is set
        $data_type = explode('(',
            $def['data_type']);                                                       // explode datatypes that contain a size i.e. varchar(255)
        if (!isSet($data_type[1])) {
            $data_type[1] = '';
        }                                                 // if size is used then extract it
        $data_type[1] = str_replace(')', '',
            $data_type[1]);                                                   // remove extra ')' and extract data type
        return array(
            'data_type' => $data_type[0],
            'size' => $data_type[1]
        );                                       // return datatype with size
    }

    /********************************************************************
     *
     * GETSLUG
     ********************************************************************/

    private function getSlug($slug, $column, $parent)
    {
        $count = 1;
        $i = 0;
        while ($count > 0) {
            $new_slug = $slug;
            if ($i == 0) {
                $appendage = '';
            } else {
                $appendage = ' ' . $i;
            }
            $params = array(
                'slug' => strtolower(removeSpecialChars(str_replace('-' . ($i - 1), '', $new_slug) . $appendage, '-',
                    'and'))
            );
            if (!empty($parent) && isSet($this->parent_column)) {
                $parent_sql = ' AND ' . $this->parent_column . ' = :' . $this->parent_column . ' ';
                $params[$this->parent_column] = $parent;
            } else {
                $parent_sql = '';
            }
            $sql = ' SELECT ' . $column . ' FROM ' . $this->table . ' WHERE ' . $this->slug_value_column . ' = :slug ' . $parent_sql . ' ';
            $statement = $this->oDBOConnection->prepare($sql);
            $statement->execute($params);
            $count = count($statement->fetchAll());
            ++$i;
        }
        return $params['slug'];

    }

    /********************************************************************
     *
     * SORT
     ********************************************************************/

    public function sort($column, $order = 'asc', $with = null, $query = '')
    {

        parse_str($query, $this->params);
        $this->column = $column;
        $this->order = $order;
        if (empty($with)) {
            $this->with = array();
        } else {
            $this->with = explode('|', $with);
        }

        usort($this->data, array($this, 'sortCallback'));

        return $this;
    }

    private function sortCallback($a, $b)
    {

        $column = $this->column;
        $filters = array();

        $with_array = $this->with;
        if (!empty($this->with)) {

            $with = array_shift($with_array);

            if (empty($a->$with) || empty($b->$with)) {
                return false;
            }
            $filters_a = $a->$with;
            $filters_b = $b->$with;

            $final_a = new stdClass();
            forEach ($filters_a as $a) {
                forEach ($this->with as $i => $with) {
                    if (!empty($a->$with)) {
                        forEach ($a->$with as $a_item) {
                            forEach ($this->params as $key => $value) {
                                if (!empty($a_item->$key) && $a_item->$key == $value) {
                                    $final_a = $a_item;
                                }
                            }
                        }
                    }
                }
            }

            $final_b = new stdClass();
            forEach ($filters_b as $b) {
                forEach ($this->with as $i => $with) {
                    if (!empty($b->$with)) {
                        forEach ($b->$with as $b_item) {
                            forEach ($this->params as $key => $value) {
                                if (!empty($b_item->$key) && $b_item->$key == $value) {
                                    $final_b = $b_item;
                                }
                            }
                        }
                    }
                }
            }

        }

        if (empty($final_a->$column)) {
            return false;
        }
        if (empty($final_b->$column)) {
            return true;
        }

        $a = $final_a->$column;
        $b = $final_b->$column;

        switch ($this->order) {
            case 'asc':
            case 'ASC':
                if ($a > $b) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 'desc':
            case 'DESC':
                if ($a < $b) {
                    return true;
                } else {
                    return false;
                }
                break;
        }

    }

    /********************************************************************
     *
     * GETFIRST
     ********************************************************************/

    public function getFirst()
    {
        if (empty($this->errors)) {
            if (!isSet($this->data) || !is_array($this->data)) {
                $this->data = array();
            }
            forEach ($this->data as $i => $data) {
                $v = &$this->data[$i];
                return $v;
            }
            return reset($this->data);
        } else {
            return 0;
        }
    }

    public function run($sql, $bind = [], $fetchStyle = \PDO::FETCH_OBJ)
    {
        return $this->data = $this->oDBOConnection->run($sql, $bind, $fetchStyle);
    }

    public function beginStatement($sql)
    {
        return $this->oDBOConnection->beginStatement($sql);
    }

    public function explain($sql)
    {

        $this->console('EXPLAIN ' . $sql);

        try {

            $result = $this->oDBOConnection->query('EXPLAIN ' . $sql);
            forEach ($result as $r) {
                $this->console($r);
            }

        } catch (Exception $e) {
            if (isset($this->is_transaction) && $this->is_transaction) {
                $this->rollbackTransaction();
            }
            $this->throwError($e);
            $this->logError(oCoreProjectEnum::ODBO, $e);
        }

        return $this;
    }

    /********************************************************************
     *
     * runStoredProc
     ********************************************************************/

   

    /********************************************************************
     *
     * COUNT
     ********************************************************************/

    public function count($params = array())
    {

        $values = array();
        $where_str = $this->getWhere($params, $values);
        $this->sql = 'SELECT COUNT(*) AS count FROM ' . $this->table . ' ' . $where_str;
        $statement = $this->oDBOConnection->prepare($this->sql);
        forEach ($values as $value) {
            if (is_integer($value)) {
                $statement->bindValue($value['key'], trim($value['value']), \PDO::PARAM_INT);
            } else {
                $statement->bindValue($value['key'], trim((string)$value['value']), \PDO::PARAM_STR);
            }
        }
        $statement->execute();
        while ($row = $statement->fetch()) {
            $this->data[] = $row;
        }
        $this->data = $this->data[0];
        unset($this->data[0]);
        return $this;

    }

    /********************************************************************
     *
     * RAND
     ********************************************************************/

    public function random($params = array())
    {

        if (!empty($params['rows']) && is_numeric($params['rows'])) {
            $rows = $params['rows'];
        } else {
            $rows = 1;
        }
        $values = array();
        $where_str = $this->getWhere($params, $values);
        $statement = $this->oDBOConnection->prepare('SELECT * FROM ' . $this->table . ' ' . $where_str . ' ORDER BY RAND() LIMIT ' . $rows);
        forEach ($values as $value) {
            if (is_integer($value)) {
                $statement->bindValue($value['key'], trim($value['value']), \PDO::PARAM_INT);
            } else {
                $statement->bindValue($value['key'], trim((string)$value['value']), \PDO::PARAM_STR);
            }
        }
        $statement->execute();
        $statement->setFetchMode(\PDO::FETCH_NUM);
        $this->data = $statement->fetchAll(\PDO::FETCH_OBJ);
        return $this;

    }

    /********************************************************************
     *
     * MATH FUNCTIONS
     ********************************************************************/

    public function sum($params = array())
    {
        $this->math('SUM', 'sum', $params);
    }

    public function average($params = array())
    {
        $this->math('AVG', 'average', $params);
    }

    public function maximum($params = array())
    {
        $this->math('MAX', 'maximum', $params);
    }

    public function minimum($params = array())
    {
        $this->math('MIN', 'minimum', $params);
    }

    public function truncate()
    {
        $statement = $this->oDBOConnection->prepare('TRUNCATE TABLE ' . $this->table);
        $statement->execute();
    }

    private function math($fn, $key, $params = array())
    {

        $column = $params['column'];
        unset($params['column']);
        if (array_key_exists($column, $this->table_definition)) {
            $values = array();
            $where_str = $this->getWhere($params, $values);
            $statement = $this->oDBOConnection->prepare('SELECT ' . $fn . '(' . $column . ') as ' . $key . ' FROM ' . $this->table . ' ' . $where_str);
            forEach ($values as $value) {
                if (is_integer($value)) {
                    $statement->bindValue($value['key'], trim($value['value']), \PDO::PARAM_INT);
                } else {
                    $statement->bindValue($value['key'], trim((string)$value['value']), \PDO::PARAM_STR);
                }
            }
            $statement->execute();
            while ($row = $statement->fetch()) {
                $this->data[] = $row;
            }
            $this->data = $this->data[0];
            unset($this->data[0]);
            return $this;
        } else {
            $this->throwError('Column does not exist.');
        }

    }

    /********************************************************************
     *
     * UNIQUE
     ********************************************************************/

    public function unique($params = array())
    {

        $column = $params['column'];
        unset($params['column']);

        if (array_key_exists($column, $this->table_definition)) {
            $values = array();
            $where_str = $this->getWhere($params, $values);
            $statement = $this->oDBOConnection->prepare('SELECT DISTINCT ' . $column . ' FROM ' . $this->table . ' ' . $where_str);
            forEach ($values as $value) {
                if (is_integer($value)) {
                    $statement->bindValue($value['key'], trim($value['value']), \PDO::PARAM_INT);
                } else {
                    $statement->bindValue($value['key'], trim((string)$value['value']), \PDO::PARAM_STR);
                }
            }
            $statement->execute();
            while ($row = $statement->fetch()) {
                $this->data[] = $row[$column];
            }
            return $this;
        } else {
            $this->throwError('Column does not exist.');
        }

    }

    /********************************************************************
     *
     * LOG
     ********************************************************************/

    protected function log($object, $label = null)
    {

        if (__OBRAY_DEBUG_MODE__) {
            $sql = 'CREATE TABLE IF NOT EXISTS ologs ( olog_id INT UNSIGNED NOT NULL AUTO_INCREMENT,olog_label VARCHAR(255),olog_data TEXT,OCDT DATETIME,OCU INT UNSIGNED, PRIMARY KEY (olog_id) ) ENGINE=' . __OBRAY_DATABASE_ENGINE__ . ' DEFAULT CHARSET=' . __OBRAY_DATABASE_CHARACTER_SET__ . '; ';
            $statement = $this->oDBOConnection->prepare($sql);
            $statement->execute();
        }

        $sql = 'INSERT INTO ologs(olog_label,olog_data,OCDT,OCU) VALUES(:olog_label,:olog_data,:OCDT,:OCU);';
        $statement = $this->oDBOConnection->prepare($sql);
        $statement->bindValue('olog_label', $label, \PDO::PARAM_STR);
        $statement->bindValue('olog_data', json_encode($object, JSON_PRETTY_PRINT), \PDO::PARAM_STR);
        $statement->bindValue('OCDT', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $statement->bindValue('OCU', isSet($_SESSION['ouser']->ouser_id) ? $_SESSION['ouser']->ouser_id : 0,
            \PDO::PARAM_INT);
        $statement->execute();

    }

    /********************************************************************
     *
     * GENERATETOKEN
     ********************************************************************/

    private function generateToken()
    {
        $safe = false;
        return hash('sha512', base64_encode(openssl_random_pseudo_bytes(128, $safe)));
    }

}

?>
<?php
require_once('config.php');
require_once('libs/exception.php');
class AlfaDB
{
    function __construct($dbuser, $dbpassword, $dbname, $dbhost)
    {
        $this->dbh = @mysql_connect($dbhost, $dbuser, $dbpassword, true);
        if (!$this->dbh)
            throw new EDBException();
        mysql_set_charset('utf8', $this->dbh);
        $this->Select($dbname);
    }

    function Select($db)
    {
        if (!@mysql_select_db($db, $this->dbh))
            throw new EDBException();
    }

    function Query($query)
    {
        if (!$result = mysql_query($query, $this->dbh))
        {
            throw new E_QueryException($query);
            //if (DEBUG_MODE == true)
                //die('Query failed: '.$query.' ('.mysql_error().')');
            //else
            //{
                //ReportError('Query failed: '.$query.' ('.mysql_error().')');
                //throw new Exception('An Database error occured, please notify your admin of this problem.');
            //}
        }
        return $result;
    }

    /**************************************************************************
    * Returns all records in an array using the supplied query
    *
    * @param mixed $query
    * @param mixed $key
    * @return mixed
    */
    function LoadAll($query, $key = null)
    {
        $data = array();
        if ($r = $this->Query($query))
        {
            if ($key == null)
            {
                while ($row = mysql_fetch_assoc($r))
                    $data[] = $row;
            }
            else
            {
                while ($row = mysql_fetch_assoc($r))
                    $data[$row[$key]] = $row;
            }
            mysql_free_result($r);
        }
        return $data;
    }

    /**************************************************************************
    * Returns a single record in an array using the supplied query
    *
    * @param mixed $query
    * @return mixed
    */
    function LoadSingle($query)
    {
        if ($data = $this->LoadAll($query))
            return $data[0];
    }

    function Update($conditions, $table, $fields)
    {
        //global $config;
        //$safeItemID = $this->escape($itemID);
        //$table = 'table_'.$table;
        $table_string = $table;//$config->$table;
        $query = "UPDATE {$table_string} SET ";
        foreach ($fields as $field => $value)
        {
            $safeField = $this->escape($field);
            $safeValue = $this->escape($value);
            $query .= "{$safeField} = '{$safeValue}', ";
        }
        $query = trim($query, ', ');
        if ((is_array($conditions)) and (!empty($conditions)))
        {
            $query .= ' WHERE ';
            foreach ($conditions as $field => $value)
            {
                $safeField = $this->escape($field);
                $safeValue = $this->escape($value);
                $query .= "{$safeField} = '{$safeValue}' AND ";
            }
            $query = rtrim($query, ' AND ');
        }
        else
            $query .= "WHERE id = '{$this->escape($conditions)}'";
        //var_dump($query);
        $this->Query($query);
    }

    //TODO: Allow multiple vars to be cleaned
    //TODO: Also prevent xss by stripping tags(strip_tags)
    function escape($string)
    {
        if ($this->dbh)
            return mysql_real_escape_string($string, $this->dbh);
        else
            return addslashes($string);
    }

    function DeleteRow($conditions, $table)
    {
        //global $config;
        assert(((is_array($conditions)) and (!empty($conditions))));
        //$table = 'table_'.$table;
        $table_string = $table;//$config->$table;
        $query = "DELETE FROM {$table_string} WHERE ";
        foreach ($conditions as $field => $value)
        {
            $safeField = $this->escape($field);
            $safeValue = $this->escape($value);
            $query .= "{$safeField} = '{$safeValue}' AND ";
        }

        $query = rtrim($query, 'AND ');
        $this->Query($query);
    }

    function InsertRow($table, $values)
    {
        //global $config;
        assert(((is_array($values)) and (!empty($values))));
        //$table = 'table_'.$table;
        $table_string = $table;//$config->$table;
        $query = "INSERT INTO {$table_string} SET ";
        foreach ($values as $field => $value)
        {
            $safeField = $this->escape($field);
            $safeValue = $this->escape($value);
            $query .= "{$safeField} = '{$safeValue}', ";
        }
        $query = trim($query, ', ');
        $this->Query($query);
        return($this->GetLastInsertID());
    }

    function GetLastInsertID()
    {
        return(mysql_insert_id($this->dbh));
    }

    function LoadRow($table, $conditions, $fields = '*')
    {
        /*global $config;
        //$safeItemID = $this->escape($itemID);
        $table = 'table_'.$table;
        $table_string = $config->$table;
        if (is_array($fields))
        {
            $field_string = '';
            foreach ($fields as $value)
            {
                $safeValue = $this->escape($value);
                $field_string .= $safeValue.', ';
            }
            $field_string = rtrim($field_string, ',');
        }
        else
            $field_string = '*';
        $query = "SELECT {$field_string} {$table_string} WHERE ";
        if ((is_array($conditions)) and (!empty($conditions)))
        {
            foreach ($conditions as $field => $value)
            {
                $safeField = $this->escape($field);
                $safeValue = $this->escape($value);
                $query .= "{$safeField} = '{$safeValue}' AND ";
            }
            $query = rtrim($query, ' AND ');
        }
        else
            $query .= "id = '{$this->escape($conditions)}'";
        //var_dump($query);*/
        $query = $this->CreateQuery($table, $conditions, $fields);
        return($this->LoadSingle($query.' LIMIT 1'));
    }

    protected function CreateQuery($table, $conditions, $fields)
    {
        //global $config;
        //$safeItemID = $this->escape($itemID);
        //$table = 'table_'.$table;
        $table_string = $table;//$config->$table;
        if (is_array($fields))
        {
            $field_string = '';
            foreach ($fields as $value)
            {
                $safeValue = $this->escape($value);
                $field_string .= $safeValue.', ';
            }
            $field_string = rtrim($field_string, ',');
        }
        else
            $field_string = '*';
        $query = "SELECT {$field_string} FROM {$table_string} WHERE ";
        if ((is_array($conditions)) and (!empty($conditions)))
        {
            foreach ($conditions as $field => $value)
            {
                $safeField = $this->escape($field);
                $safeValue = $this->escape($value);
                $query .= "{$safeField} = '{$safeValue}' AND ";
            }
            $query = rtrim($query, ' AND ');
        }
        else
            $query .= "id = '{$this->escape($conditions)}'";
        return($query);
    }

    function LoadAllRows($table, $conditions, $fields = '*')
    {
        $query = $this->CreateQuery($table, $conditions, $fields);
        return($this->LoadAll($query));
    }
}

class E_QueryException extends E_ServerError
{
 	function __construct($query)
 	{
 		if (DEBUG == 'TRUE')
 	 		parent::__construct($query);
 	 	else
 	 		parent::__construct('An database error occured');
	}
}

if (!isset($AlfaDB))
{
    $AlfaDB = new AlfaDB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
}
?>
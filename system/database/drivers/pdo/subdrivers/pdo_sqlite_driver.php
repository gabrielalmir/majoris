<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2019 - 2022, CodeIgniter Foundation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc. (https://ellislab.com/)
 * @copyright	Copyright (c) 2014 - 2019, British Columbia Institute of Technology (https://bcit.ca/)
 * @copyright	Copyright (c) 2019 - 2022, CodeIgniter Foundation (https://codeigniter.com/)
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	https://codeigniter.com
 * @since	Version 3.0.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PDO SQLite Database Adapter Class
 *
 * Note: _DB is an extender class that the app controller
 * creates dynamically based on whether the query builder
 * class is being used or not.
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/userguide3/database/
 */
class CI_DB_pdo_sqlite_driver extends CI_DB_pdo_driver {

	/**
	 * Sub-driver
	 *
	 * @var	string
	 */
	public $subdriver = 'sqlite';

	/**
	 * Busy timeout, in milliseconds
	 *
	 * 0 leaves the connection default of pdo_sqlite.
	 *
	 * @var	int|string
	 */
	public $busy_timeout = 0;

	/**
	 * Run PRAGMA journal_mode=WAL after connecting
	 *
	 * @var	bool
	 */
	public $wal = FALSE;

	/**
	 * Run PRAGMA foreign_keys=ON after connecting
	 *
	 * @var	bool
	 */
	public $foreign_keys = FALSE;

	/**
	 * Why the last db_connect() failed
	 *
	 * @var	string|null
	 */
	protected $_connect_error;

	// --------------------------------------------------------------------

	/**
	 * ORDER BY random keyword
	 *
	 * @var	array
	 */
	protected $_random_keyword = array('RANDOM()', 'RANDOM()');

	// --------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * Builds the DSN if not already set.
	 *
	 * @param	array	$params
	 * @return	void
	 */
	public function __construct($params)
	{
		parent::__construct($params);

		if (empty($this->dsn))
		{
			$this->dsn = 'sqlite:';

			if (empty($this->database) && empty($this->hostname))
			{
				$this->database = ':memory:';
			}

			$this->database = empty($this->database) ? $this->hostname : $this->database;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Database connection
	 *
	 * @param	bool	$persistent
	 * @return	object|bool
	 */
	public function db_connect($persistent = FALSE)
	{
		$this->_connect_error = NULL;

		try
		{
			$pragmas = $this->_connect_pragmas();
		}
		catch (InvalidArgumentException $e)
		{
			$this->_connect_error = $e->getMessage();
			return FALSE;
		}

		$conn_id = parent::db_connect($persistent);
		if ( ! $conn_id instanceof PDO)
		{
			return $conn_id;
		}

		try
		{
			$this->_run_pragmas($conn_id, $pragmas);
			return $conn_id;
		}
		catch (RuntimeException $e)
		{
			$this->_connect_error = $e->getMessage();
			return FALSE;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Connection PRAGMAs
	 *
	 * Validates busy_timeout, wal and foreign_keys and returns the
	 * statements to run after connecting, each mapped to the value
	 * it must return (NULL when any is fine). The defaults return none.
	 *
	 * PRAGMA busy_timeout is used instead of PDO::ATTR_TIMEOUT,
	 * which pdo_sqlite reads as seconds.
	 *
	 * @return	array
	 * @throws	InvalidArgumentException	On an invalid setting
	 */
	protected function _connect_pragmas()
	{
		$pragmas = array();

		$busy_timeout = $this->busy_timeout;
		if (is_string($busy_timeout) && preg_match('/^[0-9]{1,10}$/', $busy_timeout))
		{
			$busy_timeout = (int) $busy_timeout;
		}

		if ( ! is_int($busy_timeout) OR $busy_timeout < 0 OR $busy_timeout > 2147483647)
		{
			throw new InvalidArgumentException("Invalid 'busy_timeout' setting: expected an integer number of milliseconds from 0 to 2147483647, got ".var_export($this->busy_timeout, TRUE).'.');
		}
		elseif ($busy_timeout > 0)
		{
			$pragmas['PRAGMA busy_timeout = '.$busy_timeout] = NULL;
		}

		foreach (array('wal' => 'PRAGMA journal_mode = WAL', 'foreign_keys' => 'PRAGMA foreign_keys = ON') as $key => $sql)
		{
			if ( ! in_array($this->$key, array(TRUE, FALSE, 1, 0, '1', '0'), TRUE))
			{
				throw new InvalidArgumentException("Invalid '".$key."' setting: expected TRUE or FALSE, got ".var_export($this->$key, TRUE).'.');
			}
			elseif ((bool) $this->$key)
			{
				$pragmas[$sql] = ($key === 'wal') ? 'wal' : NULL;
			}
		}

		return $pragmas;
	}

	// --------------------------------------------------------------------

	/**
	 * Run the connection PRAGMAs
	 *
	 * @param	PDO	$conn_id
	 * @param	array	$pragmas	From _connect_pragmas()
	 * @return	void
	 * @throws	RuntimeException	When a PRAGMA fails
	 */
	protected function _run_pragmas($conn_id, $pragmas)
	{
		foreach ($pragmas as $sql => $expected)
		{
			if (($statement = $conn_id->query($sql)) === FALSE)
			{
				$error = $conn_id->errorInfo();
				throw new RuntimeException($sql.' failed: '.(isset($error[2]) ? $error[2] : $error[0]));
			}

			$result = $statement->fetchColumn();
			// A PRAGMA that returns a row keeps its statement active until the cursor is closed
			$statement->closeCursor();

			if ($expected !== NULL && strtolower((string) $result) !== $expected)
			{
				throw new RuntimeException($sql.' failed: journal_mode is '.var_export($result, TRUE).'.');
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Error
	 *
	 * Returns an array containing code and message of the last
	 * database error that has occurred.
	 *
	 * @return	array
	 */
	public function error()
	{
		if (isset($this->_connect_error))
		{
			return array('code' => 'HY000', 'message' => $this->_connect_error);
		}

		return parent::error();
	}

	// --------------------------------------------------------------------

	/**
	 * Connection error message
	 *
	 * @return	string
	 */
	protected function _connect_error_message()
	{
		return isset($this->_connect_error) ? $this->_connect_error : '';
	}

	// --------------------------------------------------------------------

	/**
	 * Show table query
	 *
	 * Generates a platform-specific query string so that the table names can be fetched
	 *
	 * @param	bool	$prefix_limit
	 * @return	string
	 */
	protected function _list_tables($prefix_limit = FALSE)
	{
		$sql = 'SELECT "NAME" FROM "SQLITE_MASTER" WHERE "TYPE" = \'table\'';

		if ($prefix_limit === TRUE && $this->dbprefix !== '')
		{
			return $sql.' AND "NAME" LIKE \''.$this->escape_like_str($this->dbprefix)."%' "
				.sprintf($this->_like_escape_str, $this->_like_escape_chr);
		}

		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch Field Names
	 *
	 * @param	string	$table	Table name
	 * @return	array
	 */
	public function list_fields($table)
	{
		if (($result = $this->query('PRAGMA TABLE_INFO('.$this->protect_identifiers($table, TRUE, NULL, FALSE).')')) === FALSE)
		{
			return FALSE;
		}

		$fields = array();
		foreach ($result->result_array() as $row)
		{
			$fields[] = $row['name'];
		}

		return $fields;
	}

	// --------------------------------------------------------------------

	/**
	 * Returns an object with field data
	 *
	 * @param	string	$table
	 * @return	array
	 */
	public function field_data($table)
	{
		if (($query = $this->query('PRAGMA TABLE_INFO('.$this->protect_identifiers($table, TRUE, NULL, FALSE).')')) === FALSE)
		{
			return FALSE;
		}

		$query = $query->result_array();
		if (empty($query))
		{
			return FALSE;
		}

		$retval = array();
		for ($i = 0, $c = count($query); $i < $c; $i++)
		{
			$retval[$i]			= new stdClass();
			$retval[$i]->name		= $query[$i]['name'];
			$retval[$i]->type		= $query[$i]['type'];
			$retval[$i]->max_length		= NULL;
			$retval[$i]->default		= $query[$i]['dflt_value'];
			$retval[$i]->primary_key	= isset($query[$i]['pk']) ? (int) $query[$i]['pk'] : 0;
		}

		return $retval;
	}

	// --------------------------------------------------------------------

	/**
	 * Replace statement
	 *
	 * @param	string	$table	Table name
	 * @param	array	$keys	INSERT keys
	 * @param	array	$values	INSERT values
	 * @return 	string
	 */
	protected function _replace($table, $keys, $values)
	{
		return 'INSERT OR '.parent::_replace($table, $keys, $values);
	}

	// --------------------------------------------------------------------

	/**
	 * Truncate statement
	 *
	 * Generates a platform-specific truncate string from the supplied data
	 *
	 * If the database does not support the TRUNCATE statement,
	 * then this method maps to 'DELETE FROM table'
	 *
	 * @param	string	$table
	 * @return	string
	 */
	protected function _truncate($table)
	{
		return 'DELETE FROM '.$table;
	}

}

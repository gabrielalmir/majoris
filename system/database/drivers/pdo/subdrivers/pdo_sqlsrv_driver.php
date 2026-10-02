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
 * PDO SQLSRV Database Adapter Class
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
class CI_DB_pdo_sqlsrv_driver extends CI_DB_pdo_driver {

	/**
	 * Sub-driver
	 *
	 * @var	string
	 */
	public $subdriver = 'sqlsrv';

	/**
	 * TrustServerCertificate DSN option
	 *
	 * Alias of the TrustServerCertificate setting; sent only when set.
	 *
	 * @var	mixed
	 */
	public $trust_server_certificate;

	/**
	 * LoginTimeout DSN option, in seconds
	 *
	 * Alias of the LoginTimeout setting; sent only when set and numeric.
	 *
	 * @var	int|string|null
	 */
	public $login_timeout;

	// --------------------------------------------------------------------

	/**
	 * Connection settings error
	 *
	 * Set when the DSN can't be built from an invalid setting,
	 * reported by error().
	 *
	 * @var	string|null
	 */
	protected $_connect_error;

	/**
	 * SCOPE_IDENTITY() read in the batch of the last INSERT
	 *
	 * @var	string|int|null
	 */
	protected $_insert_id;

	/**
	 * Statement of the last INSERT batch
	 *
	 * @var	PDOStatement|null
	 */
	protected $_insert_stmt;

	/**
	 * Rows affected by the INSERT of that batch
	 *
	 * @var	int
	 */
	protected $_insert_affected_rows = 0;

	/**
	 * Statement error of the last INSERT batch
	 *
	 * PDO::errorInfo() doesn't see errors raised while moving
	 * through the rowsets of a statement.
	 *
	 * @var	array|null
	 */
	protected $_insert_error;

	/**
	 * ORDER BY random keyword
	 *
	 * @var	array
	 */
	protected $_random_keyword = array('NEWID()', 'RAND(%d)');

	/**
	 * Quoted identifier flag
	 *
	 * Whether to use SQL-92 standard quoted identifier
	 * (double quotes) or brackets for identifier escaping.
	 *
	 * @var	bool
	 */
	protected $_quoted_identifier;

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
			$this->dsn = 'sqlsrv:Server='.(empty($this->hostname) ? '127.0.0.1' : $this->hostname);

			empty($this->port) OR $this->dsn .= ','.$this->port;
			empty($this->database) OR $this->dsn .= ';Database='.$this->database;

			// Some custom options

			if (isset($this->QuotedId))
			{
				$this->dsn .= ';QuotedId='.$this->QuotedId;
				$this->_quoted_identifier = (bool) $this->QuotedId;
			}

			if (isset($this->ConnectionPooling))
			{
				$this->dsn .= ';ConnectionPooling='.$this->ConnectionPooling;
			}

			if (($encrypt = $this->_encrypt_option()) === FALSE)
			{
				$this->_connect_error = "Invalid 'encrypt' setting: expected TRUE, FALSE, 'yes', 'no', 'strict' or 'optional'.";
			}
			else
			{
				$this->dsn .= ';Encrypt='.$encrypt;
			}

			if (isset($this->TraceOn))
			{
				$this->dsn .= ';TraceOn='.$this->TraceOn;
			}

			if (isset($this->trust_server_certificate))
			{
				$this->dsn .= ';TrustServerCertificate='.(is_bool($this->trust_server_certificate) ? (int) $this->trust_server_certificate : $this->trust_server_certificate);
			}
			elseif (isset($this->TrustServerCertificate))
			{
				$this->dsn .= ';TrustServerCertificate='.$this->TrustServerCertificate;
			}

			empty($this->APP) OR $this->dsn .= ';APP='.$this->APP;
			empty($this->Failover_Partner) OR $this->dsn .= ';Failover_Partner='.$this->Failover_Partner;

			if (isset($this->login_timeout) && is_numeric($this->login_timeout))
			{
				$this->dsn .= ';LoginTimeout='.(int) $this->login_timeout;
			}
			else
			{
				empty($this->LoginTimeout) OR $this->dsn .= ';LoginTimeout='.$this->LoginTimeout;
			}

			empty($this->MultipleActiveResultSets) OR $this->dsn .= ';MultipleActiveResultSets='.$this->MultipleActiveResultSets;
			empty($this->TraceFile) OR $this->dsn .= ';TraceFile='.$this->TraceFile;
			empty($this->WSID) OR $this->dsn .= ';WSID='.$this->WSID;
		}
		elseif (preg_match('/QuotedId=(0|1)/', $this->dsn, $match))
		{
			$this->_quoted_identifier = (bool) $match[1];
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
		if (isset($this->_connect_error))
		{
			return FALSE;
		}

		if ( ! empty($this->char_set) && preg_match('/utf[^8]*8/i', $this->char_set))
		{
			$this->options[PDO::SQLSRV_ENCODING_UTF8] = 1;
		}

		$this->conn_id = parent::db_connect($persistent);

		if ( ! is_object($this->conn_id) OR is_bool($this->_quoted_identifier))
		{
			return $this->conn_id;
		}

		// Determine how identifiers are escaped
		$query = $this->query('SELECT CASE WHEN (@@OPTIONS | 256) = @@OPTIONS THEN 1 ELSE 0 END AS qi');
		$query = $query->row_array();
		$this->_quoted_identifier = empty($query) ? FALSE : (bool) $query['qi'];
		$this->_escape_char = ($this->_quoted_identifier) ? '"' : array('[', ']');

		return $this->conn_id;
	}

	// --------------------------------------------------------------------

	/**
	 * Encrypt option
	 *
	 * Maps the 'encrypt' setting to the text value ODBC Driver 18 expects.
	 *
	 * @return	string|bool	'yes', 'no', 'strict' or 'optional'; FALSE if the setting is invalid
	 */
	protected function _encrypt_option()
	{
		if (is_bool($this->encrypt))
		{
			return $this->encrypt ? 'yes' : 'no';
		}

		if (is_string($this->encrypt))
		{
			$value = strtolower($this->encrypt);

			if (in_array($value, array('yes', 'no', 'strict', 'optional'), TRUE))
			{
				return $value;
			}

			// '0', '1' and '' used to take the same path as FALSE: only boolean TRUE encrypted.
			if ($value === '0' OR $value === '1' OR $value === '')
			{
				return 'no';
			}

			return FALSE;
		}

		if ($this->encrypt === NULL OR $this->encrypt === 0 OR $this->encrypt === 1)
		{
			return 'no';
		}

		return FALSE;
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
			return array('code' => 'IMSSP', 'message' => $this->_connect_error);
		}

		if (isset($this->_insert_error[0]))
		{
			return array(
				'code' => isset($this->_insert_error[1]) ? $this->_insert_error[0].'/'.$this->_insert_error[1] : $this->_insert_error[0],
				'message' => isset($this->_insert_error[2]) ? $this->_insert_error[2] : ''
			);
		}

		return parent::error();
	}

	// --------------------------------------------------------------------

	/**
	 * Execute the query
	 *
	 * @param	string	$sql	SQL query
	 * @return	mixed
	 */
	protected function _execute($sql)
	{
		$this->_insert_error = NULL;

		if (preg_match('/^\s*INSERT\s/i', $sql))
		{
			$this->_insert_id = NULL;
			$this->_insert_stmt = NULL;

			if (($batch = $this->_insert_id_batch($sql)) !== FALSE)
			{
				$stmt = FALSE;
				if (($result = $this->_execute_insert_batch($batch)) !== FALSE)
				{
					list($stmt, $this->_insert_affected_rows, $this->_insert_id) = $result;
					$this->_insert_stmt = $stmt;
				}

				return $stmt;
			}
		}

		return parent::_execute($sql);
	}

	// --------------------------------------------------------------------

	/**
	 * INSERT batch with SCOPE_IDENTITY()
	 *
	 * SCOPE_IDENTITY() is NULL in any batch other than the INSERT's,
	 * so the SELECT is appended to the INSERT itself.
	 *
	 * Only a single INSERT statement qualifies: one with OUTPUT already
	 * returns a result set, INSERT ... EXEC runs a procedure, and a batch
	 * written with more than one statement is left as the caller wrote it.
	 *
	 * @param	string	$sql
	 * @return	string|bool	FALSE if $sql doesn't qualify
	 */
	protected function _insert_id_batch($sql)
	{
		// Blank out comments, and replace string literals and quoted
		// identifiers with a placeholder of the same length, so that
		// only keywords and statement separators are left to look at.
		$code = preg_replace_callback(
			'#--[^\n]*+|/\*.*?\*/|\'[^\']*+(?:\'\'[^\']*+)*+\'|"[^"]*+(?:""[^"]*+)*+"|\[[^\]]*+(?:\]\][^\]]*+)*+\]#s',
			function ($match)
			{
				return str_repeat(($match[0][0] === '-' OR $match[0][0] === '/') ? ' ' : '?', strlen($match[0]));
			},
			$sql
		);

		if ( ! is_string($code) OR ! preg_match('/^\s*INSERT\s/i', $code))
		{
			return FALSE;
		}

		$code = rtrim($code);
		if (substr($code, -1) === ';')
		{
			$code = rtrim(substr($code, 0, -1));
		}

		if (strpos($code, ';') !== FALSE OR preg_match('/\b(OUTPUT|EXEC|EXECUTE)\b/i', $code))
		{
			return FALSE;
		}

		return substr($sql, 0, strlen($code)).'; SELECT SCOPE_IDENTITY() AS insert_id';
	}

	// --------------------------------------------------------------------

	/**
	 * Execute an INSERT batch built by _insert_id_batch()
	 *
	 * Row counts come before the SELECT: the INSERT's, preceded by those
	 * of any trigger that doesn't SET NOCOUNT ON. The count read last,
	 * right before the SELECT, is the INSERT's.
	 *
	 * @param	string	$batch
	 * @return	array|bool	Statement, affected rows and insert ID; FALSE on failure
	 */
	protected function _execute_insert_batch($batch)
	{
		if (($stmt = parent::_execute($batch)) === FALSE)
		{
			return FALSE;
		}

		$affected_rows = -1;
		while ($stmt->columnCount() === 0)
		{
			$affected_rows = $stmt->rowCount();

			if ( ! $stmt->nextRowset())
			{
				$this->_insert_error = $stmt->errorInfo();
				return FALSE;
			}
		}

		$insert_id = $stmt->fetchColumn();
		if ($insert_id === FALSE && $stmt->errorCode() !== '00000')
		{
			$this->_insert_error = $stmt->errorInfo();
			return FALSE;
		}

		$stmt->closeCursor();
		return array($stmt, $affected_rows, ($insert_id === FALSE) ? NULL : $insert_id);
	}

	// --------------------------------------------------------------------

	/**
	 * Affected Rows
	 *
	 * @return	int
	 */
	public function affected_rows()
	{
		if (isset($this->_insert_stmt) && $this->result_id === $this->_insert_stmt)
		{
			return $this->_insert_affected_rows;
		}

		return parent::affected_rows();
	}

	// --------------------------------------------------------------------

	/**
	 * Insert ID
	 *
	 * Returns the SCOPE_IDENTITY() read in the same batch as the last
	 * INSERT. A sequence name, or an INSERT that didn't qualify for the
	 * batch (see _insert_id_batch()), goes to PDO::lastInsertId() as before.
	 *
	 * @param	string	$name
	 * @return	int
	 */
	public function insert_id($name = NULL)
	{
		if ($name === NULL && isset($this->_insert_stmt))
		{
			return $this->_insert_id;
		}

		return parent::insert_id($name);
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
		$sql = 'SELECT '.$this->escape_identifiers('name')
			.' FROM '.$this->escape_identifiers('sysobjects')
			.' WHERE '.$this->escape_identifiers('type')." = 'U'";

		if ($prefix_limit === TRUE && $this->dbprefix !== '')
		{
			$sql .= ' AND '.$this->escape_identifiers('name')." LIKE '".$this->escape_like_str($this->dbprefix)."%' "
				.sprintf($this->_like_escape_str, $this->_like_escape_chr);
		}

		return $sql.' ORDER BY '.$this->escape_identifiers('name');
	}

	// --------------------------------------------------------------------

	/**
	 * Show column query
	 *
	 * Generates a platform-specific query string so that the column names can be fetched
	 *
	 * @param	string	$table
	 * @return	string
	 */
	protected function _list_columns($table = '')
	{
		return 'SELECT COLUMN_NAME
			FROM INFORMATION_SCHEMA.Columns
			WHERE UPPER(TABLE_NAME) = '.$this->escape(strtoupper($table));
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
		$sql = 'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, COLUMN_DEFAULT
			FROM INFORMATION_SCHEMA.Columns
			WHERE UPPER(TABLE_NAME) = '.$this->escape(strtoupper($table));

		if (($query = $this->query($sql)) === FALSE)
		{
			return FALSE;
		}
		$query = $query->result_object();

		$retval = array();
		for ($i = 0, $c = count($query); $i < $c; $i++)
		{
			$retval[$i]			= new stdClass();
			$retval[$i]->name		= $query[$i]->COLUMN_NAME;
			$retval[$i]->type		= $query[$i]->DATA_TYPE;
			$retval[$i]->max_length		= ($query[$i]->CHARACTER_MAXIMUM_LENGTH > 0) ? $query[$i]->CHARACTER_MAXIMUM_LENGTH : $query[$i]->NUMERIC_PRECISION;
			$retval[$i]->default		= $query[$i]->COLUMN_DEFAULT;
		}

		return $retval;
	}

	// --------------------------------------------------------------------

	/**
	 * Update statement
	 *
	 * Generates a platform-specific update string from the supplied data
	 *
	 * @param	string	$table
	 * @param	array	$values
	 * @return	string
	 */
	protected function _update($table, $values)
	{
		$this->qb_limit = FALSE;
		$this->qb_orderby = array();
		return parent::_update($table, $values);
	}

	// --------------------------------------------------------------------

	/**
	 * Delete statement
	 *
	 * Generates a platform-specific delete string from the supplied data
	 *
	 * @param	string	$table
	 * @return	string
	 */
	protected function _delete($table)
	{
		if ($this->qb_limit)
		{
			return 'WITH ci_delete AS (SELECT TOP '.$this->qb_limit.' * FROM '.$table.$this->_compile_wh('qb_where').') DELETE FROM ci_delete';
		}

		return parent::_delete($table);
	}

	// --------------------------------------------------------------------

	/**
	 * LIMIT
	 *
	 * Generates a platform-specific LIMIT clause
	 *
	 * @param	string	$sql	SQL Query
	 * @return	string
	 */
	protected function _limit($sql)
	{
		// As of SQL Server 2012 (11.0.*) OFFSET is supported
		if (version_compare($this->version(), '11', '>='))
		{
			// SQL Server OFFSET-FETCH can be used only with the ORDER BY clause
			empty($this->qb_orderby) && $sql .= ' ORDER BY (SELECT NULL)';

			return $sql.' OFFSET '.(int) $this->qb_offset.' ROWS FETCH NEXT '.$this->qb_limit.' ROWS ONLY';
		}

		$limit = $this->qb_offset + $this->qb_limit;

		// An ORDER BY clause is required for ROW_NUMBER() to work
		if ($this->qb_offset && ! empty($this->qb_orderby))
		{
			$orderby = $this->_compile_order_by();

			// We have to strip the ORDER BY clause
			$sql = trim(substr($sql, 0, strrpos($sql, $orderby)));

			// Get the fields to select from our subquery, so that we can avoid CI_rownum appearing in the actual results
			if (count($this->qb_select) === 0 OR strpos(implode(',', $this->qb_select), '*') !== FALSE)
			{
				$select = '*'; // Inevitable
			}
			else
			{
				// Use only field names and their aliases, everything else is out of our scope.
				$select = array();
				$field_regexp = ($this->_quoted_identifier)
					? '("[^\"]+")' : '(\[[^\]]+\])';
				for ($i = 0, $c = count($this->qb_select); $i < $c; $i++)
				{
					$select[] = preg_match('/(?:\s|\.)'.$field_regexp.'$/i', $this->qb_select[$i], $m)
						? $m[1] : $this->qb_select[$i];
				}
				$select = implode(', ', $select);
			}

			return 'SELECT '.$select." FROM (\n\n"
				.preg_replace('/^(SELECT( DISTINCT)?)/i', '\\1 ROW_NUMBER() OVER('.trim($orderby).') AS '.$this->escape_identifiers('CI_rownum').', ', $sql)
				."\n\n) ".$this->escape_identifiers('CI_subquery')
				."\nWHERE ".$this->escape_identifiers('CI_rownum').' BETWEEN '.($this->qb_offset + 1).' AND '.$limit;
		}

		return preg_replace('/(^\SELECT (DISTINCT)?)/i','\\1 TOP '.$limit.' ', $sql);
	}

	// --------------------------------------------------------------------

	/**
	 * Insert batch statement
	 *
	 * Generates a platform-specific insert string from the supplied data.
	 *
	 * @param	string	$table	Table name
	 * @param	array	$keys	INSERT keys
	 * @param	array	$values	INSERT values
	 * @return	string|bool
	 */
	protected function _insert_batch($table, $keys, $values)
	{
		// Multiple-value inserts are only supported as of SQL Server 2008
		if (version_compare($this->version(), '10', '>='))
		{
			return parent::_insert_batch($table, $keys, $values);
		}

		return ($this->db_debug) ? $this->display_error('db_unsupported_feature') : FALSE;
	}

}

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
 * CodeIgniter Session Database Driver
 *
 * @package	CodeIgniter
 * @subpackage	Libraries
 * @category	Sessions
 * @author	Andrey Andreev
 * @link	https://codeigniter.com/userguide3/libraries/sessions.html
 */
class CI_Session_database_driver extends CI_Session_driver implements CI_Session_driver_interface {

	/**
	 * DB object
	 *
	 * @var	object
	 */
	protected $_db;

	/**
	 * Row exists flag
	 *
	 * @var	bool
	 */
	protected $_row_exists = FALSE;

	/**
	 * Lock "driver" flag
	 *
	 * @var	string
	 */
	protected $_platform;

	/**
	 * SQLite lock file handle
	 *
	 * @var	resource|null
	 */
	protected $_lock_handle;

	// ------------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * @param	array	$params	Configuration parameters
	 * @return	void
	 */
	public function __construct(&$params)
	{
		parent::__construct($params);

		$CI =& get_instance();
		isset($CI->db) OR $CI->load->database();
		$this->_db = $CI->db;

		if ( ! $this->_db instanceof CI_DB_query_builder)
		{
			throw new Exception('Query Builder not enabled for the configured database. Aborting.');
		}
		elseif ($this->_db->pconnect)
		{
			throw new Exception('Configured database connection is persistent. Aborting.');
		}
		elseif ($this->_db->cache_on)
		{
			throw new Exception('Configured database connection has cache enabled. Aborting.');
		}

		$db_driver = $this->_db->dbdriver.(empty($this->_db->subdriver) ? '' : '_'.$this->_db->subdriver);
		if (strpos($db_driver, 'mysql') !== FALSE)
		{
			$this->_platform = 'mysql';
		}
		elseif (in_array($db_driver, array('postgre', 'pdo_pgsql'), TRUE))
		{
			$this->_platform = 'postgre';
		}
		elseif (in_array($db_driver, array('sqlite3', 'pdo_sqlite'), TRUE))
		{
			$this->_platform = 'sqlite';
		}
		elseif (in_array($db_driver, array('sqlsrv', 'pdo_sqlsrv'), TRUE))
		{
			$this->_platform = 'sqlsrv';
		}

		// Note: BC work-around for the old 'sess_table_name' setting, should be removed in the future.
		if ( ! isset($this->_config['save_path']) && ($this->_config['save_path'] = config_item('sess_table_name')))
		{
			log_message('debug', 'Session: "sess_save_path" is empty; using BC fallback to "sess_table_name".');
		}

		$this->_config['lock_wait'] = $this->_lock_setting('lock_wait', 0, 0);
		$this->_config['lock_retry_ms'] = $this->_lock_setting('lock_retry_ms', 100, 1);
	}

	// ------------------------------------------------------------------------

	/**
	 * Lock setting
	 *
	 * Validates 'sess_lock_wait' (seconds) and 'sess_lock_retry_ms'.
	 * A missing key takes its default; anything other than a whole
	 * number at or above the minimum is rejected.
	 *
	 * @param	string	$key	Setting name, without the 'sess_' prefix
	 * @param	int	$default	Value used when the key is not set
	 * @param	int	$min	Smallest accepted value
	 * @return	int
	 */
	protected function _lock_setting($key, $default, $min)
	{
		if ( ! isset($this->_config[$key]))
		{
			return $default;
		}

		$value = $this->_config[$key];
		if ((is_int($value) OR (is_string($value) && ctype_digit($value))) && (int) $value >= $min)
		{
			return (int) $value;
		}

		throw new Exception('Session: "sess_'.$key.'" must be a whole number, '.$min.' or greater; got '.var_export($value, TRUE).'.');
	}

	// ------------------------------------------------------------------------

	/**
	 * Open
	 *
	 * Initializes the database connection
	 *
	 * @param	string	$save_path	Table name
	 * @param	string	$name		Session cookie name, unused
	 * @return	bool
	 */
	public function open($save_path, $name)
	{
		if (empty($this->_db->conn_id) && ! $this->_db->db_connect())
		{
			return $this->_failure;
		}

		return $this->_success;
	}

	// ------------------------------------------------------------------------

	/**
	 * Read
	 *
	 * Reads session data and acquires a lock
	 *
	 * @param	string	$session_id	Session ID
	 * @return	string	Serialized session data
	 */
	public function read($session_id)
	{
		if ($this->_get_lock($session_id) === FALSE)
		{
			return $this->_failure;
		}

		// Prevent previous QB calls from messing with our queries
		$this->_db->reset_query();

		// Needed by write() to detect session_regenerate_id() calls
		$this->_session_id = $session_id;

		$this->_db
			->select('data')
			->from($this->_config['save_path'])
			->where('id', $session_id);

		if ($this->_config['match_ip'])
		{
			$this->_db->where('ip_address', $_SERVER['REMOTE_ADDR']);
		}

		if ( ! ($result = $this->_db->get()) OR ($result = $result->row()) === NULL)
		{
			// PHP7 will reuse the same SessionHandler object after
			// ID regeneration, so we need to explicitly set this to
			// FALSE instead of relying on the default ...
			$this->_row_exists = FALSE;
			$this->_fingerprint = md5('');
			return '';
		}

		// PostgreSQL's variant of a BLOB datatype is Bytea, which is a
		// PITA to work with, so we use base64-encoded data in a TEXT
		// field instead.
		$result = ($this->_platform === 'postgre')
			? base64_decode(rtrim($result->data))
			: $result->data;

		$this->_fingerprint = md5($result);
		$this->_row_exists = TRUE;
		return $result;
	}

	// ------------------------------------------------------------------------

	/**
	 * Write
	 *
	 * Writes (create / update) session data
	 *
	 * @param	string	$session_id	Session ID
	 * @param	string	$session_data	Serialized session data
	 * @return	bool
	 */
	public function write($session_id, $session_data)
	{
		// Prevent previous QB calls from messing with our queries
		$this->_db->reset_query();

		// Was the ID regenerated?
		if (isset($this->_session_id) && $session_id !== $this->_session_id)
		{
			if ( ! $this->_release_lock() OR ! $this->_get_lock($session_id))
			{
				return $this->_failure;
			}

			$this->_row_exists = FALSE;
			$this->_session_id = $session_id;
		}
		elseif ($this->_lock === FALSE)
		{
			return $this->_failure;
		}

		if ($this->_row_exists === FALSE)
		{
			$insert_data = array(
				'id' => $session_id,
				'ip_address' => $_SERVER['REMOTE_ADDR'],
				'timestamp' => time(),
				'data' => ($this->_platform === 'postgre' ? base64_encode($session_data) : $session_data)
			);

			if ($this->_db->insert($this->_config['save_path'], $insert_data))
			{
				$this->_fingerprint = md5($session_data);
				$this->_row_exists = TRUE;
				return $this->_success;
			}

			return $this->_failure;
		}

		$this->_db->where('id', $session_id);
		if ($this->_config['match_ip'])
		{
			$this->_db->where('ip_address', $_SERVER['REMOTE_ADDR']);
		}

		$update_data = array('timestamp' => time());
		if ($this->_fingerprint !== md5($session_data))
		{
			$update_data['data'] = ($this->_platform === 'postgre')
				? base64_encode($session_data)
				: $session_data;
		}

		if ($this->_db->update($this->_config['save_path'], $update_data))
		{
			$this->_fingerprint = md5($session_data);
			return $this->_success;
		}

		return $this->_failure;
	}

	// ------------------------------------------------------------------------

	/**
	 * Close
	 *
	 * Releases locks
	 *
	 * @return	bool
	 */
	public function close()
	{
		return ($this->_lock && ! $this->_release_lock())
			? $this->_failure
			: $this->_success;
	}

	// ------------------------------------------------------------------------

	/**
	 * Destroy
	 *
	 * Destroys the current session.
	 *
	 * @param	string	$session_id	Session ID
	 * @return	bool
	 */
	public function destroy($session_id)
	{
		if ($this->_lock)
		{
			// Prevent previous QB calls from messing with our queries
			$this->_db->reset_query();

			$this->_db->where('id', $session_id);
			if ($this->_config['match_ip'])
			{
				$this->_db->where('ip_address', $_SERVER['REMOTE_ADDR']);
			}

			if ( ! $this->_db->delete($this->_config['save_path']))
			{
				return $this->_failure;
			}
		}

		if ($this->close() === $this->_success)
		{
			$this->_cookie_destroy();
			return $this->_success;
		}

		return $this->_failure;
	}

	// ------------------------------------------------------------------------

	/**
	 * Garbage Collector
	 *
	 * Deletes expired sessions
	 *
	 * @param	int 	$maxlifetime	Maximum lifetime of sessions
	 * @return	bool
	 */
	public function gc($maxlifetime)
	{
		// Prevent previous QB calls from messing with our queries
		$this->_db->reset_query();

		return ($this->_db->delete($this->_config['save_path'], 'timestamp < '.(time() - $maxlifetime)))
			? $this->_success
			: $this->_failure;
	}

	// --------------------------------------------------------------------

	/**
	 * Update Timestamp
	 *
	 * Update session timestamp without modifying data
	 *
	 * @param	string	$id	Session ID
	 * @param	string	$data	Unknown & unused
	 * @return	bool
	 */
	public function updateTimestamp($id, $unknown)
	{
		// Prevent previous QB calls from messing with our queries
		$this->_db->reset_query();

		$this->_db->where('id', $id);
		if ($this->_config['match_ip'])
		{
			$this->_db->where('ip_address', $_SERVER['REMOTE_ADDR']);
		}

		return (bool) $this->_db->update($this->_config['save_path'], array('timestamp' => time()));
	}

	// --------------------------------------------------------------------

	/**
	 * Validate ID
	 *
	 * Checks whether a session ID record exists server-side,
	 * to enforce session.use_strict_mode.
	 *
	 * @param	string	$id	Session ID
	 * @return	bool
	 */
	public function validateId($id)
	{
		// Prevent previous QB calls from messing with our queries
		$this->_db->reset_query();

		$this->_db->select('1')->from($this->_config['save_path'])->where('id', $id);
		empty($this->_config['match_ip']) OR $this->_db->where('ip_address', $_SERVER['REMOTE_ADDR']);
		$result = $this->_db->get();
		empty($result) OR $result = $result->row();

		return ! empty($result);
	}

	// ------------------------------------------------------------------------

	/**
	 * Get lock
	 *
	 * Acquires a lock, depending on the underlying platform.
	 *
	 * @param	string	$session_id	Session ID
	 * @return	bool
	 */
	protected function _get_lock($session_id)
	{
		if ($this->_platform === 'mysql')
		{
			$arg = md5($session_id.($this->_config['match_ip'] ? '_'.$_SERVER['REMOTE_ADDR'] : ''));
			if ($this->_db->query("SELECT GET_LOCK('".$arg."', 300) AS ci_session_lock")->row()->ci_session_lock)
			{
				$this->_lock = $arg;
				return TRUE;
			}

			return FALSE;
		}
		elseif ($this->_platform === 'postgre')
		{
			$arg = "hashtext('".$session_id."')".($this->_config['match_ip'] ? ", hashtext('".$_SERVER['REMOTE_ADDR']."')" : '');
			if ($this->_config['lock_wait'] === 0)
			{
				if ($this->_db->simple_query('SELECT pg_advisory_lock('.$arg.')'))
				{
					$this->_lock = $arg;
					return TRUE;
				}

				return FALSE;
			}

			$deadline = microtime(TRUE) + $this->_config['lock_wait'];
			do
			{
				if ( ! ($result = $this->_db->query('SELECT pg_try_advisory_lock('.$arg.')::int AS ci_session_lock')))
				{
					return FALSE;
				}

				if ((int) $result->row()->ci_session_lock === 1)
				{
					$this->_lock = $arg;
					return TRUE;
				}

				$remaining = $deadline - microtime(TRUE);
				if ($remaining > 0)
				{
					usleep((int) (min($this->_config['lock_retry_ms'] / 1000, $remaining) * 1000000));
				}
			}
			while ($remaining > 0);

			log_message('error', 'Session: Timed out after '.$this->_config['lock_wait'].' second(s) waiting for the PostgreSQL advisory lock.');
			return FALSE;
		}
		elseif ($this->_platform === 'sqlite')
		{
			return $this->_get_sqlite_lock($session_id);
		}
		elseif ($this->_platform === 'sqlsrv')
		{
			$arg = 'ci_session:'.md5($session_id.($this->_config['match_ip'] ? '_'.$_SERVER['REMOTE_ADDR'] : ''));
			$timeout = ($this->_config['lock_wait'] === 0) ? -1 : $this->_config['lock_wait'] * 1000;

			$code = $this->_sqlsrv_lock_call("sp_getapplock @Resource = '".$arg."', @LockMode = 'Exclusive', @LockOwner = 'Session', @LockTimeout = ".$timeout);
			if ($code !== FALSE && $code >= 0)
			{
				$this->_lock = $arg;
				return TRUE;
			}

			log_message('error', 'Session: Unable to obtain the SQL Server application lock; sp_getapplock returned '.($code === FALSE ? 'no result' : $code).'.');
			return FALSE;
		}

		return parent::_get_lock($session_id);
	}

	// ------------------------------------------------------------------------

	/**
	 * SQL Server lock call
	 *
	 * Runs sp_getapplock or sp_releaseapplock and reads its return code.
	 *
	 * The batch starts with SET NOCOUNT ON, which the driver executes
	 * without a scrollable cursor, and so that the SELECT is its first
	 * result. NOCOUNT is then switched back off, as the drivers connect,
	 * so that affected_rows() keeps working for the application.
	 *
	 * @param	string	$call	Procedure name and arguments
	 * @return	int|bool	Return code; FALSE if it could not be read
	 */
	protected function _sqlsrv_lock_call($call)
	{
		$result = $this->_db->query(
			'SET NOCOUNT ON; DECLARE @ci_session_lock INT; EXEC @ci_session_lock = '.$call.'; SET NOCOUNT OFF; SELECT @ci_session_lock AS ci_session_lock',
			FALSE,
			TRUE
		);

		if ( ! $result OR ($row = $result->row()) === NULL OR ! isset($row->ci_session_lock))
		{
			return FALSE;
		}

		return (int) $row->ci_session_lock;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get SQLite lock
	 *
	 * Takes an exclusive flock() on a file next to the database, so that
	 * requests for the same session are serialized without holding a
	 * lock on the database itself.
	 *
	 * @param	string	$session_id	Session ID
	 * @return	bool
	 */
	protected function _get_sqlite_lock($session_id)
	{
		// An in-memory or temporary database is private to this process
		if (($path = $this->_sqlite_lock_path($session_id)) === NULL)
		{
			return parent::_get_lock($session_id);
		}

		if (($handle = fopen($path, 'c')) === FALSE)
		{
			log_message('error', 'Session: Unable to open the SQLite session lock file "'.$path.'".');
			return FALSE;
		}

		if ($this->_config['lock_wait'] === 0)
		{
			if (flock($handle, LOCK_EX))
			{
				$this->_lock_handle = $handle;
				$this->_lock = $path;
				return TRUE;
			}

			fclose($handle);
			log_message('error', 'Session: Unable to lock the SQLite session lock file "'.$path.'".');
			return FALSE;
		}

		$deadline = microtime(TRUE) + $this->_config['lock_wait'];
		do
		{
			if (flock($handle, LOCK_EX | LOCK_NB))
			{
				$this->_lock_handle = $handle;
				$this->_lock = $path;
				return TRUE;
			}

			$remaining = $deadline - microtime(TRUE);
			if ($remaining > 0)
			{
				usleep((int) (min($this->_config['lock_retry_ms'] / 1000, $remaining) * 1000000));
			}
		}
		while ($remaining > 0);

		fclose($handle);
		log_message('error', 'Session: Timed out after '.$this->_config['lock_wait'].' second(s) waiting for the SQLite session lock file "'.$path.'".');
		return FALSE;
	}

	// ------------------------------------------------------------------------

	/**
	 * SQLite lock path
	 *
	 * @param	string	$session_id	Session ID
	 * @return	string|null	Lock file path, or NULL when the database has no file
	 */
	protected function _sqlite_lock_path($session_id)
	{
		if ($this->_db->dbdriver === 'pdo')
		{
			// pdo_sqlite opens whatever follows the "sqlite:" prefix of the DSN
			$database = (string) $this->_db->dsn;
			$database = (string) substr($database, strpos($database, ':') + 1);
		}
		else
		{
			$database = (string) $this->_db->database;
		}

		if ($database === '' OR $database === ':memory:')
		{
			return NULL;
		}

		return $database.'.ci_session_'.md5($session_id).'.lock';
	}

	// ------------------------------------------------------------------------

	/**
	 * Release lock
	 *
	 * Releases a previously acquired lock
	 *
	 * @return	bool
	 */
	protected function _release_lock()
	{
		if ( ! $this->_lock)
		{
			return TRUE;
		}

		if ($this->_platform === 'mysql')
		{
			if ($this->_db->query("SELECT RELEASE_LOCK('".$this->_lock."') AS ci_session_lock")->row()->ci_session_lock)
			{
				$this->_lock = FALSE;
				return TRUE;
			}

			return FALSE;
		}
		elseif ($this->_platform === 'postgre')
		{
			if ($this->_db->simple_query('SELECT pg_advisory_unlock('.$this->_lock.')'))
			{
				$this->_lock = FALSE;
				return TRUE;
			}

			return FALSE;
		}
		elseif ($this->_platform === 'sqlite' && isset($this->_lock_handle))
		{
			// The file stays: another process may already be opening it
			if (flock($this->_lock_handle, LOCK_UN) && fclose($this->_lock_handle))
			{
				$this->_lock_handle = NULL;
				$this->_lock = FALSE;
				return TRUE;
			}

			return FALSE;
		}
		elseif ($this->_platform === 'sqlsrv')
		{
			$code = $this->_sqlsrv_lock_call("sp_releaseapplock @Resource = '".$this->_lock."', @LockOwner = 'Session'");
			if ($code !== FALSE && $code >= 0)
			{
				$this->_lock = FALSE;
				return TRUE;
			}

			log_message('error', 'Session: Unable to release the SQL Server application lock; sp_releaseapplock returned '.($code === FALSE ? 'no result' : $code).'.');
			return FALSE;
		}

		return parent::_release_lock();
	}
}

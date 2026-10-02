<?php

class Sqlite3_pragma_test extends CI_TestCase {

	/**
	 * Temporary database file
	 *
	 * @var	string
	 */
	protected $file;

	/**
	 * Driver under test
	 *
	 * @var	CI_DB_sqlite3_driver|null
	 */
	protected $db;

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');

		$this->file = tempnam(sys_get_temp_dir(), 'ci_sqlite3_');
	}

	// ------------------------------------------------------------------------

	public function tear_down()
	{
		if (isset($this->db))
		{
			$this->db->close();
		}

		foreach (array('', '-wal', '-shm', '-journal') as $suffix)
		{
			if (is_file($this->file.$suffix))
			{
				unlink($this->file.$suffix);
			}
		}
	}

	// ------------------------------------------------------------------------

	protected function connect($config = array(), $file = NULL)
	{
		$this->db = new CI_DB_sqlite3_driver(array_merge(array(
			'database' => isset($file) ? $file : $this->file,
			'db_debug' => FALSE
		), $config));

		$this->db->initialize();
		return $this->db;
	}

	// ------------------------------------------------------------------------

	protected function pragma($name)
	{
		return $this->query_pragma($this->db->conn_id, $name);
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	SQLite3	$sqlite
	 * @param	string	$name
	 * @return	mixed
	 */
	protected function query_pragma($sqlite, $name)
	{
		return $sqlite->querySingle('PRAGMA '.$name);
	}

	// ------------------------------------------------------------------------

	protected function default_busy_timeout()
	{
		$sqlite = new SQLite3($this->file);
		$busy_timeout = $this->query_pragma($sqlite, 'busy_timeout');
		$sqlite->close();

		return $busy_timeout;
	}

	// ------------------------------------------------------------------------

	public function test_default_sends_no_pragma()
	{
		$this->connect();

		$this->assertSame('delete', $this->pragma('journal_mode'));
		$this->assertSame(0, $this->pragma('foreign_keys'));
		$this->assertSame($this->default_busy_timeout(), $this->pragma('busy_timeout'));
	}

	// ------------------------------------------------------------------------

	public function test_wal_file_stays_wal_when_disabled()
	{
		$sqlite = new SQLite3($this->file);
		$this->assertSame('wal', $sqlite->querySingle('PRAGMA journal_mode = WAL'));
		$sqlite->close();

		$this->connect(array('wal' => FALSE));
		$this->assertSame('wal', $this->pragma('journal_mode'));
	}

	// ------------------------------------------------------------------------

	public function test_busy_timeout_zero_keeps_default()
	{
		$default = $this->default_busy_timeout();

		foreach (array(0, '0') as $busy_timeout)
		{
			$this->connect(array('busy_timeout' => $busy_timeout));
			$this->assertSame($default, $this->pragma('busy_timeout'), var_export($busy_timeout, TRUE));
			$this->db->close();
		}
	}

	// ------------------------------------------------------------------------

	public function test_busy_timeout_is_milliseconds()
	{
		foreach (array(5000, '5000') as $busy_timeout)
		{
			$this->connect(array('busy_timeout' => $busy_timeout));
			$this->assertSame(5000, $this->pragma('busy_timeout'), var_export($busy_timeout, TRUE));
			$this->db->close();
		}
	}

	// ------------------------------------------------------------------------

	public function test_wal_when_enabled()
	{
		$this->connect(array('wal' => TRUE));

		$this->assertSame('wal', $this->pragma('journal_mode'));
		$this->assertSame(0, $this->pragma('foreign_keys'));
	}

	// ------------------------------------------------------------------------

	public function test_foreign_keys_when_enabled()
	{
		$this->connect(array('foreign_keys' => TRUE));

		$this->assertSame(1, $this->pragma('foreign_keys'));
		$this->assertSame('delete', $this->pragma('journal_mode'));
	}

	// ------------------------------------------------------------------------

	public function test_pragma_results_are_not_left_pending()
	{
		$this->connect(array('busy_timeout' => 5000, 'wal' => TRUE, 'foreign_keys' => TRUE));

		$this->assertTrue($this->db->simple_query('CREATE TABLE pending_test (id INTEGER)'));
		$this->assertTrue($this->db->simple_query('DROP TABLE pending_test'));
	}

	// ------------------------------------------------------------------------

	public function test_invalid_settings_do_not_connect()
	{
		$settings = array(
			array('busy_timeout', -1),
			array('busy_timeout', '-1'),
			array('busy_timeout', 'abc'),
			array('busy_timeout', '5s'),
			array('busy_timeout', 1.5),
			array('busy_timeout', NULL),
			array('busy_timeout', 2147483648),
			array('wal', 'yes'),
			array('foreign_keys', 'on')
		);

		$file = $this->file.'-invalid';
		foreach ($settings as $setting)
		{
			list($key, $value) = $setting;
			$label = $key.' = '.var_export($value, TRUE);

			try
			{
				$this->connect(array($key => $value), $file);
				$this->fail($label.' connected');
			}
			catch (RuntimeException $e)
			{
				$this->assertStringContainsString("Invalid '".$key."' setting", $e->getMessage(), $label);
			}

			$this->assertFalse($this->db->conn_id, $label);
			$error = $this->db->error();
			$this->assertStringContainsString("Invalid '".$key."' setting", $error['message'], $label);
			$this->assertFileDoesNotExist($file, $label);
		}
	}

}

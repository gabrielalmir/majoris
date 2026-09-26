<?php

// Named as the file: run on its own, PHPUnit picks the class by a case-sensitive match
class sqlsrv_server_test extends CI_TestCase {

	/**
	 * DB_DRIVER of the PHPUnit configuration
	 *
	 * @var	string
	 */
	protected $driver;

	/**
	 * Connections opened by the test
	 *
	 * @var	array
	 */
	protected $connections = array();

	public function set_up()
	{
		$this->driver = defined('DB_DRIVER') ? constant('DB_DRIVER') : '';

		if ( ! in_array($this->driver, array('sqlsrv', 'pdo/sqlsrv'), TRUE))
		{
			$this->markTestSkipped('Runs against a SQL Server only.');
		}
		elseif ( ! extension_loaded($this->driver === 'sqlsrv' ? 'sqlsrv' : 'pdo_sqlsrv'))
		{
			$this->markTestSkipped('The '.($this->driver === 'sqlsrv' ? 'sqlsrv' : 'pdo_sqlsrv').' extension is not loaded.');
		}
		elseif (getenv('SQLSRV_HOST') === FALSE OR getenv('SQLSRV_HOST') === '')
		{
			$this->markTestSkipped('SQLSRV_HOST is not set.');
		}

		require_once SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php';
		require_once SYSTEM_PATH.'libraries/Session/Session_driver.php';
	}

	// ------------------------------------------------------------------------

	public function tear_down()
	{
		if (isset($this->connections[0]))
		{
			$this->connections[0]->query('DROP TABLE IF EXISTS ci_server_identity');
			$this->connections[0]->query('DROP TABLE IF EXISTS ci_server_sessions');
		}

		foreach ($this->connections as $db)
		{
			$db->close();
		}

		$this->connections = array();
	}

	// ------------------------------------------------------------------------

	/**
	 * A new connection, with encryption on and the server certificate trusted
	 *
	 * @return	object
	 */
	protected function connect()
	{
		$params = array(
			'dsn' => '',
			'hostname' => getenv('SQLSRV_HOST'),
			'username' => getenv('SQLSRV_USER'),
			'password' => getenv('SQLSRV_PASSWORD'),
			'database' => getenv('SQLSRV_DATABASE'),
			'dbdriver' => ($this->driver === 'sqlsrv') ? 'sqlsrv' : 'pdo',
			'encrypt' => 'yes',
			'trust_server_certificate' => TRUE,
			'pconnect' => FALSE,
			'db_debug' => TRUE,
			'cache_on' => FALSE,
			'char_set' => 'utf8',
			'dbcollat' => ''
		);

		if ($this->driver === 'pdo/sqlsrv')
		{
			$params['subdriver'] = 'sqlsrv';
		}

		// Sets up the driver files that DB() loads
		$mock = new Mock_Database_DB(array($this->driver => $params));
		$mock->set_dsn($this->driver);

		$db = Mock_Database_DB::DB($params, TRUE);
		$db->initialize();
		$this->assertNotEmpty($db->conn_id, 'No connection to '.getenv('SQLSRV_HOST'));

		$this->connections[] = $db;
		return $db;
	}

	// ------------------------------------------------------------------------

	/**
	 * Identity table, seeded apart from 1 so that it can't be mistaken for a row count
	 *
	 * @param	object	$db
	 * @return	void
	 */
	protected function create_identity_table($db)
	{
		$db->query('DROP TABLE IF EXISTS ci_server_identity');
		$db->query('CREATE TABLE ci_server_identity (id INT IDENTITY(41, 1) PRIMARY KEY, name VARCHAR(20) NOT NULL)');
	}

	// ------------------------------------------------------------------------

	public function test_connects_with_encryption_and_trusted_certificate()
	{
		$db = $this->connect();

		$row = $db->query('SELECT encrypt_option FROM sys.dm_exec_connections WHERE session_id = @@SPID')->row();
		$this->assertSame('TRUE', $row->encrypt_option);
		$this->assertTrue(version_compare($db->version(), '15', '>='), 'SQL Server 2019 or later expected, got '.$db->version());
	}

	// ------------------------------------------------------------------------

	public function test_insert_id_and_affected_rows()
	{
		$db = $this->connect();
		$this->create_identity_table($db);

		$this->assertTrue($db->insert('ci_server_identity', array('name' => 'first')));
		$this->assertEquals(41, $db->insert_id());
		$this->assertSame(1, $db->affected_rows());

		$this->assertTrue($db->insert('ci_server_identity', array('name' => 'second')));
		$this->assertEquals(42, $db->insert_id());
		$this->assertSame(1, $db->affected_rows());

		$this->assertEquals(42, $db->query('SELECT MAX(id) AS id FROM ci_server_identity')->row()->id);
	}

	// ------------------------------------------------------------------------

	public function test_limit_without_order_by()
	{
		$db = $this->connect();
		$this->create_identity_table($db);
		$db->insert_batch('ci_server_identity', array(
			array('name' => 'a'),
			array('name' => 'b'),
			array('name' => 'c')
		));

		$query = $db->limit(2, 1)->get('ci_server_identity');

		$this->assertNotFalse($query);
		$this->assertCount(2, $query->result_array());
		$this->assertStringContainsString('ORDER BY (SELECT NULL)', $db->last_query());
		$this->assertStringNotContainsString('ORDER BY 1', $db->last_query());
		$this->assertStringContainsString('OFFSET 1 ROWS FETCH NEXT 2 ROWS ONLY', $db->last_query());
	}

	// ------------------------------------------------------------------------

	public function test_num_rows_on_the_default_cursor()
	{
		$db = $this->connect();
		$this->create_identity_table($db);
		$db->insert_batch('ci_server_identity', array(
			array('name' => 'a'),
			array('name' => 'b'),
			array('name' => 'c')
		));

		$query = $db->query('SELECT id, name FROM ci_server_identity');

		if ($this->driver === 'sqlsrv')
		{
			$this->assertSame(SQLSRV_CURSOR_CLIENT_BUFFERED, $db->scrollable);
			$this->assertSame(3, $query->num_rows());

			// Counted by the cursor, no row was fetched
			$this->assertSame(array(), $query->result_array);
			$this->assertSame(array(), $query->result_object);
		}
		else
		{
			// The forward-only PDO cursor has no row count for a SELECT
			$this->assertSame(3, $query->num_rows());
		}

		$this->assertCount(3, $query->result_array());
	}

	// ------------------------------------------------------------------------

	public function test_concurrent_requests_on_the_same_session()
	{
		$first_db = $this->connect();
		$second_db = $this->connect();

		$first_db->query('DROP TABLE IF EXISTS ci_server_sessions');
		$first_db->query('CREATE TABLE ci_server_sessions (id VARCHAR(128) NOT NULL PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, timestamp BIGINT NOT NULL DEFAULT 0, data VARCHAR(MAX) NOT NULL)');
		$first_db->insert('ci_server_sessions', array(
			'id' => 'ci_server_session',
			'ip_address' => '127.0.0.1',
			'timestamp' => time(),
			'data' => 'user|s:5:"alice";'
		));

		$params = array('save_path' => 'ci_server_sessions', 'match_ip' => FALSE);

		// Each driver takes the connection that is $CI->db when it is built
		$this->ci_instance_var('db', $first_db);
		$first = new CI_Session_database_driver($params);

		$second_params = $params + array('lock_wait' => 1);
		$this->ci_instance_var('db', $second_db);
		$second = new CI_Session_database_driver($second_params);

		$this->assertSame('user|s:5:"alice";', $first->read('ci_server_session'));

		$start = microtime(TRUE);
		$this->assertFalse($second->read('ci_server_session'));
		$this->assertGreaterThanOrEqual(0.9, microtime(TRUE) - $start);

		$this->assertTrue($first->write('ci_server_session', 'user|s:3:"bob";'));
		$this->assertTrue($first->close());

		$this->assertSame('user|s:3:"bob";', $second->read('ci_server_session'));
		$this->assertTrue($second->close());
	}

}

<?php

class Session_database_lock_test extends CI_TestCase {

	/**
	 * @var	Mock_Database_Postgre_Driver
	 */
	protected $db;

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');

		require_once SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php';
		require_once SYSTEM_PATH.'libraries/Session/Session_driver.php';

		$this->db = new Mock_Database_Postgre_Driver(array(
			'hostname' => 'db.local',
			'database' => 'app',
			'dbdriver' => 'postgre'
		));

		// Statements are recorded by the mock, no connection is made
		$this->db->conn_id = new stdClass();
		$this->db->replies = array(
			'SELECT "data"' => array(array('data' => base64_encode('user|s:5:"alice";')))
		);

		$this->ci_instance_var('db', $this->db);
	}

	// ------------------------------------------------------------------------

	protected function session_driver(array $config = array())
	{
		$params = array_merge(array(
			'save_path' => 'ci_sessions',
			'match_ip' => FALSE
		), $config);

		return new CI_Session_database_driver($params);
	}

	// ------------------------------------------------------------------------

	public function test_lock_wait_defaults_to_the_blocking_lock()
	{
		$driver = $this->session_driver();

		$this->assertSame('user|s:5:"alice";', $driver->read('abc123'));
		$this->assertSame(array("SELECT pg_advisory_lock(hashtext('abc123'))"), $this->db->recorded('SELECT pg_'));
		$this->assertCount(1, $this->db->recorded('SELECT "data"'));
	}

	// ------------------------------------------------------------------------

	public function test_lock_retried_until_granted()
	{
		$this->db->replies['SELECT pg_try_advisory_lock'] = array(
			array('ci_session_lock' => '0'),
			array('ci_session_lock' => '0'),
			array('ci_session_lock' => '1')
		);
		$driver = $this->session_driver(array('lock_wait' => '5', 'lock_retry_ms' => '10'));

		$start = microtime(TRUE);
		$this->assertSame('user|s:5:"alice";', $driver->read('abc123'));
		$this->assertLessThan(2, microtime(TRUE) - $start);

		$this->assertSame(
			array_fill(0, 3, "SELECT pg_try_advisory_lock(hashtext('abc123'))::int AS ci_session_lock"),
			$this->db->recorded('SELECT pg_')
		);
		$this->assertCount(1, $this->db->recorded('SELECT "data"'));

		$this->assertTrue($driver->close());
		$this->assertSame(array("SELECT pg_advisory_unlock(hashtext('abc123'))"), $this->db->recorded('SELECT pg_advisory_unlock'));
	}

	// ------------------------------------------------------------------------

	public function test_lock_timeout_fails_the_read()
	{
		$this->db->replies['SELECT pg_try_advisory_lock'] = array(array('ci_session_lock' => '0'));
		$driver = $this->session_driver(array('lock_wait' => 1, 'lock_retry_ms' => 200));

		$start = microtime(TRUE);
		$this->assertFalse($driver->read('abc123'));
		$elapsed = microtime(TRUE) - $start;

		$this->assertGreaterThanOrEqual(0.95, $elapsed);
		$this->assertLessThan(3, $elapsed);
		$this->assertGreaterThan(1, count($this->db->recorded('SELECT pg_try_advisory_lock')));

		// No session data is read, and nothing can be written or unlocked
		$this->assertSame(array(), $this->db->recorded('SELECT "data"'));
		$this->assertFalse($driver->write('abc123', 'user|s:7:"mallory";'));
		$this->assertTrue($driver->close());
		$this->assertSame(array(), $this->db->recorded('SELECT pg_advisory_unlock'));
		$this->assertSame(array(), $this->db->recorded('INSERT'));
		$this->assertSame(array(), $this->db->recorded('UPDATE'));
	}

	// ------------------------------------------------------------------------

	public function test_failed_try_lock_query_fails_the_read()
	{
		$this->db->replies['SELECT pg_try_advisory_lock'] = array(FALSE);
		$driver = $this->session_driver(array('lock_wait' => 5));

		$this->assertFalse($driver->read('abc123'));
		$this->assertCount(1, $this->db->recorded('SELECT pg_try_advisory_lock'));
		$this->assertSame(array(), $this->db->recorded('SELECT "data"'));
	}

	// ------------------------------------------------------------------------

	public function test_lock_settings_default_when_missing()
	{
		$params = array('save_path' => 'ci_sessions', 'match_ip' => FALSE);
		new CI_Session_database_driver($params);

		$this->assertSame(0, $params['lock_wait']);
		$this->assertSame(100, $params['lock_retry_ms']);
	}

	// ------------------------------------------------------------------------

	public function test_lock_settings_from_strings()
	{
		$params = array('save_path' => 'ci_sessions', 'match_ip' => FALSE, 'lock_wait' => '3', 'lock_retry_ms' => '250');
		new CI_Session_database_driver($params);

		$this->assertSame(3, $params['lock_wait']);
		$this->assertSame(250, $params['lock_retry_ms']);
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	invalid_lock_settings
	 */
	public function test_invalid_lock_setting_is_rejected($key, $value)
	{
		$this->expectException('Exception');
		$this->expectExceptionMessage('"sess_'.$key.'" must be a whole number');

		$this->session_driver(array($key => $value));
	}

	// ------------------------------------------------------------------------

	public function invalid_lock_settings()
	{
		return array(
			array('lock_wait', '-1'),
			array('lock_wait', '1.5'),
			array('lock_wait', 'forever'),
			array('lock_wait', -2),
			array('lock_retry_ms', '0'),
			array('lock_retry_ms', 0),
			array('lock_retry_ms', ''),
			array('lock_retry_ms', FALSE)
		);
	}

}

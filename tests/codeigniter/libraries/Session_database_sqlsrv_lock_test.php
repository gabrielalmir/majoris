<?php

class Session_database_sqlsrv_lock_test extends CI_TestCase {

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');

		require_once SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php';
		require_once SYSTEM_PATH.'libraries/Session/Session_driver.php';
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	string	$dbdriver	'sqlsrv' or 'pdo'
	 * @return	Mock_Database_Sqlsrv_Session|Mock_Database_Sqlsrv_Pdosession
	 */
	protected function db($dbdriver)
	{
		$db = ($dbdriver === 'pdo')
			? new Mock_Database_Sqlsrv_Pdosession(array('hostname' => 'db.local', 'database' => 'app', 'dbdriver' => 'pdo', 'subdriver' => 'sqlsrv'))
			: new Mock_Database_Sqlsrv_Session(array('hostname' => 'db.local', 'database' => 'app', 'dbdriver' => 'sqlsrv'));

		// Statements are recorded by the mock, no connection is made
		$db->conn_id = new stdClass();
		$db->replies = array(
			'sp_getapplock' => array(array('ci_session_lock' => 0)),
			'sp_releaseapplock' => array(array('ci_session_lock' => 0)),
			'ci_sessions' => array(array('data' => 'user|s:5:"alice";'))
		);

		$this->ci_instance_var('db', $db);
		return $db;
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

	public function drivers()
	{
		return array(array('sqlsrv'), array('pdo'));
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_lock_wait_zero_waits_without_a_limit($dbdriver)
	{
		$db = $this->db($dbdriver);
		$driver = $this->session_driver();

		$this->assertSame('user|s:5:"alice";', $driver->read('abc123'));

		$lock = $db->recorded('sp_getapplock');
		$this->assertCount(1, $lock);
		$this->assertStringStartsWith('SET NOCOUNT ON;', $lock[0]);
		$this->assertStringContainsString("sp_getapplock @Resource = 'ci_session:".md5('abc123')."'", $lock[0]);
		$this->assertStringContainsString("@LockMode = 'Exclusive'", $lock[0]);
		$this->assertStringContainsString("@LockOwner = 'Session'", $lock[0]);
		$this->assertStringContainsString('@LockTimeout = -1;', $lock[0]);
		$this->assertSame('SET NOCOUNT ON;', substr($db->statements[0], 0, 15));

		// SET is a write type, the result has to be asked for
		$this->assertTrue($db->return_objects[0]);
		$this->assertCount(1, $db->recorded('ci_sessions'));
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_lock_wait_is_sent_in_milliseconds($dbdriver)
	{
		$db = $this->db($dbdriver);
		$db->replies['sp_getapplock'] = array(array('ci_session_lock' => '1'));
		$driver = $this->session_driver(array('lock_wait' => '2'));

		$this->assertSame('user|s:5:"alice";', $driver->read('abc123'));

		$lock = $db->recorded('sp_getapplock');
		$this->assertCount(1, $lock);
		$this->assertStringContainsString('@LockTimeout = 2000;', $lock[0]);
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_match_ip_is_part_of_the_resource($dbdriver)
	{
		$db = $this->db($dbdriver);
		$driver = $this->session_driver(array('match_ip' => TRUE));

		$this->assertSame('user|s:5:"alice";', $driver->read('abc123'));

		$lock = $db->recorded('sp_getapplock');
		$this->assertStringContainsString("@Resource = 'ci_session:".md5('abc123_'.$_SERVER['REMOTE_ADDR'])."'", $lock[0]);
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_close_releases_the_lock($dbdriver)
	{
		$db = $this->db($dbdriver);
		$driver = $this->session_driver();

		$this->assertSame('user|s:5:"alice";', $driver->read('abc123'));
		$this->assertTrue($driver->write('abc123', 'user|s:3:"bob";'));
		$this->assertTrue($driver->close());

		$release = $db->recorded('sp_releaseapplock');
		$this->assertCount(1, $release);
		$this->assertStringStartsWith('SET NOCOUNT ON;', $release[0]);
		$this->assertStringContainsString("sp_releaseapplock @Resource = 'ci_session:".md5('abc123')."', @LockOwner = 'Session'", $release[0]);

		// Released once only
		$this->assertTrue($driver->close());
		$this->assertCount(1, $db->recorded('sp_releaseapplock'));
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	negative_codes
	 */
	public function test_negative_code_fails_the_read($dbdriver, $code)
	{
		$db = $this->db($dbdriver);
		$db->replies['sp_getapplock'] = array(array('ci_session_lock' => $code));
		$driver = $this->session_driver(array('lock_wait' => 1));

		$this->assertFalse($driver->read('abc123'));
		$this->assertCount(1, $db->recorded('sp_getapplock'));

		// The session table is never read, nothing is written or released
		$this->assertSame(array(), $db->recorded('ci_sessions'));
		$this->assertFalse($driver->write('abc123', 'user|s:7:"mallory";'));
		$this->assertTrue($driver->close());
		$this->assertSame(array(), $db->recorded('sp_releaseapplock'));
		$this->assertSame(array(), $db->recorded('ci_sessions'));
	}

	// ------------------------------------------------------------------------

	public function negative_codes()
	{
		return array(
			array('sqlsrv', -1),
			array('sqlsrv', -3),
			array('pdo', '-1'),
			array('pdo', '-999')
		);
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_failed_lock_query_fails_the_read($dbdriver)
	{
		$db = $this->db($dbdriver);
		$db->replies['sp_getapplock'] = array(FALSE);
		$driver = $this->session_driver();

		$this->assertFalse($driver->read('abc123'));
		$this->assertSame(array(), $db->recorded('ci_sessions'));
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_failed_release_fails_the_close($dbdriver)
	{
		$db = $this->db($dbdriver);
		$db->replies['sp_releaseapplock'] = array(array('ci_session_lock' => -999));
		$driver = $this->session_driver();

		$this->assertSame('user|s:5:"alice";', $driver->read('abc123'));
		$this->assertFalse($driver->close());
	}

}

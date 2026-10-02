<?php

class Postgre_lastval_server_test extends CI_TestCase {

	/**
	 * DB_DRIVER of the PHPUnit configuration
	 *
	 * @var	string
	 */
	protected $driver;

	public function set_up()
	{
		$this->driver = constant('DB_DRIVER');

		if ( ! in_array($this->driver, array('pgsql', 'pdo/pgsql'), TRUE))
		{
			$this->markTestSkipped('Runs against a PostgreSQL server only.');
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * A new connection, so that no sequence has been used in the session
	 *
	 * @return	object
	 */
	protected function connect()
	{
		$config = Mock_Database_DB::config($this->driver);
		$connection = new Mock_Database_DB($config);
		$db = Mock_Database_DB::DB($connection->set_dsn($this->driver), TRUE);
		$db->initialize();
		$db->db_debug = TRUE;
		return $db;
	}

	// ------------------------------------------------------------------------

	public function test_session_without_sequence_returns_zero()
	{
		$db = $this->connect();

		$this->assertSame(0, $db->insert_id());
		$this->assertTrue($db->trans_status());
	}

	// ------------------------------------------------------------------------

	public function test_transaction_is_not_aborted()
	{
		$db = $this->connect();
		$db->trans_begin();

		$this->assertSame(0, $db->insert_id());
		$this->assertTrue($db->trans_status());
		$this->assertEquals(1, $db->query('SELECT 1 AS one')->row()->one);

		$db->trans_rollback();
	}

	// ------------------------------------------------------------------------

	public function test_lastval_after_nextval()
	{
		$db = $this->connect();
		$db->query('CREATE TEMPORARY SEQUENCE ci_lastval_test START 41');
		$db->query("SELECT nextval('ci_lastval_test')");

		$this->assertEquals(41, $db->insert_id());
	}

}

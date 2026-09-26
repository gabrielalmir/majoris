<?php

class Pdo_pgsql_insert_id_test extends CI_TestCase {

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');
	}

	// ------------------------------------------------------------------------

	protected function driver()
	{
		$db = new Mock_Database_Postgre_Pdo(array(
			'dbdriver' => 'pdo',
			'subdriver' => 'pgsql',
			'hostname' => 'db.local',
			'database' => 'app',
			'db_debug' => TRUE
		));

		// A stand-in for the PDO connection, no server is needed
		$db->conn_id = new Mock_Database_Postgre_Connection();
		return $db;
	}

	// ------------------------------------------------------------------------

	public function test_session_without_sequence_returns_zero()
	{
		$db = $this->driver();
		$db->conn_id->replies = array('SELECT LASTVAL()' => '!55000');

		$this->assertSame(0, $db->insert_id());
		$this->assertSame(array('SELECT LASTVAL()'), $db->conn_id->queries);
		$this->assertSame(array(), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_errmode_is_silent_only_for_the_statement()
	{
		$db = $this->driver();
		$db->conn_id->replies = array('SELECT LASTVAL()' => '!55000');

		$db->insert_id();

		$this->assertSame(array(PDO::ERRMODE_SILENT), $db->conn_id->query_errmodes);
		$this->assertSame(PDO::ERRMODE_EXCEPTION, $db->conn_id->errmode);
	}

	// ------------------------------------------------------------------------

	public function test_lastval_is_returned()
	{
		$db = $this->driver();
		$db->conn_id->replies = array('SELECT LASTVAL()' => '42');

		$this->assertSame('42', $db->insert_id());
		$this->assertSame(array(), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_transaction_survives_session_without_sequence()
	{
		$db = $this->driver();
		$db->conn_id->in_transaction = TRUE;
		$db->conn_id->replies = array('SELECT LASTVAL()' => '!55000');

		$this->assertSame(0, $db->insert_id());
		$this->assertSame(
			array(
				'SAVEPOINT ci_insert_id',
				'SELECT LASTVAL()',
				'ROLLBACK TO SAVEPOINT ci_insert_id',
				'RELEASE SAVEPOINT ci_insert_id'
			),
			$db->conn_id->queries
		);
		$this->assertSame(array(), $db->statements);
		$this->assertTrue($db->trans_status());
	}

	// ------------------------------------------------------------------------

	public function test_other_errors_go_through_query()
	{
		$db = $this->driver();
		$db->conn_id->replies = array('SELECT LASTVAL()' => '!08006');

		$this->assertSame('99', $db->insert_id());
		$this->assertSame(array('SELECT LASTVAL() AS ins_id'), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_refused_savepoint_goes_through_query()
	{
		$db = $this->driver();
		$db->conn_id->in_transaction = TRUE;
		$db->conn_id->replies = array('SAVEPOINT' => '!25P02');

		$this->assertSame('99', $db->insert_id());
		$this->assertSame(array('SAVEPOINT ci_insert_id'), $db->conn_id->queries);
		$this->assertSame(array('SELECT LASTVAL() AS ins_id'), $db->statements);
	}

}

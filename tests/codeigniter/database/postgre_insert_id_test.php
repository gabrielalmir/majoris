<?php

class Postgre_insert_id_test extends CI_TestCase {

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');
	}

	// ------------------------------------------------------------------------

	protected function driver()
	{
		$db = new Mock_Database_Postgre_Driver(array(
			'hostname' => 'db.local',
			'database' => 'app',
			'db_debug' => TRUE
		));

		// Statements are recorded by the mock, no connection is made
		$db->conn_id = new stdClass();
		$db->replies = array('SELECT LASTVAL() AS ins_id' => array(array('ins_id' => '99')));
		return $db;
	}

	// ------------------------------------------------------------------------

	public function test_session_without_sequence_returns_zero()
	{
		$db = $this->driver();
		$db->quiet_replies = array('SELECT LASTVAL()' => array('55000', NULL));

		$this->assertSame(0, $db->insert_id());
		$this->assertSame(array('SELECT LASTVAL()'), $db->quiet_statements);
		$this->assertSame(array(), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_lastval_is_returned()
	{
		$db = $this->driver();
		$db->quiet_replies = array('SELECT LASTVAL()' => array('00000', '42'));

		$this->assertSame(42, $db->insert_id());
		$this->assertSame(array(), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_transaction_survives_session_without_sequence()
	{
		$db = $this->driver();
		$db->transaction_block = TRUE;
		$db->quiet_replies = array('SELECT LASTVAL()' => array('55000', NULL));

		$this->assertSame(0, $db->insert_id());
		$this->assertSame(
			array(
				'SAVEPOINT ci_insert_id',
				'SELECT LASTVAL()',
				'ROLLBACK TO SAVEPOINT ci_insert_id',
				'RELEASE SAVEPOINT ci_insert_id'
			),
			$db->quiet_statements
		);
		$this->assertSame(array(), $db->statements);
		$this->assertTrue($db->trans_status());
	}

	// ------------------------------------------------------------------------

	public function test_lastval_inside_transaction_releases_the_savepoint()
	{
		$db = $this->driver();
		$db->transaction_block = TRUE;
		$db->quiet_replies = array('SELECT LASTVAL()' => array('00000', '7'));

		$this->assertSame(7, $db->insert_id());
		$this->assertSame(
			array('SAVEPOINT ci_insert_id', 'SELECT LASTVAL()', 'RELEASE SAVEPOINT ci_insert_id'),
			$db->quiet_statements
		);
	}

	// ------------------------------------------------------------------------

	public function test_other_errors_go_through_query()
	{
		$db = $this->driver();
		$db->quiet_replies = array('SELECT LASTVAL()' => array('57P01', NULL));

		$this->assertSame(99, $db->insert_id());
		$this->assertSame(array('SELECT LASTVAL() AS ins_id'), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_statement_not_sent_goes_through_query()
	{
		$db = $this->driver();
		$db->quiet_replies = array('SELECT LASTVAL()' => FALSE);

		$this->assertSame(99, $db->insert_id());
		$this->assertSame(array('SELECT LASTVAL() AS ins_id'), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_failed_transaction_goes_through_query()
	{
		$db = $this->driver();
		$db->transaction_block = NULL;

		$this->assertSame(99, $db->insert_id());
		$this->assertSame(array(), $db->quiet_statements);
		$this->assertSame(array('SELECT LASTVAL() AS ins_id'), $db->statements);
	}

	// ------------------------------------------------------------------------

	public function test_refused_savepoint_goes_through_query()
	{
		$db = $this->driver();
		$db->transaction_block = TRUE;
		$db->quiet_replies = array('SAVEPOINT' => array('25P02', NULL));

		$this->assertSame(99, $db->insert_id());
		$this->assertSame(array('SAVEPOINT ci_insert_id'), $db->quiet_statements);
		$this->assertSame(array('SELECT LASTVAL() AS ins_id'), $db->statements);
	}

}

<?php

class Sqlsrv_insert_id_test extends CI_TestCase {

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');
	}

	// ------------------------------------------------------------------------

	protected function driver()
	{
		$db = new Mock_Database_Sqlsrv_Driver(array(
			'hostname' => 'db.local',
			'database' => 'app',
			'char_set' => 'utf8'
		));

		// The INSERT batch is recorded by the mock, no connection is made
		$db->conn_id = new stdClass();
		return $db;
	}

	// ------------------------------------------------------------------------

	public function test_raw_insert_shares_the_batch()
	{
		$db = $this->driver();
		$sql = "INSERT INTO job (name) VALUES ('Report')";

		$this->assertTrue($db->query($sql));
		$this->assertSame(array($sql.'; SELECT SCOPE_IDENTITY() AS insert_id'), $db->insert_batches);
		$this->assertSame($sql, $db->last_query());
	}

	// ------------------------------------------------------------------------

	public function test_query_builder_insert_shares_the_batch()
	{
		$db = $this->driver();

		$this->assertTrue($db->insert('job', array('name' => 'Report', 'qty' => 5)));
		$this->assertCount(1, $db->insert_batches);
		$this->assertStringStartsWith('INSERT INTO ', $db->insert_batches[0]);
		$this->assertStringEndsWith("VALUES ('Report', 5); SELECT SCOPE_IDENTITY() AS insert_id", $db->insert_batches[0]);
	}

	// ------------------------------------------------------------------------

	public function test_insert_id_and_affected_rows_come_from_the_batch()
	{
		$db = $this->driver();
		$db->insert_result = array(3, '42');

		$db->query("INSERT INTO job (name) VALUES ('a'), ('b'), ('c')");

		$this->assertSame('42', $db->insert_id());
		$this->assertSame(3, $db->affected_rows());
	}

	// ------------------------------------------------------------------------

	public function test_insert_batch_adds_up_affected_rows()
	{
		$db = $this->driver();
		$db->insert_result = array(2, '7');

		$this->assertSame(2, $db->insert_batch('job', array(array('qty' => 1), array('qty' => 2))));
		$this->assertStringEndsWith('VALUES (1), (2); SELECT SCOPE_IDENTITY() AS insert_id', $db->insert_batches[0]);
		$this->assertSame('7', $db->insert_id());
	}

	// ------------------------------------------------------------------------

	public function test_insert_id_without_insert_runs_no_query()
	{
		$db = $this->driver();

		$this->assertNull($db->insert_id());
		$this->assertSame(array(), $db->queries);
	}

	// ------------------------------------------------------------------------

	public function test_trailing_semicolon_and_comment()
	{
		$this->assertSame(
			"INSERT INTO job (name) VALUES ('a'); SELECT SCOPE_IDENTITY() AS insert_id",
			$this->driver()->insert_id_batch("INSERT INTO job (name) VALUES ('a'); -- imported\n")
		);
	}

	// ------------------------------------------------------------------------

	public function test_literals_and_identifiers_are_not_statements()
	{
		$sql = "INSERT INTO job (name, [output], \"exec\") VALUES ('a; OUTPUT; EXEC b -- c', 'it''s', N'/* d */')";

		$this->assertSame($sql.'; SELECT SCOPE_IDENTITY() AS insert_id', $this->driver()->insert_id_batch($sql));
	}

	// ------------------------------------------------------------------------

	public function test_update_and_delete_are_untouched()
	{
		$db = $this->driver();

		$this->assertFalse($db->insert_id_batch($db->set('qty', 1)->where('id', 2)->get_compiled_update('job')));
		$this->assertFalse($db->insert_id_batch($db->where('id', 2)->get_compiled_delete('job')));
		$this->assertFalse($db->insert_id_batch('UPDATE job SET qty = 1'));
		$this->assertFalse($db->insert_id_batch('DELETE FROM job'));
		$this->assertFalse($db->insert_id_batch('EXEC dbo.add_job 1'));
	}

	// ------------------------------------------------------------------------

	public function test_insert_with_output_is_untouched()
	{
		$db = $this->driver();

		$this->assertFalse($db->insert_id_batch("INSERT INTO job (name) OUTPUT INSERTED.id VALUES ('a')"));
		$this->assertFalse($db->insert_id_batch("INSERT INTO job (name) OUTPUT INSERTED.id INTO @ids VALUES ('a')"));
	}

	// ------------------------------------------------------------------------

	public function test_caller_batch_is_untouched()
	{
		$db = $this->driver();

		$this->assertFalse($db->insert_id_batch("INSERT INTO job (name) VALUES ('a'); SELECT 1"));
		$this->assertFalse($db->insert_id_batch("INSERT INTO job (name) VALUES ('a');\nINSERT INTO job (name) VALUES ('b')"));
		$this->assertFalse($db->insert_id_batch('INSERT INTO job (name) EXEC dbo.job_names'));
	}

}

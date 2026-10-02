<?php

class DB_save_queries_limit_test extends CI_TestCase {

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

		// load_rdriver() does not autoload the result classes
		class_exists('CI_DB_sqlite3_result');
	}

	// ------------------------------------------------------------------------

	public function tear_down()
	{
		if (isset($this->db))
		{
			$this->db->close();
		}
	}

	// ------------------------------------------------------------------------

	protected function connect(array $config = array())
	{
		$this->db = new CI_DB_sqlite3_driver(array_merge(array(
			'database' => ':memory:',
			'dbdriver' => 'sqlite3',
			'db_debug' => FALSE
		), $config));

		$this->db->initialize();
		return $this->db;
	}

	// ------------------------------------------------------------------------

	protected function run_queries(CI_DB_driver $db)
	{
		$db->query('CREATE TABLE t (n INTEGER)');
		$db->query('INSERT INTO t (n) VALUES (1)');
		$db->query('INSERT INTO t (n) VALUES (2)');
		$db->query('SELECT n FROM t ORDER BY n');
	}

	// ------------------------------------------------------------------------

	public function test_missing_key_keeps_every_query()
	{
		$db = $this->connect();
		$this->assertSame(0, $db->save_queries_limit);

		$this->run_queries($db);

		$this->assertCount(4, $db->queries);
		$this->assertCount(4, $db->query_times);
		$this->assertSame('CREATE TABLE t (n INTEGER)', $db->queries[0]);
		$this->assertSame('SELECT n FROM t ORDER BY n', $db->last_query());
	}

	// ------------------------------------------------------------------------

	public function test_zero_keeps_every_query()
	{
		$db = $this->connect(array('save_queries_limit' => 0));

		$this->run_queries($db);

		$this->assertSame(
			array(
				'CREATE TABLE t (n INTEGER)',
				'INSERT INTO t (n) VALUES (1)',
				'INSERT INTO t (n) VALUES (2)',
				'SELECT n FROM t ORDER BY n'
			),
			$db->queries
		);
		$this->assertCount(4, $db->query_times);
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	limits_of_two
	 */
	public function test_limit_keeps_the_last_queries($limit)
	{
		$db = $this->connect(array('save_queries_limit' => $limit));

		$this->run_queries($db);

		$this->assertSame(array('INSERT INTO t (n) VALUES (2)', 'SELECT n FROM t ORDER BY n'), $db->queries);
		$this->assertCount(2, $db->query_times);
		$this->assertSame(array(0, 1), array_keys($db->query_times));
		$this->assertSame('SELECT n FROM t ORDER BY n', $db->last_query());
		$this->assertSame(4, $db->query_count);

		// A failed query is kept as well, and still is the last one;
		// SQLite3::query() warns about it before the driver reports it
		$level = error_reporting(error_reporting() & ~E_WARNING);
		$failed = $db->query('SELECT n FROM missing_table');
		error_reporting($level);

		$this->assertFalse($failed);
		$this->assertSame(array('SELECT n FROM t ORDER BY n', 'SELECT n FROM missing_table'), $db->queries);
		$this->assertSame(0, $db->query_times[1]);
		$this->assertSame('SELECT n FROM missing_table', $db->last_query());
	}

	// ------------------------------------------------------------------------

	public function limits_of_two()
	{
		return array(array(2), array('2'));
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	invalid_limits
	 */
	public function test_invalid_limit_fails_without_running_the_query($limit)
	{
		$db = $this->connect(array('save_queries_limit' => $limit));

		try
		{
			$db->query('CREATE TABLE t (n INTEGER)');
			$this->fail('The query ran with save_queries_limit '.var_export($limit, TRUE));
		}
		catch (RuntimeException $e)
		{
			$this->assertSame(
				"Invalid 'save_queries_limit' setting: expected a whole number, 0 or greater; got ".var_export($limit, TRUE).'.',
				$e->getMessage()
			);
		}

		$this->assertSame(array(), $db->queries);
		$this->assertSame(0, $db->query_count);

		// The table was not created
		$this->assertNull($db->conn_id->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 't'"));
	}

	// ------------------------------------------------------------------------

	public function invalid_limits()
	{
		return array(
			array(-1),
			array('-1'),
			array('ten'),
			array('2.5'),
			array(2.5),
			array(''),
			array(NULL),
			array(TRUE)
		);
	}

}

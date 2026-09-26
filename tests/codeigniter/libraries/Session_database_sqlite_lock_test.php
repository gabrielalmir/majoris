<?php

class Session_database_sqlite_lock_test extends CI_TestCase {

	/**
	 * @var	string
	 */
	protected $dir;

	/**
	 * @var	string
	 */
	protected $database;

	/**
	 * @var	string
	 */
	protected $child;

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');

		// load_rdriver() does not autoload the result classes
		class_exists('CI_DB_sqlite3_result');
		class_exists('CI_DB_pdo_result');

		require_once SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php';
		require_once SYSTEM_PATH.'libraries/Session/Session_driver.php';

		$this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ci_session_lock_'.getmypid().'_'.mt_rand();
		mkdir($this->dir);
		$this->database = $this->dir.DIRECTORY_SEPARATOR.'app.sqlite';

		$sqlite = new SQLite3($this->database);
		$sqlite->exec('CREATE TABLE ci_sessions (id VARCHAR(128) NOT NULL PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, timestamp INTEGER NOT NULL DEFAULT 0, data BLOB NOT NULL)');
		$sqlite->exec("INSERT INTO ci_sessions (id, ip_address, timestamp, data) VALUES ('sess1', '127.0.0.1', ".time().", 'start')");
		$sqlite->close();

		$this->child = $this->dir.DIRECTORY_SEPARATOR.'child.php';
		file_put_contents($this->child, <<<'CHILD'
<?php
$args = json_decode($argv[1], TRUE);

function &get_instance()
{
	static $CI;
	isset($CI) OR $CI = new stdClass();
	return $CI;
}

function &get_config()
{
	static $config = array();
	return $config;
}

function log_message($level, $message)
{
	fwrite(STDERR, $level.': '.$message."\n");
	return TRUE;
}

require $args['bootstrap'];

class_exists('Mock_Database_DB_Driver');
class_exists('CI_DB_sqlite3_result');
require_once SYSTEM_PATH.'libraries/Session/CI_Session_driver_interface.php';
require_once SYSTEM_PATH.'libraries/Session/Session_driver.php';

$db = new CI_DB_sqlite3_driver(array('database' => $args['database'], 'dbdriver' => 'sqlite3', 'db_debug' => FALSE));
$db->initialize();
$CI =& get_instance();
$CI->db = $db;

$params = array('save_path' => 'ci_sessions', 'match_ip' => FALSE, 'lock_wait' => $args['lock_wait'], 'lock_retry_ms' => 20);
$driver = new CI_Session_database_driver($params);
$driver->open('', 'ci_session');

echo "ready\n";
fflush(STDOUT);

$start = microtime(TRUE);
$data = $driver->read('sess1');
$result = array('read' => $data, 'waited' => microtime(TRUE) - $start);
$result['write'] = $driver->write('sess1', ($data === FALSE ? '' : $data).$args['append']);
$result['close'] = $driver->close();

echo json_encode($result)."\n";
CHILD
		);
	}

	// ------------------------------------------------------------------------

	public function tear_down()
	{
		foreach (glob($this->dir.DIRECTORY_SEPARATOR.'*') as $file)
		{
			unlink($file);
		}

		rmdir($this->dir);
	}

	// ------------------------------------------------------------------------

	public function test_lock_held_by_another_process_fails_the_read_until_released()
	{
		$driver = $this->session_driver($this->db_driver('sqlite3', $this->database));
		$this->assertSame('start', $driver->read('sess1'));

		$lock_file = $this->database.'.ci_session_'.md5('sess1').'.lock';
		$this->assertFileExists($lock_file);

		$child = $this->start_child(1, ',child');
		$result = $this->finish_child($child);

		$this->assertFalse($result['read'], $result['stderr']);
		$this->assertGreaterThanOrEqual(0.95, $result['waited']);
		$this->assertLessThan(3, $result['waited']);
		$this->assertFalse($result['write']);
		$this->assertTrue($result['close']);
		$this->assertStringContainsString('Timed out after 1 second(s)', $result['stderr']);
		$this->assertSame('start', $this->stored_data());

		$this->assertTrue($driver->close());
		$this->assertFileExists($lock_file);

		$result = $this->finish_child($this->start_child(1, ',child'));

		$this->assertSame('start', $result['read'], $result['stderr']);
		$this->assertLessThan(0.5, $result['waited']);
		$this->assertTrue($result['write']);
		$this->assertTrue($result['close']);
		$this->assertSame('start,child', $this->stored_data());
	}

	// ------------------------------------------------------------------------

	public function test_concurrent_writes_to_the_same_session_are_both_kept()
	{
		$driver = $this->session_driver($this->db_driver('sqlite3', $this->database));
		$this->assertSame('start', $driver->read('sess1'));

		// The child reads the same session while this process still holds it
		$child = $this->start_child(0, ',child');
		$this->assertSame("ready\n", fgets($child[1][1]));
		usleep(300000);

		$this->assertTrue($driver->write('sess1', 'start,parent'));
		$this->assertTrue($driver->close());

		$result = $this->finish_child($child);

		// Without the lock the child would have read "start" and written "start,child"
		$this->assertSame('start,parent', $result['read'], $result['stderr']);
		$this->assertGreaterThanOrEqual(0.2, $result['waited']);
		$this->assertTrue($result['write']);
		$this->assertTrue($result['close']);
		$this->assertSame('start,parent,child', $this->stored_data());
	}

	// ------------------------------------------------------------------------

	public function test_pdo_sqlite_locks_the_file_next_to_the_dsn_database()
	{
		$driver = $this->session_driver($this->db_driver('pdo', 'sqlite:'.$this->database));
		$this->assertSame('start', $driver->read('sess1'));

		$lock_file = $this->database.'.ci_session_'.md5('sess1').'.lock';
		$probe = fopen($lock_file, 'c');
		$this->assertFalse(flock($probe, LOCK_EX | LOCK_NB));

		$this->assertTrue($driver->write('sess1', 'start,pdo'));
		$this->assertTrue($driver->close());
		$this->assertTrue(flock($probe, LOCK_EX | LOCK_NB));
		fclose($probe);

		$this->assertSame('start,pdo', $this->stored_data());
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	databases_without_a_file
	 */
	public function test_database_without_a_file_takes_no_lock_file($dbdriver, $database)
	{
		$db = $this->db_driver($dbdriver, $database);
		$db->query('CREATE TABLE ci_sessions (id VARCHAR(128) NOT NULL PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, timestamp INTEGER NOT NULL DEFAULT 0, data BLOB NOT NULL)');

		$driver = $this->session_driver($db, array('lock_wait' => 1));
		$this->assertSame('', $driver->read('sess1'));
		$this->assertTrue($driver->write('sess1', 'memory'));
		$this->assertTrue($driver->close());

		$this->assertSame('memory', $driver->read('sess1'));
		$this->assertTrue($driver->close());

		$this->assertSame(array(), glob('*.ci_session_*.lock'));
		$this->assertSame(array(), glob($this->dir.DIRECTORY_SEPARATOR.'*.lock'));
	}

	// ------------------------------------------------------------------------

	public function databases_without_a_file()
	{
		return array(
			array('sqlite3', ':memory:'),
			array('sqlite3', ''),
			array('pdo', 'sqlite::memory:')
		);
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	string	$dbdriver	'sqlite3' or 'pdo'
	 * @param	string	$database	Database path, or the DSN for 'pdo'
	 * @return	CI_DB_sqlite3_driver|CI_DB_pdo_sqlite_driver
	 */
	protected function db_driver($dbdriver, $database)
	{
		$db = ($dbdriver === 'pdo')
			? new CI_DB_pdo_sqlite_driver(array('dsn' => $database, 'dbdriver' => 'pdo', 'subdriver' => 'sqlite', 'db_debug' => FALSE))
			: new CI_DB_sqlite3_driver(array('database' => $database, 'dbdriver' => 'sqlite3', 'db_debug' => FALSE));

		$db->initialize();
		$this->assertNotEmpty($db->conn_id);
		$this->ci_instance_var('db', $db);

		return $db;
	}

	// ------------------------------------------------------------------------

	protected function session_driver($db, array $config = array())
	{
		$params = array_merge(array(
			'save_path' => 'ci_sessions',
			'match_ip' => FALSE,
			'lock_retry_ms' => 20
		), $config);

		$driver = new CI_Session_database_driver($params);
		$this->assertTrue($driver->open('', 'ci_session'));

		return $driver;
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	int	$lock_wait	sess_lock_wait for the child
	 * @param	string	$append	Appended to the session data the child reads
	 * @return	array	Process resource and its pipes
	 */
	protected function start_child($lock_wait, $append)
	{
		$args = json_encode(array(
			'bootstrap' => PROJECT_BASE.'tests/Bootstrap.php',
			'database' => $this->database,
			'lock_wait' => $lock_wait,
			'append' => $append
		));

		$pipes = array();
		$process = proc_open(
			array(PHP_BINARY, $this->child, $args),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$this->assertIsResource($process);

		return array($process, $pipes);
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	array	$child	As returned by start_child()
	 * @return	array	The child's result, plus its stderr
	 */
	protected function finish_child(array $child)
	{
		$stdout = stream_get_contents($child[1][1]);
		$stderr = stream_get_contents($child[1][2]);
		fclose($child[1][1]);
		fclose($child[1][2]);
		$this->assertSame(0, proc_close($child[0]), $stdout.$stderr);

		$lines = explode("\n", trim($stdout));
		$result = json_decode(end($lines), TRUE);
		$this->assertIsArray($result, $stdout.$stderr);
		$result['stderr'] = $stderr;

		return $result;
	}

	// ------------------------------------------------------------------------

	protected function stored_data()
	{
		$sqlite = new SQLite3($this->database);
		$data = $sqlite->querySingle("SELECT data FROM ci_sessions WHERE id = 'sess1'");
		$sqlite->close();

		return $data;
	}

}

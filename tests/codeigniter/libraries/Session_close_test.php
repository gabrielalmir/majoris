<?php

class Session_close_test extends CI_TestCase {

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
		$this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ci_session_close_'.getmypid().'_'.mt_rand();
		mkdir($this->dir);
		mkdir($this->dir.DIRECTORY_SEPARATOR.'sessions');
		mkdir($this->dir.DIRECTORY_SEPARATOR.'application');
		$this->database = $this->dir.DIRECTORY_SEPARATOR.'app.sqlite';

		$sqlite = new SQLite3($this->database);
		$sqlite->exec('CREATE TABLE ci_sessions (id VARCHAR(128) NOT NULL PRIMARY KEY, ip_address VARCHAR(45) NOT NULL, timestamp INTEGER NOT NULL DEFAULT 0, data BLOB NOT NULL)');
		$sqlite->close();

		// session_start() needs a process that hasn't sent any output yet
		$this->child = $this->dir.DIRECTORY_SEPARATOR.'child.php';
		file_put_contents($this->child, <<<'CHILD'
<?php
$args = json_decode($argv[1], TRUE);

define('BASEPATH', $args['system']);
define('APPPATH', $args['application']);

function &get_instance()
{
	static $CI;
	isset($CI) OR $CI = new stdClass();
	return $CI;
}

function &get_config()
{
	static $config;
	isset($config) OR $config = $GLOBALS['args']['config'];
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

if ($args['config']['sess_driver'] === 'database')
{
	$db = new CI_DB_sqlite3_driver(array('database' => $args['database'], 'dbdriver' => 'sqlite3', 'db_debug' => FALSE));
	$db->initialize();
	$CI =& get_instance();
	$CI->db = $db;
}

if (isset($args['settings']))
{
	// Each value goes through config.php's 'sess_auto_close'
	$results = array();
	foreach ($args['settings'] as $value)
	{
		$config =& get_config();
		if ($value === NULL)
		{
			unset($config['sess_auto_close']);
		}
		else
		{
			$config['sess_auto_close'] = $value;
		}

		try
		{
			$session = new CI_Session();
			$results[] = array('auto_close' => $session->auto_close_enabled(), 'status' => session_status());
			$session->close();
		}
		catch (Exception $e)
		{
			$results[] = array('error' => $e->getMessage(), 'status' => session_status());
		}
	}

	echo json_encode($results)."\n";
	exit;
}

$session = new CI_Session();
$_SESSION['who'] = $args['who'];

echo json_encode(array('id' => session_id(), 'auto_close' => $session->auto_close_enabled(), 'status' => session_status()))."\n";
fflush(STDOUT);

while (($line = fgets(STDIN)) !== FALSE && trim($line) !== 'exit')
{
	if (trim($line) === 'close')
	{
		$session->close();
		$_SESSION['who'] = 'after close';
		echo json_encode(array('status' => session_status()))."\n";
		fflush(STDOUT);
	}
}
CHILD
		);
	}

	// ------------------------------------------------------------------------

	public function tear_down()
	{
		foreach (array('sessions', 'application') as $dir)
		{
			array_map('unlink', glob($this->dir.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.'*'));
			rmdir($this->dir.DIRECTORY_SEPARATOR.$dir);
		}

		array_map('unlink', glob($this->dir.DIRECTORY_SEPARATOR.'*'));
		rmdir($this->dir);
	}

	// ------------------------------------------------------------------------

	public function drivers()
	{
		return array(array('files'), array('database'));
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_close_writes_the_session_and_releases_the_lock($driver)
	{
		$child = $this->start_child($driver, array('who' => 'first'));
		$opened = $this->read_line($child);

		$this->assertSame(PHP_SESSION_ACTIVE, $opened['status']);
		$this->assertFalse($opened['auto_close']);
		$this->assertFalse($this->lock_is_free($driver, $opened['id']));
		$this->assertStringNotContainsString('first', (string) $this->stored_data($driver, $opened['id']));

		fwrite($child[1][0], "close\n");
		$this->assertSame(array('status' => PHP_SESSION_NONE), $this->read_line($child));

		// The request is still running, but the session is saved and unlocked
		$this->assertTrue($this->lock_is_free($driver, $opened['id']));
		$this->assertStringContainsString('who|s:5:"first";', $this->stored_data($driver, $opened['id']));

		// A second call has no session to close, and nothing to warn about
		fwrite($child[1][0], "close\n");
		$this->assertSame(array('status' => PHP_SESSION_NONE), $this->read_line($child));

		$stderr = $this->finish_child($child);
		$this->assertStringNotContainsString('error:', $stderr);

		// Changes made after close() are not saved
		$this->assertStringContainsString('who|s:5:"first";', $this->stored_data($driver, $opened['id']));
	}

	// ------------------------------------------------------------------------

	/**
	 * @dataProvider	drivers
	 */
	public function test_without_close_the_lock_is_held_until_the_request_ends($driver)
	{
		$child = $this->start_child($driver, array('who' => 'first'));
		$opened = $this->read_line($child);

		$this->assertFalse($opened['auto_close']);
		$this->assertFalse($this->lock_is_free($driver, $opened['id']));

		$this->finish_child($child);

		$this->assertTrue($this->lock_is_free($driver, $opened['id']));
		$this->assertStringContainsString('who|s:5:"first";', $this->stored_data($driver, $opened['id']));
	}

	// ------------------------------------------------------------------------

	public function test_close_without_an_open_session_does_nothing()
	{
		$class = new ReflectionClass('CI_Session');
		$session = $class->newInstanceWithoutConstructor();

		$this->assertSame(PHP_SESSION_NONE, session_status());
		$session->close();
		$this->assertSame(PHP_SESSION_NONE, session_status());
		$this->assertFalse($session->auto_close_enabled());
	}

	// ------------------------------------------------------------------------

	public function test_auto_close_setting()
	{
		$valid = array(NULL, TRUE, 1, '1', FALSE, 0, '0');
		$invalid = array('yes', 'true', '', 2, -1, 1.5, 'on');

		$child = $this->start_child('files', array('settings' => array_merge($valid, $invalid)));
		$results = $this->read_line($child);
		$this->finish_child($child);

		$this->assertCount(count($valid) + count($invalid), $results);

		$expected = array(FALSE, TRUE, TRUE, TRUE, FALSE, FALSE, FALSE);
		foreach ($expected as $i => $auto_close)
		{
			$this->assertSame(array('auto_close' => $auto_close, 'status' => PHP_SESSION_ACTIVE), $results[$i], var_export($valid[$i], TRUE));
		}

		// Rejected before the session is started
		foreach ($invalid as $i => $value)
		{
			$result = $results[count($valid) + $i];
			$this->assertSame(PHP_SESSION_NONE, $result['status'], var_export($value, TRUE));
			$this->assertSame(
				'Session: "sess_auto_close" must be TRUE, FALSE, 1, 0, \'1\' or \'0\'; got '.var_export($value, TRUE).'.',
				$result['error']
			);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	string	$driver	Session driver
	 * @param	array	$args	Extra child arguments
	 * @return	array	Process resource and its pipes
	 */
	protected function start_child($driver, array $args)
	{
		$args = json_encode(array_merge(array(
			'bootstrap' => PROJECT_BASE.'tests/Bootstrap.php',
			'system' => SYSTEM_PATH,
			'application' => $this->dir.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR,
			'database' => $this->database,
			'config' => array(
				'sess_driver' => $driver,
				'sess_cookie_name' => 'ci_session',
				'sess_samesite' => 'Lax',
				'sess_expiration' => 7200,
				'sess_save_path' => ($driver === 'files') ? $this->dir.DIRECTORY_SEPARATOR.'sessions' : 'ci_sessions',
				'sess_match_ip' => FALSE,
				'sess_time_to_update' => 0,
				'cookie_path' => '/',
				'cookie_domain' => '',
				'cookie_secure' => FALSE
			)
		), $args));

		$pipes = array();
		$process = proc_open(
			array(PHP_BINARY, $this->child, $args),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$this->assertIsResource($process);

		return array($process, $pipes);
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	array	$child	As returned by start_child()
	 * @return	array	The decoded line
	 */
	protected function read_line(array $child)
	{
		$line = fgets($child[1][1]);
		$result = json_decode((string) $line, TRUE);

		if ( ! is_array($result))
		{
			$this->fail('Unexpected child output: '.$line.stream_get_contents($child[1][1]).stream_get_contents($child[1][2]));
		}

		return $result;
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	array	$child	As returned by start_child()
	 * @return	string	The child's stderr
	 */
	protected function finish_child(array $child)
	{
		$status = proc_get_status($child[0]);
		if ($status['running'])
		{
			fwrite($child[1][0], "exit\n");
		}
		fclose($child[1][0]);

		$stdout = stream_get_contents($child[1][1]);
		$stderr = stream_get_contents($child[1][2]);
		fclose($child[1][1]);
		fclose($child[1][2]);

		$this->assertSame(0, proc_close($child[0]), $stdout.$stderr);
		$this->assertSame('', $stdout);

		return $stderr;
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	string	$driver
	 * @param	string	$id	Session ID
	 * @return	bool
	 */
	protected function lock_is_free($driver, $id)
	{
		$path = ($driver === 'files')
			? $this->dir.DIRECTORY_SEPARATOR.'sessions'.DIRECTORY_SEPARATOR.'ci_session'.$id
			: $this->database.'.ci_session_'.md5($id).'.lock';

		$this->assertFileExists($path);

		$probe = fopen($path, 'r');
		$free = flock($probe, LOCK_EX | LOCK_NB);
		$free && flock($probe, LOCK_UN);
		fclose($probe);

		return $free;
	}

	// ------------------------------------------------------------------------

	/**
	 * @param	string	$driver
	 * @param	string	$id	Session ID
	 * @return	string|null
	 */
	protected function stored_data($driver, $id)
	{
		if ($driver === 'files')
		{
			$data = file_get_contents($this->dir.DIRECTORY_SEPARATOR.'sessions'.DIRECTORY_SEPARATOR.'ci_session'.$id);
			return ($data === FALSE) ? NULL : $data;
		}

		$sqlite = new SQLite3($this->database);
		$statement = $sqlite->prepare('SELECT data FROM ci_sessions WHERE id = :id');
		$statement->bindValue(':id', $id, SQLITE3_TEXT);
		$row = $statement->execute()->fetchArray(SQLITE3_ASSOC);
		$sqlite->close();

		return ($row === FALSE) ? NULL : $row['data'];
	}

}

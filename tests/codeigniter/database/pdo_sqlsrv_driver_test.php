<?php

class Pdo_sqlsrv_driver_test extends CI_TestCase {

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');
	}

	// ------------------------------------------------------------------------

	protected function driver($config = array())
	{
		return new Mock_Database_Sqlsrv_Pdo(array_merge(array(
			'dbdriver' => 'pdo',
			'subdriver' => 'sqlsrv',
			'hostname' => 'db.local',
			'port' => 1433,
			'database' => 'app'
		), $config));
	}

	// ------------------------------------------------------------------------

	public function test_encrypt_default_is_no()
	{
		$this->assertSame('sqlsrv:Server=db.local,1433;Database=app;Encrypt=no', $this->driver()->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_encrypt_valid_values()
	{
		$values = array(
			'yes' => array(TRUE, 'yes', 'YES'),
			'no' => array(FALSE, 'no', 0, 1, '0', '1', '', NULL),
			'strict' => array('strict', 'STRICT'),
			'optional' => array('optional')
		);

		foreach ($values as $expected => $settings)
		{
			foreach ($settings as $setting)
			{
				$dsn = $this->driver(array('encrypt' => $setting))->dsn;
				$this->assertStringContainsString(';Encrypt='.$expected, $dsn, var_export($setting, TRUE));
			}
		}
	}

	// ------------------------------------------------------------------------

	public function test_encrypt_invalid_does_not_connect()
	{
		foreach (array('mandatory', 'true') as $setting)
		{
			$db = $this->driver(array('encrypt' => $setting));

			$this->assertStringNotContainsString('Encrypt=', $db->dsn);
			$this->assertFalse($db->db_connect(), var_export($setting, TRUE));
			$error = $db->error();
			$this->assertStringContainsString("Invalid 'encrypt' setting", $error['message']);
		}
	}

	// ------------------------------------------------------------------------

	public function test_initialize_reports_driver_message()
	{
		$db = $this->driver(array('encrypt' => 'mandatory'));

		$this->setExpectedException('RuntimeException', "Unable to connect to the database. Invalid 'encrypt' setting");
		$db->initialize();
	}

	// ------------------------------------------------------------------------

	public function test_optional_settings_absent_by_default()
	{
		$dsn = $this->driver()->dsn;

		$this->assertStringNotContainsString('TrustServerCertificate', $dsn);
		$this->assertStringNotContainsString('LoginTimeout', $dsn);
	}

	// ------------------------------------------------------------------------

	public function test_trust_server_certificate()
	{
		$this->assertStringContainsString(';TrustServerCertificate=1', $this->driver(array('trust_server_certificate' => TRUE))->dsn);
		$this->assertStringContainsString(';TrustServerCertificate=1', $this->driver(array('TrustServerCertificate' => 1))->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_login_timeout()
	{
		$this->assertStringContainsString(';LoginTimeout=5', $this->driver(array('login_timeout' => '5'))->dsn);
		$this->assertStringContainsString(';LoginTimeout=7', $this->driver(array('LoginTimeout' => 7))->dsn);
		$this->assertStringNotContainsString('LoginTimeout', $this->driver(array('login_timeout' => 'soon'))->dsn);
	}

	// ------------------------------------------------------------------------

	public function test_limit_without_order_by()
	{
		$sql = $this->driver()->limit(10, 20)->get_compiled_select('job');

		$this->assertStringContainsString('ORDER BY (SELECT NULL) OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
		$this->assertStringNotContainsString('ORDER BY 1', $sql);
	}

	// ------------------------------------------------------------------------

	public function test_limit_with_order_by()
	{
		$sql = $this->driver()->order_by('name')->limit(10)->get_compiled_select('job');

		$this->assertStringContainsString('OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
		$this->assertStringNotContainsString('(SELECT NULL)', $sql);
	}

}

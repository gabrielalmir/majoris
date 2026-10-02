<?php

class Sqlsrv_driver_test extends CI_TestCase {

	public function set_up()
	{
		// CI_DB is declared either by DB() or by the database driver mock
		class_exists('CI_DB', FALSE) OR class_exists('Mock_Database_DB_Driver');
	}

	// ------------------------------------------------------------------------

	protected function driver($config = array())
	{
		return new Mock_Database_Sqlsrv_Driver(array_merge(array(
			'hostname' => 'db.local',
			'username' => 'app',
			'password' => 'secret',
			'database' => 'app',
			'char_set' => 'utf8'
		), $config));
	}

	// ------------------------------------------------------------------------

	public function test_encrypt_default_is_no()
	{
		$options = $this->driver()->connection_options();
		$this->assertSame('no', $options['Encrypt']);
	}

	// ------------------------------------------------------------------------

	public function test_encrypt_valid_values()
	{
		$values = array(
			'yes' => array(TRUE, 'yes', 'YES'),
			'no' => array(FALSE, 'no', 'No', 0, 1, '0', '1', '', NULL),
			'strict' => array('strict', 'Strict'),
			'optional' => array('optional', 'OPTIONAL')
		);

		foreach ($values as $expected => $settings)
		{
			foreach ($settings as $setting)
			{
				$options = $this->driver(array('encrypt' => $setting))->connection_options();
				$this->assertSame($expected, $options['Encrypt'], var_export($setting, TRUE));
			}
		}
	}

	// ------------------------------------------------------------------------

	public function test_encrypt_invalid_does_not_connect()
	{
		foreach (array('mandatory', 'true') as $setting)
		{
			$db = $this->driver(array('encrypt' => $setting));

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

	public function test_port_is_appended()
	{
		$this->assertSame('db.local,1533', $this->driver(array('port' => 1533))->connection_hostname());
	}

	// ------------------------------------------------------------------------

	public function test_port_already_in_hostname()
	{
		$db = $this->driver(array('hostname' => 'db.local,1433', 'port' => 1533));
		$this->assertSame('db.local,1433', $db->connection_hostname());
	}

	// ------------------------------------------------------------------------

	public function test_hostname_without_port()
	{
		$this->assertSame('db.local', $this->driver(array('port' => ''))->connection_hostname());
	}

	// ------------------------------------------------------------------------

	public function test_optional_settings_absent_by_default()
	{
		$options = $this->driver()->connection_options();

		$this->assertArrayNotHasKey('TrustServerCertificate', $options);
		$this->assertArrayNotHasKey('LoginTimeout', $options);
	}

	// ------------------------------------------------------------------------

	public function test_trust_server_certificate()
	{
		$options = $this->driver(array('trust_server_certificate' => TRUE))->connection_options();
		$this->assertTrue($options['TrustServerCertificate']);

		$options = $this->driver(array('TrustServerCertificate' => 1))->connection_options();
		$this->assertSame(1, $options['TrustServerCertificate']);
	}

	// ------------------------------------------------------------------------

	public function test_login_timeout()
	{
		$options = $this->driver(array('login_timeout' => '5'))->connection_options();
		$this->assertSame(5, $options['LoginTimeout']);

		$options = $this->driver(array('LoginTimeout' => 7))->connection_options();
		$this->assertSame(7, $options['LoginTimeout']);

		$options = $this->driver(array('login_timeout' => 'soon'))->connection_options();
		$this->assertArrayNotHasKey('LoginTimeout', $options);
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

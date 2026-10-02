<?php

class Output_cache_test extends CI_TestCase {

	protected $cache_dir;
	protected $cache_file;
	protected $sentinel_file;
	protected $output;

	public function set_up()
	{
		$this->cache_dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'majoris_output_cache_'.getmypid();
		if ( ! is_dir($this->cache_dir))
		{
			mkdir($this->cache_dir, 0777, TRUE);
		}

		$this->sentinel_file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cachewakeup_'.getmypid();
		$this->cache_file = NULL;

		$_SERVER['REQUEST_TIME'] = time();
		unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);

		$this->output = new Mock_Core_Output_cache();
	}

	// --------------------------------------------------------------------

	public function tear_down()
	{
		if ( ! empty($this->cache_file) && file_exists($this->cache_file))
		{
			unlink($this->cache_file);
		}

		if (file_exists($this->sentinel_file))
		{
			unlink($this->sentinel_file);
		}

		if (is_dir($this->cache_dir))
		{
			rmdir($this->cache_dir);
		}
	}

	// --------------------------------------------------------------------

	public function test_display_cache_rejects_object_envelope()
	{
		$CFG = new CI_TestConfig();
		$CFG->config = array(
			'base_url' => 'http://example.com/',
			'index_page' => 'index.php',
			'cache_path' => rtrim($this->cache_dir, '/\\').DIRECTORY_SEPARATOR
		);

		$URI = new stdClass();
		$URI->uri_string = 'cached/page';

		$cache_info = new Mock_Cachewakeup(array(
			'expire' => $_SERVER['REQUEST_TIME'] + 60,
			'headers' => array(
				array('X-Cache: yes', TRUE)
			)
		));

		$this->cache_file = $CFG->item('cache_path').md5($CFG->item('base_url').$CFG->slash_item('index_page').$URI->uri_string);
		file_put_contents($this->cache_file, serialize($cache_info).'ENDCI--->cached body');

		$this->assertFalse($this->output->_display_cache($CFG, $URI));
		$this->assertFalse(file_exists($this->sentinel_file));
		$this->assertFalse($this->output->cache_header_called);
		$this->assertFalse($this->output->display_called);
	}

	// --------------------------------------------------------------------

	public function test_display_cache_skips_non_string_headers()
	{
		$CFG = new CI_TestConfig();
		$CFG->config = array(
			'base_url' => 'http://example.com/',
			'index_page' => 'index.php',
			'cache_path' => rtrim($this->cache_dir, '/\\').DIRECTORY_SEPARATOR
		);

		$URI = new stdClass();
		$URI->uri_string = 'cached/page';

		$cache_info = array(
			'expire' => $_SERVER['REQUEST_TIME'] + 60,
			'headers' => array(
				array('X-Cache: yes', TRUE),
				array('X-Skip: no', new stdClass())
			)
		);

		$this->cache_file = $CFG->item('cache_path').md5($CFG->item('base_url').$CFG->slash_item('index_page').$URI->uri_string);
		file_put_contents($this->cache_file, serialize($cache_info).'ENDCI--->cached body');

		$this->assertTrue($this->output->_display_cache($CFG, $URI));
		$this->assertTrue($this->output->cache_header_called);
		$this->assertTrue($this->output->display_called);
		$this->assertSame('cached body', $this->output->display_output);
		$this->assertEquals(1, count($this->output->headers));
		$this->assertSame(array('X-Cache: yes', TRUE), $this->output->headers[0]);
	}

}

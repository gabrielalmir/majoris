<?php

class Mock_Core_Output_cache extends CI_Output {

	public $cache_header_called = FALSE;
	public $cache_header_args = array();
	public $display_called = FALSE;
	public $display_output = NULL;

	public function set_cache_header($last_modified, $expiration)
	{
		$this->cache_header_called = TRUE;
		$this->cache_header_args = array($last_modified, $expiration);
	}

	public function _display($output = NULL)
	{
		$this->display_called = TRUE;
		$this->display_output = $output;
	}

}

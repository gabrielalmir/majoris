<?php

class Mock_Cachewakeup {

	public $data = array();

	public function __construct($data = array())
	{
		$this->data = $data;
	}

	public function __wakeup()
	{
		$sentinel = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cachewakeup_'.getmypid();
		touch($sentinel);
	}

}

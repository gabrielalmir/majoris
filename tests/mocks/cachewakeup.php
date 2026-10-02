<?php

class Mock_Cachewakeup implements ArrayAccess {

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

	public function offsetExists($offset)
	{
		return isset($this->data[$offset]);
	}

	public function offsetGet($offset)
	{
		return $this->data[$offset];
	}

	public function offsetSet($offset, $value)
	{
		if ($offset === NULL)
		{
			$this->data[] = $value;
		}
		else
		{
			$this->data[$offset] = $value;
		}
	}

	public function offsetUnset($offset)
	{
		unset($this->data[$offset]);
	}

}

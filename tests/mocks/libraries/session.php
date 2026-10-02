<?php

/**
 * Mock library to add testing features to Session driver library
 */
class Mock_Libraries_Session extends CI_Session {
	/**
	 * Simulate new page load
	 */
	public function reload()
	{
		$this->_flashdata_sweep();
		$this->_flashdata_mark();
		$this->_tempdata_sweep();
	}
}

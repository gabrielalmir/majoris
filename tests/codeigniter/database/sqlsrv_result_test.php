<?php

class Sqlsrv_result_test extends CI_TestCase {

	protected function result($scrollable)
	{
		$driver = new stdClass();
		$driver->conn_id = FALSE;
		$driver->result_id = FALSE;
		$driver->scrollable = $scrollable;

		return new Mock_Database_Sqlsrv_Result($driver);
	}

	// ------------------------------------------------------------------------

	/**
	 * 'buffered', 'static' and 'keyset' are the values of
	 * SQLSRV_CURSOR_CLIENT_BUFFERED, SQLSRV_CURSOR_STATIC and SQLSRV_CURSOR_KEYSET
	 */
	public function test_native_count_cursors()
	{
		foreach (array('buffered', 'static', 'keyset') as $scrollable)
		{
			$this->assertTrue($this->result($scrollable)->cursor_has_native_count(), $scrollable);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * 'forward' and 'dynamic' are the values of
	 * SQLSRV_CURSOR_FORWARD and SQLSRV_CURSOR_DYNAMIC
	 */
	public function test_counted_by_reading_cursors()
	{
		foreach (array('forward', 'dynamic', FALSE) as $scrollable)
		{
			$this->assertFalse($this->result($scrollable)->cursor_has_native_count(), var_export($scrollable, TRUE));
		}
	}

	// ------------------------------------------------------------------------

	public function test_forward_cursor_counts_read_rows()
	{
		$result = $this->result(FALSE);
		$result->result_array = array(array('id' => 1), array('id' => 2));

		$this->assertSame(2, $result->num_rows());
	}

}

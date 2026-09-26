<?php

class Mock_Database_Sqlsrv_Result extends CI_DB_sqlsrv_result {

	/**
	 * Expose the native row count decision
	 *
	 * @return	bool
	 */
	public function cursor_has_native_count()
	{
		return $this->_cursor_has_native_count();
	}

}

<?php

class Mock_Database_Sqlsrv_Driver extends CI_DB_sqlsrv_driver {

	/**
	 * Batches handed to _execute_insert_batch()
	 *
	 * @var	array
	 */
	public $insert_batches = array();

	/**
	 * Affected rows and insert ID the INSERT batch reports
	 *
	 * @var	array
	 */
	public $insert_result = array(1, '1');

	/**
	 * Fixed server version, so that no connection is needed
	 *
	 * @return	string
	 */
	public function version()
	{
		return '15.00.2000';
	}

	/**
	 * Expose the sqlsrv_connect() options
	 *
	 * @param	bool	$pooling
	 * @return	array|bool
	 */
	public function connection_options($pooling = FALSE)
	{
		return $this->_connection_options($pooling);
	}

	/**
	 * Expose the sqlsrv_connect() server name
	 *
	 * @return	string
	 */
	public function connection_hostname()
	{
		return $this->_connection_hostname();
	}

	/**
	 * Expose the INSERT batch built for SCOPE_IDENTITY()
	 *
	 * @param	string	$sql
	 * @return	string|bool
	 */
	public function insert_id_batch($sql)
	{
		return $this->_insert_id_batch($sql);
	}

	/**
	 * Record the batch instead of calling sqlsrv_query()
	 *
	 * @param	string	$batch
	 * @return	array
	 */
	protected function _execute_insert_batch($batch)
	{
		$this->insert_batches[] = $batch;
		return array(new stdClass(), $this->insert_result[0], $this->insert_result[1]);
	}

}

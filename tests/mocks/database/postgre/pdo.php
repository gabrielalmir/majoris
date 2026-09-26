<?php

class Mock_Database_Postgre_Pdo extends CI_DB_pdo_pgsql_driver {

	/**
	 * Statements handed to query()
	 *
	 * @var	array
	 */
	public $statements = array();

	/**
	 * Row query() answers with
	 *
	 * @var	array
	 */
	public $reply = array('ins_id' => '99');

	/**
	 * Fixed server version, so that no connection is needed
	 *
	 * @return	string
	 */
	public function version()
	{
		return '16.0';
	}

	/**
	 * Record the statement instead of running it
	 *
	 * @param	string	$sql
	 * @param	array|bool	$binds
	 * @param	bool	$return_object
	 * @return	CI_DB_result
	 */
	public function query($sql, $binds = FALSE, $return_object = NULL)
	{
		$this->statements[] = $sql;

		$result = new CI_DB_result($this);
		$result->result_object = array((object) $this->reply);
		$result->num_rows = 1;
		return $result;
	}

}

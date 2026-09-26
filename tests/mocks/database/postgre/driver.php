<?php

class Mock_Database_Postgre_Driver extends CI_DB_postgre_driver {

	/**
	 * Statements handed to query() and simple_query()
	 *
	 * @var	array
	 */
	public $statements = array();

	/**
	 * Rows query() answers with, by statement prefix
	 *
	 * Each entry is a list of rows (or FALSE for a failed query), used
	 * in order; the last one repeats.
	 *
	 * @var	array
	 */
	public $replies = array();

	/**
	 * Statements handed to _quiet_query()
	 *
	 * @var	array
	 */
	public $quiet_statements = array();

	/**
	 * What _quiet_query() answers with, by statement prefix
	 *
	 * @var	array
	 */
	public $quiet_replies = array();

	/**
	 * What _transaction_block() answers with
	 *
	 * @var	bool|null
	 */
	public $transaction_block = FALSE;

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
	 * Quote without pg_escape_literal()
	 *
	 * @param	string	$str
	 * @return	string
	 */
	public function escape($str)
	{
		return "'".str_replace("'", "''", (string) $str)."'";
	}

	/**
	 * Record the statement and answer from $replies
	 *
	 * @param	string	$sql
	 * @param	array|bool	$binds
	 * @param	bool	$return_object
	 * @return	CI_DB_result|bool
	 */
	public function query($sql, $binds = FALSE, $return_object = NULL)
	{
		$this->statements[] = $sql;

		foreach ($this->replies as $prefix => $rows)
		{
			if (strpos($sql, $prefix) !== 0)
			{
				continue;
			}

			$row = (count($rows) > 1) ? array_shift($this->replies[$prefix]) : $rows[0];
			if ($row === FALSE)
			{
				return FALSE;
			}

			$result = new CI_DB_result($this);
			$result->result_object = array((object) $row);
			$result->num_rows = 1;
			return $result;
		}

		return FALSE;
	}

	/**
	 * Record the statement
	 *
	 * @param	string	$sql
	 * @return	bool
	 */
	public function simple_query($sql)
	{
		$this->statements[] = $sql;
		return TRUE;
	}

	/**
	 * Statements recorded with the given prefix
	 *
	 * @param	string	$prefix
	 * @return	array
	 */
	public function recorded($prefix)
	{
		$recorded = array();
		foreach ($this->statements as $sql)
		{
			strpos($sql, $prefix) === 0 && $recorded[] = $sql;
		}

		return $recorded;
	}

	/**
	 * Answer from $transaction_block instead of pg_transaction_status()
	 *
	 * @return	bool|null
	 */
	protected function _transaction_block()
	{
		return $this->transaction_block;
	}

	/**
	 * Record the statement and answer from $quiet_replies
	 *
	 * @param	string	$sql
	 * @return	array|bool
	 */
	protected function _quiet_query($sql)
	{
		$this->quiet_statements[] = $sql;

		foreach ($this->quiet_replies as $prefix => $reply)
		{
			if (strpos($sql, $prefix) === 0)
			{
				return $reply;
			}
		}

		return array('00000', NULL);
	}

}

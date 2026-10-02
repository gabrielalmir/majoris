<?php

class Mock_Database_Sqlsrv_Session extends CI_DB_sqlsrv_driver {

	/**
	 * Statements handed to query()
	 *
	 * @var	array
	 */
	public $statements = array();

	/**
	 * $return_object handed to query(), in the same order as $statements
	 *
	 * @var	array
	 */
	public $return_objects = array();

	/**
	 * Rows query() answers with, by a string the statement contains
	 *
	 * Each entry is a list of rows (or FALSE for a failed query), used
	 * in order; the last one repeats.
	 *
	 * @var	array
	 */
	public $replies = array();

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
	 * Quote without the extension
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
		$this->return_objects[] = $return_object;

		foreach ($this->replies as $needle => $rows)
		{
			if (strpos($sql, $needle) === FALSE)
			{
				continue;
			}

			$row = (count($rows) > 1) ? array_shift($this->replies[$needle]) : $rows[0];
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
	 * Statements that contain the given string
	 *
	 * @param	string	$needle
	 * @return	array
	 */
	public function recorded($needle)
	{
		$recorded = array();
		foreach ($this->statements as $sql)
		{
			strpos($sql, $needle) !== FALSE && $recorded[] = $sql;
		}

		return $recorded;
	}

}

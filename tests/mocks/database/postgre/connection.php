<?php

/**
 * Stands in for the PDO connection of pdo_pgsql
 */
class Mock_Database_Postgre_Connection {

	/**
	 * PDO::ATTR_ERRMODE as configured
	 *
	 * @var	int
	 */
	public $errmode = PDO::ERRMODE_EXCEPTION;

	/**
	 * PDO::ATTR_ERRMODE at the time of each query()
	 *
	 * @var	array
	 */
	public $query_errmodes = array();

	/**
	 * Statements handed to query()
	 *
	 * @var	array
	 */
	public $queries = array();

	/**
	 * What query() answers with, by statement prefix:
	 * the first column value, or an SQLSTATE string prefixed by '!'
	 *
	 * @var	array
	 */
	public $replies = array();

	/**
	 * What inTransaction() answers with
	 *
	 * @var	bool
	 */
	public $in_transaction = FALSE;

	/**
	 * SQLSTATE of the last query()
	 *
	 * @var	string
	 */
	protected $sqlstate = '00000';

	/**
	 * PDO::getAttribute()
	 *
	 * @param	int	$attribute
	 * @return	mixed
	 */
	public function getAttribute($attribute)
	{
		return ($attribute === PDO::ATTR_ERRMODE) ? $this->errmode : NULL;
	}

	/**
	 * PDO::setAttribute()
	 *
	 * @param	int	$attribute
	 * @param	mixed	$value
	 * @return	bool
	 */
	public function setAttribute($attribute, $value)
	{
		$attribute === PDO::ATTR_ERRMODE && $this->errmode = $value;
		return TRUE;
	}

	/**
	 * PDO::inTransaction()
	 *
	 * @return	bool
	 */
	public function inTransaction()
	{
		return $this->in_transaction;
	}

	/**
	 * PDO::errorCode()
	 *
	 * @return	string
	 */
	public function errorCode()
	{
		return $this->sqlstate;
	}

	/**
	 * PDO::query(), answered from $replies
	 *
	 * @param	string	$sql
	 * @return	Mock_Database_Postgre_Statement|bool
	 */
	public function query($sql)
	{
		$this->queries[] = $sql;
		$this->query_errmodes[] = $this->errmode;
		$this->sqlstate = '00000';

		$value = FALSE;
		foreach ($this->replies as $prefix => $reply)
		{
			if (strpos($sql, $prefix) === 0)
			{
				$value = $reply;
				break;
			}
		}

		if (is_string($value) && strpos($value, '!') === 0)
		{
			$this->sqlstate = substr($value, 1);
			return FALSE;
		}

		return new Mock_Database_Postgre_Statement($value);
	}

}

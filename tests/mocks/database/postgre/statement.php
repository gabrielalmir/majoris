<?php

/**
 * Stands in for the PDOStatement of pdo_pgsql
 */
class Mock_Database_Postgre_Statement {

	/**
	 * First column of the first row, FALSE when there is no row
	 *
	 * @var	mixed
	 */
	protected $value;

	/**
	 * Class constructor
	 *
	 * @param	mixed	$value
	 * @return	void
	 */
	public function __construct($value)
	{
		$this->value = $value;
	}

	/**
	 * PDOStatement::fetchColumn()
	 *
	 * @return	mixed
	 */
	public function fetchColumn()
	{
		return $this->value;
	}

}

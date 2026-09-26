<?php

class DB_removed_driver_test extends CI_TestCase {

	public function test_mysql_points_to_mysqli()
	{
		$this->setExpectedException('RuntimeException', 'The mysql extension was removed in PHP 7; use the mysqli database driver instead.');

		Mock_Database_DB::DB('mysql://u:p@localhost/db');
	}

	// ------------------------------------------------------------------------

	public function test_mssql_points_to_sqlsrv()
	{
		$this->setExpectedException('RuntimeException', 'The mssql extension was removed in PHP 7; use the sqlsrv database driver instead.');

		Mock_Database_DB::DB('mssql://u:p@localhost/db');
	}

}

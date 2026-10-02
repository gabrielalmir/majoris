###################
Server Requirements
###################

`PHP <https://www.php.net/>`_ 7.4 through 8.5. PHP 7.4 is the floor.
Older PHP versions are not supported.

A database is required for most web application programming.
Drivers in this tree:

  - MySQL via the *mysqli* and *pdo* drivers. The old *mysql* extension
    left in PHP 7; do not select ``dbdriver`` ``mysql``.
  - SQL Server via *sqlsrv* and *pdo*. The old *mssql* extension left in
    PHP 7; do not select ``dbdriver`` ``mssql``.
  - PostgreSQL via the *postgre* and *pdo* drivers
  - SQLite via the *sqlite3* and *pdo* drivers
  - Oracle via the *oci8* and *pdo* drivers
  - CUBRID via the *cubrid* and *pdo* drivers
  - Interbase/Firebird via the *ibase* and *pdo* drivers
  - ODBC via the *odbc* and *pdo* drivers (ODBC is an abstraction layer)

``mysqli`` keeps working. The active database work in this repository is
SQL Server 2019, PostgreSQL and SQLite, described in ``MAJORIS.md``.

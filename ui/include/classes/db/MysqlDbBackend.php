<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

/**
 * Database backend class for MySQL.
 */
class MysqlDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return bool
	 */
	protected function checkDbVersionTable() {
		$table_exists = DBfetch(DBselect("SHOW TABLES LIKE 'dbversion'"));

		if (!$table_exists) {
			$this->setError(_s('Unable to determine current Zabbix database version: %1$s.',
				_s('the table "%1$s" was not found', 'dbversion')
			));

			return false;
		}

		return true;
	}

	/**
	 * Check database and table fields encoding.
	 *
	 * @return bool
	 */
	public function checkEncoding() {
		global $DB;

		return $this->checkDatabaseEncoding($DB) && $this->checkTablesEncoding($DB);
	}

	/**
	 * Check database schema encoding. On error will set warning message.
	 *
	 * @param array $DB  Array of database settings, same as global $DB.
	 *
	 * @return bool
	 */
	protected function checkDatabaseEncoding(array $DB) {
		$row = DBfetch(DBselect('SELECT default_character_set_name db_charset FROM information_schema.schemata'.
			' WHERE schema_name='.zbx_dbstr($DB['DATABASE'])
		));

		if ($row && strtoupper($row['db_charset']) != ZBX_DB_DEFAULT_CHARSET) {
			$this->setWarning(_s('Incorrect default charset for Zabbix database: %1$s.',
				_s('"%1$s" instead "%2$s"', $row['db_charset'], ZBX_DB_DEFAULT_CHARSET)
			));
			return false;
		}

		return true;
	}

	/**
	 * Check tables schema encoding. On error will set warning message.
	 *
	 * @param array $DB  Array of database settings, same as global $DB.
	 *
	 * @return bool
	 */
	protected function checkTablesEncoding(array $DB) {
		// Aliasing table_name to ensure field name is lowercase.
		$tables = DBfetchColumn(DBSelect('SELECT table_name AS table_name FROM information_schema.columns'.
			' WHERE table_schema='.zbx_dbstr($DB['DATABASE']).
				' AND '.dbConditionString('table_name', array_keys(DB::getSchema())).
				' AND '.dbConditionString('data_type', ['text', 'varchar', 'longtext']).
				' AND ('.
					' UPPER(character_set_name)!='.zbx_dbstr(ZBX_DB_DEFAULT_CHARSET).
					' OR collation_name!='.zbx_dbstr(ZBX_DB_MYSQL_DEFAULT_COLLATION).
				')'
		), 'table_name');

		if ($tables) {
			$tables = array_unique($tables);
			$this->setWarning(_n('Unsupported charset or collation for table: %1$s.',
				'Unsupported charset or collation for tables: %1$s.',
				implode(', ', $tables), implode(', ', $tables), count($tables)
			));
			return false;
		}

		return true;
	}

	/**
	* Check if database is using IEEE754 compatible double precision columns.
	*
	* @return bool
	*/
	public function isDoubleIEEE754() {
		global $DB;

		$conditions_or = [
			'(table_name=\'history\' AND column_name=\'value\')',
			'(table_name=\'trends\' AND column_name IN (\'value_min\', \'value_avg\', \'value_max\'))'
		];

		$sql =
			'SELECT COUNT(*) cnt FROM information_schema.columns'.
				' WHERE table_schema='.zbx_dbstr($DB['DATABASE']).
					' AND column_type=\'double\''.
					' AND ('.implode(' OR ', $conditions_or).')';

		$result = DBfetch(DBselect($sql));

		return (is_array($result) && array_key_exists('cnt', $result) && $result['cnt'] == 4);
	}

	/**
	 * Check is current connection contain requested cipher list.
	 *
	 * @return bool
	 */
	public function isConnectionSecure() {
		$row = DBfetch(DBselect("SHOW STATUS LIKE 'ssl_cipher'"));

		if (!$row || !$row['Value']) {
			$this->setError('Error connecting to database. Empty cipher.');
			return false;
		}

		return true;
	}

	/**
	 * Create connection to database server.
	 *
	 * @param string $host         Host name.
	 * @param string $port         Port.
	 * @param string $user         User name.
	 * @param string $password     Password.
	 * @param string $dbname       Database name.
	 * @param string $schema       DB schema.
	 *
	 * @param
	 * @return resource|null
	 */
	public function connect($host, $port, $user, $password, $dbname, $schema) {
		$resource = mysqli_init();
		$tls_mode = null;

		if ($this->tls_encryption) {
			$cipher_suit = ($this->tls_cipher_list === '') ? null : $this->tls_cipher_list;
			$resource->ssl_set($this->tls_key_file, $this->tls_cert_file, $this->tls_ca_file, null, $cipher_suit);

			$tls_mode = MYSQLI_CLIENT_SSL;
		}

		@$resource->real_connect($host, $user, $password, $dbname, $port, null, $tls_mode);

		if ($resource->error) {
			$this->setError($resource->error);
			return null;
		}

		if ($resource->errno) {
			$this->setError('Database error code '.$resource->errno);
			return null;
		}

		if ($resource->autocommit(true) === false) {
			$this->setError('Error setting auto commit.');
			return null;
		}

		return $resource;
	}

	/**
	 * Initialize connection.
	 *
	 * @return bool
	 */
	public function init() {
		DBexecute('SET NAMES utf8');
	}
}

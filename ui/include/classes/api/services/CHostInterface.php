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
 * Class containing methods for operations with host interfaces.
 */
class CHostInterface extends CApiService {

	protected $tableName = 'interface';
	protected $tableAlias = 'hi';
	protected $sortColumns = ['interfaceid', 'dns', 'ip'];

	/**
	 * Get interface data.
	 *
	 * @param array  $options
	 * @param array  $options['hostids']		Interface IDs
	 * @param bool   $options['editable']		only with read-write permission. Ignored for SuperAdmins
	 * @param bool   $options['selectHosts']	select Interface hosts
	 * @param bool   $options['selectItems']	select Items
	 * @param int    $options['count']			count Interfaces, returned column name is rowscount
	 * @param string $options['pattern']		search hosts by pattern in Interface name
	 * @param int    $options['limit']			limit selection
	 * @param string $options['sortfield']		field to sort by
	 * @param string $options['sortorder']		sort order
	 *
	 * @return array|boolean Interface data as array or false if error
	 */
	public function get(array $options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['interface' => 'hi.interfaceid'],
			'from'		=> ['interface' => 'interface hi'],
			'where'		=> [],
			'group'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'groupids'					=> null,
			'hostids'					=> null,
			'interfaceids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'editable'					=> false,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_EXTEND,
			'selectHosts'				=> null,
			'selectItems'				=> null,
			'countOutput'				=> false,
			'groupCount'				=> false,
			'preservekeys'				=> false,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		];
		$options = zbx_array_merge($defOptions, $options);

		// editable + PERMISSION CHECK
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN && !$options['nopermissions']) {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ;
			$userGroups = getUserGroupsByUserId(self::$userData['userid']);

			$sqlParts['where'][] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE hi.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
					' AND MAX(r.permission)>='.zbx_dbstr($permission).
				')';
		}

		// interfaceids
		if (!is_null($options['interfaceids'])) {
			zbx_value2array($options['interfaceids']);
			$sqlParts['where']['interfaceid'] = dbConditionInt('hi.interfaceid', $options['interfaceids']);
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$sqlParts['where']['hostid'] = dbConditionInt('hi.hostid', $options['hostids']);

			if ($options['groupCount']) {
				$sqlParts['group']['hostid'] = 'hi.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('i.itemid', $options['itemids']);
			$sqlParts['where']['hi'] = 'hi.interfaceid=i.interfaceid';
		}

		// triggerids
		if (!is_null($options['triggerids'])) {
			zbx_value2array($options['triggerids']);

			$sqlParts['from']['functions'] = 'functions f';
			$sqlParts['from']['items'] = 'items i';
			$sqlParts['where'][] = dbConditionInt('f.triggerid', $options['triggerids']);
			$sqlParts['where']['hi'] = 'hi.hostid=i.hostid';
			$sqlParts['where']['fi'] = 'f.itemid=i.itemid';
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('interface hi', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('interface hi', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		if ($this->outputIsRequested('details', $options['output'])) {
			$sqlParts['left_join']['interface_snmp'] = ['from' => 'interface_snmp his',
				'on' => 'his.interfaceid=hi.interfaceid'
			];
			$sqlParts['left_table'] = 'interface';
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($interface = DBfetch($res)) {
			if ($options['countOutput']) {
				if ($options['groupCount']) {
					$result[] = $interface;
				}
				else {
					$result = $interface['rowscount'];
				}
			}
			else {
				$result[$interface['interfaceid']] = $interface;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		if ($result) {
			$result = $this->addRelatedObjects($options, $result);
			$result = $this->unsetExtraFields($result, ['hostid'], $options['output']);
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		// Moving additional fields to separate object.
		if ($this->outputIsRequested('details', $options['output'])) {
			foreach ($result as &$value) {
				$snmp_fields = ['version', 'bulk', 'community', 'securityname', 'securitylevel', 'authpassphrase',
					'privpassphrase', 'authprotocol', 'privprotocol', 'contextname'
				];

				$interface_type = $value['type'];

				if (!$this->outputIsRequested('type', $options['output'])) {
					unset($value['type']);
				}

				$details = [];

				// Handle SNMP related fields.
				if ($interface_type == INTERFACE_TYPE_SNMP) {
					foreach ($snmp_fields as $field_name) {
						$details[$field_name] = $value[$field_name];
						unset($value[$field_name]);
					}

					if ($details['version'] == SNMP_V1 || $details['version'] == SNMP_V2C) {
						foreach (['securityname', 'securitylevel', 'authpassphrase', 'privpassphrase', 'authprotocol',
								'privprotocol', 'contextname'] as $snmp_field_name) {
							unset($details[$snmp_field_name]);
						}
					}
					else {
						unset($details['community']);
					}
				}
				else {
					foreach ($snmp_fields as $field_name) {
						unset($value[$field_name]);
					}
				}

				$value['details'] = $details;
			}
			unset($value);
		}

		return $result;
	}

	/**
	 * Check interfaces input.
	 *
	 * @param array  $interfaces
	 * @param string $method
	 */
	public function checkInput(array &$interfaces, $method) {
		$update = ($method == 'update');

		// permissions
		if ($update) {
			$interfaceDBfields = ['interfaceid' => null];
			$dbInterfaces = $this->get([
				'output' => API_OUTPUT_EXTEND,
				'interfaceids' => zbx_objectValues($interfaces, 'interfaceid'),
				'editable' => true,
				'preservekeys' => true
			]);
		}
		else {
			$interfaceDBfields = [
				'hostid' => null,
				'ip' => null,
				'dns' => null,
				'useip' => null,
				'port' => null,
				'main' => null
			];
		}

		$dbHosts = API::Host()->get([
			'output' => ['host'],
			'hostids' => zbx_objectValues($interfaces, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$dbProxies = API::Proxy()->get([
			'output' => ['host'],
			'proxyids' => zbx_objectValues($interfaces, 'hostid'),
			'editable' => true,
			'preservekeys' => true
		]);

		$check_have_items = [];
		foreach ($interfaces as &$interface) {
			if (!check_db_fields($interfaceDBfields, $interface)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if ($update) {
				if (!isset($dbInterfaces[$interface['interfaceid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				$dbInterface = $dbInterfaces[$interface['interfaceid']];
				if (isset($interface['hostid']) && bccomp($dbInterface['hostid'], $interface['hostid']) != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot switch host for interface.'));
				}

				if (array_key_exists('type', $interface) && $interface['type'] != $dbInterface['type']) {
					$check_have_items[] = $interface['interfaceid'];
				}

				$interface['hostid'] = $dbInterface['hostid'];

				// we check all fields on "updated" interface
				$updInterface = $interface;
				$interface = zbx_array_merge($dbInterface, $interface);
			}
			else {
				if (!isset($dbHosts[$interface['hostid']]) && !isset($dbProxies[$interface['hostid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				if (isset($dbProxies[$interface['hostid']])) {
					$interface['type'] = INTERFACE_TYPE_UNKNOWN;
				}
				elseif (!isset($interface['type'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}
			}

			if (zbx_empty($interface['ip']) && zbx_empty($interface['dns'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('IP and DNS cannot be empty for host interface.'));
			}

			if ($interface['useip'] == INTERFACE_USE_IP && zbx_empty($interface['ip'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Interface with DNS "%1$s" cannot have empty IP address.', $interface['dns']));
			}

			if ($interface['useip'] == INTERFACE_USE_DNS && zbx_empty($interface['dns'])) {
				if ($dbHosts && !empty($dbHosts[$interface['hostid']]['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Interface with IP "%1$s" cannot have empty DNS name while having "Use DNS" property on "%2$s".',
							$interface['ip'],
							$dbHosts[$interface['hostid']]['host']
					));
				}
				elseif ($dbProxies && !empty($dbProxies[$interface['hostid']]['host'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Interface with IP "%1$s" cannot have empty DNS name while having "Use DNS" property on "%2$s".',
							$interface['ip'],
							$dbProxies[$interface['hostid']]['host']
					));
				}
				else {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Interface with IP "%1$s" cannot have empty DNS name.', $interface['ip']));
				}
			}

			if (isset($interface['dns'])) {
				$this->checkDns($interface);
			}
			if (isset($interface['ip'])) {
				$this->checkIp($interface);
			}
			if (isset($interface['port']) || $method == 'create') {
				$this->checkPort($interface);
			}

			if ($update) {
				$interface = $updInterface;
			}
		}
		unset($interface);

		// check if any of the affected hosts are discovered
		if ($update) {
			$interfaces = $this->extendObjects('interface', $interfaces, ['hostid']);

			if ($check_have_items) {
				$this->checkIfInterfaceHasItems($check_have_items);
			}
		}
		$this->checkValidator(zbx_objectValues($interfaces, 'hostid'), new CHostNormalValidator([
			'message' => _('Cannot update interface for discovered host "%1$s".')
		]));
	}

	/**
	 * Check SNMP related inputs.
	 *
	 * @param array $interfaces
	 */
	protected function checkSnmpInput(array $interfaces) {
		foreach ($interfaces as $interface) {
			if (!array_key_exists('type', $interface) || $interface['type'] != INTERFACE_TYPE_SNMP) {
				continue;
			}

			if (!array_key_exists('details', $interface)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			$this->checkSnmpVersion($interface);

			$this->checkSnmpCommunity($interface);

			$this->checkSnmpBulk($interface);

			$this->checkSnmpSecurityLevel($interface);

			$this->checkSnmpAuthProtocol($interface);

			$this->checkSnmpPrivProtocol($interface);
		}
	}

	/**
	 * Sanitize SNMP fields by version.
	 *
	 * @param array $interfaces
	 *
	 * @return array
	 */
	protected function sanitizeSnmpFields(array $interfaces): array {
		$default_fields = [
			'community' => '',
			'securityname' => '',
			'securitylevel' => DB::getDefault('interface_snmp', 'securitylevel'),
			'authpassphrase' => '',
			'privpassphrase' => '',
			'authprotocol' => DB::getDefault('interface_snmp', 'authprotocol'),
			'privprotocol' => DB::getDefault('interface_snmp', 'privprotocol'),
			'contextname' => ''
		];

		foreach ($interfaces as &$interface) {
			if ($interface['version'] == SNMP_V1 || $interface['version'] == SNMP_V2C) {
				unset($interface['securityname'], $interface['securitylevel'], $interface['authpassphrase'],
					$interface['privpassphrase'], $interface['authprotocol'], $interface['privprotocol'],
					$interface['contextname']
				);
			}
			else {
				unset($interface['community']);
			}

			$interface = $interface + $default_fields;
		}

		return $interfaces;
	}

	/**
	 * Create SNMP interfaces.
	 *
	 * @param array $interfaces
	 */
	protected function createSnmpInterfaceDetails(array $interfaces) {
		if (count($interfaces)) {
			if (count(array_column($interfaces, 'interfaceid')) != count($interfaces)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			$interfaces = $this->sanitizeSnmpFields($interfaces);

			foreach ($interfaces as $interface) {
				DB::insert('interface_snmp', [$interface], false);
			}
		}
	}

	/**
	 * Add interfaces.
	 *
	 * @param array $interfaces  multidimensional array with Interfaces data
	 *
	 * @return array
	 */
	public function create(array $interfaces) {
		$interfaces = zbx_toArray($interfaces);

		$this->checkInput($interfaces, __FUNCTION__);
		$this->checkSnmpInput($interfaces);
		$this->checkMainInterfacesOnCreate($interfaces);

		$interfaceids = DB::insert('interface', $interfaces);

		$snmp_interfaces = [];
		foreach ($interfaceids as $key => $id) {
			if ($interfaces[$key]['type'] == INTERFACE_TYPE_SNMP) {
				$snmp_interfaces[] = ['interfaceid' => $id] + $interfaces[$key]['details'];
			}
		}

		$this->createSnmpInterfaceDetails($snmp_interfaces);

		return ['interfaceids' => $interfaceids];
	}

	protected function updateInterfaces(array $interfaces): bool {
		$data = [];

		foreach ($interfaces as $interface) {
			$data[] = [
				'values' => $interface,
				'where' => ['interfaceid' => $interface['interfaceid']]
			];
		}

		DB::update('interface', $data);

		return true;
	}

	protected function updateInterfaceDetails(array $interfaces): bool {
		$db_interfaces = $this->get([
			'output' => ['type', 'details'],
			'interfaceids' => array_column($interfaces, 'interfaceid'),
			'preservekeys' => true
		]);
		DB::delete('interface_snmp', ['interfaceid' => array_column($interfaces, 'interfaceid')]);

		$snmp_interfaces = [];
		foreach ($interfaces as $interface) {
			$interfaceid = $interface['interfaceid'];

			// Check new interface type or, if interface type not present, check type from db.
			if ((!array_key_exists('type', $interface) && $db_interfaces[$interfaceid]['type'] != INTERFACE_TYPE_SNMP)
					|| (array_key_exists('type', $interface) && $interface['type'] != INTERFACE_TYPE_SNMP)) {
				continue;
			}
			else {
				// Type is required for SNMP validation.
				$interface['type'] = INTERFACE_TYPE_SNMP;
			}

			// Merge details with db values or set only vaules from db.
			$interface['details'] = array_key_exists('details', $interface)
				? $interface['details'] + $db_interfaces[$interfaceid]['details']
				: $db_interfaces[$interfaceid]['details'];

			$this->checkSnmpInput([$interface]);

			$snmp_interfaces[] = ['interfaceid' => $interfaceid] + $interface['details'];
		}

		$this->createSnmpInterfaceDetails($snmp_interfaces);

		return true;
	}

	/**
	 * Update interfaces.
	 *
	 * @param array $interfaces   multidimensional array with Interfaces data
	 *
	 * @return array
	 */
	public function update(array $interfaces) {
		$interfaces = zbx_toArray($interfaces);

		$this->checkInput($interfaces, __FUNCTION__);
		$this->checkMainInterfacesOnUpdate($interfaces);

		$this->updateInterfaces($interfaces);

		$this->updateInterfaceDetails($interfaces);

		return ['interfaceids' => array_column($interfaces, 'interfaceid')];
	}

	/**
	 * Delete interfaces.
	 * Interface cannot be deleted if it's main interface and exists other interface of same type on same host.
	 * Interface cannot be deleted if it is used in items.
	 *
	 * @param array $interfaceids
	 *
	 * @return array
	 */
	public function delete(array $interfaceids) {
		if (empty($interfaceids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}

		$dbInterfaces = $this->get([
			'output' => API_OUTPUT_EXTEND,
			'interfaceids' => $interfaceids,
			'editable' => true,
			'preservekeys' => true
		]);
		foreach ($interfaceids as $interfaceId) {
			if (!isset($dbInterfaces[$interfaceId])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
			}
		}

		$this->checkMainInterfacesOnDelete($interfaceids);

		DB::delete('interface', ['interfaceid' => $interfaceids]);
		DB::delete('interface_snmp', ['interfaceid' => $interfaceids]);

		return ['interfaceids' => $interfaceids];
	}

	public function massAdd(array $data) {
		$interfaces = zbx_toArray($data['interfaces']);
		$hosts = zbx_toArray($data['hosts']);

		$insertData = [];
		foreach ($interfaces as $interface) {
			foreach ($hosts as $host) {
				$newInterface = $interface;
				$newInterface['hostid'] = $host['hostid'];

				$insertData[] = $newInterface;
			}
		}

		$interfaceIds = $this->create($insertData);

		return ['interfaceids' => $interfaceIds];
	}

	protected function validateMassRemove(array $data) {
		// check permissions
		$this->checkHostPermissions($data['hostids']);

		// check interfaces
		foreach ($data['interfaces'] as $interface) {
			if (!isset($interface['dns']) || !isset($interface['ip']) || !isset($interface['port'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			$this->checkDns($interface);
			$this->checkIp($interface);
			$this->checkPort($interface);

			// check main interfaces
			$interfacesToRemove = API::getApiService()->select($this->tableName(), [
				'output' => ['interfaceid'],
				'filter' => [
					'hostid' => $data['hostids'],
					'ip' => $interface['ip'],
					'dns' => $interface['dns'],
					'port' => $interface['port']
				]
			]);
			if ($interfacesToRemove) {
				$this->checkMainInterfacesOnDelete(zbx_objectValues($interfacesToRemove, 'interfaceid'));
			}
		}
	}

	/**
	 * Remove hosts from interfaces.
	 *
	 * @param array $data
	 * @param array $data['interfaceids']
	 * @param array $data['hostids']
	 * @param array $data['templateids']
	 *
	 * @return array
	 */
	public function massRemove(array $data) {
		$data['interfaces'] = zbx_toArray($data['interfaces']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		$this->validateMassRemove($data);

		$interfaceIds = [];
		foreach ($data['interfaces'] as $interface) {
			$interfaces = $this->get([
				'output' => ['interfaceid'],
				'filter' => [
					'hostid' => $data['hostids'],
					'ip' => $interface['ip'],
					'dns' => $interface['dns'],
					'port' => $interface['port']
				],
				'editable' => true,
				'preservekeys' => true
			]);

			if ($interfaces) {
				$interfaceIds = array_merge($interfaceIds, array_keys($interfaces));
			}
		}

		if ($interfaceIds) {
			$interfaceIds = array_keys(array_flip($interfaceIds));
			DB::delete('interface', ['interfaceid' => $interfaceIds]);
		}

		return ['interfaceids' => $interfaceIds];
	}

	/**
	 * Replace existing interfaces with input interfaces.
	 *
	 * @param array $host
	 */
	public function replaceHostInterfaces(array $host) {
		if (isset($host['interfaces']) && !is_null($host['interfaces'])) {
			$host['interfaces'] = zbx_toArray($host['interfaces']);

			$this->checkHostInterfaces($host['interfaces'], $host['hostid']);

			$interfaces_delete = DB::select('interface', [
				'output' => [],
				'filter' => ['hostid' => $host['hostid']],
				'preservekeys' => true
			]);

			$interfaces_add = [];
			$interfaces_update = [];

			foreach ($host['interfaces'] as $interface) {
				$interface['hostid'] = $host['hostid'];

				if (!array_key_exists('interfaceid', $interface)) {
					$interfaces_add[] = $interface;
				}
				elseif (array_key_exists($interface['interfaceid'], $interfaces_delete)) {
					$interfaces_update[] = $interface;
					unset($interfaces_delete[$interface['interfaceid']]);
				}
			}

			if ($interfaces_update) {
				$this->checkInput($interfaces_update, 'update');

				$this->updateInterfaces($interfaces_update);

				$this->updateInterfaceDetails($interfaces_update);
			}

			if ($interfaces_add) {
				$this->checkInput($interfaces_add, 'create');
				$interfaceids = DB::insert('interface', $interfaces_add);

				$this->checkSnmpInput($interfaces_add);

				$snmp_interfaces = [];
				foreach ($interfaceids as $key => $id) {
					if ($interfaces_add[$key]['type'] == INTERFACE_TYPE_SNMP) {
						$snmp_interfaces[] = ['interfaceid' => $id] + $interfaces_add[$key]['details'];
					}
				}

				$this->createSnmpInterfaceDetails($snmp_interfaces);

				foreach ($host['interfaces'] as &$interface) {
					if (!array_key_exists('interfaceid', $interface)) {
						$interface['interfaceid'] = array_shift($interfaceids);
					}
				}
				unset($interface);
			}

			if ($interfaces_delete) {
				$this->delete(array_keys($interfaces_delete));
			}

			return ['interfaceids' => array_column($host['interfaces'], 'interfaceid')];
		}

		return ['interfaceids' => []];
	}

	/**
	 * Validates the "dns" field.
	 *
	 * @throws APIException if the field is invalid.
	 *
	 * @param array $interface
	 * @param string $interface['dns']
	 */
	protected function checkDns(array $interface) {
		if ($interface['dns'] === '') {
			return;
		}

		$user_macro_parser = new CUserMacroParser();

		if (!preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/', $interface['dns'])
				&& $user_macro_parser->parse($interface['dns']) != CParser::PARSE_SUCCESS) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect interface DNS parameter "%1$s" provided.', $interface['dns'])
			);
		}
	}

	/**
	 * Validates the "ip" field.
	 *
	 * @throws APIException if the field is invalid.
	 *
	 * @param array $interface
	 * @param string $interface['ip']
	 */
	protected function checkIp(array $interface) {
		if ($interface['ip'] === '') {
			return;
		}

		$user_macro_parser = new CUserMacroParser();

		if (preg_match('/^'.ZBX_PREG_MACRO_NAME_FORMAT.'$/', $interface['ip'])
				|| $user_macro_parser->parse($interface['ip']) == CParser::PARSE_SUCCESS) {
			return;
		}

		$ip_parser = new CIPParser(['v6' => ZBX_HAVE_IPV6]);

		if ($ip_parser->parse($interface['ip']) != CParser::PARSE_SUCCESS) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid IP address "%1$s".', $interface['ip']));
		}
	}

	/**
	 * Validates the "port" field.
	 *
	 * @throws APIException if the field is empty or invalid.
	 *
	 * @param array $interface
	 */
	protected function checkPort(array $interface) {
		if (!isset($interface['port']) || zbx_empty($interface['port'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Port cannot be empty for host interface.'));
		}
		elseif (!validatePortNumberOrMacro($interface['port'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect interface port "%1$s" provided.', $interface['port'])
			);
		}
	}

	/**
	 * Checks if the current user has access to the given hosts. Assumes the "hostid" field is valid.
	 *
	 * @throws APIException if the user doesn't have write permissions for the given hosts
	 *
	 * @param array $hostIds	an array of host IDs
	 */
	protected function checkHostPermissions(array $hostIds) {
		if ($hostids) {
			$hostids = array_unique($hostids);

			$count = API::Host()->get([
				'countOutput' => true,
				'hostids' => $hostids,
				'editable' => true
			]);

			if ($count != count($hostids)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}
		}
	}

	private function checkHostInterfaces(array $interfaces, $hostid) {
		$interfacesWithMissingData = [];

		foreach ($interfaces as $interface) {
			if (!isset($interface['type'], $interface['main'])) {
				$interfacesWithMissingData[] = $interface['interfaceid'];
			}
		}

		if ($interfacesWithMissingData) {
			$dbInterfaces = API::HostInterface()->get([
				'interfaceids' => $interfacesWithMissingData,
				'output' => ['main', 'type'],
				'preservekeys' => true,
				'nopermissions' => true
			]);
		}

		foreach ($interfaces as $id => $interface) {
			if (isset($interface['interfaceid']) && isset($dbInterfaces[$interface['interfaceid']])) {
				$interfaces[$id] = array_merge($interface, $dbInterfaces[$interface['interfaceid']]);
			}
			$interfaces[$id]['hostid'] = $hostid;
		}

		$this->checkMainInterfaces($interfaces);
	}

	private function checkMainInterfacesOnCreate(array $interfaces) {
		$hostIds = [];
		foreach ($interfaces as $interface) {
			$hostIds[$interface['hostid']] = $interface['hostid'];
		}

		$dbInterfaces = API::HostInterface()->get([
			'hostids' => $hostIds,
			'output' => ['hostid', 'main', 'type'],
			'preservekeys' => true,
			'nopermissions' => true
		]);
		$interfaces = array_merge($dbInterfaces, $interfaces);

		$this->checkMainInterfaces($interfaces);
	}

	/**
	 * Prepares data to validate main interface for every interface type. Executes main interface validation.
	 *
	 * @param array $interfaces                     Array of interfaces to validate.
	 * @param int   $interfaces[]['hostid']         Updated interface's hostid.
	 * @param int   $interfaces[]['interfaceid']    Updated interface's interfaceid.
	 *
	 * @throws APIException
	 */
	private function checkMainInterfacesOnUpdate(array $interfaces) {
		$hostids = array_keys(array_flip(zbx_objectValues($interfaces, 'hostid')));

		$dbInterfaces = API::HostInterface()->get([
			'hostids' => $hostids,
			'output' => ['hostid', 'main', 'type'],
			'preservekeys' => true,
			'nopermissions' => true
		]);

		// update interfaces from DB with data that will be updated.
		foreach ($interfaces as $interface) {
			if (isset($dbInterfaces[$interface['interfaceid']])) {
				$dbInterfaces[$interface['interfaceid']] = array_merge(
					$dbInterfaces[$interface['interfaceid']],
					$interface
				);
			}
		}

		$this->checkMainInterfaces($dbInterfaces);
	}

	private function checkMainInterfacesOnDelete(array $interfaceIds) {
		$this->checkIfInterfaceHasItems($interfaceIds);

		$hostids = [];
		$dbResult = DBselect('SELECT DISTINCT i.hostid FROM interface i WHERE '.dbConditionInt('i.interfaceid', $interfaceIds));
		while ($hostData = DBfetch($dbResult)) {
			$hostids[$hostData['hostid']] = $hostData['hostid'];
		}

		$dbInterfaces = API::HostInterface()->get([
			'hostids' => $hostids,
			'output' => ['hostid', 'main', 'type'],
			'preservekeys' => true,
			'nopermissions' => true
		]);

		foreach ($interfaceIds as $interfaceId) {
			unset($dbInterfaces[$interfaceId]);
		}

		$this->checkMainInterfaces($dbInterfaces);
	}

	/**
	 * Check if main interfaces are correctly set for every interface type.
	 * Each host must either have only one main interface for each interface type, or have no interface of that type at all.
	 *
	 * @param array $interfaces
	 */
	private function checkMainInterfaces(array $interfaces) {
		$interfaceTypes = [];
		foreach ($interfaces as $interface) {
			if (!isset($interfaceTypes[$interface['hostid']])) {
				$interfaceTypes[$interface['hostid']] = [];
			}

			if (!isset($interfaceTypes[$interface['hostid']][$interface['type']])) {
				$interfaceTypes[$interface['hostid']][$interface['type']] = ['main' => 0, 'all' => 0];
			}

			if ($interface['main'] == INTERFACE_PRIMARY) {
				$interfaceTypes[$interface['hostid']][$interface['type']]['main']++;
			}
			else {
				$interfaceTypes[$interface['hostid']][$interface['type']]['all']++;
			}
		}

		foreach ($interfaceTypes as $interfaceHostId => $interfaceType) {
			foreach ($interfaceType as $type => $counters) {
				if ($counters['all'] && !$counters['main']) {
					$host = API::Host()->get([
						'hostids' => $interfaceHostId,
						'output' => ['name'],
						'preservekeys' => true,
						'nopermissions' => true
					]);
					$host = reset($host);

					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('No default interface for "%1$s" type on "%2$s".', hostInterfaceTypeNumToName($type), $host['name']));
				}

				if ($counters['main'] > 1) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Host cannot have more than one default interface of the same type.'));
				}
			}
		}
	}

	private function checkIfInterfaceHasItems(array $interfaceIds) {
		$items = API::Item()->get([
			'output' => ['name'],
			'selectHosts' => ['name'],
			'interfaceids' => $interfaceIds,
			'preservekeys' => true,
			'nopermissions' => true,
			'limit' => 1
		]);

		foreach ($items as $item) {
			$host = reset($item['hosts']);

			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Interface is linked to item "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Check if SNMP version is valid. Valid versions: SNMP_V1, SNMP_V2C, SNMP_V3.
	 *
	 * @param array $interface
	 *
	 * @throws APIException if "version" value is incorrect.
	 */
	protected function checkSnmpVersion(array $interface) {
		if (!array_key_exists('version', $interface['details'])
				|| !in_array($interface['details']['version'], [SNMP_V1, SNMP_V2C, SNMP_V3])) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}
	}

	/**
	 * Check SNMP community. For SNMPv1 and SNMPv2c it required.
	 *
	 * @param array $interface
	 *
	 * @throws APIException if "community" value is incorrect.
	 */
	protected function checkSnmpCommunity(array $interface) {
		if (($interface['details']['version'] == SNMP_V1 || $interface['details']['version'] == SNMP_V2C)
				&& (!array_key_exists('community', $interface['details'])
					|| $interface['details']['community'] === '')) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}
	}

	/**
	 * Validates SNMP interface "bulk" field.
	 *
	 * @param array $interface
	 *
	 * @throws APIException if "bulk" value is incorrect.
	 */
	protected function checkSnmpBulk(array $interface) {
		if ($interface['type'] !== null && (($interface['type'] != INTERFACE_TYPE_SNMP
					&& isset($interface['details']['bulk']) && $interface['details']['bulk'] != SNMP_BULK_ENABLED)
					|| ($interface['type'] == INTERFACE_TYPE_SNMP && isset($interface['details']['bulk'])
						&& (zbx_empty($interface['details']['bulk'])
							|| ($interface['details']['bulk'] != SNMP_BULK_DISABLED
							&& $interface['details']['bulk'] != SNMP_BULK_ENABLED))))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect bulk value for interface.'));
		}
	}

	/**
	 * Check SNMP Security level field.
	 *
	 * @param array $interface
	 * @param array $interface['details']
	 * @param array $interface['details']['version']        SNMP version
	 * @param array $interface['details']['securitylevel']  SNMP security level
	 *
	 * @throws APIException if "securitylevel" value is incorrect.
	 */
	protected function checkSnmpSecurityLevel(array $interface) {
		if ($interface['details']['version'] == SNMP_V3 && (array_key_exists('securitylevel', $interface['details'])
					&& !in_array($interface['details']['securitylevel'], [ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,
						ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV]))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}
	}

	/**
	 * Check SNMP authentication  protocol.
	 *
	 * @param array $interface
	 * @param array $interface['details']
	 * @param array $interface['details']['version']       SNMP version
	 * @param array $interface['details']['authprotocol']  SNMP authentication protocol
	 *
	 * @throws APIException if "authprotocol" value is incorrect.
	 */
	protected function checkSnmpAuthProtocol(array $interface) {
		if ($interface['details']['version'] == SNMP_V3 && (array_key_exists('authprotocol', $interface['details'])
					&& !in_array($interface['details']['authprotocol'], [ITEM_AUTHPROTOCOL_MD5,
						ITEM_AUTHPROTOCOL_SHA]))) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
		}
	}

	/**
	 * Check SNMP Privacy protocol.
	 *
	 * @param array $interface
	 * @param array $interface['details']
	 * @param array $interface['details']['version']       SNMP version
	 * @param array $interface['details']['privprotocol']  SNMP privacy protocol
	 *
	 * @throws APIException if "privprotocol" value is incorrect.
	 */
	protected function checkSnmpPrivProtocol(array $interface) {
		if ($interface['details']['version'] == SNMP_V3 && (array_key_exists('privprotocol', $interface['details'])
				&& !in_array($interface['details']['privprotocol'], [ITEM_PRIVPROTOCOL_DES, ITEM_PRIVPROTOCOL_AES]))) {
			self::exception(ZBX_API_ERROR_PARAMETERS,  _('Incorrect arguments passed to function.'));
		}
	}

	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$sqlParts = parent::applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		if ($this->outputIsRequested('details', $options['output'])) {
			// Select interface type to check show details array or not.
			$sqlParts = $this->addQuerySelect('hi.type', $sqlParts);

			$sqlParts = $this->addQuerySelect(dbConditionCoalesce('his.version', SNMP_V2C, 'version'), $sqlParts);
			$sqlParts = $this->addQuerySelect(dbConditionCoalesce('his.bulk', SNMP_BULK_ENABLED, 'bulk'), $sqlParts);
			$sqlParts = $this->addQuerySelect(dbConditionCoalesce('his.community', '', 'community'), $sqlParts);
			$sqlParts = $this->addQuerySelect(dbConditionCoalesce('his.securityname', '', 'securityname'), $sqlParts);
			$sqlParts = $this->addQuerySelect(
				dbConditionCoalesce('his.securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV, 'securitylevel'),
				$sqlParts
			);
			$sqlParts = $this->addQuerySelect(
				dbConditionCoalesce('his.authpassphrase', '', 'authpassphrase'),
				$sqlParts
			);
			$sqlParts = $this->addQuerySelect(
				dbConditionCoalesce('his.privpassphrase', '', 'privpassphrase'),
				$sqlParts
			);
			$sqlParts = $this->addQuerySelect(
				dbConditionCoalesce('his.authprotocol', ITEM_AUTHPROTOCOL_MD5, 'authprotocol'),
				$sqlParts
			);
			$sqlParts = $this->addQuerySelect(
				dbConditionCoalesce('his.privprotocol', ITEM_PRIVPROTOCOL_DES, 'privprotocol'),
				$sqlParts
			);
			$sqlParts = $this->addQuerySelect(dbConditionCoalesce('his.contextname', '', 'contextname'), $sqlParts);
		}

		if (!$options['countOutput'] && $options['selectHosts'] !== null) {
			$sqlParts = $this->addQuerySelect('hi.hostid', $sqlParts);
		}

		return $sqlParts;
	}

	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);

		$interfaceIds = array_keys($result);

		// adding hosts
		if ($options['selectHosts'] !== null && $options['selectHosts'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'interfaceid', 'hostid');
			$hosts = API::Host()->get([
				'output' => $options['selectHosts'],
				'hosts' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);
			$result = $relationMap->mapMany($result, $hosts, 'hosts');
		}

		// adding items
		if ($options['selectItems'] !== null) {
			if ($options['selectItems'] != API_OUTPUT_COUNT) {
				$items = API::Item()->get([
					'output' => $this->outputExtend($options['selectItems'], ['itemid', 'interfaceid']),
					'interfaceids' => $interfaceIds,
					'nopermissions' => true,
					'preservekeys' => true,
					'filter' => ['flags' => null]
				]);
				$relationMap = $this->createRelationMap($items, 'interfaceid', 'itemid');

				$items = $this->unsetExtraFields($items, ['interfaceid', 'itemid'], $options['selectItems']);
				$result = $relationMap->mapMany($result, $items, 'items', $options['limitSelects']);
			}
			else {
				$items = API::Item()->get([
					'interfaceids' => $interfaceIds,
					'nopermissions' => true,
					'filter' => ['flags' => null],
					'countOutput' => true,
					'groupCount' => true
				]);
				$items = zbx_toHash($items, 'interfaceid');
				foreach ($result as $interfaceid => $interface) {
					$result[$interfaceid]['items'] = array_key_exists($interfaceid, $items)
						? $items[$interfaceid]['rowscount']
						: '0';
				}
			}
		}

		return $result;
	}
}

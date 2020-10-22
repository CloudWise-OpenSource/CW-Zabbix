<?php declare(strict_types=1);
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


class CControllerActionOperationGet extends CController {

	protected function checkInput() {
		$fields = [
			'eventsource'   => 'required|in '.implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION, EVENT_SOURCE_INTERNAL]),
			'recovery'      => 'required|in '.implode(',', [ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_ACKNOWLEDGE_OPERATION]),
			'actionid'      => 'db actions.actionid',
			'operation'     => 'array'
		];

		$ret = $this->validateInput($fields) && $this->validateInputConstraints();

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
		}

		return $ret;
	}

	protected function validateInputConstraints(): bool {
		$eventsource = $this->getInput('eventsource');
		$recovery = $this->getInput('recovery');

		$allowed_operations = getAllowedOperations($eventsource);

		if (!array_key_exists($recovery, $allowed_operations)) {
			error(_('Unsupported operation.'));
			return false;
		}

		return true;
	}

	protected function checkPermissions() {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			if (!$this->getInput('actionid', '0')) {
				return true;
			}

			return (bool) API::Action()->get([
				'output' => [],
				'actionids' => $this->getInput('actionid'),
				'editable' => true
			]);
		}

		return false;
	}

	protected function doAction() {
		$operation = $this->getInput('operation', []) + $this->defaultOperationObject();

		$eventsource = (int) $this->getInput('eventsource');
		$recovery = (int) $this->getInput('recovery');

		$data = [
			'popup_config' => $this->popupConfig($operation, $eventsource, $recovery),
			'debug' => null
		];

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}

	/**
	 * Returns empty default operation object.
	 *
	 * @return array
	 */
	private function defaultOperationObject(): array {
		return [
			'opmessage_usr' => [],
			'opmessage_grp' => [],
			'opmessage' => [
				'subject' => '',
				'message' => '',
				'mediatypeid' => '0',
				'default_msg' => '1'
			],
			'operationtype' => '0',
			'esc_step_from' => '1',
			'esc_step_to' => '1',
			'esc_period' => '0',
			'opcommand_hst' => [],
			'opcommand_grp' => [],
			'evaltype' => (string) CONDITION_EVAL_TYPE_AND_OR,
			'opconditions' => [],
			'opgroup' => [],
			'optemplate' => [],
			'opinventory' => [
				'inventory_mode' => (string) HOST_INVENTORY_MANUAL
			],
			'opcommand' => [
				'type' => (string) ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scriptid' => '0',
				'execute_on' => (string) ZBX_SCRIPT_EXECUTE_ON_AGENT,
				'port' => '',
				'authtype' => (string) ITEM_AUTHTYPE_PASSWORD,
				'username' => '',
				'password' => '',
				'publickey' => '',
				'privatekey' => '',
				'command' => ''
			]
		];
	}

	/**
	 * Transforms operation object into operation config object. Needed meta data is queried.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 * @param int $recovery     Action event phase.
	 *
	 * @return array  Each of fields is nullable, meaning given operation may not have particular configuration domain.
	 */
	private function popupConfig(array $operation, int $eventsource, int $recovery): array {
		$operation_type = $this->popupConfigOperationType($operation, $eventsource, $recovery);
		$operation_steps = $this->popupConfigOperationSteps($operation, $eventsource, $recovery);
		$operation_message = $this->popupConfigOperationMessage($operation, $eventsource);
		$operation_command = $this->popupConfigOperationCommand($operation, $eventsource);
		$operation_attr = $this->popupConfigOperationAttr($operation, $eventsource);
		$operation_condition = $this->popupConfigOperationCondition($operation, $eventsource, $recovery);

		return [
			'operation_type' => $operation_type,
			'operation_steps' => $operation_steps,
			'operation_message' => $operation_message,
			'operation_command' => $operation_command,
			'operation_attr' => $operation_attr,
			'operation_condition' => $operation_condition
		];
	}

	/**
	 * Returns "operation type" configuration fields for given operation in given source.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 * @param int $recovery     Action event phase.
	 *
	 * @return array
	 */
	private function popupConfigOperationType(array $operation, int $eventsource, int $recovery): array {
		$operation_type_options = [];

		foreach (getAllowedOperations($eventsource)[$recovery] as $operation_type) {
			$operation_type_options[] = [
				'value' => $operation_type,
				'name' => operation_type2str($operation_type)
			];
		}

		return [
			'options' => $operation_type_options,
			'selected' => $operation['operationtype']
		];
	}

	/**
	 * Returns populated "escalation steps" domain configuration fields for given operation in given source.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 * @param int $recovery     Action event phase.
	 *
	 * @return array|null
	 */
	private function popupConfigOperationSteps(array $operation, int $eventsource, int $recovery): ?array {
		if ($eventsource == EVENT_SOURCE_TRIGGERS || $eventsource == EVENT_SOURCE_INTERNAL) {
			if ($recovery == ACTION_OPERATION) {
				return [
					'from' => $operation['esc_step_from'],
					'to' => $operation['esc_step_to'],
					'duration' => $operation['esc_period']
				];
			}
		}

		return null;
	}

	/**
	 * Returns populated "message" domain configuration fields for given operation in given source.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 *
	 * @return array|null
	 */
	private function popupConfigOperationMessage(array $operation, int $eventsource): ?array {
		if ($eventsource == EVENT_SOURCE_TRIGGERS || $eventsource == EVENT_SOURCE_DISCOVERY
				|| $eventsource == EVENT_SOURCE_AUTOREGISTRATION || $eventsource == EVENT_SOURCE_INTERNAL) {
			$usergroups = [];
			if ($operation['opmessage_grp']) {
				$usergroups = API::UserGroup()->get([
					'output' => ['usergroupid', 'name'],
					'usrgrpids' => array_column($operation['opmessage_grp'], 'usrgrpid')
				]);
			}

			$users = [];
			if ($operation['opmessage_usr']) {
				$db_users = API::User()->get([
					'output' => ['userid', 'alias', 'name', 'surname'],
					'userids' => array_column($operation['opmessage_usr'], 'userid')
				]);
				CArrayHelper::sort($db_users, ['alias']);

				foreach ($db_users as $db_user) {
					$users[] = [
						'id' => $db_user['userid'],
						'name' => getUserFullname($db_user)
					];
				}
			}

			$mediatypes = API::MediaType()->get(['output' => ['mediatypeid', 'name']]);
			CArrayHelper::sort($mediatypes, ['name']);
			$mediatypes = array_values($mediatypes);

			return [
				'custom_message' => $operation['opmessage']['default_msg'] === '0',
				'subject' => $operation['opmessage']['subject'],
				'body' => $operation['opmessage']['message'],
				'mediatypeid' => $operation['opmessage']['mediatypeid'],
				'mediatypes' => $mediatypes,
				'usergroups' => $usergroups,
				'users' => $users
			];
		}

		return null;
	}

	/**
	 * Returns populated "command" domain configuration fields for given operation in given context (eventsource).
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 *
	 * @return array|null
	 */
	private function popupConfigOperationCommand(array $operation, int $eventsource): ?array {
		if ($eventsource == EVENT_SOURCE_TRIGGERS || $eventsource == EVENT_SOURCE_DISCOVERY
				|| $eventsource == EVENT_SOURCE_AUTOREGISTRATION) {
			$current_host = false;

			$hostids = [];
			foreach ($operation['opcommand_hst'] as $hostid) {
				if ($hostid == '0') {
					$current_host = true;
				}
				else {
					$hostids[] = $hostid;
				}
			}

			$operation_command = [
				'type' => $operation['opcommand']['type'],
				'current_host' => $current_host,
				'execute_on' => $operation['opcommand']['execute_on'],
				'command' => $operation['opcommand']['command'],
				'username' => $operation['opcommand']['username'],
				'password' => $operation['opcommand']['password'],
				'privatekey' => $operation['opcommand']['privatekey'],
				'publickey' => $operation['opcommand']['publickey'],
				'port' => $operation['opcommand']['port'],
				'authtype' => $operation['opcommand']['authtype'],
				'global_script' => ['scriptid' => '0', 'name' => ''],
				'hosts' => [],
				'groups' => []
			];

			if ($operation['opcommand']['scriptid']) {
				$db_scrpt = API::Script()->get([
					'output' => ['name', 'scriptid'],
					'scriptids' => (array) $operation['opcommand']['scriptid']
				]);

				if ($db_scrpt) {
					$operation_command['global_script'] = $db_scrpt[0];
				}
			}

			if ($hostids) {
				$operation_command['hosts'] = CArrayHelper::renameObjectsKeys(API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $hostids
				]), ['hostid' => 'id']);
			}

			if ($operation['opcommand_grp']) {
				$operation_command['groups'] = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $operation['opcommand_grp']
				]), ['groupid' => 'id']);
			}

			return $operation_command;
		}

		return null;
	}

	/**
	 * Returns populated "attributes" domain configuration fields for given operation in given source.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 *
	 * @return array|null
	 */
	private function popupConfigOperationAttr(array $operation, int $eventsource): ?array {
		if ($eventsource == EVENT_SOURCE_DISCOVERY || $eventsource == EVENT_SOURCE_AUTOREGISTRATION) {
			$operation_attr = [
				'hostgroups' => [],
				'templates' => []
			];

			if ($operation['opgroup']) {
				$operation_attr['hostgroups'] = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => array_column($operation['opgroup'], 'groupid')
				]), ['groupid' => 'id']);
			}

			if ($operation['optemplate']) {
				$operation_attr['templates'] = CArrayHelper::renameObjectsKeys(API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => array_column($operation['optemplate'], 'templateid'),
					'editable' => true
				]), ['templateid' => 'id']);
			}

			if ($operation['opinventory']) {
				$operation_attr['inventory_mode'] = $operation['opinventory']['inventory_mode'];
			}

			return $operation_attr;
		}

		return null;
	}

	/**
	 * Returns populated "conditions" domain configuration fields for given operation in given source.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 *
	 * @return array|null
	 */
	private function popupConfigOperationCondition(array $operation, int $eventsource, int $recovery): ?array {
		if ($eventsource == EVENT_SOURCE_TRIGGERS) {
			if ($recovery == ACTION_OPERATION) {
				$operation_condition = [
					'conditions' => [],
					'evaltype' => $operation['evaltype']
				];

				foreach ($operation['opconditions'] as $index => $opcondition) {
					$name = getConditionDescription($opcondition['conditiontype'], $opcondition['operator'],
						$opcondition['value'], ''
					);

					$operation_condition['conditions'][] = [
						'formulaid' => num2letter($index),
						'name' => $name,
						'conditiontype' => $opcondition['conditiontype'],
						'operator' => $opcondition['operator'],
						'value' => $opcondition['value']
					];
				}

				return $operation_condition;
			}
		}

		return null;
	}
}

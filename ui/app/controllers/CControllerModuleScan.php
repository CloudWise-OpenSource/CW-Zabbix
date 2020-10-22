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
 * Module directory scan action.
 */
class CControllerModuleScan extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		clear_messages();

		$db_modules_create = [];
		$db_modules_create_names = [];
		$db_modules_delete = [];
		$db_modules_delete_names = [];

		$db_modules = API::Module()->get([
			'output' => ['id', 'relative_path'],
			'sortfield' => 'relative_path',
			'preservekeys' => true
		]);

		$db_moduleids = [];
		$healthy_modules = [];

		foreach ($db_modules as $moduleid => $db_module) {
			$db_moduleids[$db_module['relative_path']] = $moduleid;
		}

		$module_manager = new CModuleManager(APP::ModuleManager()->getModulesDir());

		foreach (new DirectoryIterator($module_manager->getModulesDir()) as $item) {
			if (!$item->isDir() || $item->isDot()) {
				continue;
			}

			$relative_path = $item->getFilename();

			$manifest = $module_manager->addModule($relative_path);

			if (!$manifest) {
				continue;
			}

			$is_stored = array_key_exists($relative_path, $db_moduleids);
			$is_healthy = !$is_stored || $db_modules[$db_moduleids[$relative_path]]['id'] === $manifest['id'];

			if ($is_healthy) {
				$healthy_modules[] = $relative_path;
			}

			if (!$is_stored || !$is_healthy) {
				$db_modules_create[] = [
					'id' => $manifest['id'],
					'relative_path' => $relative_path,
					'status' => MODULE_STATUS_DISABLED,
					'config' => []
				];
				$db_modules_create_names[] = $manifest['name'];
			}
		}

		foreach (array_diff_key($db_moduleids, array_flip($healthy_modules)) as $relative_path => $moduleid) {
			$db_modules_delete[] = $moduleid;
			$db_modules_delete_names[] = $relative_path;
		}

		if ($db_modules_create) {
			$result = API::Module()->create($db_modules_create);

			if ($result) {
				info(_n('Module added: %1$s.', 'Modules added: %1$s.', implode(', ', $db_modules_create_names),
					count($db_modules_create)
				));
			}
			else {
				error(_n('Cannot add module: %1$s.', 'Cannot add modules: %1$s.',
					implode(', ', $db_modules_create_names), count($db_modules_create)
				));
			}
		}

		if ($db_modules_delete) {
			$result = API::Module()->delete($db_modules_delete);

			if ($result) {
				info(_n('Module deleted: %1$s.', 'Modules deleted: %1$s.', implode(', ', $db_modules_delete_names),
					count($db_modules_delete)
				));
			}
			else {
				error(_n('Cannot delete module: %1$s.', 'Cannot delete modules: %1$s.',
					implode(', ', $db_modules_delete_names), count($db_modules_delete)
				));
			}
		}

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'module.list'));

		$message = ($db_modules_create || $db_modules_delete)
			? _('Modules updated')
			: _('No new modules discovered');

		if (hasErrorMesssages()) {
			$response->setMessageError($message);
		}
		else {
			$response->setMessageOk($message);
		}

		$this->setResponse($response);
	}
}

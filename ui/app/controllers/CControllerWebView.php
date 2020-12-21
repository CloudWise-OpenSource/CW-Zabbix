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


class CControllerWebView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'filter_groupids' => 'array_id',
			'filter_hostids'  => 'array_id',
			'sort'            => 'in hostname,name',
			'sortorder'       => 'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'filter_rst'      => 'in 1',
			'filter_set'      => 'in 1',
			'page'            => 'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$sort_field = $this->getInput('sort', CProfile::get('web.httpmon.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.httpmon.sortorder', ZBX_SORT_UP));

		CProfile::update('web.httpmon.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.httpmon.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.httpmon.filter.groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.httpmon.filter.hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
		}
		else if ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.httpmon.filter.groupids');
			CProfile::deleteIdx('web.httpmon.filter.hostids');
		}

		$data['filter'] = [
			'groupids' => CProfile::getArray('web.httpmon.filter.groupids', []),
			'hostids' => CProfile::getArray('web.httpmon.filter.hostids', [])
		];

		// Select host groups.
		$data['filter']['groupids'] = $data['filter']['groupids']
			? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['name', 'groupid'],
				'groupids' => $data['filter']['groupids'],
				'preservekeys' => true
			]), ['groupid' => 'id'])
			: [];

		$filter_groupids = $data['filter']['groupids'] ? array_keys($data['filter']['groupids']) : null;
		if ($filter_groupids) {
			$filter_groupids = getSubGroups($filter_groupids);
		}

		// Select hosts.
		$data['filter']['hostids'] = $data['filter']['hostids']
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['name', 'hostid'],
				'hostids' => $data['filter']['hostids'],
				'with_monitored_items' => true,
				'with_httptests' => true,
				'preservekeys' => true
			]), ['hostid' => 'id'])
			: [];

		$data['screen_view'] = CScreenBuilder::getScreen([
			'resourcetype' => SCREEN_RESOURCE_HTTPTEST,
			'mode' => SCREEN_MODE_JS,
			'dataId' => 'httptest',
			'page' => $this->getInput('page', 1),
			'data' => [
				'sort' => $sort_field,
				'sortorder' => $sort_order,
				'groupids' => $filter_groupids,
				'hostids' => $data['filter']['hostids'] ? array_keys($data['filter']['hostids']) : null
			]
		])->get();

		$data += [
			'profileIdx' => 'web.web.filter',
			'active_tab' => CProfile::get('web.web.filter.active', 1)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Web monitoring'));
		$this->setResponse($response);
	}
}

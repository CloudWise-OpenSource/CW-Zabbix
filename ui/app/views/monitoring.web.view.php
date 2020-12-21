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
 * @var CView $this
 */

$this->addJsFile('gtlc.js');
$this->addJsFile('flickerfreescreen.js');
$this->addJsFile('layout.mode.js');
$this->addJsFile('multiselect.js');


$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

(new CWidget())
	->setTitle(_('Web monitoring'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true, get_icon('kioskmode', ['mode' => $web_layout_mode])))
			->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter((new CUrl('zabbix.php'))->setArgument('action', 'web.view')))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFormItem((new CVar('action', 'web.view'))->removeId())
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow((new CLabel(_('Host groups'), 'filter_groupids__ms')),
					(new CMultiSelect([
						'multiple' => true,
						'name' => 'filter_groupids[]',
						'object_name' => 'hostGroup',
						'data' => $data['filter']['groupids'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'host_groups',
								'srcfld1' => 'groupid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_groupids_',
								'with_httptests' => true,
								'real_hosts' => true,
								'enrich_parent_groups' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
				->addRow((new CLabel(_('Hosts'), 'filter_hostids__ms')),
					(new CMultiSelect([
						'multiple' => true,
						'name' => 'filter_hostids[]',
						'object_name' => 'hosts',
						'data' => $data['filter']['hostids'],
						'popup' => [
							'parameters' => [
								'srctbl' => 'hosts',
								'srcfld1' => 'hostid',
								'dstfrm' => 'zbx_filter',
								'dstfld1' => 'filter_hostids_',
								'with_monitored_items' => true,
								'with_httptests' => true
							]
						]
					]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				)
		])
	)
	->addItem($data['screen_view'])
	->show();

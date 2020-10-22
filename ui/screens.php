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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';
require_once dirname(__FILE__).'/include/screens.inc.php';
require_once dirname(__FILE__).'/include/blocks.inc.php';

$page['title'] = _('Custom screens');
$page['file'] = 'screens.php';
$page['scripts'] = [
	'class.calendar.js',
	'gtlc.js',
	'multiselect.js',
	'flickerfreescreen.js',
	'class.svg.canvas.js',
	'class.svg.map.js',
	'layout.mode.js'
];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);
$page['web_layout_mode'] = CViewHelper::loadLayoutMode();

define('ZBX_PAGE_DO_JS_REFRESH', 1);

ob_start();
require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'hostid' =>		[T_ZBX_INT,			O_OPT, P_SYS,	DB_ID,		null],
	'tr_groupid' =>	[T_ZBX_INT,			O_OPT, P_SYS,	DB_ID,		null],
	'tr_hostid' =>	[T_ZBX_INT,			O_OPT, P_SYS,	DB_ID,		null],
	'elementid' =>	[T_ZBX_INT,			O_OPT, P_SYS|P_NZERO, DB_ID, null],
	'screenname' =>	[T_ZBX_STR,			O_OPT, P_SYS,	null,		null],
	'step' =>		[T_ZBX_INT,			O_OPT, P_SYS,	BETWEEN(0, 65535), null],
	'from' =>		[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'to' =>			[T_ZBX_RANGE_TIME,	O_OPT, P_SYS,	null,		null],
	'reset' =>		[T_ZBX_STR,			O_OPT, P_SYS,	IN('"reset"'), null]
];
check_fields($fields);
validateTimeSelectorPeriod(getRequest('from'), getRequest('to'));

/*
 * Permissions
 */
// Validate group IDs.
if (getRequest('tr_groupid') && !isReadableHostGroups([getRequest('tr_groupid')])) {
	access_deny();
}

// Validate host IDs.
if (getRequest('hostid') && !isReadableHosts([getRequest('hostid')])) {
	access_deny();
}
if (getRequest('tr_hostid') && !isReadableHosts([getRequest('tr_hostid')])) {
	access_deny();
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Display
 */
$data = [];

$options = [
	'output' => ['screenid', 'name']
];

// Save dynamic item hostid.
if (hasRequest('hostid')) {
	CProfile::update('web.screens.hostid', getRequest('hostid'), PROFILE_TYPE_ID);
}
elseif (hasRequest('reset')) {
	CProfile::delete('web.screens.hostid');
}

if (getRequest('elementid')) {
	$options['screenids'] = getRequest('elementid');
	CProfile::update('web.screens.elementid', getRequest('elementid'), PROFILE_TYPE_ID);
}
elseif (hasRequest('screenname')) {
	$options['filter']['name'] = getRequest('screenname');
}
elseif (CProfile::get('web.screens.elementid')) {
	$options['screenids'] = CProfile::get('web.screens.elementid');
}
else {
	// Redirect to screen list.
	ob_end_clean();
	redirect('screenconf.php');
}

$screens = API::Screen()->get($options);

if (!$screens && (getRequest('elementid') || hasRequest('screenname'))) {
	access_deny();
}
elseif (!$screens) {
	// Redirect to screen list.
	ob_end_clean();
	redirect('screenconf.php');
}
else {
	$data['screen'] = reset($screens);
	$data['screen']['editable'] = (bool) API::Screen()->get([
		'output' => [],
		'screenids' => [$data['screen']['screenid']],
		'editable' => true
	]);
	$data['active_tab'] = CProfile::get('web.screens.filter.active', 1);

	$timeselector_options = [
		'profileIdx' => 'web.screens.filter',
		'profileIdx2' => $data['screen']['screenid'],
		'from' => getRequest('from'),
		'to' => getRequest('to')
	];
	updateTimeSelectorPeriod($timeselector_options);

	$data += $timeselector_options;

	// Dynamic item host selector.
	$data['has_dynamic_widgets'] = check_dynamic_items($data['screen']['screenid'], 0);

	if ($data['has_dynamic_widgets']) {
		$hostid = getRequest('hostid', CProfile::get('web.screens.hostid', 0));
		$data['host'] = ($hostid > 0)
			? CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => [$hostid],
				'monitored_hosts' => 1
			]), ['hostid' => 'id'])
			: [];

		$data['hostid'] = $data['host'] ? $data['host'][0]['id'] : null;
	}
}

ob_end_flush();

// render view
echo (new CView('monitoring.screen', $data))->getOutput();

require_once dirname(__FILE__).'/include/page_footer.php';

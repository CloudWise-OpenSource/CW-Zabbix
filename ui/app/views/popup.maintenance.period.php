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

$form = (new CForm())
	->cleanItems()
	->setId('maintenance_period_form')
	->addVar('action', 'popup.maintenance.period')
	->addVar('refresh', 1)
	->addVar('update', $data['update'])
	->addVar('index', $data['index']);

$form_list = (new CFormList());

if (array_key_exists('timeperiodid', $data)) {
	$form->addVar('timeperiodid', $data['timeperiodid']);
}

$days_weekly = [];
$days_monthly = [];

foreach ([1, 4, 6, 2, 5, 7, 3] as $day) {
	$value = 1 << ($day - 1);
	$days_weekly[] = [
		'name' => getDayOfWeekCaption($day),
		'value' => $value,
		'checked' => ($data['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY && (bool) ($value & $data['dayofweek']))
	];
	$days_monthly[] = [
		'name' => getDayOfWeekCaption($day),
		'value' => $value,
		'checked' => ($data['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY && (bool) ($value & $data['dayofweek']))
	];
}

$months = [];

foreach ([1, 5, 9, 2, 6, 10, 3, 7, 11, 4, 8, 12] as $month) {
	$value = 1 << ($month - 1);
	$months[] = [
		'name' => getMonthCaption($month),
		'value' => $value,
		'checked' => ($data['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY && (bool) ($value & $data['month']))
	];
}

$form_list
	->addRow((new CLabel(_('Period type'), 'timeperiod_type')),
		(new CComboBox('timeperiod_type', $data['timeperiod_type'], null, [
			TIMEPERIOD_TYPE_ONETIME	=> _('One time only'),
			TIMEPERIOD_TYPE_DAILY	=> _('Daily'),
			TIMEPERIOD_TYPE_WEEKLY	=> _('Weekly'),
			TIMEPERIOD_TYPE_MONTHLY	=> _('Monthly')
		]))->setAttribute('autofocus', 'autofocus')
	)
	->addRow((new CLabel(_('Every day(s)'), 'every_day'))->setAsteriskMark(),
		(new CNumericBox('every', ($data['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY) ? $data['every'] : 1, 3))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setId('every_day')
			->setAriaRequired(),
		'row_timeperiod_every_day'
	)
	->addRow((new CLabel(_('Every week(s)'), 'every_week'))->setAsteriskMark(),
		(new CNumericBox('every', ($data['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY) ? $data['every'] : 1, 2))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setId('every_week')
			->setAriaRequired(),
		'row_timeperiod_every_week'
	)
	->addRow((new CLabel(_('Day of week'), 'days'))->setAsteriskMark(),
		(new CCheckBoxList('days'))
			->addClass(ZBX_STYLE_COLUMNS)
			->addClass(ZBX_STYLE_COLUMNS_3)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setOptions($days_weekly),
		'row_timeperiod_dayofweek'
	)
	->addRow((new CLabel(_('Month'), 'months'))->setAsteriskMark(),
		(new CCheckBoxList('months'))
			->addClass(ZBX_STYLE_COLUMNS)
			->addClass(ZBX_STYLE_COLUMNS_3)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setOptions($months),
		'row_timeperiod_months'
	)
	->addRow(new CLabel(_('Date'), 'month_date_type'),
		(new CRadioButtonList('month_date_type', (int) $data['month_date_type']))
			->addValue(_('Day of month'), 0)
			->addValue(_('Day of week'), 1)
			->setModern(true),
		'row_timeperiod_date'
	)
	->addRow((new CLabel(_('Day of week'), 'every_dow'))->setAsteriskMark(),
		(new CComboBox('every', ($data['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) ? $data['every'] : 1, null, [
			1 => _('first'),
			2 => _x('second', 'adjective'),
			3 => _('third'),
			4 => _('fourth'),
			5 => _('last')
		]))->setId('every_dow'),
		'row_timeperiod_week'
	)
	->addRow('',
		(new CCheckBoxList('monthly_days'))
			->addClass(ZBX_STYLE_COLUMNS)
			->addClass(ZBX_STYLE_COLUMNS_3)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setOptions($days_monthly),
		'row_timeperiod_week_days'
	)
	->addRow((new CLabel(_('Day of month'), 'day'))->setAsteriskMark(),
		(new CNumericBox('day', ($data['month_date_type'] == 0) ? $data['day'] : 1, 2))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired(),
		'row_timeperiod_day'
	)
	->addRow((new CLabel(_('Date'), 'start_date'))->setAsteriskMark(),
		(new CDateSelector('start_date', $data['start_date']))
			->setDateFormat(ZBX_DATE_TIME)
			->setPlaceholder(_('YYYY-MM-DD hh:mm'))
			->setAriaRequired(),
		'row_timepreiod_start_date'
	)
	->addRow(new CLabel(_('At (hour:minute)'), 'hour'),
		(new CDiv([
			(new CNumericBox('hour', $data['hour'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			' : ',
			(new CNumericBox('minute', $data['minute'], 2))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		]))->addClass(ZBX_STYLE_FORM_FIELDS_INLINE),
		'row_timeperiod_period_at_hours_minutes'
	)
	->addRow((new CLabel(_('Maintenance period length'), 'period_days'))->setAsteriskMark(),
		(new CDiv([
			(new CNumericBox('period_days', $data['period_days'], 3))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			new CLabel(_('Days'), 'period_days'),
			new CComboBox('period_hours', $data['period_hours'], null, range(0, 23)),
			new CLabel(_('Hours'), 'period_hours'),
			new CComboBox('period_minutes', $data['period_minutes'], null, range(0, 59)),
			new CLabel(_('Minutes'), 'period_minutes')
		]))->addClass(ZBX_STYLE_FORM_FIELDS_INLINE),
		'row_timeperiod_period_length'
	);

$form
	->addItem($form_list)
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$output = [
	'header' => $data['title'],
	'body' => (new CDiv([$data['errors'], $form]))->toString(),
	'buttons' => [
		[
			'title' => $data['update'] ? _('Apply') : _('Add'),
			'class' => 'dialogue-widget-save',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitMaintenancePeriod(overlay);'
		]
	],
	'params' => $data['params'],
	'script_inline' => $this->readJsFile('popup.maintenance.period.js.php')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);

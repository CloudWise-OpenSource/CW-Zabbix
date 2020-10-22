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

require_once dirname(__FILE__).'/js/configuration.action.edit.js.php';

$widget = (new CWidget())->setTitle(_('Actions'));

// create form
$actionForm = (new CForm())
	->setId('action.edit')
	->setName('action.edit')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form'])
	->addVar('eventsource', $data['eventsource']);

if ($data['actionid']) {
	$actionForm->addVar('actionid', $data['actionid']);
}

// Action tab.
$action_tab = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['action']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	);

// Create condition table.
$condition_table = (new CTable(_('No conditions defined.')))
	->setId('conditionTable')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

$i = 0;

if ($data['action']['filter']['conditions']) {
	$actionConditionStringValues = actionConditionValueToString([$data['action']], $data['config']);

	foreach ($data['action']['filter']['conditions'] as $cIdx => $condition) {
		if (!isset($condition['conditiontype'])) {
			$condition['conditiontype'] = 0;
		}
		if (!isset($condition['operator'])) {
			$condition['operator'] = 0;
		}
		if (!isset($condition['value'])) {
			$condition['value'] = '';
		}
		if (!array_key_exists('value2', $condition)) {
			$condition['value2'] = '';
		}
		if (!str_in_array($condition['conditiontype'], $data['allowedConditions'])) {
			continue;
		}

		$label = isset($condition['formulaid']) ? $condition['formulaid'] : num2letter($i);

		$labelSpan = (new CSpan($label))
			->addClass('label')
			->setAttribute('data-conditiontype', $condition['conditiontype'])
			->setAttribute('data-formulaid', $label);

		$condition_table->addRow(
			[
				$labelSpan,
				(new CCol(getConditionDescription($condition['conditiontype'], $condition['operator'],
					$actionConditionStringValues[0][$cIdx], $condition['value2']
				)))->addClass(ZBX_STYLE_TABLE_FORMS_OVERFLOW_BREAK),
				(new CCol([
					(new CButton('remove', _('Remove')))
						->onClick('javascript: removeCondition('.$i.');')
						->addClass(ZBX_STYLE_BTN_LINK)
						->removeId(),
					new CVar('conditions['.$i.']', $condition)
				]))->addClass(ZBX_STYLE_NOWRAP)
			],
			null, 'conditions_'.$i
		);

		$i++;
	}
}

$formula = (new CTextBox('formula', $data['action']['filter']['formula']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setId('formula')
	->setAttribute('placeholder', 'A or (B and C) &hellip;');

$calculationTypeComboBox = new CComboBox('evaltype', $data['action']['filter']['evaltype'],
	'processTypeOfCalculation()',
	[
		CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
		CONDITION_EVAL_TYPE_AND => _('And'),
		CONDITION_EVAL_TYPE_OR => _('Or'),
		CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
	]
);

$action_tab->addRow(_('Type of calculation'), [
	$calculationTypeComboBox,
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CSpan())->setId('conditionLabel'),
	$formula
]);

$condition_table->addRow([
	(new CSimpleButton(_('Add')))
		->onClick('return PopUp("popup.condition.actions",'.json_encode([
			'type' => ZBX_POPUP_CONDITION_TYPE_ACTION,
			'source' => $data['eventsource']
		]).', null, this);')
		->addClass(ZBX_STYLE_BTN_LINK)
]);

$action_tab->addRow(_('Conditions'),
	(new CDiv($condition_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$action_tab->addRow(_('Enabled'),
	(new CCheckBox('status', ACTION_STATUS_ENABLED))->setChecked($data['action']['status'] == ACTION_STATUS_ENABLED)
);

// Operations tab.
$operation_tab = new CFormList();

if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$operation_tab->addRow((new CLabel(_('Default operation step duration'), 'esc_period'))->setAsteriskMark(),
		(new CTextBox('esc_period', $data['action']['esc_period']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	);
}

if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
	$operation_tab->addRow(_('Pause operations for suppressed problems'),
		(new CCheckBox('pause_suppressed', ACTION_PAUSE_SUPPRESSED_TRUE))
			->setChecked($data['action']['pause_suppressed'] == ACTION_PAUSE_SUPPRESSED_TRUE)
	);
}

// create operation table
$operations_table = (new CTable())->setAttribute('style', 'width: 100%;');
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$operations_table->setHeader([_('Steps'), _('Details'), _('Start in'), _('Duration'), _('Action')]);
	$delays = count_operations_delay($data['action']['operations'], $data['action']['esc_period']);
}
else {
	$operations_table->setHeader([_('Details'), _('Action')]);
}

if ($data['action']['operations']) {
	$actionOperationDescriptions = getActionOperationDescriptions([$data['action']], ACTION_OPERATION);

	$action_operation_hints = getActionOperationHints($data['action']['operations']);

	$simple_interval_parser = new CSimpleIntervalParser();

	foreach ($data['action']['operations'] as $operationid => $operation) {
		if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_OPERATION])) {
			continue;
		}

		if (array_key_exists('opcommand', $operation)) {
			$operation['opcommand'] += [
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
			];
		}

		if (!isset($operation['opconditions'])) {
			$operation['opconditions'] = [];
		}
		if (!isset($operation['mediatypeid'])) {
			$operation['mediatypeid'] = 0;
		}

		$details = new CSpan($actionOperationDescriptions[0][$operationid]);

		if (array_key_exists($operationid, $action_operation_hints) && $action_operation_hints[$operationid]) {
			$details->setHint($action_operation_hints[$operationid]);
		}

		$operation_for_popup = array_merge($operation, ['id' => $operationid]);
		foreach (['opcommand_grp' => 'groupid', 'opcommand_hst' => 'hostid'] as $var => $field) {
			if (array_key_exists($var, $operation_for_popup)) {
				$operation_for_popup[$var] = zbx_objectValues($operation_for_popup[$var], $field);
			}
		}

		if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
			$esc_steps_txt = null;
			$esc_period_txt = null;
			$esc_delay_txt = null;

			if ($operation['esc_step_from'] < 1) {
				$operation['esc_step_from'] = 1;
			}

			$esc_steps_txt = $operation['esc_step_from'].' - '.$operation['esc_step_to'];

			// display N-N as N
			$esc_steps_txt = ($operation['esc_step_from'] == $operation['esc_step_to'])
				? $operation['esc_step_from']
				: $operation['esc_step_from'].' - '.$operation['esc_step_to'];

			$esc_period_txt = ($simple_interval_parser->parse($operation['esc_period']) == CParser::PARSE_SUCCESS
					&& timeUnitToSeconds($operation['esc_period']) == 0)
				? _('Default')
				: $operation['esc_period'];

			$esc_delay_txt = ($delays[$operation['esc_step_from']] === null)
				? _('Unknown')
				: ($delays[$operation['esc_step_from']] != 0
					? convertUnits(['value' => $delays[$operation['esc_step_from']], 'units' => 'uptime'])
					: _('Immediately')
				);

			$operation_row = [
				$esc_steps_txt,
				$details,
				$esc_delay_txt,
				$esc_period_txt,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('operation_details.open(this,'.$data['actionid'].','.$data['eventsource'].','.
								ACTION_OPERATION.','.json_encode($operation_for_popup).')'
							)
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick('removeOperation('.$operationid.', '.ACTION_OPERATION.');')
								->addClass(ZBX_STYLE_BTN_LINK)
								->removeId(),
							new CVar('operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			];
		}
		else {
			$operation_row = [
				$details,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('operation_details.open(this,'.$data['actionid'].','.$data['eventsource'].','.
								ACTION_OPERATION.','.json_encode($operation_for_popup).')'
							)
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick('removeOperation('.$operationid.', '.ACTION_OPERATION.');')
								->addClass(ZBX_STYLE_BTN_LINK)
								->removeId(),
							new CVar('operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			];
		}
		$operations_table->addRow($operation_row, null, 'operations_'.$operationid);
	}
}

$operations_table->addRow(
	(new CSimpleButton(_('Add')))
		->onClick('operation_details.open(this,'.$data['actionid'].','.$data['eventsource'].','.ACTION_OPERATION.')')
		->addClass(ZBX_STYLE_BTN_LINK)
);

$operation_tab->addRow(_('Operations'),
	(new CDiv($operations_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// Recovery operation tab.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	// Create operation table.
	$operations_table = (new CTable())->setAttribute('style', 'width: 100%;');
	$operations_table->setHeader([_('Details'), _('Action')]);

	if ($data['action']['recovery_operations']) {
		$actionOperationDescriptions = getActionOperationDescriptions([$data['action']], ACTION_RECOVERY_OPERATION);

		$action_operation_hints = getActionOperationHints($data['action']['recovery_operations']);

		foreach ($data['action']['recovery_operations'] as $operationid => $operation) {
			if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_RECOVERY_OPERATION])) {
				continue;
			}
			if (!isset($operation['opconditions'])) {
				$operation['opconditions'] = [];
			}

			if (!array_key_exists('opmessage', $operation)) {
				$operation['opmessage'] = [];
			}

			$operation['opmessage'] += [
				'mediatypeid' => '0',
				'message' => '',
				'subject' => '',
				'default_msg' => '1'
			];

			$details = new CSpan($actionOperationDescriptions[0][$operationid]);

			if (array_key_exists($operationid, $action_operation_hints) && $action_operation_hints[$operationid]) {
				$details->setHint($action_operation_hints[$operationid]);
			}

			$operation_for_popup = array_merge($operation, ['id' => $operationid]);
			foreach (['opcommand_grp' => 'groupid', 'opcommand_hst' => 'hostid'] as $var => $field) {
				if (array_key_exists($var, $operation_for_popup)) {
					$operation_for_popup[$var] = zbx_objectValues($operation_for_popup[$var], $field);
				}
			}

			$operations_table->addRow([
				$details,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('operation_details.open(this,'.$data['actionid'].','.$data['eventsource'].','.
								ACTION_RECOVERY_OPERATION.','.json_encode($operation_for_popup).')'
							)
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick(
									'javascript: removeOperation('.$operationid.', '.ACTION_RECOVERY_OPERATION.');'
								)
								->addClass(ZBX_STYLE_BTN_LINK)
								->removeId(),
							new CVar('recovery_operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			], null, 'recovery_operations_'.$operationid);
		}
	}

	$operations_table->addRow(
		(new CSimpleButton(_('Add')))
			->onClick('operation_details.open(this,'.$data['actionid'].','.$data['eventsource'].','.
				ACTION_RECOVERY_OPERATION.')'
			)
			->addClass(ZBX_STYLE_BTN_LINK)
	);

	$operation_tab->addRow(_('Recovery operations'),
		(new CDiv($operations_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

// Acknowledge operations
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
	$action_formname = $actionForm->getName();

	$operations_table = (new CTable())->setAttribute('style', 'width: 100%;');
	$operations_table->setHeader([_('Details'), _('Action')]);

	if ($data['action']['ack_operations']) {
		$operation_descriptions = getActionOperationDescriptions([$data['action']], ACTION_ACKNOWLEDGE_OPERATION);

		$operation_hints = getActionOperationHints($data['action']['ack_operations']);

		foreach ($data['action']['ack_operations'] as $operationid => $operation) {
			if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_ACKNOWLEDGE_OPERATION])) {
				continue;
			}
			$operation += [
				'opconditions'	=> [],
				'mediatypeid'	=> 0
			];

			$details = new CSpan($operation_descriptions[0][$operationid]);

			if (array_key_exists($operationid, $operation_hints) && $operation_hints[$operationid]) {
				$details->setHint($operation_hints[$operationid]);
			}

			$operation_for_popup = array_merge($operation, ['id' => $operationid]);
			foreach (['opcommand_grp' => 'groupid', 'opcommand_hst' => 'hostid'] as $var => $field) {
				if (array_key_exists($var, $operation_for_popup)) {
					$operation_for_popup[$var] = zbx_objectValues($operation_for_popup[$var], $field);
				}
			}

			$operations_table->addRow([
				$details,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('operation_details.open(this,'.$data['actionid'].','.$data['eventsource'].','.
								ACTION_ACKNOWLEDGE_OPERATION.','.json_encode($operation_for_popup).')'
							)
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick('javascript: removeOperation('.$operationid.', '.ACTION_ACKNOWLEDGE_OPERATION.
									');'
								)
								->addClass(ZBX_STYLE_BTN_LINK)
								->removeId(),
							new CVar('ack_operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			], null, 'ack_operations_'.$operationid);
		}
	}

	$operations_table->addRow(
		(new CSimpleButton(_('Add')))
			->onClick('operation_details.open(this,'.$data['actionid'].','.$data['eventsource'].','.
				ACTION_ACKNOWLEDGE_OPERATION.')'
			)
			->addClass(ZBX_STYLE_BTN_LINK)
	);

	$operation_tab->addRow(_('Update operations'),
		(new CDiv($operations_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

// Append tabs to form.
$action_tabs = (new CTabView())
	->addTab('actionTab', _('Action'), $action_tab)
	->addTab('operationTab', _('Operations'), $operation_tab);

if (!hasRequest('form_refresh')) {
	$action_tabs->setSelected(0);
}

// Append buttons to form.
$others = [];
if ($data['actionid']) {
	$form_buttons = [
		new CSubmit('update', _('Update')), [
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete current action?'),
				url_param('form').url_param('eventsource').url_param('actionid')
			),
			new CButtonCancel(url_param('actiontype'))
		]
	];
}
else {
	$form_buttons = [
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('actiontype'))]
	];
}

$action_tabs->setFooter([
	(new CList())
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->addItem([
			new CDiv(''),
			(new CDiv((new CLabel(_('At least one operation must exist.')))->setAsteriskMark()))
				->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
		]),
	makeFormFooter($form_buttons[0], $form_buttons[1])
]);
$actionForm->addItem($action_tabs);

// Append form to widget.
$widget->addItem($actionForm);

$widget->show();

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
?>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-tmpl">
	<?php
	$preproc_types_cbbox = new CComboBox('preprocessing[#{rowNum}][type]', '');

	foreach (get_preprocessing_types(null, true, $data['preprocessing_types']) as $group) {
		$cb_group = new COptGroup($group['label']);

		foreach ($group['types'] as $type => $label) {
			$cb_group->addItem(new CComboItem($type, $label));
		}

		$preproc_types_cbbox->addItem($cb_group);
	}

	echo (new CListItem([
		(new CDiv([
			(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON),
			(new CDiv($preproc_types_cbbox))
				->addClass('list-numbered-item')
				->addClass('step-name'),
			(new CDiv())->addClass('step-parameters'),
			(new CDiv(new CCheckBox('preprocessing[#{rowNum}][on_fail]')))->addClass('step-on-fail'),
			(new CDiv([
				(new CButton('preprocessing[#{rowNum}][test]', _('Test')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('preprocessing-step-test')
					->removeId(),
				(new CButton('preprocessing[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
					->removeId()
			]))->addClass('step-action')
		]))->addClass('preprocessing-step'),
		(new CDiv([
			new CLabel(_('Custom on fail')),
			(new CRadioButtonList('preprocessing[#{rowNum}][error_handler]', ZBX_PREPROC_FAIL_DISCARD_VALUE))
				->addValue(_('Discard value'), ZBX_PREPROC_FAIL_DISCARD_VALUE)
				->addValue(_('Set value to'), ZBX_PREPROC_FAIL_SET_VALUE)
				->addValue(_('Set error to'), ZBX_PREPROC_FAIL_SET_ERROR)
				->setModern(true)
				->setEnabled(false),
			(new CTextBox('preprocessing[#{rowNum}][error_handler_params]'))
				->setEnabled(false)
				->addStyle('display: none;')
		]))
			->addClass('on-fail-options')
			->addStyle('display: none;')
	]))
		->addClass('preprocessing-list-item')
		->addClass('sortable')
		->setAttribute('data-step', '#{rowNum}');
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-single-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))->setAttribute('placeholder', '#{placeholder}') ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-double-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))->setAttribute('placeholder', '#{placeholder_0}').
			(new CTextBox('preprocessing[#{rowNum}][params][1]', ''))->setAttribute('placeholder', '#{placeholder_1}')
	?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-multiline-tmpl">
	<?= (new CMultilineInput('preprocessing[#{rowNum}][params][0]', '', ['add_post_js' => false])) ?>
</script>

<script type="text/x-jquery-tmpl" id="preprocessing-steps-parameters-custom-width-chkbox-tmpl">
	<?= (new CTextBox('preprocessing[#{rowNum}][params][0]', ''))
			->setAttribute('placeholder', '#{placeholder_0}')
			->setWidth('#{width_0}')
			->setAttribute('maxlength', 1).
		(new CTextBox('preprocessing[#{rowNum}][params][1]', ''))
			->setAttribute('placeholder', '#{placeholder_1}')
			->setWidth('#{width_1}')
			->setAttribute('maxlength', 1).
		(new CCheckBox('preprocessing[#{rowNum}][params][2]', '#{chkbox_value}'))
			->setLabel('#{chkbox_label}')
			->setChecked('#{chkbox_default}')
	?>
</script>

<script type="text/javascript">
	jQuery(function($) {
		function makeParameterInput(index, type) {
			var preproc_param_single_tmpl = new Template($('#preprocessing-steps-parameters-single-tmpl').html()),
				preproc_param_double_tmpl = new Template($('#preprocessing-steps-parameters-double-tmpl').html()),
				preproc_param_custom_width_chkbox_tmpl =
					new Template($('#preprocessing-steps-parameters-custom-width-chkbox-tmpl').html()),
				preproc_param_multiline_tmpl = new Template($('#preprocessing-steps-parameters-multiline-tmpl').html());

			switch (type) {
				case '<?= ZBX_PREPROC_MULTIPLIER ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('number')) ?>
					})).css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_RTRIM ?>':
				case '<?= ZBX_PREPROC_LTRIM ?>':
				case '<?= ZBX_PREPROC_TRIM ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('list of characters')) ?>
					})).css('width', <?= ZBX_TEXTAREA_SMALL_WIDTH ?>);

				case '<?= ZBX_PREPROC_XPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_XML ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('XPath')) ?>
					}));

				case '<?= ZBX_PREPROC_JSONPATH ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_JSON ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('$.path.to.node')) ?>
					}));

				case '<?= ZBX_PREPROC_REGSUB ?>':
				case '<?= ZBX_PREPROC_ERROR_FIELD_REGEX ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(_('pattern')) ?>,
						placeholder_1: <?= json_encode(_('output')) ?>
					}));

				case '<?= ZBX_PREPROC_VALIDATE_RANGE ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(_('min')) ?>,
						placeholder_1: <?= json_encode(_('max')) ?>
					}));

				case '<?= ZBX_PREPROC_VALIDATE_REGEX ?>':
				case '<?= ZBX_PREPROC_VALIDATE_NOT_REGEX ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('pattern')) ?>
					}));

				case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(_('seconds')) ?>
					})).css('width', <?= ZBX_TEXTAREA_NUMERIC_BIG_WIDTH ?>);

				case '<?= ZBX_PREPROC_SCRIPT ?>':
					return $(preproc_param_multiline_tmpl.evaluate({rowNum: index})).multilineInput({
						title: <?= json_encode(_('JavaScript')) ?>,
						placeholder: <?= json_encode(_('script')) ?>,
						placeholder_textarea: 'return value',
						label_before: 'function (value) {',
						label_after: '}',
						grow: 'auto',
						rows: 0,
						maxlength: <?= (int) $data['preprocessing_script_maxlength'] ?>
					});

				case '<?= ZBX_PREPROC_PROMETHEUS_PATTERN ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(
							_('<metric name>{<label name>="<label value>", ...} == <value>')
						) ?>,
						placeholder_1: <?= json_encode(_('<label name>')) ?>
					}));

				case '<?= ZBX_PREPROC_PROMETHEUS_TO_JSON ?>':
					return $(preproc_param_single_tmpl.evaluate({
						rowNum: index,
						placeholder: <?= json_encode(
							_('<metric name>{<label name>="<label value>", ...} == <value>')
						) ?>
					}));

				case '<?= ZBX_PREPROC_CSV_TO_JSON ?>':
					return $(preproc_param_custom_width_chkbox_tmpl.evaluate({
						rowNum: index,
						width_0: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>,
						width_1: <?= ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH ?>,
						placeholder_0: ',',
						placeholder_1: '"',
						chkbox_label: <?= json_encode(_('With header row')) ?>,
						chkbox_value: <?= ZBX_PREPROC_CSV_HEADER ?>,
						chkbox_default: true
					}));

				case '<?= ZBX_PREPROC_STR_REPLACE ?>':
					return $(preproc_param_double_tmpl.evaluate({
						rowNum: index,
						placeholder_0: <?= json_encode(_('search string')) ?>,
						placeholder_1: <?= json_encode(_('replacement')) ?>
					}));

				default:
					return '';
			}
		}

		var $preprocessing = $('#preprocessing'),
			step_index = $preprocessing.find('li.sortable').length;

		$preprocessing.sortable({
			disabled: $preprocessing.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').hasClass('<?= ZBX_STYLE_DISABLED ?>'),
			items: 'li.sortable',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6
		});

		$preprocessing
			.on('click', '.element-table-add', function() {
				var preproc_row_tmpl = new Template($('#preprocessing-steps-tmpl').html()),
					$row = $(preproc_row_tmpl.evaluate({rowNum: step_index})),
					type = $('select[name*="type"]', $row).val();

				$('.step-parameters', $row).html(makeParameterInput(step_index, type));
				$(this).closest('.preprocessing-list-foot').before($row);

				$('.preprocessing-list-head').show();

				var sortable_count = $preprocessing.find('li.sortable').length;

				if (sortable_count == 1) {
					$('#preproc_test_all').show();
					$preprocessing
						.sortable('disable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
				}
				else if (sortable_count > 1) {
					$preprocessing
						.sortable('enable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').removeClass('<?= ZBX_STYLE_DISABLED ?>');
				}

				step_index++;
			})
			.on('click', '#preproc_test_all', function() {
				var step_nums = [];
				$('select[name^="preprocessing"][name$="[type]"]', $preprocessing).each(function() {
					var str = $(this).attr('name');
					step_nums.push(str.substr(14, str.length - 21));
				});

				openItemTestDialog(step_nums, true, false, this, -1);
			})
			.on('click', '.preprocessing-step-test', function() {
				var str = $(this).attr('name'),
					step_nr = $(this).attr('data-step'),
					num = str.substr(14, str.length - 21);

				openItemTestDialog([num], false, false, this, num);
			})
			.on('click', '.element-table-remove', function() {
				$(this).closest('li.sortable').remove();

				var sortable_count = $preprocessing.find('li.sortable').length;

				if (sortable_count == 0) {
					$('#preproc_test_all').hide();
					$('.preprocessing-list-head').hide();
				}
				else if (sortable_count == 1) {
					$preprocessing
						.sortable('disable')
						.find('div.<?= ZBX_STYLE_DRAG_ICON ?>').addClass('<?= ZBX_STYLE_DISABLED ?>');
				}
			})
			.on('change', 'select[name*="type"]', function() {
				var $row = $(this).closest('.preprocessing-list-item'),
					type = $(this).val(),
					$on_fail = $row.find('[name*="on_fail"]');

				$('.step-parameters', $row).html(makeParameterInput($row.data('step'), type));

				// Disable "Custom on fail" for some of the preprocessing types.
				switch (type) {
					case '<?= ZBX_PREPROC_RTRIM ?>':
					case '<?= ZBX_PREPROC_LTRIM ?>':
					case '<?= ZBX_PREPROC_TRIM ?>':
					case '<?= ZBX_PREPROC_THROTTLE_VALUE ?>':
					case '<?= ZBX_PREPROC_THROTTLE_TIMED_VALUE ?>':
					case '<?= ZBX_PREPROC_SCRIPT ?>':
					case '<?= ZBX_PREPROC_STR_REPLACE ?>':
						$on_fail
							.prop('checked', false)
							.prop('disabled', true)
							.trigger('change');
						break;

					default:
						$on_fail.prop('disabled', false);
						break;
				}
			})
			.on('change', 'input[type="text"][name*="params"]', function() {
				$(this).attr('title', $(this).val());
			})
			.on('change', 'input[name*="on_fail"]', function() {
				var $on_fail_options = $(this).closest('.preprocessing-list-item').find('.on-fail-options');

				if ($(this).is(':checked')) {
					$on_fail_options.find('input').prop('disabled', false);
					$on_fail_options.show();
				}
				else {
					$on_fail_options.find('input').prop('disabled', true);
					$on_fail_options.hide();
				}
			})
			.on('change', 'input[name*="error_handler]"]', function() {
				var error_handler = $(this).val(),
					$error_handler_params = $(this).closest('.on-fail-options').find('[name*="error_handler_params"]');

				if (error_handler == '<?= ZBX_PREPROC_FAIL_DISCARD_VALUE ?>') {
					$error_handler_params
						.prop('disabled', true)
						.hide();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_VALUE ?>') {
					$error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= json_encode(_('value')) ?>)
						.show();
				}
				else if (error_handler == '<?= ZBX_PREPROC_FAIL_SET_ERROR ?>') {
					$error_handler_params
						.prop('disabled', false)
						.attr('placeholder', <?= json_encode(_('error message')) ?>)
						.show();
				}
			});
	});
</script>

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

$this->includeJsFile('administration.authentication.edit.js.php');

// Authentication general fields and HTTP authentication fields.
$auth_tab = (new CFormList('list_auth'))
	->addRow(new CLabel(_('Default authentication'), 'authentication_type'),
		(new CRadioButtonList('authentication_type', (int) $data['authentication_type']))
			->setAttribute('autofocus', 'autofocus')
			->addValue(_x('Internal', 'authentication'), ZBX_AUTH_INTERNAL)
			->addValue(_('LDAP'), ZBX_AUTH_LDAP)
			->setModern(true)
			->removeId()
	);

// HTTP authentication fields.
$http_tab = (new CFormList('list_http'))
	->addRow(new CLabel(_('Enable HTTP authentication'), 'http_auth_enabled'),
		(new CCheckBox('http_auth_enabled', ZBX_AUTH_HTTP_ENABLED))
			->setChecked($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
			->setUncheckedValue(ZBX_AUTH_HTTP_DISABLED)
	)
	->addRow(new CLabel(_('Default login form'), 'http_login_form'),
		(new CComboBox('http_login_form', $data['http_login_form'], null, [
			ZBX_AUTH_FORM_ZABBIX => _('Zabbix login form'),
			ZBX_AUTH_FORM_HTTP => _('HTTP login form')
		]))->setEnabled($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
	)
	->addRow(new CLabel(_('Remove domain name'), 'http_strip_domains'),
		(new CTextBox('http_strip_domains', $data['http_strip_domains']))
			->setEnabled($data['http_auth_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Case sensitive login'), 'http_case_sensitive'),
		(new CCheckBox('http_case_sensitive', ZBX_AUTH_CASE_SENSITIVE))
			->setChecked($data['http_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
			->setEnabled($data['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED)
			->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
	);

// LDAP configuration fields.
if ($data['change_bind_password']) {
	$password_box = [
		new CVar('change_bind_password', 1),
		(new CPassBox('ldap_bind_password', $data['ldap_bind_password']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	];
}
else {
	$password_box = [
		new CVar('action_passw_change', $data['action_passw_change']),
		(new CButton('change_bind_password', _('Change password')))
			->setEnabled($data['ldap_enabled'])
			->addClass(ZBX_STYLE_BTN_GREY)
	];
}

$ldap_tab = (new CFormList('list_ldap'))
	->addRow(new CLabel(_('Enable LDAP authentication'), 'ldap_configured'),
		$data['ldap_error']
			? (new CLabel($data['ldap_error']))->addClass(ZBX_STYLE_RED)
			: (new CCheckBox('ldap_configured', ZBX_AUTH_LDAP_ENABLED))
				->setChecked($data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED)
				->setUncheckedValue(ZBX_AUTH_LDAP_DISABLED)
	)
	->addRow((new CLabel(_('LDAP host'), 'ldap_host'))->setAsteriskMark(),
		(new CTextBox('ldap_host', $data['ldap_host']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Port'), 'ldap_port'))->setAsteriskMark(),
		(new CNumericBox('ldap_port', $data['ldap_port'], 5))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Base DN'), 'ldap_base_dn'))->setAsteriskMark(),
		(new CTextBox('ldap_base_dn', $data['ldap_base_dn']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Search attribute'), 'ldap_search_attribute'))->setAsteriskMark(),
		(new CTextBox('ldap_search_attribute', $data['ldap_search_attribute']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(new CLabel(_('Bind DN'), 'ldap_bind_dn'),
		(new CTextBox('ldap_bind_dn', $data['ldap_bind_dn']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(new CLabel(_('Case sensitive login'), 'ldap_case_sensitive'),
		(new CCheckBox('ldap_case_sensitive', ZBX_AUTH_CASE_SENSITIVE))
			->setChecked($data['ldap_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
			->setEnabled($data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED)
			->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
	)
	->addRow(new CLabel(_('Bind password'), 'ldap_bind_password'), $password_box)
	->addRow(_('Test authentication'), ' ['._('must be a valid LDAP user').']')
	->addRow((new CLabel(_('Login'), 'ldap_test_user'))->setAsteriskMark(),
		(new CTextBox('ldap_test_user', $data['ldap_test_user']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('User password'), 'ldap_test_password'))->setAsteriskMark(),
		(new CPassBox('ldap_test_password', $data['ldap_test_password']))
			->setEnabled($data['ldap_enabled'])
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	);

// SAML authentication fields.
$saml_tab = (new CFormList('list_saml'))
	->addRow(new CLabel(_('Enable SAML authentication'), 'saml_auth_enabled'),
		$data['saml_error']
			? (new CLabel($data['saml_error']))->addClass(ZBX_STYLE_RED)
			: (new CCheckBox('saml_auth_enabled', ZBX_AUTH_SAML_ENABLED))
				->setChecked($data['saml_auth_enabled'] == ZBX_AUTH_SAML_ENABLED)
				->setUncheckedValue(ZBX_AUTH_SAML_DISABLED)
	)
	->addRow((new CLabel(_('IdP entity ID'), 'saml_idp_entityid'))->setAsteriskMark(),
		(new CTextBox('saml_idp_entityid', $data['saml_idp_entityid'], false,
			DB::getFieldLength('config', 'saml_idp_entityid')
		))
			->setEnabled($data['saml_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('SSO service URL'), 'saml_sso_url'))->setAsteriskMark(),
		(new CTextBox('saml_sso_url', $data['saml_sso_url'], false, DB::getFieldLength('config', 'saml_sso_url')))
			->setEnabled($data['saml_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(new CLabel(_('SLO service URL'), 'saml_slo_url'),
		(new CTextBox('saml_slo_url', $data['saml_slo_url'], false, DB::getFieldLength('config', 'saml_slo_url')))
			->setEnabled($data['saml_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Username attribute'), 'saml_username_attribute'))->setAsteriskMark(),
		(new CTextBox('saml_username_attribute', $data['saml_username_attribute'], false,
			DB::getFieldLength('config', 'saml_username_attribute')
		))
			->setEnabled($data['saml_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('SP entity ID'), 'saml_sp_entityid'))->setAsteriskMark(),
		(new CTextBox('saml_sp_entityid', $data['saml_sp_entityid'], false,
			DB::getFieldLength('config', 'saml_sp_entityid')
		))
			->setEnabled($data['saml_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(new CLabel(_('SP name ID format'), 'saml_nameid_format'),
		(new CTextBox('saml_nameid_format', $data['saml_nameid_format'], false,
			DB::getFieldLength('config', 'saml_nameid_format')
		))
			->setEnabled($data['saml_enabled'])
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient')
	)
	->addRow(_('Sign'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('saml_sign_messages'))
				->setLabel(_('Messages'))
				->setChecked($data['saml_sign_messages'] == 1)
				->setUncheckedValue(0)
				->setEnabled($data['saml_enabled'])
			)
			->addItem((new CCheckBox('saml_sign_assertions'))
				->setLabel(_('Assertions'))
				->setChecked($data['saml_sign_assertions'] == 1)
				->setUncheckedValue(0)
				->setEnabled($data['saml_enabled'])
			)
			->addItem((new CCheckBox('saml_sign_authn_requests'))
				->setLabel(_('AuthN requests'))
				->setChecked($data['saml_sign_authn_requests'] == 1)
				->setUncheckedValue(0)
				->setEnabled($data['saml_enabled'])
			)
			->addItem((new CCheckBox('saml_sign_logout_requests'))
				->setLabel(_('Logout requests'))
				->setChecked($data['saml_sign_logout_requests'] == 1)
				->setUncheckedValue(0)
				->setEnabled($data['saml_enabled'])
			)
			->addItem((new CCheckBox('saml_sign_logout_responses'))
				->setLabel(_('Logout responses'))
				->setChecked($data['saml_sign_logout_responses'] == 1)
				->setUncheckedValue(0)
				->setEnabled($data['saml_enabled'])
			)
	)
	->addRow(_('Encrypt'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('saml_encrypt_nameid'))
				->setLabel(_('Name ID'))
				->setChecked($data['saml_encrypt_nameid'] == 1)
				->setUncheckedValue(0)
				->setEnabled($data['saml_enabled'])
			)
			->addItem((new CCheckBox('saml_encrypt_assertions'))
				->setLabel(_('Assertions'))
				->setChecked($data['saml_encrypt_assertions'] == 1)
				->setUncheckedValue(0)
				->setEnabled($data['saml_enabled'])
			)
	)
	->addRow(new CLabel(_('Case sensitive login'), 'saml_case_sensitive'),
		(new CCheckBox('saml_case_sensitive'))
			->setChecked($data['saml_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE)
			->setUncheckedValue(ZBX_AUTH_CASE_INSENSITIVE)
			->setEnabled($data['saml_enabled'])
	);

(new CWidget())
	->setTitle(_('Authentication'))
	->addItem((new CForm())
		->addVar('action', $data['action_submit'])
		->addVar('db_authentication_type', $data['db_authentication_type'])
		->setName('form_auth')
		->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
		->disablePasswordAutofill()
		->addItem((new CTabView())
			->setSelected($data['form_refresh'] ? null : 0)
			->addTab('auth', _('Authentication'), $auth_tab)
			->addTab('http', _('HTTP settings'), $http_tab)
			->addTab('ldap', _('LDAP settings'), $ldap_tab)
			->addTab('saml', _('SAML settings'), $saml_tab)
			->setFooter(makeFormFooter(
				(new CSubmit('update', _('Update'))),
				[(new CSubmitButton(_('Test'), 'ldap_test', 1))
					->addStyle(($data['form_refresh'] && get_cookie('tab', 0) == 2) ? '' : 'display: none')
					->setEnabled($data['ldap_enabled'])
				]
			))
			->onTabChange('jQuery("[name=ldap_test]")[(ui.newTab.index() == 2) ? "show" : "hide"]()')
	))
	->show();

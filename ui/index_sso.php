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


require_once __DIR__.'/include/config.inc.php';

$config = select_config();
$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');

$request = CSession::getValue('request');
CSession::unsetValue(['request']);

if (hasRequest('request')) {
	$request = getRequest('request');
	preg_match('/^\/?(?<filename>[a-z0-9_.]+\.php)(\?.*)?$/i', $request, $test_request);

	if (!array_key_exists('filename', $test_request) || !file_exists('./'.$test_request['filename'])
			|| $test_request['filename'] === basename(__FILE__)) {
		$request = '';
	}

	if ($request !== '') {
		$redirect_to->setArgument('request', $request);
		CSession::setValue('request', $request);
	}
}

if ($config['saml_auth_enabled'] == ZBX_AUTH_SAML_DISABLED) {
	CSession::unsetValue(['request']);

	redirect($redirect_to->toString());
}

require_once __DIR__.'/vendor/php-saml/_toolkit_loader.php';
require_once __DIR__.'/vendor/xmlseclibs/xmlseclibs.php';

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;

$baseurl = Utils::getSelfURLNoQuery();
$relay_state = null;

$sp_key = '';
$sp_cert = '';
$idp_cert = '';

global $SSO;

if (is_array($SSO) && array_key_exists('SP_KEY', $SSO)) {
	if (file_exists($SSO['SP_KEY'])) {
		$sp_key = file_get_contents($SSO['SP_KEY']);
	}
}
elseif (file_exists('conf/certs/sp.key')) {
	$sp_key = file_get_contents('conf/certs/sp.key');
}

if (is_array($SSO) && array_key_exists('SP_CERT', $SSO)) {
	if (file_exists($SSO['SP_CERT'])) {
		$sp_cert = file_get_contents($SSO['SP_CERT']);
	}
}
elseif (file_exists('conf/certs/sp.crt')) {
	$sp_cert = file_get_contents('conf/certs/sp.crt');
}

if (is_array($SSO) && array_key_exists('IDP_CERT', $SSO)) {
	if (file_exists($SSO['IDP_CERT'])) {
		$idp_cert = file_get_contents($SSO['IDP_CERT']);
	}
}
elseif (file_exists('conf/certs/idp.crt')) {
	$idp_cert = file_get_contents('conf/certs/idp.crt');
}

$settings = [
	'sp' => [
		'entityId' => $config['saml_sp_entityid'],
		'assertionConsumerService' => [
			'url' => $baseurl.'?acs'
		],
		'singleLogoutService' => [
			'url' => $baseurl.'?sls',
		],
		'NameIDFormat' => $config['saml_nameid_format'],
		'x509cert' => $sp_cert,
		'privateKey' => $sp_key
	],
	'idp' => [
		'entityId' => $config['saml_idp_entityid'],
		'singleSignOnService' => [
			'url' => $config['saml_sso_url'],
		],
		'singleLogoutService' => [
			'url' => $config['saml_slo_url'],
		],
		'x509cert' => $idp_cert
	],
	'security' => [
		'nameIdEncrypted' => (bool) $config['saml_encrypt_nameid'],
		'authnRequestsSigned' => (bool) $config['saml_sign_authn_requests'],
		'logoutRequestSigned' => (bool) $config['saml_sign_logout_requests'],
		'logoutResponseSigned' => (bool) $config['saml_sign_logout_responses'],
		'wantMessagesSigned' => (bool) $config['saml_sign_messages'],
		'wantAssertionsEncrypted' => (bool) $config['saml_encrypt_assertions'],
		'wantAssertionsSigned' => (bool) $config['saml_sign_assertions'],
		'wantNameIdEncrypted' => (bool) $config['saml_encrypt_nameid']
	]
];

if (is_array($SSO) && array_key_exists('SETTINGS', $SSO)) {
	foreach (['strict', 'compress', 'contactPerson', 'organization'] as $option) {
		if (array_key_exists($option, $SSO['SETTINGS'])) {
			$settings[$option] = $SSO['SETTINGS'][$option];
		}
	}

	if (array_key_exists('sp', $SSO['SETTINGS'])) {
		foreach (['attributeConsumingService', 'x509certNew'] as $option) {
			if (array_key_exists($option, $SSO['SETTINGS']['sp'])) {
				$settings['sp'][$option] = $SSO['SETTINGS']['sp'][$option];
			}
		}
	}

	if (array_key_exists('idp', $SSO['SETTINGS'])) {
		if (array_key_exists('singleLogoutService', $SSO['SETTINGS']['idp'])
				&& array_key_exists('responseUrl', $SSO['SETTINGS']['idp']['singleLogoutService'])) {
			$settings['idp']['singleLogoutService']['responseUrl'] =
				$SSO['SETTINGS']['idp']['singleLogoutService']['responseUrl'];
		}

		foreach (['certFingerprint', 'certFingerprintAlgorithm', 'x509certMulti'] as $option) {
			if (array_key_exists($option, $SSO['SETTINGS']['idp'])) {
				$settings['idp'][$option] = $SSO['SETTINGS']['idp'][$option];
			}
		}
	}

	if (array_key_exists('security', $SSO['SETTINGS'])) {
		foreach (['signMetadata', 'wantNameId', 'requestedAuthnContext', 'requestedAuthnContextComparison',
				'wantXMLValidation', 'relaxDestinationValidation', 'destinationStrictlyMatches', 'lowercaseUrlencoding',
				'rejectUnsolicitedResponsesWithInResponseTo', 'signatureAlgorithm', 'digestAlgorithm'] as $option) {
			if (array_key_exists($option, $SSO['SETTINGS']['security'])) {
				$settings['security'][$option] = $SSO['SETTINGS']['security'][$option];
			}
		}
	}
}

try {
	$auth = new Auth($settings);

	if (hasRequest('acs') && !CSession::keyExists('saml_data')) {
		$auth->processResponse();

		if (!$auth->isAuthenticated()) {
			throw new Exception($auth->getLastErrorReason());
		}

		$user_attributes = $auth->getAttributes();

		if (!array_key_exists($config['saml_username_attribute'], $user_attributes)) {
			throw new Exception(
				_s('The parameter "%1$s" is missing from the user attributes.', $config['saml_username_attribute'])
			);
		}

		CSession::setValue('saml_data', [
			'username_attribute' => reset($user_attributes[$config['saml_username_attribute']]),
			'nameid' => $auth->getNameId(),
			'nameid_format' => $auth->getNameIdFormat(),
			'nameid_name_qualifier' => $auth->getNameIdNameQualifier(),
			'nameid_sp_name_qualifier' => $auth->getNameIdSPNameQualifier(),
			'session_index' => $auth->getSessionIndex()
		]);

		if (hasRequest('RelayState') && strpos(getRequest('RelayState'), $baseurl) === false) {
			$relay_state = getRequest('RelayState');
		}
	}

	if ($config['saml_slo_url'] !== '') {
		if (hasRequest('slo') && CSession::keyExists('saml_data')) {
			$saml_data = CSession::getValue('saml_data');

			CWebUser::logout();

			$auth->logout(null, [], $saml_data['nameid'], $saml_data['session_index'], false,
				$saml_data['nameid_format'], $saml_data['nameid_name_qualifier'], $saml_data['nameid_sp_name_qualifier']
			);
		}

		if (hasRequest('sls')) {
			$auth->processSLO();

			redirect('index.php');
		}
	}

	if (CWebUser::isLoggedIn() && !CWebUser::isGuest()) {
		redirect($redirect_to->toString());
	}

	if (CSession::keyExists('saml_data')) {
		$saml_data = CSession::getValue('saml_data');
		$user = API::getApiService('user')->loginByAlias($saml_data['username_attribute'],
			($config['saml_case_sensitive'] == ZBX_AUTH_CASE_SENSITIVE), $config['authentication_type']
		);

		if ($user['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
			CSession::unsetValue(['saml_data']);

			throw new Exception(_('GUI access disabled.'));
		}

		CWebUser::setSessionCookie($user['sessionid']);

		$redirect = array_filter([$request, $user['url'], $relay_state, ZBX_DEFAULT_URL]);
		redirect(reset($redirect));
	}

	$auth->login();
}
catch (Exception $e) {
	error($e->getMessage());
}

echo (new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => array_column(clear_messages(), 'message'),
	'buttons' => [
		(new CButton('login', _('Login')))->onClick(
			'document.location = '.json_encode(
				$redirect_to
					->setArgument('request', $request)
					->getUrl()
			).';'
		)
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->getOutput();

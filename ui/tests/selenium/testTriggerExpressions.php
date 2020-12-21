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

require_once dirname(__FILE__).'/../include/CWebTest.php';

class testTriggerExpressions extends CWebTest {

	const TRIGGER_ID = 17094;		//'Lack of available memory on server {HOST.NAME}'

	public function testTriggerExpressions_SimpleTest() {
		// Open advanced editor for testing trigger expression results.
		$this->page->login()->open('triggers.php?form=update&triggerid='.self::TRIGGER_ID);
		$this->query('button:Expression constructor')->waitUntilPresent()->one()->click();
		$this->query('button:Test')->waitUntilPresent()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		// Check table headers presence in tesing dialog.
		$table_headers = ['Expression Variable Elements', 'Result type', 'Value',
						'Expression', 'Result', 'Error'];

		foreach ($table_headers as $header) {
			$this->assertTrue($dialog->query('xpath://table//th[text() ="'.$header.'"]')->one()->isPresent());
		}

		// Type value in expression testing form.
		$dialog->query('xpath:.//input[@type="text"]')->waitUntilPresent()->one()->fill('20M');

		// Verify zabbix server connection error message.
		$dialog->query('button:Test')->one()->click();

		$message = $dialog->query('tag:output')->waitUntilPresent()->asMessage()->one();
		$this->assertTrue($message->isBad());
		$this->assertEquals('Cannot evaluate expression', $message->getTitle());

		$message_details = [
			'Connection to Zabbix server "localhost" refused. Possible reasons:',
			'1. Incorrect server IP/DNS in the "zabbix.conf.php";',
			'2. Security environment (for example, SELinux) is blocking the connection;',
			'3. Zabbix server daemon not running;',
			'4. Firewall is blocking TCP connection.',
			'Connection refused'
		];

		foreach ($message_details as $line) {
			$this->assertTrue($message->hasLine($line));
		}
	}
}

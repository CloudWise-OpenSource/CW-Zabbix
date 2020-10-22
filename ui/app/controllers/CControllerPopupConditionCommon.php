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
 * Abstract class for condition popups.
 */
abstract class CControllerPopupConditionCommon extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = $this->getCheckInputs();

		$ret = $this->validateInput($fields);

		if ($this->hasInput('validate')) {
			$ret = $ret && $this->validateFieldsManually();
		}

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN);
	}

	protected function doAction() {
		if ($this->hasInput('validate')) {
			return $this->setResponse(
				(new CControllerResponseData(
					['main_block' => json_encode($this->getManuallyValidatedFields())]
				))->disableView()
			);
		}

		return $this->setResponse(new CControllerResponseData($this->getControllerResponseData()));
	}

	/**
	 * Get fields array with validation rules.
	 *
	 * @return array
	 */
	abstract protected function getCheckInputs();

	/**
	 * Get last type for condition.
	 * If last type changed we save new value to DB.
	 *
	 * @return string
	 */
	abstract protected function getConditionLastType();

	/**
	 * Validate manually fields that we can't properly validate via standard MVC validation rules.
	 */
	abstract protected function validateFieldsManually();

	/**
	 * Get data array with manually validated fields for response.
	 *
	 * @return array
	 */
	abstract protected function getManuallyValidatedFields();

	/**
	 * Get data array for response.
	 *
	 * @return array
	 */
	abstract protected function getControllerResponseData();
}

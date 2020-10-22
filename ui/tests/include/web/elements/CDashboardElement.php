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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Dashboard element.
 */
class CDashboardElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('class:dashbrd-grid-container'))->asDashboard();
	}

	/**
	 * Get dashboard title as text.
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->query('xpath://h1[@id="page-title-general"]')->one()->getText();
	}

	/**
	 * Check if dashboard is empty.
	 *
	 * @return boolean
	 */
	public function isEmpty() {
		return ($this->query('xpath:.//div[@class="dashbrd-grid-new-widget-placeholder"]')->one(false)->isValid());
	}

	/**
	 * Get dashboard widgets.
	 *
	 * @return CElementCollection
	 */
	public function getWidgets() {
		return $this->query("xpath:.//div[".CXPathHelper::fromClass("dashbrd-grid-widget").
				" or ".CXPathHelper::fromClass("dashbrd-grid-iterator")."]")->asWidget()->all();
	}

	/**
	 * Get widget by name.
	 *
	 * @param string  $name            widget name
	 * @param boolean $should_exist    if method is allowed to return null as a result
	 *
	 * @return CWidgetElement|CNullElement
	 */
	public function getWidget($name, $should_exist = true) {
		$query = $this->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-head") or'.
				' contains(@class, "dashbrd-grid-iterator-head")]/h4[text()='.
				CXPathHelper::escapeQuotes($name).']/../../..');

		if ($should_exist) {
			$query->waitUntilPresent();
		}

		$widget = $query->asWidget()->one($should_exist);
		if ($widget->isValid() && $should_exist) {
				$widget->waitUntilReady();
		}

		return $widget;
	}

	/**
	 * Get dashboard controls section.
	 *
	 * @return CElement
	 */
	public function getControls() {
		return $this->query('xpath://ul[@id="dashbrd-control"]')->one();
	}

	/**
	 * Begin dashboard editing.
	 *
	 * @return $this
	 */
	public function edit() {
		$controls = $this->getControls();

		if (!$controls->query('xpath:.//nav[@class="dashbrd-edit"]')->one()->isDisplayed()) {
			$controls->query('id:dashbrd-edit')->one()->click();
			$controls->query('xpath:.//nav[@class="dashbrd-edit"]')->waitUntilVisible();
		}

		return $this;
	}

	/**
	 * Open widget adding form.
	 * Dashboard should be in editing mode.
	 *
	 * @return COverlayDialogElement
	 */
	public function addWidget() {
		$this->checkIfEditable();
		$this->getControls()->query('id:dashbrd-add-widget')->one()->click();

		return $this->query('xpath://div[contains(@class, "overlay-dialogue")][@data-dialogueid="widgetConfg"]')
				->waitUntilVisible()->asOverlayDialog()->one()->waitUntilReady();
	}

	/**
	 * Cancel dashboard editing.
	 *
	 * @return $this
	 */
	public function cancelEditing() {
		$controls = $this->getControls();

		if ($controls->query('xpath:.//nav[@class="dashbrd-edit"]')->one()->isDisplayed()) {
			$controls->query('id:dashbrd-cancel')->one()->click(true);

			if (!$controls->isStalled()) {
				$controls->query('xpath:.//nav[@class="dashbrd-edit"]')->waitUntilNotVisible();
			}
		}

		return $this;
	}

	/**
	 * Save dashboard.
	 * Dashboard should be in editing mode.
	 *
	 * @return $this
	 */
	public function save() {
		$controls = $this->getControls();

		if ($controls->query('xpath:.//nav[@class="dashbrd-edit"]')->one()->isDisplayed()) {
			$controls->query('id:dashbrd-save')->one()->waitUntilClickable()->click(true);
			$controls->query('xpath:.//nav[@class="dashbrd-edit"]')->waitUntilNotVisible();
		}

		return $this;
	}

	/**
	 * Delete widget with the provided name.
	 * Dashboard should be in editing mode.
	 *
	 * @param string $name    name of widget to be deleted
	 *
	 * @return $this
	 */
	public function deleteWidget($name) {
		$this->checkIfEditable();
		$this->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-head") or contains(@class,'.
				' "dashbrd-grid-iterator-head")]/h4[text()="'.$name.
				'"]/../ul/li/button[@title="Actions"]')->asPopupButton()->one()->select('Delete')->waitUntilNotVisible();


		return $this;
	}

	/**
	 * Copy widget with the provided name.
	 *
	 * @param string $name    name of widget to be copied
	 *
	 * @return $this
	 */
	public function copyWidget($name) {
		$this->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-head") or contains(@class,'.
				' "dashbrd-grid-iterator-head")]/h4[text()="'.$name.
				'"]/../ul/li/button[@title="Actions"]')->asPopupButton()->one()->select('Copy');

		return $this;
	}

	/**
	 * Paste copied widget.
	 * Dashboard should be in editing mode.
	 *
	 * @return $this
	 */
	public function pasteWidget() {
		$this->checkIfEditable();
		$this->getControls()->query('id:dashbrd-paste-widget')->one()->waitUntilClickable()->click(true);

		return $this;
	}

	/**
	 * Replace widget with the provided name to previously copied widget.
	 * Dashboard should be in editing mode.
	 *
	 * @param string $name    name of widget to be replaced
	 *
	 * @return $this
	 */
	public function replaceWidget($name) {
		$this->checkIfEditable();

		$this->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-head") or contains(@class,'.
				' "dashbrd-grid-iterator-head")]/h4[text()="'.$name.
				'"]/../ul/li/button[@title="Actions"]')->asPopupButton()->one()->select('Paste');

		return $this;
	}

	/**
	 * Checking Dashboard controls state.
	 *
	 * @param boolean $editable    editable state of dashboard
	 *
	 * @return boolean
	 */
	public function isEditable($editable = true) {
		return $this->getControls()->query('xpath:.//nav[@class="dashbrd-edit"]')->one()->isDisplayed($editable);
	}

	/**
	 * Checking that Dashboard is in edit mode.
	 *
	 * @param boolean $editable    editable state of dashboard
	 *
	 * @throws \Exception
	 */
	public function checkIfEditable($editable = true) {
		if ($this->isEditable($editable) === false) {
			throw new \Exception('Dashboard is'.($editable ? ' not' : '').' in editing mode.');
		}
	}
}

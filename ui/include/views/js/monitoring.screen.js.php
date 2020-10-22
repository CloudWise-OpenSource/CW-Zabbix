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

<script type="text/javascript">
	var monitoringScreen = {
		refreshOnAcknowledgeCreateSubscribed: false,

		/**
		 * Find and refresh screen responsible for launching the "Update problem" popup after it was submitted.
		 *
		 * @param {object} response  The response object from the "acknowledge.create" action.
		 * @param {object} overlay   The overlay object of the "Update problem" popup form.
		 */
		refreshOnAcknowledgeCreateHandler: function(response, overlay) {
			var handle_selector = '.screenitem',
				handle = overlay.trigger_parents.filter(handle_selector).get(0);

			if (!handle) {
				var dialogue = overlay.trigger_parents.filter('.overlay-dialogue');
				if (dialogue.length) {
					var dialogue_overlay = overlays_stack.getById(dialogue.data('hintboxid'));
					if (dialogue_overlay && dialogue_overlay.type === 'hintbox') {
						handle = dialogue_overlay.element.closest(handle_selector);
					}
				}
			}

			if (handle) {
				for (var id in flickerfreeScreen.screens) {
					if (flickerfreeScreen.screens.hasOwnProperty(id)) {
						var screen = document.getElementById('flickerfreescreen_' + id);
						if ($.contains(screen, handle) || $.contains(handle, screen)) {
							for (var i = overlays_stack.length - 1; i >= 0; i--) {
								var hintbox = overlays_stack.getById(overlays_stack.stack[i]);
								if (hintbox.type === 'hintbox') {
									hintbox_handle = hintbox.element.closest(handle_selector);
									if ($.contains(screen, hintbox_handle) || $.contains(hintbox_handle, screen)) {
										hintBox.hideHint(hintbox.element, true);
									}
								}
							}

							clearMessages();
							addMessage(makeMessageBox('good', [], response.message, true, false));
							flickerfreeScreen.refresh(id);
						}
					}
				}
			}
		},
		refreshOnAcknowledgeCreate: function() {
			if (!this.refreshOnAcknowledgeCreateSubscribed) {
				$.subscribe('acknowledge.create',
					(event, response, overlay) => this.refreshOnAcknowledgeCreateHandler(response, overlay)
				);

				this.refreshOnAcknowledgeCreateSubscribed = true;
			}
		}
	};
</script>

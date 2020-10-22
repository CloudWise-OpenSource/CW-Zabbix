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


const SIDEBAR_VIEW_MODE_FULL       = 0;
const SIDEBAR_VIEW_MODE_COMPACT    = 1;
const SIDEBAR_VIEW_MODE_HIDDEN     = 2;

const SIDEBAR_HOVER_DELAY          = 1000;

const SIDEBAR_EVENT_BLUR           = 'blur';
const SIDEBAR_EVENT_CLOSE          = 'close';
const SIDEBAR_EVENT_FOCUS          = 'focus';
const SIDEBAR_EVENT_OPEN           = 'open';
const SIDEBAR_EVENT_VIEWMODECHANGE = 'viewmodechange';

class CSidebar extends CBaseComponent {

	constructor(target) {
		super(target);

		this.init();
		this.registerEvents();
	}

	init() {
		this._sidebar_toggle = document.getElementById('sidebar-button-toggle');
		this._sidebar_scrollable = this._target.querySelector('.scrollable');

		this._is_focused = false;
		this._is_opened = false;

		const sidebar_header = this._target.querySelector('.sidebar-header'),
			sidebar_header_style = window.getComputedStyle(sidebar_header),
			sidebar_header_style_position = sidebar_header_style.position;

		sidebar_header.style.position = 'absolute';

		let max_width = sidebar_header.clientWidth + parseInt(sidebar_header_style.getPropertyValue('margin-left'))
			+ parseInt(sidebar_header_style.getPropertyValue('margin-right'));

		sidebar_header.style.position = sidebar_header_style_position;

		for (const child of this._target.querySelectorAll('nav > ul')) {
			const position = window.getComputedStyle(child).position;
			child.style.position = 'absolute';
			max_width = Math.max(max_width, child.clientWidth);
			child.style.position = position;
		}
		this._target.style.maxWidth = max_width + 'px';

		const server_name = this._target.querySelector('.server-name');
		if (server_name) {
			server_name.style.width = 'auto';
			server_name.style.maxWidth = max_width + 'px';
		}

		this._view_mode = SIDEBAR_VIEW_MODE_FULL;
		if (this.hasClass('is-compact')) {
			this._view_mode = SIDEBAR_VIEW_MODE_COMPACT;
		}
		else if (this.hasClass('is-hidden')) {
			this._view_mode = SIDEBAR_VIEW_MODE_HIDDEN;
			this.addClass('focus-off');
		}
		this.setViewMode(this._view_mode);
	}

	open() {
		clearTimeout(this._opened_timer);

		if (!this._is_opened) {
			this._is_opened = true;

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				ZABBIX.MenuMain.expandSelected();
			}

			if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
				this.removeClass('focus-off');
				ZABBIX.MenuMain.focusSelected();
			}

			if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
				this._target.style.zIndex = '10001';
				setTimeout(() => {
					document.addEventListener('keyup', this._events.escape);
					document.addEventListener('click', this._events.click);
				});
			}

			setTimeout(() => this.addClass('is-opened'));

			this.fire(SIDEBAR_EVENT_OPEN);
		}

		return this;
	}

	close() {
		clearTimeout(this._opened_timer);

		if (this._is_opened) {
			this._is_opened = false;

			if (this._view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
				ZABBIX.MenuMain.collapseAll();
				this._sidebar_scrollable.scrollTop = 0;
			}

			if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
				const active_parent = document.activeElement.parentElement;
				if (active_parent !== null && active_parent.classList.contains('has-submenu')) {
					active_parent.blur();
				}

				this._opened_timer = setTimeout(() => {
					if (this._view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
						this.addClass('focus-off');
					}
					this._target.style.zIndex = null;
				}, UI_TRANSITION_DURATION);

				document.removeEventListener('keyup', this._events.escape);
				document.removeEventListener('click', this._events.click);
			}

			this.removeClass('is-opened');

			this.fire(SIDEBAR_EVENT_CLOSE);
		}

		return this;
	}

	/**
	 * Set view mode {0: full, 1: compact, 2: hidden}.
	 *
	 * @param {number} view_mode
	 *
	 * @returns {CSidebar}
	 */
	setViewMode(view_mode) {
		if (view_mode === SIDEBAR_VIEW_MODE_FULL) {
			this._is_opened = false;
			this.removeClass('is-opened');
		}
		this.toggleClass('is-compact', view_mode === SIDEBAR_VIEW_MODE_COMPACT);
		this.toggleClass('is-hidden', view_mode === SIDEBAR_VIEW_MODE_HIDDEN);

		if (this._view_mode !== view_mode) {
			this._view_mode = view_mode;
			this.fire(SIDEBAR_EVENT_VIEWMODECHANGE, {view_mode: this._view_mode});
		}

		return this;
	}

	/**
	 * Register all DOM events.
	 */
	registerEvents() {
		this._events = {

			mouseenter: () => {
				this.open();
			},

			mouseleave: () => {
				if (!this._is_focused || document.activeElement.parentElement === null
						|| document.activeElement.parentElement.classList.contains('has-submenu')) {
					clearTimeout(this._opened_timer);
					this._opened_timer = setTimeout(() => this.close(), SIDEBAR_HOVER_DELAY);
				}
			},

			focusin: (e) => {
				if (!this._target.contains(e.relatedTarget)) {
					this._is_focused = true;

					if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
						this.open();
					}

					this.fire(SIDEBAR_EVENT_FOCUS);
				}
			},

			focusout: (e) => {
				if (!this._target.contains(e.relatedTarget) && !this._target.matches(':hover')) {
					this._is_focused = false;

					if ([SIDEBAR_VIEW_MODE_COMPACT, SIDEBAR_VIEW_MODE_HIDDEN].includes(this._view_mode)) {
						setTimeout(() => this.close());
					}

					this.fire(SIDEBAR_EVENT_BLUR);
				}
			},

			click: (e) => {
				if (this._is_opened && !this._target.contains(e.target)) {
					this.close();
				}
			},

			escape: (e) => {
				if (e.key === 'Escape') {
					this.close();
				}
			},

			toggle: (e) => {
				if (!this._is_opened) {
					this.open();
				}
				else {
					this.close();
				}

				e.preventDefault();
			},

			expandSelected: () => {
				this._expand_timer = setTimeout(() => {
					ZABBIX.MenuMain.expandSelected();
				}, MENU_EXPAND_SELECTED_DELAY);
			},

			cancelExpandSelected: () => {
				clearTimeout(this._expand_timer);
			},

			expand: (e) => {
				if (this._sidebar_scrollable.scrollHeight > this._sidebar_scrollable.clientHeight) {
					setTimeout(() => {
						e.detail.menu_item._target.scrollIntoView({
							behavior: 'smooth',
							block: 'nearest'
						});
					}, UI_TRANSITION_DURATION);
				}
			},

			viewmodechange: (e) => {
				if (e.target.classList.contains('button-compact')) {
					ZABBIX.MenuMain.collapseAll();
					clearTimeout(this._expand_timer);
					this.setViewMode(SIDEBAR_VIEW_MODE_COMPACT);
				}
				else if (e.target.classList.contains('button-hide')) {
					this.setViewMode(SIDEBAR_VIEW_MODE_HIDDEN);
				}
				else {
					this.setViewMode(SIDEBAR_VIEW_MODE_FULL);
				}

				this._events._update(this._view_mode);

				e.preventDefault();
			},

			/**
			 * Update event listeners based on view mode.
			 *
			 * @param view_mode
			 *
			 * @private
			 */
			_update: (view_mode) => {
				if (view_mode === SIDEBAR_VIEW_MODE_COMPACT) {
					this.on('mouseenter', this._events.mouseenter);
					this.on('mouseleave', this._events.mouseleave);
				}
				else {
					this.off('mouseenter', this._events.mouseenter);
					this.off('mouseleave', this._events.mouseleave);
				}

				if (this._sidebar_toggle !== null) {
					if (view_mode === SIDEBAR_VIEW_MODE_HIDDEN) {
						this._sidebar_toggle.addEventListener('click', this._events.toggle);
					}
					else {
						this._sidebar_toggle.removeEventListener('click', this._events.toggle);
					}
				}

				if ([SIDEBAR_VIEW_MODE_FULL, SIDEBAR_VIEW_MODE_HIDDEN].includes(view_mode)) {
					this.on('mouseleave', this._events.expandSelected);
					this.on('mouseenter', this._events.cancelExpandSelected);
				}
				else {
					this.off('mouseleave', this._events.expandSelected);
					this.off('mouseenter', this._events.cancelExpandSelected);
				}

				if (view_mode === SIDEBAR_VIEW_MODE_FULL) {
					document.removeEventListener('keyup', this._events.escape);
					document.removeEventListener('click', this._events.click);
				}
			}
		};

		this.on('focusin', this._events.focusin);
		this.on('focusout', this._events.focusout);

		for (const el of this._target.querySelectorAll('.js-sidebar-mode')) {
			el.addEventListener('click', this._events.viewmodechange);
		}

		ZABBIX.MenuMain.on('expand', this._events.expand);

		this._events._update(this._view_mode);
	}
}

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
	jQuery(document).ready(function() {
		<?php if (defined('ZBX_PAGE_DO_REFRESH') && CWebUser::getRefresh() != 0): ?>
			PageRefresh.init(<?= CWebUser::getRefresh() * 1000 ?>);
		<?php endif ?>

		<?php if (isset($page['scripts']) && in_array('flickerfreescreen.js', $page['scripts'])): ?>
			window.flickerfreeScreen.responsiveness = <?php echo SCREEN_REFRESH_RESPONSIVENESS * 1000; ?>;
		<?php endif ?>

		// the chkbxRange.init() method must be called after the inserted post scripts and initializing cookies
		cookie.init();
		chkbxRange.init();
	});
</script>

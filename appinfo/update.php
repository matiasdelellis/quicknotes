<?php
/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2016
 */

$installedVersion = \OCP\Config::getAppValue('quicknotes', 'installed_version');

if (version_compare($installedVersion, '0.0.8', '<')) {
	$sqls = array(
		"INSERT INTO `*PREFIX*quicknotes_colors` (color) VALUES ('#F7EB98');", // Other color the default no handle easy duplicate colors..
		"UPDATE `*PREFIX*quicknotes_notes` SET color_id = (SELECT DISTINCT id FROM `*PREFIX*quicknotes_colors` WHERE color = '#F7EB98');"
	);
	foreach ($sqls as $sql) {
		$query = \OCP\DB::prepare($sql);
		$query->execute();
	}
}


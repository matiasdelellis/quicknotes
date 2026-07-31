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

require_once __DIR__ . '/../../../tests/bootstrap.php';

$autoload = __DIR__ . '/../appinfo/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
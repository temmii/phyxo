<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!defined('PHPWG_ROOT_PATH')) {
    die("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

define('RATING_BASE_URL', get_root_url().'admin/index.php?page=rating');

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'photos';
}

$tabsheet = new TabSheet();
$tabsheet->add('photos', l10n('Photos'), RATING_BASE_URL.'&amp;section=photos');
$tabsheet->add('users', l10n('Users'), RATING_BASE_URL.'&amp;section=users');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => RATING_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'rating_'.$page['section'];

include(PHPWG_ROOT_PATH.'admin/rating_'.$page['section'].'.php');

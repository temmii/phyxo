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

if (!defined("PHPWG_ROOT_PATH")) {
    die ("Hacking attempt!");
}

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Basic checks                                                          |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

check_input_parameter('cat_id', $_GET, false, PATTERN_ID);
check_input_parameter('image_id', $_GET, false, PATTERN_ID);

define('PHOTO_BASE_URL', get_root_url().'admin/index.php?page=photo&amp;image_id='.$_GET['image_id']);

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'properties';
}

$tabsheet = new TabSheet();
$tabsheet->add('properties', l10n('Properties'), PHOTO_BASE_URL.'&amp;section=properties');
$tabsheet->add('coi', l10n('Center of interest'), PHOTO_BASE_URL.'&amp;section=coi', 'fa-crop');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => PHOTO_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

if (!empty($_GET['cat_id'])) {
    $query = 'SELECT * FROM '.CATEGORIES_TABLE.' WHERE id = '.(int) $_GET['cat_id'];
    $category = $conn->db_fetch_assoc($conn->db_query($query));
}

// +-----------------------------------------------------------------------+
// |                             Load the tab                              |
// +-----------------------------------------------------------------------+

$template_filename = 'photo_'.$page['section'];

include(PHPWG_ROOT_PATH.'admin/photo_'.$page['section'].'.php');

<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined('PHOTOS_ADD_BASE_URL')) {
    die ("Hacking attempt!");
}

// +-----------------------------------------------------------------------+
// |                        batch management request                       |
// +-----------------------------------------------------------------------+

if (isset($_GET['batch'])) {
    check_input_parameter('batch', $_GET, false, '/^\d+(,\d+)*$/');

    $query = 'DELETE FROM '.CADDIE_TABLE.' WHERE user_id = '.$conn->db_real_escape_string($user['id']);
    $conn->db_query($query);

    $inserts = array();
    foreach (explode(',', $_GET['batch']) as $image_id) {
        $inserts[] = array(
            'user_id' => $user['id'],
            'element_id' => $image_id,
        );
    }
    $conn->mass_inserts(
        CADDIE_TABLE,
        array_keys($inserts[0]),
        $inserts
    );

    redirect(get_root_url().'admin/index.php?page=batch_manager&filter=prefilter-caddie');
}

// +-----------------------------------------------------------------------+
// |                             prepare form                              |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH.'admin/include/photos_add_direct_prepare.inc.php');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+
trigger_notify('loc_end_photo_add_direct');

$template->assign_var_from_handle('ADMIN_CONTENT', 'photos_add');

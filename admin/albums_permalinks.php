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

if (!defined("ALBUMS_BASE_URL")) {
    die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions_permalinks.php');

$selected_cat = array();
if (isset($_POST['set_permalink']) and $_POST['cat_id']>0) {
    $permalink = $_POST['permalink'];
    if (empty($permalink)) {
        delete_cat_permalink($_POST['cat_id'], isset($_POST['save']));
    } else {
        set_cat_permalink($_POST['cat_id'], $permalink, isset($_POST['save']));
    }
    $selected_cat = array( $_POST['cat_id'] );
} elseif ( isset($_GET['delete_permanent'])) {
    $query = 'DELETE FROM '.OLD_PERMALINKS_TABLE;
    $query .= ' WHERE permalink=\''.$conn->db_real_escape_string($_GET['delete_permanent']).'\' LIMIT 1';
    $result = $conn->db_query($query);
    if ($conn->db_changes($result)==0) {
        $page['errors'][] = l10n('Cannot delete the old permalink !');
    }
}

$query = 'SELECT id,permalink,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
display_select_cat_wrapper($query, $selected_cat, 'categories', false);

// --- generate display of active permalinks -----------------------------------
$sort_by = parse_sort_variables(
    array('id', 'name', 'permalink'), 'name',
    'psf',
    array('delete_permanent'),
    'SORT_'
);

$query = 'SELECT id, permalink, uppercats, global_rank FROM '.CATEGORIES_TABLE.' WHERE permalink IS NOT NULL';
if ($sort_by[0]=='id' or $sort_by[0]=='permalink') {
    $query .= ' ORDER BY '.$sort_by[0];
}
$categories=array();
$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $row['name'] = get_cat_display_name_cache($row['uppercats']);
    $categories[] = $row;
}

if ($sort_by[0]=='name') {
    usort($categories, 'global_rank_compare');
}
$template->assign( 'permalinks', $categories );

// --- generate display of old permalinks --------------------------------------

$sort_by = parse_sort_variables(
    array('cat_id','permalink','date_deleted','last_hit','hit'), null,
    'dpsf',
    array('delete_permanent'),
    'SORT_OLD_', '#old_permalinks'
);

$url_del_base = ALBUMS_BASE_URL.'&map;section=permalinks';
$query = 'SELECT * FROM '.OLD_PERMALINKS_TABLE;
if (count($sort_by)) {
    $query .= ' ORDER BY '.$sort_by[0];
}
$result = $conn->db_query($query);
$deleted_permalinks = array();
while ($row = $conn->db_fetch_assoc($result)) {
    $row['name'] = get_cat_display_name_cache($row['cat_id']);
    $row['U_DELETE'] = add_url_params(
        $url_del_base,
        array('delete_permanent'=> $row['permalink'])
    );
    $deleted_permalinks[] = $row;
}
$template->assign('deleted_permalinks', $deleted_permalinks);
$template->assign('U_HELP', get_root_url().'admin/popuphelp.php?page=permalinks');

$template->assign_var_from_handle('ADMIN_CONTENT', 'albums');
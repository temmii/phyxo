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

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

if (!$conf['enable_synchronization']) {
    die('synchronization is disabled');
}

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!empty($_POST) or isset($_GET['action'])) {
    \Phyxo\Functions\Utils::check_token();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+
$template_filename = 'site_manager';

// +-----------------------------------------------------------------------+
// |                        new site creation form                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) and !empty($_POST['galleries_url'])) {
    $is_remote = \Phyxo\Functions\URL::url_is_remote($_POST['galleries_url']);
    if ($is_remote) {
        fatal_error('remote sites not supported');
    }
    $url = preg_replace('/[\/]*$/', '', $_POST['galleries_url']);
    $url .= '/';
    if (!(strpos($url, '.') === 0)) {
        $url = './' . $url;
    }

    // site must not exists
    $query = 'SELECT COUNT(id) AS count FROM ' . SITES_TABLE;
    $query .= ' WHERE galleries_url = \'' . $url . '\';';
    $row = $conn->db_fetch_assoc($conn->db_query($query));
    if ($row['count'] > 0) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('This site already exists') . ' [' . $url . ']';
    }
    if (count($page['errors']) == 0) {
        if (!file_exists($url)) {
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Directory does not exist') . ' [' . $url . ']';
        }
    }

    if (count($page['errors']) == 0) {
        $query = 'INSERT INTO ' . SITES_TABLE . ' (galleries_url) VALUES(\'' . $url . '\');';
        $conn->db_query($query);
        $page['infos'][] = $url . ' ' . \Phyxo\Functions\Language::l10n('created');
    }
}

// +-----------------------------------------------------------------------+
// |                            actions on site                            |
// +-----------------------------------------------------------------------+
if (isset($_GET['site']) and is_numeric($_GET['site'])) {
    $page['site'] = $_GET['site'];
}
if (isset($_GET['action']) and isset($page['site'])) {
    $query = 'SELECT galleries_url FROM ' . SITES_TABLE . ' WHERE id = ' . $conn->db_real_escape_string($page['site']);
    list($galleries_url) = $conn->db_fetch_row($conn->db_query($query));
    if ($_GET['action'] == 'delete') {
        delete_site($page['site']);
        $page['infos'][] = $galleries_url . ' ' . \Phyxo\Functions\Language::l10n('deleted');
    }
}

$template->assign(
    array(
        'F_ACTION' => \Phyxo\Functions\URL::get_root_url() . 'admin/index.php' . \Phyxo\Functions\URL::get_query_string_diff(array('action', 'site', 'pwg_token')),
        'PWG_TOKEN' => \Phyxo\Functions\Utils::get_token(),
    )
);

$query = 'SELECT c.site_id, COUNT(DISTINCT c.id) AS nb_categories, COUNT(i.id) AS nb_images';
$query .= ' FROM ' . CATEGORIES_TABLE . ' AS c LEFT JOIN ' . IMAGES_TABLE . ' AS i ON c.id=i.storage_category_id';
$query .= ' WHERE c.site_id IS NOT NULL GROUP BY c.site_id;';
$sites_detail = $conn->query2array($query, 'site_id');

$query = 'SELECT * FROM ' . SITES_TABLE;
$result = $conn->db_query($query);

while ($row = $conn->db_fetch_assoc($result)) {
    $is_remote = \Phyxo\Functions\URL::url_is_remote($row['galleries_url']);
    $base_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php';
    $base_url .= '?page=site_manager';
    $base_url .= '&amp;site=' . $row['id'];
    $base_url .= '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token();
    $base_url .= '&amp;action=';

    $update_url = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php';
    $update_url .= '?page=site_update';
    $update_url .= '&amp;site=' . $row['id'];

    $tpl_var =
        array(
        'NAME' => $row['galleries_url'],
        'TYPE' => \Phyxo\Functions\Language::l10n($is_remote ? 'Remote' : 'Local'),
        'CATEGORIES' => (int)@$sites_detail[$row['id']]['nb_categories'],
        'IMAGES' => (int)@$sites_detail[$row['id']]['nb_images'],
        'U_SYNCHRONIZE' => $update_url
    );

    if ($row['id'] != 1) {
        $tpl_var['U_DELETE'] = $base_url . 'delete';
    }

    $plugin_links = array();
    //$plugin_links is array of array composed of U_HREF, U_HINT & U_CAPTION
    $plugin_links = \Phyxo\Functions\Plugin::trigger_change(
        'get_admins_site_links',
        $plugin_links,
        $row['id'],
        $is_remote
    );
    $tpl_var['plugin_links'] = $plugin_links;

    $template->append('sites', $tpl_var);
}

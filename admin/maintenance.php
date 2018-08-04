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

use Phyxo\Template\FileCombiner;

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (isset($_GET['action'])) {
    check_pwg_token();
}

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'lock_gallery':
        {
            conf_update_param('gallery_locked', 'true');
            redirect(get_root_url() . 'admin/index.php?page=maintenance');
            break;
        }
    case 'unlock_gallery':
        {
            conf_update_param('gallery_locked', 'false');
            $_SESSION['page_infos'] = array(\Phyxo\Functions\Language::l10n('Gallery unlocked'));
            redirect(get_root_url() . 'admin/index.php?page=maintenance');
            break;
        }
    case 'categories':
        {
            images_integrity();
            update_uppercats();
            update_category('all');
            update_global_rank();
            invalidate_user_cache(true);
            break;
        }
    case 'images':
        {
            images_integrity();
            update_path();
            include_once(PHPWG_ROOT_PATH . 'include/functions_rate.inc.php');
            update_rating_score();
            invalidate_user_cache();
            break;
        }
    case 'delete_orphan_tags':
        {
            $services['tags']->deleteOrphanTags();
            break;
        }
    case 'user_cache':
        {
            invalidate_user_cache();
            break;
        }
    case 'history_detail':
        {
            $query = 'DELETE FROM ' . HISTORY_TABLE . ';';
            $conn->db_query($query);
            break;
        }
    case 'history_summary':
        {
            $query = 'DELETE FROM ' . HISTORY_SUMMARY_TABLE . ';';
            $conn->db_query($query);
            break;
        }
    case 'sessions':
        {
    // pwg_session_gc(); @TODO : sessions handler could be files so no db cleanup
            break;
        }
    case 'feeds':
        {
            $query = 'DELETE FROM ' . USER_FEED_TABLE . ' WHERE last_check IS NULL;';
            $conn->db_query($query);
            break;
        }
    case 'database':
        {
            if ($conn->do_maintenance_all_tables()) {
                $page['infos'][] = \Phyxo\Functions\Language::l10n('All optimizations have been successfully completed.');
            } else {
                $page['errors'][] = \Phyxo\Functions\Language::l10n('Optimizations have been completed with some errors.');
            }
            break;
        }
    case 'search':
        {
            $query = 'DELETE FROM ' . SEARCH_TABLE . ';';
            $conn->db_query($query);
            break;
        }
    case 'compiled-templates':
        {
            $template->delete_compiled_templates();
            FileCombiner::clear_combined_files();
            $persistent_cache->purge(true);
            break;
        }
    case 'derivatives':
        {
            clear_derivative_cache($_GET['type']);
            break;
        }
    default:
        {
            break;
        }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$url_format = get_root_url() . 'admin/index.php?page=maintenance&amp;action=%s&amp;pwg_token=' . get_pwg_token();

$purge_urls[\Phyxo\Functions\Language::l10n('All')] = sprintf($url_format, 'derivatives') . '&amp;type=all';
foreach (ImageStdParams::get_defined_type_map() as $params) {
    $purge_urls[\Phyxo\Functions\Language::l10n($params->type)] = sprintf($url_format, 'derivatives') . '&amp;type=' . $params->type;
}
$purge_urls[\Phyxo\Functions\Language::l10n(IMG_CUSTOM)] = sprintf($url_format, 'derivatives') . '&amp;type=' . IMG_CUSTOM;

$template->assign(
    array(
        'U_MAINT_CATEGORIES' => sprintf($url_format, 'categories'),
        'U_MAINT_IMAGES' => sprintf($url_format, 'images'),
        'U_MAINT_ORPHAN_TAGS' => sprintf($url_format, 'delete_orphan_tags'),
        'U_MAINT_USER_CACHE' => sprintf($url_format, 'user_cache'),
        'U_MAINT_HISTORY_DETAIL' => sprintf($url_format, 'history_detail'),
        'U_MAINT_HISTORY_SUMMARY' => sprintf($url_format, 'history_summary'),
        'U_MAINT_SESSIONS' => sprintf($url_format, 'sessions'),
        'U_MAINT_FEEDS' => sprintf($url_format, 'feeds'),
        'U_MAINT_DATABASE' => sprintf($url_format, 'database'),
        'U_MAINT_SEARCH' => sprintf($url_format, 'search'),
        'U_MAINT_COMPILED_TEMPLATES' => sprintf($url_format, 'compiled-templates'),
        'U_MAINT_DERIVATIVES' => sprintf($url_format, 'derivatives'),
        'purge_derivatives' => $purge_urls,
        //'U_HELP' => get_root_url().'admin/popuphelp.php?page=maintenance',
    )
);


if ($conf['gallery_locked']) {
    $template->assign(
        array(
            'U_MAINT_UNLOCK_GALLERY' => sprintf($url_format, 'unlock_gallery'),
        )
    );
} else {
    $template->assign(
        array(
            'U_MAINT_LOCK_GALLERY' => sprintf($url_format, 'lock_gallery'),
        )
    );
}

// +-----------------------------------------------------------------------+
// | Define advanced features                                              |
// +-----------------------------------------------------------------------+

$advanced_features = array();

//$advanced_features is array of array composed of CAPTION & URL
$advanced_features = trigger_change(
    'get_admin_advanced_features_links',
    $advanced_features
);

$template->assign('advanced_features', $advanced_features);

$template_filename = 'maintenance';

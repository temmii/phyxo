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

define('COMMENTS_BASE_URL', get_root_url() . 'admin/index.php?page=comments');

use Phyxo\TabSheet\TabSheet;

if (isset($_GET['start']) and is_numeric($_GET['start'])) {
    $page['start'] = $_GET['start'];
} else {
    $page['start'] = 0;
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

if (!empty($_POST)) {
    if (empty($_POST['comments'])) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Select at least one comment');
    } else {
        check_input_parameter('comments', $_POST, true, PATTERN_ID);

        if (isset($_POST['validate'])) {
            $services['comments']->validateUserComment($_POST['comments']);

            $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                '%d user comment validated',
                '%d user comments validated',
                count($_POST['comments'])
            );
        }

        if (isset($_POST['reject'])) {
            $services['comments']->deleteUserComment($_POST['comments']);

            $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
                '%d user comment rejected',
                '%d user comments rejected',
                count($_POST['comments'])
            );
        }
    }
}

// +-----------------------------------------------------------------------+
// | Tabs                                                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'all';
}

$tabsheet = new tabsheet();
$tabsheet->add('all', \Phyxo\Functions\Language::l10n('All'), COMMENTS_BASE_URL . '&amp;section=all');
$tabsheet->add('pending', \Phyxo\Functions\Language::l10n('Pendings'), COMMENTS_BASE_URL . '&amp;section=pending');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => COMMENTS_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// |                           comments display                            |
// +-----------------------------------------------------------------------+

$nb_total = 0;
$nb_pending = 0;

$query = 'SELECT COUNT(1) AS counter,validated FROM ' . COMMENTS_TABLE . ' GROUP BY validated;';
$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $nb_total += $row['counter'];

    if ($conn->get_boolean($row['validated']) == false) {
        $nb_pending = $row['counter'];
    }
}

if ($page['section'] === 'all') {
    $template->assign([
        'NB_ELEMENTS' => $nb_total,
        'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('All'),
    ]);
} else {
    $template->assign([
        'NB_ELEMENTS' => $nb_pending,
        'TABSHEET_TITLE' => \Phyxo\Functions\Language::l10n('Pendings'),
    ]);
}

$where_clauses = array('1=1');

if ('pending' == $page['section']) {
    $where_clauses[] = 'validated=\'' . $conn->boolean_to_db(false) . '\'';
}

$query = 'SELECT c.id,c.image_id,c.date,c.author,';
$query .= $conf['user_fields']['username'] . ' AS username,c.content,i.path,';
$query .= 'i.representative_ext,validated,c.anonymous_id FROM ' . COMMENTS_TABLE . ' AS c';
$query .= ' LEFT JOIN ' . IMAGES_TABLE . ' AS i ON i.id = c.image_id';
$query .= ' LEFT JOIN ' . USERS_TABLE . ' AS u ON u.' . $conf['user_fields']['id'] . ' = c.author_id';
$query .= ' WHERE ' . implode(' AND ', $where_clauses);
$query .= ' ORDER BY c.date DESC';
$query .= ' LIMIT ' . $conf['comments_page_nb_comments'] . ' OFFSET ' . $page['start'];

$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $thumb = DerivativeImage::thumb_url(
        array(
            'id' => $row['image_id'],
            'path' => $row['path'],
        )
    );
    if (empty($row['author_id'])) {
        $author_name = $row['author'];
    } else {
        $author_name = stripslashes($row['username']);
    }
    $template->append(
        'comments',
        array(
            'U_PICTURE' => get_root_url() . 'admin/index.php?page=photo-' . $row['image_id'],
            'ID' => $row['id'],
            'TN_SRC' => $thumb,
            'AUTHOR' => trigger_change('render_comment_author', $author_name),
            'DATE' => format_date($row['date'], array('day_name', 'day', 'month', 'year', 'time')),
            'CONTENT' => trigger_change('render_comment_content', $row['content']),
            'IS_PENDING' => $conn->get_boolean($row['validated']) === false,
            'IP' => $row['anonymous_id'],
        )
    );

    $list[] = $row['id'];
}

// +-----------------------------------------------------------------------+
// |                            navigation bar                             |
// +-----------------------------------------------------------------------+

$navbar = create_navigation_bar(
    get_root_url() . 'admin/index.php' . get_query_string_diff(array('start')),
    ('pending' == $page['section'] ? $nb_pending : $nb_total),
    $page['start'],
    $conf['comments_page_nb_comments']
);

$template->assign('navbar', $navbar);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'comments';

$template->assign(array('F_ACTION' => get_root_url() . 'admin/index.php?page=comments'));

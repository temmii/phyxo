<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2017 Nicolas Roudaire         http://www.phyxo.net/ |
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

if (!defined('ALBUM_BASE_URL')) {
    die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// |                       variable initialization                         |
// +-----------------------------------------------------------------------+

$page['cat'] = $category['id'];

// +-----------------------------------------------------------------------+
// |                           form submission                             |
// +-----------------------------------------------------------------------+

// info by email to an access granted group of category informations
if (isset($_POST['submitEmail']) and !empty($_POST['group'])) {
    set_make_full_url();

    /* @TODO: if $category['representative_picture_id']
       is empty find child representative_picture_id */
    if (!empty($category['representative_picture_id'])) {
        $query = 'SELECT id, file, path, representative_ext FROM '.IMAGES_TABLE;
        $query .= ' WHERE id = '.$category['representative_picture_id'];

        $result = $conn->db_query($query);
        if ($conn->db_num_rows($result) > 0) {
            $element = $conn->db_fetch_assoc($result);

            $img_url  = '<a href="'.
                make_picture_url(array(
                    'image_id' => $element['id'],
                    'image_file' => $element['file'],
                    'category' => $category
                )).'" class="thumblnk"><img src="'.DerivativeImage::url(IMG_THUMB, $element).'"></a>';
        }
    }

    if (!isset($img_url)) {
        $img_url = '';
    }

    pwg_mail_group(
        $_POST['group'],
        array(
            'subject' => l10n('[%s] Visit album %s', $conf['gallery_title'], trigger_change('render_category_name', $category['name'], 'admin_cat_list')),
            // @TODO : change this language variable to 'Visit album %s'
            // @TODO : 'language_selected' => ....
        ),
        array(
            'filename' => 'cat_group_info',
            'assign' => array(
                'IMG_URL' => $img_url,
                'CAT_NAME' => trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
                'LINK' => make_index_url(array(
                    'category' => array(
                        'id' => $category['id'],
                        'name' => trigger_change('render_category_name', $category['name'], 'admin_cat_list'),
                        'permalink' => $category['permalink']
                    )
                )),
                'CPL_CONTENT' => empty($_POST['mail_content']) ? '' : stripslashes($_POST['mail_content']),
            )
        )
    );

    unset_make_full_url();

    $query = 'SELECT name FROM '.GROUPS_TABLE.' WHERE id = '.$conn->db_real_escape_string($_POST['group']);
    list($group_name) = $conn->db_fetch_row($conn->db_query($query));

    $page['infos'][] = l10n('An information email was sent to group "%s"', $group_name);
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->assign(
    array(
        'CATEGORIES_NAV' =>
        get_cat_display_name_from_id(
            $page['cat'],
            'admin/index.php?page=album&amp;cat_id='
        ),
        'F_ACTION' => ALBUM_BASE_URL.'&amp;section=notification',
        'PWG_TOKEN' => get_pwg_token(),
    )
);

// +-----------------------------------------------------------------------+
// |                          form construction                            |
// +-----------------------------------------------------------------------+

$query = 'SELECT id AS group_id FROM '.GROUPS_TABLE;
$all_group_ids = $conn->query2array($query, null, 'group_id');

if (count($all_group_ids) == 0) {
    $template->assign('no_group_in_gallery', true);
} else {
    if ('private' == $category['status']) {
        $query = 'SELECT group_id FROM '.GROUP_ACCESS_TABLE.' WHERE cat_id = '.$category['id'];
        $group_ids = $conn->query2array($query, null, 'group_id');

        if (count($group_ids) == 0) {
            $template->assign('permission_url', ALBUM_BASE_URL.'&amp;section=permissions');
        }
    } else {
        $group_ids = $all_group_ids;
    }

    if (count($group_ids) > 0) {
        $query = 'SELECT id,name FROM '.GROUPS_TABLE;
        $query .= ' WHERE id '.$conn->in($group_ids).' ORDER BY name ASC';
        $template->assign(
            'group_mail_options',
            $conn->query2array($query, 'id', 'name')
        );
    }
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'album');

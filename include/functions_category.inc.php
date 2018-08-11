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

/**
 * @package functions\category
 */


/**
 * Callback used for sorting by global_rank
 */
function global_rank_compare($a, $b)
{
    return strnatcasecmp($a['global_rank'], $b['global_rank']);
}

/**
 * Callback used for sorting by rank
 */
function rank_compare($a, $b)
{
    return $a['rank'] - $b['rank'];
}

/**
 * Is the category accessible to the connected user ?
 * If the user is not authorized to see this category, script exits
 *
 * @param int $category_id
 */
function check_restrictions($category_id)
{
    global $user;

    // $filter['visible_categories'] and $filter['visible_images']
    // are not used because it's not necessary (filter <> restriction)
    if (in_array($category_id, explode(',', $user['forbidden_categories']))) {
        access_denied();
    }
}

/**
 * Returns template vars for main categories menu.
 *
 * @return array[]
 */
function get_recursive_categories_menu()
{
    $flat_categories = get_categories_menu();

    $categories = [];
    foreach ($flat_categories as $category) {
        if ($category['uppercats'] === $category['id']) {
            $categories[$category['id']] = $category;
        } else {
            insert_category($categories, $category, $category['uppercats']);
        }
    }

    return $categories;
}

// insert recursively category in tree
function insert_category(&$categories, $category, $uppercats)
{
    if ($category['id'] != $uppercats) {
        $cats = explode(',', $uppercats);
        $cat = $cats[0];

        $new_uppercats = array_slice($cats, 1);
        if (count($new_uppercats) === 1) {
            $categories[$cat]['children'][$category['id']] = $category;
        } else {
            insert_category($categories[$cat]['children'], $category, implode(',', $new_uppercats));
        }
    }
}


/**
 * Returns template vars for main categories menu.
 *
 * @return array[]
 */
function get_categories_menu()
{
    global $page, $user, $filter, $conf, $conn;

    $query = 'SELECT ';
    // From CATEGORIES_TABLE
    $query .= 'id, name, permalink, nb_images, global_rank,uppercats,';
    // From USER_CACHE_CATEGORIES_TABLE
    $query .= 'date_last, max_date_last, count_images, count_categories';

    // $user['forbidden_categories'] including with USER_CACHE_CATEGORIES_TABLE
    $query .= ' FROM ' . CATEGORIES_TABLE . ' LEFT JOIN ' . USER_CACHE_CATEGORIES_TABLE;
    $query .= ' ON id = cat_id and user_id = ' . $user['id'];

    // Always expand when filter is activated
    if (!$user['expand'] and !$filter['enabled']) {
        $where = ' (id_uppercat is NULL';
        if (isset($page['category'])) {
            $where .= ' OR id_uppercat ' . $conn->in($page['category']['uppercats']);
        }
        $where .= ')';
    } else {
        $where = ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(array('visible_categories' => 'id'), null, true);
    }

    $where = \Phyxo\Functions\Plugin::trigger_change('get_categories_menu_sql_where', $where, $user['expand'], $filter['enabled']);

    $query .= ' WHERE ' . $where;

    $result = $conn->db_query($query);
    $cats = array();
    $selected_category = isset($page['category']) ? $page['category'] : null;
    while ($row = $conn->db_fetch_assoc($result)) {
        $child_date_last = @$row['max_date_last'] > @$row['date_last'];
        $row = array_merge(
            $row,
            array(
                'NAME' => \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $row['name'],
                    'get_categories_menu'
                ),
                'TITLE' => get_display_images_count(
                    $row['nb_images'],
                    $row['count_images'],
                    $row['count_categories'],
                    false,
                    ' / '
                ),
                'URL' => \Phyxo\Functions\URL::make_index_url(array('category' => $row)),
                'LEVEL' => substr_count($row['global_rank'], '.') + 1,
                'SELECTED' => $selected_category['id'] == $row['id'] ? true : false,
                'IS_UPPERCAT' => $selected_category['id_uppercat'] == $row['id'] ? true : false,
            )
        );
        if ($conf['index_new_icon']) {
            $row['icon_ts'] = \Phyxo\Functions\Utils::get_icon($row['max_date_last'], $child_date_last);
        }
        $cats[$row['id']] = $row;
        if (!empty($page['category']['id']) && $row['id'] == $page['category']['id']) {//save the number of subcats for later optim
            $page['category']['count_categories'] = $row['count_categories'];
        }
    }
    uasort($cats, 'global_rank_compare');

    // Update filtered data
    if (function_exists('update_cats_with_filtered_data')) {
        update_cats_with_filtered_data($cats);
    }

    return $cats;
}

/**
 * Retrieves informations about a category.
 *
 * @param int $id
 * @return array
 */
function get_cat_info($id)
{
    global $conn;

    $query = 'SELECT * FROM ' . CATEGORIES_TABLE . ' WHERE id = ' . $id . ';';
    $cat = $conn->db_fetch_assoc($conn->db_query($query));
    if (empty($cat)) {
        return null;
    }

    foreach ($cat as $k => $v) {
        // If the field is true or false, the variable is transformed into a boolean value.
        if ($conn->is_boolean($v)) {
            $cat[$k] = $conn->get_boolean($v);
        }
    }

    $upper_ids = explode(',', $cat['uppercats']);
    if (count($upper_ids) == 1) { // no need to make a query for level 1
        $cat['upper_names'] = array(
            array(
                'id' => $cat['id'],
                'name' => $cat['name'],
                'permalink' => $cat['permalink'],
            )
        );
    } else {
        $query = 'SELECT id, name, permalink FROM ' . CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $conn->in($cat['uppercats']);
        $names = $conn->query2array($query, 'id');

        // category names must be in the same order than uppercats list
        $cat['upper_names'] = array();
        foreach ($upper_ids as $cat_id) {
            $cat['upper_names'][] = $names[$cat_id];
        }
    }

    return $cat;
}

/**
 * Returns an array of image orders available for users/visitors.
 * Each entry is an array containing
 *  0: name
 *  1: SQL ORDER command
 *  2: visiblity (true or false)
 *
 * @return array[]
 */
function get_category_preferred_image_orders()
{
    global $conf, $page, $services;

    return \Phyxo\Functions\Plugin::trigger_change('get_category_preferred_image_orders', array(
        array(\Phyxo\Functions\Language::l10n('Default'), '', true),
        array(\Phyxo\Functions\Language::l10n('Photo title, A &rarr; Z'), 'name ASC', true),
        array(\Phyxo\Functions\Language::l10n('Photo title, Z &rarr; A'), 'name DESC', true),
        array(\Phyxo\Functions\Language::l10n('Date created, new &rarr; old'), 'date_creation DESC', true),
        array(\Phyxo\Functions\Language::l10n('Date created, old &rarr; new'), 'date_creation ASC', true),
        array(\Phyxo\Functions\Language::l10n('Date posted, new &rarr; old'), 'date_available DESC', true),
        array(\Phyxo\Functions\Language::l10n('Date posted, old &rarr; new'), 'date_available ASC', true),
        array(\Phyxo\Functions\Language::l10n('Rating score, high &rarr; low'), 'rating_score DESC', $conf['rate']),
        array(\Phyxo\Functions\Language::l10n('Rating score, low &rarr; high'), 'rating_score ASC', $conf['rate']),
        array(\Phyxo\Functions\Language::l10n('Visits, high &rarr; low'), 'hit DESC', true),
        array(\Phyxo\Functions\Language::l10n('Visits, low &rarr; high'), 'hit ASC', true),
        array(\Phyxo\Functions\Language::l10n('Permissions'), 'level DESC', $services['users']->isAdmin()),
    ));
}

/**
 * Assign a template var useable with {html_options} from a list of categories
 *
 * @param array[] $categories (at least id,name,global_rank,uppercats for each)
 * @param int[] $selected ids of selected items
 * @param string $blockname variable name in template
 * @param bool $fullname full breadcrumb or not
 */
function display_select_categories($categories, $selecteds, $blockname, $fullname = true)
{
    global $template;

    $tpl_cats = array();
    foreach ($categories as $category) {
        if ($fullname) {
            $option = strip_tags(
                get_cat_display_name_cache(
                    $category['uppercats'],
                    null
                )
            );
        } else {
            $option = str_repeat('&nbsp;', (3 * substr_count($category['global_rank'], '.')));
            $option .= '- ';
            $option .= strip_tags(
                \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_name',
                    $category['name'],
                    'display_select_categories'
                )
            );
        }
        $tpl_cats[$category['id']] = $option;
    }

    $template->assign($blockname, $tpl_cats);
    $template->assign($blockname . '_selected', $selecteds);
}

/**
 * Same as display_select_categories but categories are ordered by rank
 * @see display_select_categories()
 */
function display_select_cat_wrapper($query, $selecteds, $blockname, $fullname = true)
{
    global $conn;

    $categories = $conn->query2array($query);
    usort($categories, 'global_rank_compare');
    display_select_categories($categories, $selecteds, $blockname, $fullname);
}

/**
 * Returns all subcategory identifiers of given category ids
 *
 * @param int[] $ids
 * @return int[]
 */
function get_subcat_ids($ids)
{
    global $conn;

    $query = 'SELECT DISTINCT(id) FROM ' . CATEGORIES_TABLE . ' WHERE ';

    foreach ($ids as $num => $category_id) {
        is_numeric($category_id)
            or trigger_error(
            'get_subcat_ids expecting numeric, not ' . gettype($category_id),
            E_USER_WARNING
        );

        if ($num > 0) {
            $query .= ' OR ';
        }
        $query .= 'uppercats ' . $conn::REGEX_OPERATOR . ' \'(^|,)' . $category_id . '(,|$)\'';
    }

    return $conn->query2array($query, null, 'id');
}

/**
 * Finds a matching category id from a potential list of permalinks
 *
 * @param string[] $permalinks
 * @param int &$idx filled with the index in $permalinks that matches
 * @return int|null
 */
function get_cat_id_from_permalinks($permalinks, &$idx)
{
    global $conn;

    $query = 'SELECT cat_id AS id, permalink, 1 AS is_old FROM ' . OLD_PERMALINKS_TABLE;
    $query .= ' WHERE permalink ' . $conn->in($permalinks);
    $query .= ' UNION ';
    $query .= ' SELECT id, permalink, 0 AS is_old FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE permalink ' . $conn->in($permalinks);
    $perma_hash = $conn->query2array($query, 'permalink');

    if (empty($perma_hash)) {
        return null;
    }

    for ($i = count($permalinks) - 1; $i >= 0; $i--) {
        if (isset($perma_hash[$permalinks[$i]])) {
            $idx = $i;
            $cat_id = $perma_hash[$permalinks[$i]]['id'];
            if ($perma_hash[$permalinks[$i]]['is_old']) {
                $query = 'UPDATE ' . OLD_PERMALINKS_TABLE . ' SET last_hit=NOW(), hit=hit+1';
                $query .= ' WHERE permalink=\'' . $permalinks[$i] . '\' AND cat_id=' . $cat_id . ' LIMIT 1';
                $conn->db_query($query);
            }
            return $cat_id;
        }
    }

    return null;
}

/**
 * Returns display text for images counter of category
 *
 * @param int $cat_nb_images nb images directly in category
 * @param int $cat_count_images nb images in category (including subcats)
 * @param int $cat_count_categories nb subcats
 * @param bool $short_message if true append " in this album"
 * @param string $separator
 * @return string
 */
function get_display_images_count($cat_nb_images, $cat_count_images, $cat_count_categories, $short_message = true, $separator = '\n')
{
    $display_text = '';

    if ($cat_count_images > 0) {
        if ($cat_nb_images > 0 and $cat_nb_images < $cat_count_images) {
            $display_text .= get_display_images_count($cat_nb_images, $cat_nb_images, 0, $short_message, $separator) . $separator;
            $cat_count_images -= $cat_nb_images;
            $cat_nb_images = 0;
        }

        //at least one image direct or indirect
        $display_text .= \Phyxo\Functions\Language::l10n_dec('%d photo', '%d photos', $cat_count_images);

        if ($cat_count_categories == 0 or $cat_nb_images == $cat_count_images) {
            //no descendant categories or descendants do not contain images
            if (!$short_message) {
                $display_text .= ' ' . \Phyxo\Functions\Language::l10n('in this album');
            }
        } else {
            $display_text .= ' ' . \Phyxo\Functions\Language::l10n_dec('in %d sub-album', 'in %d sub-albums', $cat_count_categories);
        }
    }

    return $display_text;
}

/**
 * Find a random photo among all photos inside an album (including sub-albums)
 *
 * @param array $category (at least id,uppercats,count_images)
 * @param bool $recursive
 * @return int|null
 */
function get_random_image_in_category($category, $recursive = true)
{
    global $conn;

    $image_id = null;
    if ($category['count_images'] > 0) {
        $query = 'SELECT image_id FROM ' . CATEGORIES_TABLE . ' AS c';
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON ic.category_id = c.id WHERE ';
        if ($recursive) {
            $query .= '(c.id=' . $category['id'] . ' OR uppercats LIKE \'' . $category['uppercats'] . ',%\')';
        } else {
            $query .= ' c.id=' . $category['id'];
        }
        $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(
            array(
                'forbidden_categories' => 'c.id',
                'visible_categories' => 'c.id',
                'visible_images' => 'image_id',
            ),
            "\n  AND"
        );
        $query .= ' ORDER BY ' . $conn::RANDOM_FUNCTION . '() LIMIT 1;';
        $result = $conn->db_query($query);
        if ($conn->db_num_rows($result) > 0) {
            list($image_id) = $conn->db_fetch_row($result);
        }
    }

    return $image_id;
}

/**
 * Get computed array of categories, that means cache data of all categories
 * available for the current user (count_categories, count_images, etc.).
 *
 * @param array &$userdata
 * @param int $filter_days number of recent days to filter on or null
 * @return array
 */
function get_computed_categories(&$userdata, $filter_days = null)
{
    global $conn;

    $query = 'SELECT c.id AS cat_id, id_uppercat';
    // Count by date_available to avoid count null
    $query .= ', MAX(date_available) AS date_last, COUNT(date_available) AS nb_images FROM ' . CATEGORIES_TABLE . ' as c';
    $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON ic.category_id = c.id';
    $query .= ' LEFT JOIN ' . IMAGES_TABLE . ' AS i ON ic.image_id = i.id AND i.level<=' . $userdata['level'];

    if (isset($filter_days)) {
        $query .= ' AND i.date_available > ' . $conn->db_get_recent_period_expression($filter_days);
    }

    if (!empty($userdata['forbidden_categories'])) {
        $query .= ' WHERE c.id NOT IN (' . $userdata['forbidden_categories'] . ')';
    }

    $query .= ' GROUP BY c.id';
    $result = $conn->db_query($query);

    $userdata['last_photo_date'] = null;
    $cats = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $row['user_id'] = $userdata['id'];
        $row['nb_categories'] = 0;
        $row['count_categories'] = 0;
        $row['count_images'] = (int)$row['nb_images'];
        $row['max_date_last'] = $row['date_last'];
        if ($row['date_last'] > $userdata['last_photo_date']) {
            $userdata['last_photo_date'] = $row['date_last'];
        }

        $cats[$row['cat_id']] = $row;
    }

    foreach ($cats as $cat) {
        if (!isset($cat['id_uppercat'])) {
            continue;
        }

        // Piwigo before 2.5.3 may have generated inconsistent permissions, ie
        // private album A1/A2 permitted to user U1 but private album A1 not
        // permitted to U1.
        //
        // TODO 2.7: add an upgrade script to repair permissions and remove this
        // test
        if (!isset($cats[$cat['id_uppercat']])) {
            continue;
        }

        $parent = &$cats[$cat['id_uppercat']];
        $parent['nb_categories']++;

        do {
            $parent['count_images'] += $cat['nb_images'];
            $parent['count_categories']++;

            if ((empty($parent['max_date_last'])) or ($parent['max_date_last'] < $cat['date_last'])) {
                $parent['max_date_last'] = $cat['date_last'];
            }

            if (!isset($parent['id_uppercat'])) {
                break;
            }
            $parent = &$cats[$parent['id_uppercat']];
        } while (true);
        unset($parent);
    }

    if (isset($filter_days)) {
        foreach ($cats as $category) {
            if (empty($category['max_date_last'])) {
                remove_computed_category($cats, $category);
            }
        }
    }

    return $cats;
}

/**
 * Removes a category from computed array of categories and updates counters.
 *
 * @param array &$cats
 * @param array $cat category to remove
 */
function remove_computed_category(&$cats, $cat)
{
    if (isset($cats[$cat['id_uppercat']])) {
        $parent = &$cats[$cat['id_uppercat']];
        $parent['nb_categories']--;

        do {
            $parent['count_images'] -= $cat['nb_images'];
            $parent['count_categories'] -= 1 + $cat['count_categories'];

            if (!isset($cats[$parent['id_uppercat']])) {
                break;
            }
            $parent = &$cats[$parent['id_uppercat']];
        } while (true);
    }

    unset($cats[$cat['cat_id']]);
}

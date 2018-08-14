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

namespace Phyxo\Functions;

class Category
{
    /**
     * Returns template vars for main categories menu.
     *
     * @return array[]
     */
    public static function get_recursive_categories_menu()
    {
        $flat_categories = self::get_categories_menu();

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

    /**
     * Returns template vars for main categories menu.
     *
     * @return array[]
     */
    public static function get_categories_menu()
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
                    'TITLE' => self::get_display_images_count(
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
        uasort($cats, '\Phyxo\Functions\Utils::global_rank_compare');

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
    public static function get_cat_info($id)
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
    public static function get_category_preferred_image_orders()
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
     * Generates breadcrumb for a category.
     * @see get_cat_display_name()
     *
     * @param int $cat_id
     * @param string|null $url
     * @return string
     */
    public static function get_cat_display_name_from_id($cat_id, $url = '')
    {
        $cat_info = self::get_cat_info($cat_id);
        return self::get_cat_display_name($cat_info['upper_names'], $url);
    }

    /**
     * Generates breadcrumb from categories list.
     * Categories string returned contains categories as given in the input
     * array $cat_informations. $cat_informations array must be an array
     * of array( id=>?, name=>?, permalink=>?). If url input parameter is null,
     * returns only the categories name without links.
     *
     * @param array $cat_informations
     * @param string|null $url
     * @return string
     */
    public static function get_cat_display_name($cat_informations, $url = '')
    {
        global $conf;

        $output = '';
        $is_first = true;

        foreach ($cat_informations as $cat) {
           // @TODO: find a better way to control input informations
            is_array($cat) or trigger_error(
                'get_cat_display_name wrong type for category ',
                E_USER_WARNING
            );

            $cat['name'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_category_name',
                $cat['name'],
                'get_cat_display_name'
            );

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $conf['level_separator'];
            }

            if (!isset($url)) {
                $output .= $cat['name'];
            } elseif ($url == '') {
                $output .= '<a href="' . \Phyxo\Functions\URL::make_index_url(array('category' => $cat)) . '">';
                $output .= $cat['name'] . '</a>';
            } else {
                $output .= '<a href="' . $url . $cat['id'] . '">';
                $output .= $cat['name'] . '</a>';
            }
        }

        return $output;
    }

    /**
     * Generates breadcrumb from categories list using a cache.
     * @see get_cat_display_name()
     *
     * @param string $uppercats
     * @param string|null $url
     * @param bool $single_link
     * @param string|null $link_class
     * @return string
     */
    public static function get_cat_display_name_cache($uppercats, $url = '', $single_link = false, $link_class = null)
    {
        global $cache, $conf, $conn;

        if (!isset($cache['cat_names'])) {
            $query = 'SELECT id, name, permalink FROM ' . CATEGORIES_TABLE;
            $cache['cat_names'] = $conn->query2array($query, 'id');
        }

        $output = '';
        if ($single_link) {
            $single_url = \Phyxo\Functions\URL::get_root_url() . $url . array_pop(explode(',', $uppercats));
            $output .= '<a href="' . $single_url . '"';
            if (isset($link_class)) {
                $output .= ' class="' . $link_class . '"';
            }
            $output .= '>';
        }

        // @TODO: refactoring with get_cat_display_name
        $is_first = true;
        foreach (explode(',', $uppercats) as $category_id) {
            $cat = $cache['cat_names'][$category_id];

            $cat['name'] = \Phyxo\Functions\Plugin::trigger_change(
                'render_category_name',
                $cat['name'],
                'get_cat_display_name_cache'
            );

            if ($is_first) {
                $is_first = false;
            } else {
                $output .= $conf['level_separator'];
            }

            if (!isset($url) or $single_link) {
                $output .= $cat['name'];
            } elseif ($url == '') {
                $output .= '<a href="' . \Phyxo\Functions\URL::make_index_url(array('category' => $cat)) . '">' . $cat['name'] . '</a>';
            } else {
                $output .= '<a href="' . $url . $category_id . '">' . $cat['name'] . '</a>';
            }
        }

        if ($single_link and isset($single_url)) {
            $output .= '</a>';
        }

        return $output;
    }

    /**
     * Assign a template var useable with {html_options} from a list of categories
     *
     * @param array[] $categories (at least id,name,global_rank,uppercats for each)
     * @param int[] $selected ids of selected items
     * @param string $blockname variable name in template
     * @param bool $fullname full breadcrumb or not
     */
    public static function display_select_categories($categories, $selecteds, $blockname, $fullname = true)
    {
        global $template;

        $tpl_cats = array();
        foreach ($categories as $category) {
            if ($fullname) {
                $option = strip_tags(
                    self::get_cat_display_name_cache(
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
    public static function display_select_cat_wrapper($query, $selecteds, $blockname, $fullname = true)
    {
        global $conn;

        $categories = $conn->query2array($query);
        usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');
        self::display_select_categories($categories, $selecteds, $blockname, $fullname);
    }

    /**
     * Returns all subcategory identifiers of given category ids
     *
     * @param int[] $ids
     * @return int[]
     */
    public static function get_subcat_ids($ids)
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
     * Recursively deletes one or more categories.
     * It also deletes :
     *    - all the elements physically linked to the category (with delete_elements)
     *    - all the links between elements and this category
     *    - all the restrictions linked to the category
     *
     * @param int[] $ids
     * @param string $photo_deletion_mode
     *    - no_delete : delete no photo, may create orphans
     *    - delete_orphans : delete photos that are no longer linked to any category
     *    - force_delete : delete photos even if they are linked to another category
     */
    public static function delete_categories($ids, $photo_deletion_mode = 'no_delete')
    {
        global $conn;

        if (count($ids) == 0) {
            return;
        }

        // add sub-category ids to the given ids : if a category is deleted, all
        // sub-categories must be so
        $ids = self::get_subcat_ids($ids);

        // destruction of all photos physically linked to the category
        $query = 'SELECT id FROM ' . IMAGES_TABLE;
        $query .= ' WHERE storage_category_id ' . $conn->in($ids);
        $element_ids = $conn->query2array($query, null, 'id');
        \Phyxo\Functions\Utils::delete_elements($element_ids);

        // now, should we delete photos that are virtually linked to the category?
        if ('delete_orphans' == $photo_deletion_mode or 'force_delete' == $photo_deletion_mode) {
            $query = 'SELECT DISTINCT(image_id) FROM ' . IMAGE_CATEGORY_TABLE;
            $query .= ' WHERE category_id ' . $conn->in($ids);
            $image_ids_linked = $conn->query2array($query, null, 'image_id');

            if (count($image_ids_linked) > 0) {
                if ('delete_orphans' == $photo_deletion_mode) {
                    $query = 'SELECT DISTINCT(image_id) FROM ' . IMAGE_CATEGORY_TABLE;
                    $query .= ' WHERE image_id ' . $conn->in($image_ids_linked);
                    $query .= ' AND category_id NOT ' . $conn->in($ids);
                    $image_ids_not_orphans = $conn->query2array($query, null, 'image_id');
                    $image_ids_to_delete = array_diff($image_ids_linked, $image_ids_not_orphans);
                }

                if ('force_delete' == $photo_deletion_mode) {
                    $image_ids_to_delete = $image_ids_linked;
                }

                \Phyxo\Functions\Utils::delete_elements($image_ids_to_delete, true);
            }
        }

        // destruction of the links between images and this category
        $query = 'DELETE FROM ' . IMAGE_CATEGORY_TABLE . ' WHERE category_id ' . $conn->in($ids);
        $conn->db_query($query);

        // destruction of the access linked to the category
        $query = 'DELETE FROM ' . USER_ACCESS_TABLE . ' WHERE cat_id ' . $conn->in($ids);
        $conn->db_query($query);

        $query = 'DELETE FROM ' . GROUP_ACCESS_TABLE . ' WHERE cat_id ' . $conn->in($ids);
        $conn->db_query($query);

        // destruction of the category
        $query = 'DELETE FROM ' . CATEGORIES_TABLE . ' WHERE id ' . $conn->in($ids);
        $conn->db_query($query);

        $query = 'DELETE FROM ' . OLD_PERMALINKS_TABLE . ' WHERE cat_id ' . $conn->in($ids);
        $conn->db_query($query);

        $query = 'DELETE FROM ' . USER_CACHE_CATEGORIES_TABLE . ' WHERE cat_id ' . $conn->in($ids);
        $conn->db_query($query);

        \Phyxo\Functions\Plugin::trigger_notify('delete_categories', $ids);
    }

    /**
     * Verifies that the representative picture really exists in the db and
     * picks up a random representative if possible and based on config.
     *
     * @param 'all'|int|int[] $ids
     */
    public static function update_category($ids = 'all')
    {
        global $conf, $conn;

        if ($ids == 'all') {
            $where_cats = '1=1';
        } elseif (!is_array($ids)) {
            $where_cats = '%s=' . $ids;
        } else {
            if (count($ids) == 0) {
                return false;
            }
            $where_cats = '%s ' . $conn->in($ids);
        }

        // find all categories where the setted representative is not possible :
        // the picture does not exist
        $query = 'SELECT DISTINCT c.id FROM ' . CATEGORIES_TABLE . ' AS c';
        $query .= ' LEFT JOIN ' . IMAGES_TABLE . ' AS i ON c.representative_picture_id = i.id';
        $query .= ' WHERE representative_picture_id IS NOT NULL';
        $query .= ' AND ' . sprintf($where_cats, 'c.id') . ' AND i.id IS NULL;';
        $wrong_representant = $conn->query2array($query, null, 'id');

        if (count($wrong_representant) > 0) {
            $query = 'UPDATE ' . CATEGORIES_TABLE;
            $query .= ' SET representative_picture_id = NULL';
            $query .= ' WHERE id ' . $conn->in($wrong_representant);
            $conn->db_query($query);
        }

        if (!$conf['allow_random_representative']) {
            // If the random representant is not allowed, we need to find
            // categories with elements and with no representant. Those categories
            // must be added to the list of categories to set to a random
            // representant.
            $query = 'SELECT DISTINCT id FROM ' . CATEGORIES_TABLE;
            $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id = category_id';
            $query .= ' WHERE representative_picture_id IS NULL';
            $query .= ' AND ' . sprintf($where_cats, 'category_id');
            $to_rand = $conn->query2array($query, null, 'id');
            if (count($to_rand) > 0) {
                \Phyxo\Functions\Utils::set_random_representant($to_rand);
            }
        }
    }

    /**
     * Finds a matching category id from a potential list of permalinks
     *
     * @param string[] $permalinks
     * @param int &$idx filled with the index in $permalinks that matches
     * @return int|null
     */
    public static function get_cat_id_from_permalinks($permalinks, &$idx)
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
    public static function get_display_images_count($cat_nb_images, $cat_count_images, $cat_count_categories, $short_message = true, $separator = '\n')
    {
        $display_text = '';

        if ($cat_count_images > 0) {
            if ($cat_nb_images > 0 and $cat_nb_images < $cat_count_images) {
                $display_text .= self::get_display_images_count($cat_nb_images, $cat_nb_images, 0, $short_message, $separator) . $separator;
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
    public static function get_random_image_in_category($category, $recursive = true)
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
    public static function get_computed_categories(&$userdata, $filter_days = null)
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
            // TODO 2.7: add an upgrade script to repair permissions and remove this test
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
    public static function remove_computed_category(&$cats, $cat)
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

    /**
     * Change the **visible** property on a set of categories.
     *
     * @param int[] $categories
     * @param boolean|string $value
     * @param boolean $unlock_child optional   default false
     */
    public static function set_cat_visible($categories, $value, $unlock_child = false)
    {
        global $conn;

        if (($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null) {
            trigger_error("set_cat_visible invalid param $value", E_USER_WARNING);
            return false;
        }

        // unlocking a category => all its parent categories become unlocked
        if ($value) {
            $cats = self::get_uppercat_ids($categories);
            if ($unlock_child) {
                $cats = array_merge($cats, self::get_subcat_ids($categories));
            }

            $query = 'UPDATE ' . CATEGORIES_TABLE;
            $query .= ' SET visible = \'' . $conn->boolean_to_db(true) . '\'';
            $query .= ' WHERE id ' . $conn->in($cats);
            $conn->db_query($query);
        } else { // locking a category   => all its child categories become locked
            $subcats = self::get_subcat_ids($categories);
            $query = 'UPDATE ' . CATEGORIES_TABLE;
            $query .= ' SET visible = \'' . $conn->boolean_to_db(false) . '\'';
            $query .= ' WHERE id ' . $conn->in($subcats);
            $conn->db_query($query);
        }
    }

    /**
     * Change the **status** property on a set of categories : private or public.
     *
     * @param int[] $categories
     * @param string $value
     */
    public static function set_cat_status($categories, $value)
    {
        global $conn;

        if (!in_array($value, array('public', 'private'))) {
            trigger_error("set_cat_status invalid param $value", E_USER_WARNING);
            return false;
        }

        // make public a category => all its parent categories become public
        if ($value == 'public') {
            $uppercats = self::get_uppercat_ids($categories);
            $query = 'UPDATE ' . CATEGORIES_TABLE . ' SET status = \'public\'';
            $query .= ' WHERE id ' . $conn->in($uppercats);
            $conn->db_query($query);
        }

        // make a category private => all its child categories become private
        if ($value == 'private') {
            $subcats = self::get_subcat_ids($categories);

            $query = 'UPDATE ' . CATEGORIES_TABLE;
            $query .= ' SET status = \'private\'';
            $query .= ' WHERE id ' . $conn->in($subcats);
            $conn->db_query($query);

            // @TODO: add unit tests for that
            // We have to keep permissions consistant: a sub-album can't be
            // permitted to a user or group if its parent album is not permitted to
            // the same user or group. Let's remove all permissions on sub-albums if
            // it is not consistant. Let's take the following example:
            //
            // A1        permitted to U1,G1
            // A1/A2     permitted to U1,U2,G1,G2
            // A1/A2/A3  permitted to U3,G1
            // A1/A2/A4  permitted to U2
            // A1/A5     permitted to U4
            // A6        permitted to U4
            // A6/A7     permitted to G1
            //
            // (we consider that it can be possible to start with inconsistant
            // permission, given that public albums can have hidden permissions,
            // revealed once the album returns to private status)
            //
            // The admin selects A2,A3,A4,A5,A6,A7 to become private (all but A1,
            // which is private, which can be true if we're moving A2 into A1). The
            // result must be:
            //
            // A2 permission removed to U2,G2
            // A3 permission removed to U3
            // A4 permission removed to U2
            // A5 permission removed to U2
            // A6 permission removed to U4
            // A7 no permission removed
            //
            // 1) we must extract "top albums": A2, A5 and A6
            // 2) for each top album, decide which album is the reference for permissions
            // 3) remove all inconsistant permissions from sub-albums of each top-album

            // step 1, search top albums
            $top_categories = array();
            $parent_ids = array();

            $query = 'SELECT id,name,id_uppercat,uppercats,global_rank FROM ' . CATEGORIES_TABLE;
            $query .= ' WHERE id ' . $conn->in($categories);
            $all_categories = $conn->query2array($query);
            usort($all_categories, '\Phyxo\Functions\Utils::global_rank_compare');

            foreach ($all_categories as $cat) {
                $is_top = true;

                if (!empty($cat['id_uppercat'])) {
                    foreach (explode(',', $cat['uppercats']) as $id_uppercat) {
                        if (isset($top_categories[$id_uppercat])) {
                            $is_top = false;
                            break;
                        }
                    }
                }

                if ($is_top) {
                    $top_categories[$cat['id']] = $cat;

                    if (!empty($cat['id_uppercat'])) {
                        $parent_ids[] = $cat['id_uppercat'];
                    }
                }
            }

            // step 2, search the reference album for permissions
            //
            // to find the reference of each top album, we will need the parent albums
            $parent_cats = array();

            if (count($parent_ids) > 0) {
                $query = 'SELECT id,status FROM ' . CATEGORIES_TABLE;
                $query .= ' WHERE id ' . $conn->in($parent_ids);
                $parent_cats = $conn->query2array($query, 'id');
            }

            $tables = array(
                USER_ACCESS_TABLE => 'user_id',
                GROUP_ACCESS_TABLE => 'group_id'
            );

            foreach ($top_categories as $top_category) {
                // what is the "reference" for list of permissions? The parent album
                // if it is private, else the album itself
                $ref_cat_id = $top_category['id'];

                if (!empty($top_category['id_uppercat'])
                    and isset($parent_cats[$top_category['id_uppercat']])
                    and 'private' == $parent_cats[$top_category['id_uppercat']]['status']) {
                    $ref_cat_id = $top_category['id_uppercat'];
                }

                $subcats = self::get_subcat_ids(array($top_category['id']));

                foreach ($tables as $table => $field) {
                    // what are the permissions user/group of the reference album
                    $query = 'SELECT ' . $field . ' FROM ' . $table;
                    $query .= ' WHERE cat_id = ' . $conn->db_real_escape_string($ref_cat_id);
                    $ref_access = $conn->query2array($query, null, $field);

                    if (count($ref_access) == 0) {
                        $ref_access[] = -1;
                    }

                    // step 3, remove the inconsistant permissions from sub-albums
                    $query = 'DELETE FROM ' . $table;
                    $query .= ' WHERE ' . $field . ' NOT ' . $conn->in($ref_access);
                    $query .= ' AND cat_id ' . $conn->in($subcats);
                    $conn->db_query($query);
                }
            }
        }
    }

    /**
     * Returns the category comment for rendering in html textual mode (subcatify)
     * This method is called by a trigger_notify()
     *
     * @param string $desc
     * @return string
     */
    public static function render_category_literal_description($desc)
    {
        return strip_tags($desc, '<span><p><a><br><b><i><small><big><strong><em>');
    }

    /**
     * Returns all uppercats category ids of the given category ids.
     *
     * @param int[] $cat_ids
     * @return int[]
     */
    public static function get_uppercat_ids($cat_ids)
    {
        global $conn;

        if (!is_array($cat_ids) or count($cat_ids) < 1) {
            return array();
        }

        $uppercats = array();

        $query = 'SELECT uppercats FROM ' . CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $conn->in($cat_ids);
        $result = $conn->db_query($query);
        while ($row = $conn->db_fetch_assoc($result)) {
            $uppercats = array_merge($uppercats, explode(',', $row['uppercats']));
        }
        $uppercats = array_unique($uppercats);

        return $uppercats;
    }

    /**
     * Grant access to a list of categories for a list of users.
     *
     * @param int[] $category_ids
     * @param int[] $user_ids
     */
    function add_permission_on_category($category_ids, $user_ids)
    {
        global $conn;

        if (!is_array($category_ids)) {
            $category_ids = array($category_ids);
        }
        if (!is_array($user_ids)) {
            $user_ids = array($user_ids);
        }

        // check for emptiness
        if (count($category_ids) == 0 or count($user_ids) == 0) {
            return;
        }

        // make sure categories are private and select uppercats or subcats
        $cat_ids = self::get_uppercat_ids($category_ids);
        if (isset($_POST['apply_on_sub'])) {
            $cat_ids = array_merge($cat_ids, self::get_subcat_ids($category_ids));
        }

        $query = 'SELECT id  FROM ' . CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $conn->in($cat_ids);
        $query .= ' AND status = \'private\';';
        $private_cats = $conn->query2array($query, null, 'id');

        if (count($private_cats) == 0) {
            return;
        }

        $inserts = array();
        foreach ($private_cats as $cat_id) {
            foreach ($user_ids as $user_id) {
                $inserts[] = array(
                    'user_id' => $user_id,
                    'cat_id' => $cat_id
                );
            }
        }

        $conn->mass_inserts(
            USER_ACCESS_TABLE,
            array('user_id', 'cat_id'),
            $inserts,
            array('ignore' => true)
        );
    }

    /**
     * Create a virtual category.
     *
     * @param string $category_name
     * @param int $parent_id
     * @param array $options
     *    - boolean commentable
     *    - boolean visible
     *    - string status
     *    - string comment
     *    - boolean inherit
     * @return array ('info', 'id') or ('error')
     */
    public static function create_virtual_category($category_name, $parent_id = null, $options = array())
    {
        global $conf, $user, $conn;

        // is the given category name only containing blank spaces ?
        if (preg_match('/^\s*$/', $category_name)) {
            return array('error' => \Phyxo\Functions\Language::l10n('The name of an album must not be empty'));
        }

        $insert = array(
            'name' => $category_name,
            'rank' => 0,
            'global_rank' => 0,
        );

        // is the album commentable?
        if (isset($options['commentable']) and is_bool($options['commentable'])) {
            $insert['commentable'] = $options['commentable'];
        } else {
            $insert['commentable'] = $conf['newcat_default_commentable'];
        }
        $insert['commentable'] = $conn->boolean_to_string($insert['commentable']);

        // is the album temporarily locked? (only visible by administrators,
        // whatever permissions) (may be overwritten if parent album is not visible)
        if (isset($options['visible']) and is_bool($options['visible'])) {
            $insert['visible'] = $options['visible'];
        } else {
            $insert['visible'] = $conf['newcat_default_visible'];
        }
        $insert['visible'] = $conn->boolean_to_string($insert['visible']);

        // is the album private? (may be overwritten if parent album is private)
        if (isset($options['status']) and 'private' == $options['status']) {
            $insert['status'] = 'private';
        } else {
            $insert['status'] = $conf['newcat_default_status'];
        }

        // any description for this album?
        if (isset($options['comment'])) {
            $insert['comment'] = $conf['allow_html_descriptions'] ? $options['comment'] : strip_tags($options['comment']);
        }

        if (!empty($parent_id) and is_numeric($parent_id)) {
            $query = 'SELECT id, uppercats, global_rank, visible, status FROM ' . CATEGORIES_TABLE;
            $query .= ' WHERE id = ' . $parent_id . ';';
            $parent = $conn->db_fetch_assoc($conn->db_query($query));

            $insert['id_uppercat'] = (int)$parent['id'];
            $insert['global_rank'] = $parent['global_rank'] . '.' . $insert['rank'];

            // at creation, must a category be visible or not ? Warning : if the
            // parent category is invisible, the category is automatically create
            // invisible. (invisible = locked)
            if ($conn->get_boolean($parent['visible']) === false) {
                $insert['visible'] = 'false';
            }

            // at creation, must a category be public or private ? Warning : if the
            // parent category is private, the category is automatically create private.
            if ('private' == $parent['status']) {
                $insert['status'] = 'private';
            }

            $uppercats_prefix = $parent['uppercats'] . ',';
        } else {
            $uppercats_prefix = '';
        }

        // we have then to add the virtual category
        $conn->single_insert(CATEGORIES_TABLE, $insert);
        $inserted_id = $conn->db_insert_id(CATEGORIES_TABLE);

        $conn->single_update(
            CATEGORIES_TABLE,
            array('uppercats' => $uppercats_prefix . $inserted_id),
            array('id' => $inserted_id)
        );

        \Phyxo\Functions\Utils::update_global_rank();

        if ('private' == $insert['status'] and !empty($insert['id_uppercat'])
            and ((isset($options['inherit']) and $options['inherit']) or $conf['inheritance_by_default'])) {
            $query = 'SELECT group_id FROM ' . GROUP_ACCESS_TABLE;
            $query .= ' WHERE cat_id = ' . $insert['id_uppercat'];
            $granted_grps = $conn->query2array($query, null, 'group_id');
            $inserts = array();
            foreach ($granted_grps as $granted_grp) {
                $inserts[] = array('group_id' => $granted_grp, 'cat_id' => $inserted_id);
            }
            $conn->mass_inserts(GROUP_ACCESS_TABLE, array('group_id', 'cat_id'), $inserts);

            $query = 'SELECT user_id FROM ' . USER_ACCESS_TABLE . ' WHERE cat_id = ' . $insert['id_uppercat'];
            $granted_users = $conn->query2array($query, null, 'user_id');
            add_permission_on_category(
                $inserted_id,
                array_unique(array_merge(\Phyxo\Functions\Utils::get_admins(), array($user['id']), $granted_users))
            );
        } elseif ('private' == $insert['status']) {
            add_permission_on_category(
                $inserted_id,
                array_unique(array_merge(\Phyxo\Functions\Utils::get_admins(), array($user['id'])))
            );
        }

        return array(
            'info' => \Phyxo\Functions\Language::l10n('Virtual album added'),
            'id' => $inserted_id,
        );
    }

    /**
     * Returns the fulldir for each given category id.
     *
     * @param int[] intcat_ids
     * @return string[]
     */
    public static function get_fulldirs($cat_ids)
    {
        global $cat_dirs, $conn;

        if (count($cat_ids) == 0) {
            return array();
        }

        // caching directories of existing categories
        $query = 'SELECT id, dir  FROM ' . CATEGORIES_TABLE . ' WHERE dir IS NOT NULL;';
        $cat_dirs = $conn->query2array($query, 'id', 'dir');

        // caching galleries_url
        $query = 'SELECT id, galleries_url FROM ' . SITES_TABLE;
        $galleries_url = $conn->query2array($query, 'id', 'galleries_url');

        // categories : id, site_id, uppercats
        $query = 'SELECT id, uppercats, site_id FROM ' . CATEGORIES_TABLE;
        $query .= ' WHERE dir IS NOT NULL';
        $query .= ' AND id ' . $conn->in($cat_ids);
        $categories = $conn->query2array($query);

        // filling $cat_fulldirs
        $cat_dirs_callback = function ($m) use ($cat_dirs) {
            return $cat_dirs[$m[1]];
        };

        $cat_fulldirs = array();
        foreach ($categories as $category) {
            $uppercats = str_replace(',', '/', $category['uppercats']);
            $cat_fulldirs[$category['id']] = $galleries_url[$category['site_id']];
            $cat_fulldirs[$category['id']] .= preg_replace_callback(
                '/(\d+)/',
                $cat_dirs_callback,
                $uppercats
            );
        }

        unset($cat_dirs);

        return $cat_fulldirs;
    }

    /**
     * Updates categories.uppercats field based on categories.id + categories.id_uppercat
     */
    public static function update_uppercats()
    {
        global $conn;

        $query = 'SELECT id, id_uppercat, uppercats FROM ' . CATEGORIES_TABLE;
        $cat_map = $conn->query2array($query, 'id');

        $datas = array();
        foreach ($cat_map as $id => $cat) {
            $upper_list = array();

            $uppercat = $id;
            while ($uppercat) {
                $upper_list[] = $uppercat;
                $uppercat = $cat_map[$uppercat]['id_uppercat'];
            }

            $new_uppercats = implode(',', array_reverse($upper_list));
            if ($new_uppercats != $cat['uppercats']) {
                $datas[] = array(
                    'id' => $id,
                    'uppercats' => $new_uppercats
                );
            }
        }
        $fields = array('primary' => array('id'), 'update' => array('uppercats'));
        $conn->mass_updates(CATEGORIES_TABLE, $fields, $datas);
    }

    /**
     * Change the parent category of the given categories. The categories are
     * supposed virtual.
     *
     * @param int[] $category_ids
     * @param int $new_parent (-1 for root)
     */
    public static function move_categories($category_ids, $new_parent = -1)
    {
        global $page, $conn;

        if (count($category_ids) == 0) {
            return;
        }

        $new_parent = $new_parent < 1 ? 'NULL' : $new_parent;
        $categories = array();

        $query = 'SELECT id, id_uppercat, status, uppercats FROM ' . CATEGORIES_TABLE;
        $query .= ' WHERE id ' . $conn->in($category_ids);
        $result = $conn->db_query($query);
        while ($row = $conn->db_fetch_assoc($result)) {
            $categories[$row['id']] = array(
                'parent' => empty($row['id_uppercat']) ? 'NULL' : $row['id_uppercat'],
                'status' => $row['status'],
                'uppercats' => $row['uppercats']
            );
        }

        // is the movement possible? The movement is impossible if you try to move
        // a category in a sub-category or itself
        if ('NULL' != $new_parent) {
            $query = 'SELECT uppercats FROM ' . CATEGORIES_TABLE . ' WHERE id = ' . $new_parent . ';';
            list($new_parent_uppercats) = $conn->db_fetch_row($conn->db_query($query));

            foreach ($categories as $category) {
            // technically, you can't move a category with uppercats 12,125,13,14
            // into a new parent category with uppercats 12,125,13,14,24
                if (preg_match('/^' . $category['uppercats'] . '(,|$)/', $new_parent_uppercats)) {
                    $page['errors'][] = \Phyxo\Functions\Language::l10n('You cannot move an album in its own sub album');
                    return;
                }
            }
        }

        $tables = array(
            USER_ACCESS_TABLE => 'user_id',
            GROUP_ACCESS_TABLE => 'group_id'
        );

        $query = 'UPDATE ' . CATEGORIES_TABLE;
        $query .= ' SET id_uppercat = ' . $new_parent;
        $query .= ' WHERE id ' . $conn->in($category_ids);
        $conn->db_query($query);

        self::update_uppercats();
        \Phyxo\Functions\Utils::update_global_rank();

        // status and related permissions management
        if ('NULL' == $new_parent) {
            $parent_status = 'public';
        } else {
            $query = 'SELECT status FROM ' . CATEGORIES_TABLE . ' WHERE id = ' . $new_parent . ';';
            list($parent_status) = $conn->db_fetch_row($conn->db_query($query));
        }

        if ('private' == $parent_status) {
            self::set_cat_status(array_keys($categories), 'private');
        }

        $page['infos'][] = \Phyxo\Functions\Language::l10n_dec(
            '%d album moved',
            '%d albums moved',
            count($categories)
        );
    }

    /**
     * Associate a list of images to a list of categories.
     * The function will not duplicate links and will preserve ranks.
     *
     * @param int[] $images
     * @param int[] $categories
     */
    public static function associate_images_to_categories($images, $categories)
    {
        global $conn;

        if (count($images) == 0 || count($categories) == 0) {
            return false;
        }

        // get existing associations
        $query = 'SELECT image_id,category_id FROM ' . IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE image_id ' . $conn->in($images);
        $query .= ' AND category_id ' . $conn->in($categories);
        $result = $conn->db_query($query);

        $existing = array();
        while ($row = $conn->db_fetch_assoc($result)) {
            $existing[$row['category_id']][] = $row['image_id'];
        }

        // get max rank of each categories
        $query = 'SELECT category_id,MAX(rank) AS max_rank FROM ' . IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE rank IS NOT NULL';
        $query .= ' AND category_id ' . $conn->in($categories);
        $query .= ' GROUP BY category_id;';

        $current_rank_of = $conn->query2array(
            $query,
            'category_id',
            'max_rank'
        );

        // associate only not already associated images
        $inserts = array();
        foreach ($categories as $category_id) {
            if (!isset($current_rank_of[$category_id])) {
                $current_rank_of[$category_id] = 0;
            }
            if (!isset($existing[$category_id])) {
                $existing[$category_id] = array();
            }

            foreach ($images as $image_id) {
                if (!in_array($image_id, $existing[$category_id])) {
                    $rank = ++$current_rank_of[$category_id];

                    $inserts[] = array(
                        'image_id' => $image_id,
                        'category_id' => $category_id,
                        'rank' => $rank,
                    );
                }
            }
        }

        if (count($inserts)) {
            $conn->mass_inserts(
                IMAGE_CATEGORY_TABLE,
                array_keys($inserts[0]),
                $inserts
            );

            \Phyxo\Functions\Category::update_category($categories);
        }
    }

    /**
     * Dissociate images from all old categories except their storage category and
     * associate to new categories.
     * This function will preserve ranks.
     *
     * @param int[] $images
     * @param int[] $categories
     */
    public static function move_images_to_categories($images, $categories)
    {
        global $conn;

        if (count($images) == 0) {
            return false;
        }

        // let's first break links with all old albums but their "storage album"
        $query = 'DELETE FROM ' . IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id in (';
        $query .= ' SELECT id FROM ' . IMAGES_TABLE;
        $query .= ' WHERE (storage_category_id IS NULL OR storage_category_id NOT ' . $conn->in($categories) . ')';
        $query .= ')';
        $query .= ' AND image_id ' . $conn->in($images);

        $conn->db_query($query);

        if (is_array($categories) and count($categories) > 0) {
            self::associate_images_to_categories($images, $categories);
        }
    }

    /**
     * Associate images associated to a list of source categories to a list of
     * destination categories.
     *
     * @param int[] $sources
     * @param int[] $destinations
     */
    public static function associate_categories_to_categories($sources, $destinations)
    {
        global $conn;

        if (count($sources) == 0) {
            return false;
        }

        $query = 'SELECT image_id FROM ' . IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id ' . $conn->in($sources);
        $images = $conn->query2array($query, null, 'image_id');

        self::associate_images_to_categories($images, $destinations);
    }

    /**
     * Is the category accessible to the (Admin) user ?
     * Note : if the user is not authorized to see this category, category jump
     * will be replaced by admin cat_modify page
     *
     * @param int $category_id
     * @return bool
     */
    public static function cat_admin_access($category_id)
    {
        global $user;

        // $filter['visible_categories'] and $filter['visible_images']
        // are not used because it's not necessary (filter <> restriction)
        if (in_array($category_id, explode(',', $user['forbidden_categories']))) {
            return false;
        }

        return true;
    }

}
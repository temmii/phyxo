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
 * This included page checks section related parameter and provides
 * following informations:
 *
 * - $page['title']
 *
 * - $page['items']: ordered list of items to display
 *
 */

// "index.php?/category/12-foo/start-24" or
// "index.php/category/12-foo/start-24"
// must return :
//
// array(
//   'section'  => 'categories',
//   'category' => array('id'=>12, ...),
//   'start'    => 24
//   );


$page['items'] = array();
$page['start'] = $page['startcat'] = 0;

// some ISPs set PATH_INFO to empty string or to SCRIPT_FILENAME while in the
// default apache implementation it is not set
if ($conf['question_mark_in_urls'] == false and isset($_SERVER['PATH_INFO']) and !empty($_SERVER['PATH_INFO'])) {
    $rewritten = $_SERVER['PATH_INFO'];
    $rewritten = str_replace('//', '/', $rewritten);
    $path_count = count(explode('/', $rewritten));
    $page['root_path'] = PHPWG_ROOT_PATH . str_repeat('../', $path_count - 1);
} else {
    $rewritten = '';
    foreach (array_keys($_GET) as $keynum => $key) {
        $rewritten = $key;
        break;
    }
    $page['root_path'] = PHPWG_ROOT_PATH;
}

if (strncmp($page['root_path'], './', 2) == 0) {
    $page['root_path'] = substr($page['root_path'], 2);
}

// deleting first "/" if displayed
$tokens = explode('/', ltrim($rewritten, '/'));
// $tokens = array(
//   0 => category,
//   1 => 12-foo,
//   2 => start-24
//   );

$next_token = 0;

// +-----------------------------------------------------------------------+
// |                             picture page                              |
// +-----------------------------------------------------------------------+
// the first token must be the identifier for the picture
if (\Phyxo\Functions\Utils::script_basename() == 'picture') {
    $token = $tokens[$next_token];
    $next_token++;
    if (is_numeric($token)) {
        $page['image_id'] = $token;
        if ($page['image_id'] == 0) {
            bad_request('invalid picture identifier');
        }
    } else {
        preg_match('/^(\d+-)?(.*)?$/', $token, $matches);
        if (isset($matches[1]) and is_numeric($matches[1] = rtrim($matches[1], '-'))) {
            $page['image_id'] = $matches[1];
            if (!empty($matches[2])) {
                $page['image_file'] = $matches[2];
            }
        } else {
            $page['image_id'] = 0; // more work in picture.php
            if (!empty($matches[2])) {
                $page['image_file'] = $matches[2];
            } else {
                bad_request('picture identifier is missing');
            }
        }
    }
}

$page = array_merge($page, \Phyxo\Functions\URL::parse_section_url($tokens, $next_token));

if (!isset($page['section'])) {
    $page['section'] = 'categories';

    switch (\Phyxo\Functions\Utils::script_basename()) {
        case 'picture':
            break;
        case 'index':
            {
            // No section defined, go to random url
                if (!empty($conf['random_index_redirect']) and empty($tokens[$next_token])) {
                    $random_index_redirect = array();
                    foreach ($conf['random_index_redirect'] as $random_url => $random_url_condition) {
                        if (empty($random_url_condition) or eval($random_url_condition)) {
                            $random_index_redirect[] = $random_url;
                        }
                    }
                    if (!empty($random_index_redirect)) {
                        \Phyxo\Functions\Utils::redirect($random_index_redirect[mt_rand(0, count($random_index_redirect) - 1)]);
                    }
                }
                $page['is_homepage'] = true;
                break;
            }
        default:
            trigger_error('script_basename "' . \Phyxo\Functions\Utils::script_basename() . '" unknown', E_USER_WARNING);
    }
}

$page = array_merge($page, \Phyxo\Functions\URL::parse_well_known_params_url($tokens, $next_token));

//access a picture only by id, file or id-file without given section
if (\Phyxo\Functions\Utils::script_basename() == 'picture' and 'categories' == $page['section']
    and !isset($page['category']) and !isset($page['chronology_field'])) {
    $page['flat'] = true;
}

// $page['nb_image_page'] is the number of picture to display on this page
// By default, it is the same as the $user['nb_image_page']
$page['nb_image_page'] = $user['nb_image_page'];

// if flat mode is active, we must consider the image set as a standard set
// and not as a category set because we can't use the #image_category.rank :
// displayed images are not directly linked to the displayed category
if ('categories' == $page['section'] and !isset($page['flat'])) {
    $conf['order_by'] = $conf['order_by_inside_category'];
}

if (!empty($_SESSION['image_order']) && $_SESSION['image_order'] > 0) {
    $image_order_id = $_SESSION['image_order'];

    $orders = get_category_preferred_image_orders();

    // the current session stored image_order might be not compatible with
    // current image set, for example if the current image_order is the rank
    // and that we are displaying images related to a tag.
    //
    // In case of incompatibility, the session stored image_order is removed.
    if ($orders[$image_order_id][2]) {
        $conf['order_by'] = str_replace(
            'ORDER BY ',
            'ORDER BY ' . $orders[$image_order_id][1] . ',',
            $conf['order_by']
        );
        $page['super_order_by'] = true;
    } else {
        unset($_SESSION['image_order']);
        $page['super_order_by'] = false;
    }
}

$forbidden = \Phyxo\Functions\SQL::get_sql_condition_FandF(
    array(
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'id'
    ),
    'AND'
);

// +-----------------------------------------------------------------------+
// |                              category                                 |
// +-----------------------------------------------------------------------+
if ('categories' == $page['section']) {
    if (isset($page['category'])) {
        $page = array_merge(
            $page,
            array(
                'comment' => \Phyxo\Functions\Plugin::trigger_change(
                    'render_category_description',
                    $page['category']['comment'],
                    'main_page_category_description'
                ),
                'title' => get_cat_display_name($page['category']['upper_names'], '', false),
            )
        );
    } else {
        $page['title'] = ''; // will be set later
    }

    // GET IMAGES LIST
    if ($page['startcat'] == 0 and (!isset($page['chronology_field'])) and // otherwise the calendar will requery all subitems
    ((isset($page['category'])) or (isset($page['flat'])))) {
        if (!empty($page['category']['image_order']) and !isset($page['super_order_by'])) {
            $conf['order_by'] = ' ORDER BY ' . $page['category']['image_order'];
        }

        // flat categories mode
        if (isset($page['flat'])) {
            // get all allowed sub-categories
            if (isset($page['category'])) {
                $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
                $query .= ' WHERE uppercats LIKE \'' . $page['category']['uppercats'] . ',%\' ';
                $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(array('forbidden_categories' => 'id', 'visible_categories' => 'id'), 'AND');

                $subcat_ids = $conn->query2array($query, null, 'id');
                $subcat_ids[] = $page['category']['id'];
                $where_sql = 'category_id ' . $conn->in($subcat_ids);
                // remove categories from forbidden because just checked above
                $forbidden = \Phyxo\Functions\SQL::get_sql_condition_FandF(array('visible_images' => 'id'), 'AND');
            } else {
                $cache_key = $persistent_cache->make_key('all_iids' . $user['id'] . $user['cache_update_time'] . $conf['order_by']);
                unset($page['is_homepage']);
                $where_sql = '1=1';
            }
        } else { // normal mode
            $where_sql = 'category_id = ' . $page['category']['id'];
        }

        if (!isset($cache_key) || !$persistent_cache->get($cache_key, $page['items'])) {
            // main query
            $query = 'SELECT DISTINCT(image_id),' . \Phyxo\Functions\SQL::addOrderByFields($conf['order_by']) . ' FROM ' . IMAGES_TABLE;
            $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id = image_id';
            $query .= ' WHERE ' . $where_sql . ' ' . $forbidden . ' ' . $conf['order_by'];

            $page['items'] = $conn->query2array($query, null, 'image_id');

            if (isset($cache_key)) {
                $persistent_cache->set($cache_key, $page['items']);
            }
        }
    }
} else { // special sections
    if ($page['section'] == 'tags') {
        // +-----------------------------------------------------------------------+
        // |                            tags section                               |
        // +-----------------------------------------------------------------------+
        $page['tag_ids'] = array();
        foreach ($page['tags'] as $tag) {
            $page['tag_ids'][] = $tag['id'];
        }

        $items = $services['tags']->getImageIdsForTags($page['tag_ids']);

        $page = array_merge(
            $page,
            array(
                'title' => get_tags_content_title(),
                'items' => $items,
            )
        );
    } elseif ($page['section'] == 'search') {
        // +-----------------------------------------------------------------------+
        // |                           search section                              |
        // +-----------------------------------------------------------------------+
        include_once(PHPWG_ROOT_PATH . 'include/functions_search.inc.php');

        $search_result = get_search_results($page['search'], @$page['super_order_by']);
        //save the details of the query search
        if (isset($search_result['qs'])) {
            $page['qsearch_details'] = $search_result['qs'];
        }

        $page = array_merge(
            $page,
            array(
                'items' => $search_result['items'],
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(array('start' => 0)) . '">' . \Phyxo\Functions\Language::l10n('Search results') . '</a>'
            )
        );
    } elseif ($page['section'] == 'favorites') {
        // +-----------------------------------------------------------------------+
        // |                           favorite section                            |
        // +-----------------------------------------------------------------------+
        \Phyxo\Functions\Utils::check_user_favorites();

        $page = array_merge($page, array('title' => \Phyxo\Functions\Language::l10n('Favorites')));

        if (!empty($_GET['action']) && ($_GET['action'] == 'remove_all_from_favorites')) {
            $query = 'DELETE FROM ' . FAVORITES_TABLE . ' WHERE user_id = ' . $user['id'] . ';';
            $conn->db_query($query);
            \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::make_index_url(array('section' => 'favorites')));
        } else {
            $query = 'SELECT image_id FROM ' . IMAGES_TABLE;
            $query .= ' LEFT JOIN ' . FAVORITES_TABLE . ' ON image_id = id';
            $query .= ' WHERE user_id = ' . $user['id'];
            $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(array('visible_images' => 'id'), 'AND');
            $query .= ' ' . $conf['order_by'];
            $page = array_merge($page, array('items' => $conn->query2array($query, null, 'image_id')));

            if (count($page['items']) > 0) {
                $template->assign(
                    'favorite',
                    array(
                        'U_FAVORITE' => \Phyxo\Functions\URL::add_url_params(
                            \Phyxo\Functions\URL::make_index_url(array('section' => 'favorites')),
                            array('action' => 'remove_all_from_favorites')
                        ),
                    )
                );
            }
        }
    } elseif ($page['section'] == 'recent_pics') {
        // +-----------------------------------------------------------------------+
        // |                       recent pictures section                         |
        // +-----------------------------------------------------------------------+
        if (!isset($page['super_order_by'])) {
            $conf['order_by'] = str_replace(
                'ORDER BY ',
                'ORDER BY date_available DESC,',
                $conf['order_by']
            );
        }

        $query = 'SELECT DISTINCT(id),' . \Phyxo\Functions\SQL::addOrderByFields($conf['order_by']) . ' FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        $query .= ' WHERE ' . \Phyxo\Functions\SQL::get_recent_photos_sql('date_available') . ' ' . $forbidden . ' ' . $conf['order_by'];

        $page = array_merge(
            $page,
            array(
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(array('start' => 0)) . '">' . \Phyxo\Functions\Language::l10n('Recent photos') . '</a>',
                'items' => $conn->query2array($query, null, 'id')
            )
        );
    } elseif ($page['section'] == 'recent_cats') {
        // +-----------------------------------------------------------------------+
        // |                 recently updated categories section                   |
        // +-----------------------------------------------------------------------+
        $page = array_merge($page, array('title' => \Phyxo\Functions\Language::l10n('Recent albums')));
    } elseif ($page['section'] == 'most_visited') {
        // +-----------------------------------------------------------------------+
        // |                        most visited section                           |
        // +-----------------------------------------------------------------------+
        $page['super_order_by'] = true;
        $conf['order_by'] = ' ORDER BY hit DESC, id DESC';

        $query = 'SELECT DISTINCT(id), ' . \Phyxo\Functions\SQL::addOrderByFields($conf['order_by']) . ' FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        $query .= ' WHERE hit > 0';
        $query .= ' ' . $forbidden;
        $query .= ' ' . $conf['order_by'] . ' LIMIT ' . $conf['top_number'];

        $page = array_merge(
            $page,
            array(
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(array('start' => 0)) . '">' . $conf['top_number'] . ' ' . \Phyxo\Functions\Language::l10n('Most visited') . '</a>',
                'items' => $conn->query2array($query, null, 'id'),
            )
        );
    } elseif ($page['section'] == 'best_rated') {
        // +-----------------------------------------------------------------------+
        // |                          best rated section                           |
        // +-----------------------------------------------------------------------+
        $page['super_order_by'] = true;
        $conf['order_by'] = ' ORDER BY rating_score DESC, id DESC';

        $query = 'SELECT DISTINCT(id),' . \Phyxo\Functions\SQL::addOrderByFields($conf['order_by']) . ' FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        $query .= ' WHERE rating_score IS NOT NULL';
        $query .= ' ' . $forbidden;
        $query .= ' ' . $conf['order_by'] . ' LIMIT ' . $conf['top_number'];
        $page = array_merge(
            $page,
            array(
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(array('start' => 0)) . '">' . $conf['top_number'] . ' ' . \Phyxo\Functions\Language::l10n('Best rated') . '</a>',
                'items' => $conn->query2array($query, null, 'id'),
            )
        );
    } elseif ($page['section'] == 'list') {
        // +-----------------------------------------------------------------------+
        // |                             list section                              |
        // +-----------------------------------------------------------------------+
        $query = 'SELECT DISTINCT(id),' . \Phyxo\Functions\SQL::addOrderByFields($conf['order_by']) . ' FROM ' . IMAGES_TABLE;
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
        $query .= ' WHERE image_id ' . $conn->in($page['list']);
        $query .= ' ' . $forbidden;
        $query .= ' ' . $conf['order_by'];

        $page = array_merge(
            $page,
            array(
                'title' => '<a href="' . \Phyxo\Functions\URL::duplicate_index_url(array('start' => 0)) . '">' . \Phyxo\Functions\Language::l10n('Random photos') . '</a>',
                'items' => $conn->query2array($query, null, 'id'),
            )
        );
    }
}

// +-----------------------------------------------------------------------+
// |                             chronology                                |
// +-----------------------------------------------------------------------+
if (isset($page['chronology_field'])) {
    unset($page['is_homepage']);
    $template_filename = 'month_calendar';

    include_once(PHPWG_ROOT_PATH . 'include/functions_calendar.inc.php');
    initialize_calendar();
}

// title update
if (isset($page['title'])) {
    $page['section_title'] = '<a href="' . \Phyxo\Functions\URL::get_gallery_home_url() . '">' . \Phyxo\Functions\Language::l10n('Home') . '</a>';
    if (!empty($page['title'])) {
        $page['section_title'] .= $conf['level_separator'] . $page['title'];
    } else {
        $page['title'] = $page['section_title'];
    }
}

// add meta robots noindex, nofollow to avoid unnecesary robot crawls
$page['meta_robots'] = array();
if (isset($page['chronology_field']) or (isset($page['flat']) and isset($page['category']))
    or 'list' == $page['section'] or 'recent_pics' == $page['section']) {
    $page['meta_robots'] = array('noindex' => 1, 'nofollow' => 1);
} elseif ('tags' == $page['section']) {
    if (count($page['tag_ids']) > 1) {
        $page['meta_robots'] = array('noindex' => 1, 'nofollow' => 1);
    }
} elseif ('recent_cats' == $page['section']) {
    $page['meta_robots']['noindex'] = 1;
} elseif ('search' == $page['section']) {
    $page['meta_robots']['nofollow'] = 1;
}

if ($filter['enabled']) {
    $page['meta_robots']['noindex'] = 1;
}

// see if we need a redirect because of a permalink
if ('categories' == $page['section'] and isset($page['category'])) {
    $need_redirect = false;
    if (empty($page['category']['permalink'])) {
        if ($conf['category_url_style'] == 'id-name' and @$page['hit_by']['cat_url_name'] !== \Phyxo\Functions\Language::str2url($page['category']['name'])) {
            $need_redirect = true;
        }
    } else {
        if ($page['category']['permalink'] !== @$page['hit_by']['cat_permalink']) {
            $need_redirect = true;
        }
    }

    if ($need_redirect) {
        $redirect_url = \Phyxo\Functions\Utils::script_basename() == 'picture' ? \Phyxo\Functions\URL::duplicate_picture_url() : \Phyxo\Functions\URL::duplicate_index_url();

        set_status_header(301);
        \Phyxo\Functions\Utils::redirect($redirect_url);
    }
    unset($need_redirect, $page['hit_by']);
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_section_init');

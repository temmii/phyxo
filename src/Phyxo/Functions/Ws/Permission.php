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

namespace Phyxo\Functions\Ws;

use Phyxo\Ws\Server;
use Phyxo\Ws\Error;
use Phyxo\Ws\NamedArray;

class Permission
{
    /**
     * API method
     * Returns permissions
     * @param mixed[] $params
     *    @option int[] cat_id (optional)
     *    @option int[] group_id (optional)
     *    @option int[] user_id (optional)
     */
    public static function getList($params, &$service)
    {
        global $conn;

        $my_params = array_intersect(array_keys($params), ['cat_id', 'group_id', 'user_id']);
        if (count($my_params) > 1) {
            return new Error(Server::WS_ERR_INVALID_PARAM, 'Too many parameters, provide cat_id OR user_id OR group_id');
        }

        $cat_filter = '';
        if (!empty($params['cat_id'])) {
            $cat_filter = 'WHERE cat_id ' . $conn->in($params['cat_id']);
        }

        $filters = ['group_id' => '', 'user_id' => ''];
        if (!empty($params['group_id'])) {
            $filters['group_id'] = $params['group_id'];
        }
        if (!empty($params['user_id'])) {
            $filters['user_id'] = $params['user_id'];
        }

        $perms = [];

        // direct users
        $query = 'SELECT user_id, cat_id FROM ' . USER_ACCESS_TABLE;
        $query .= ' ' . $cat_filter;
        $result = $conn->db_query($query);

        while ($row = $conn->db_fetch_assoc($result)) {
            if (!isset($perms[$row['cat_id']])) {
                $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
            }
            $perms[$row['cat_id']]['users'][] = intval($row['user_id']);
        }

        // indirect users
        $query = 'SELECT ug.user_id, ga.cat_id FROM ' . USER_GROUP_TABLE . ' AS ug';
        $query .= ' LEFT JOIN ' . GROUP_ACCESS_TABLE . ' AS ga ON ug.group_id = ga.group_id ' . $cat_filter . ';';
        $result = $conn->db_query($query);

        while ($row = $conn->db_fetch_assoc($result)) {
            if (!empty($row['cat_id'])) {
                if (!isset($perms[$row['cat_id']])) {
                    $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
                }
                $perms[$row['cat_id']]['users_indirect'][] = intval($row['user_id']);
            }
        }

        // groups
        $query = 'SELECT group_id, cat_id FROM ' . GROUP_ACCESS_TABLE . ' ' . $cat_filter . ';';
        $result = $conn->db_query($query);

        while ($row = $conn->db_fetch_assoc($result)) {
            if (!isset($perms[$row['cat_id']])) {
                $perms[$row['cat_id']]['id'] = intval($row['cat_id']);
            }
            $perms[$row['cat_id']]['groups'][] = intval($row['group_id']);
        }


        // filter by group and user
        foreach ($perms as $cat_id => &$cat) {
            if (!empty($filters['group_id'])) {
                if (empty($cat['groups']) or count(array_intersect($cat['groups'], $params['group_id'])) == 0) {
                    unset($perms[$cat_id]);
                    continue;
                }
            }
            if (!empty($filters['user_id'])) {
                if ((empty($cat['users_indirect']) or count(array_intersect($cat['users_indirect'], $params['user_id'])) == 0)
                    and (empty($cat['users']) or count(array_intersect($cat['users'], $params['user_id'])) == 0)) {
                    unset($perms[$cat_id]);
                    continue;
                }
            }

            $cat['groups'] = !empty($cat['groups']) ? array_values(array_unique($cat['groups'])) : [];
            $cat['users'] = !empty($cat['users']) ? array_values(array_unique($cat['users'])) : [];
            $cat['users_indirect'] = !empty($cat['users_indirect']) ? array_values(array_unique($cat['users_indirect'])) : [];
        }
        unset($cat);

        return [
            'categories' => new NamedArray(
                array_values($perms),
                'category',
                ['id']
            )
        ];
    }

    /**
     * API method
     * Add permissions
     * @param mixed[] $params
     *    @option int[] cat_id
     *    @option int[] group_id (optional)
     *    @option int[] user_id (optional)
     *    @option bool recursive
     */
    public static function add($params, &$service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        if (!empty($params['group_id'])) {
            $cat_ids = \Phyxo\Functions\Category::get_uppercat_ids($params['cat_id']);
            if ($params['recursive']) {
                $cat_ids = array_merge($cat_ids, \Phyxo\Functions\Category::get_subcat_ids($params['cat_id']));
            }

            $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
            $query .= ' WHERE id ' . $conn->in($cat_ids) . ' AND status = \'private\';';
            $private_cats = $conn->query2array($query, null, 'id');

            $inserts = [];
            foreach ($private_cats as $cat_id) {
                foreach ($params['group_id'] as $group_id) {
                    $inserts[] = [
                        'group_id' => $group_id,
                        'cat_id' => $cat_id
                    ];
                }
            }

            $conn->mass_inserts(
                GROUP_ACCESS_TABLE,
                ['group_id', 'cat_id'],
                $inserts,
                ['ignore' => true]
            );
        }

        if (!empty($params['user_id'])) {
            if ($params['recursive']) $_POST['apply_on_sub'] = true;
            \Phyxo\Functions\Category::add_permission_on_category($params['cat_id'], $params['user_id']);
        }

        return $service->invoke('pwg.permissions.getList', ['cat_id' => $params['cat_id']]);
    }

    /**
     * API method
     * Removes permissions
     * @param mixed[] $params
     *    @option int[] cat_id
     *    @option int[] group_id (optional)
     *    @option int[] user_id (optional)
     */
    public static function remove($params, &$service)
    {
        global $conn;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        $cat_ids = \Phyxo\Functions\Category::get_subcat_ids($params['cat_id']);

        if (!empty($params['group_id'])) {
            $query = 'DELETE FROM ' . GROUP_ACCESS_TABLE;
            $query .= ' WHERE group_id ' . $conn->in($params['group_id']);
            $query .= ' AND cat_id ' . $conn->in($cat_ids);
            $conn->db_query($query);
        }

        if (!empty($params['user_id'])) {
            $query = 'DELETE FROM ' . USER_ACCESS_TABLE;
            $query .= ' WHERE user_id ' . $conn->in($params['user_id']);
            $query .= ' AND cat_id ' . $conn->in($cat_ids);
            $conn->db_query($query);
        }

        return $service->invoke('pwg.permissions.getList', ['cat_id' => $params['cat_id']]);
    }
}
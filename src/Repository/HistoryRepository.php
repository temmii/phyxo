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

namespace App\Repository;

class HistoryRepository extends BaseRepository
{
    public function getHistory($search, $types)
    {
        $clauses = [];

        if (isset($search['fields']['date-after'])) {
            $clauses[] = "date >= '" . $search['fields']['date-after'] . "'";
        }

        if (isset($search['fields']['date-before'])) {
            $clauses[] = "date <= '" . $search['fields']['date-before'] . "'";
        }

        if (isset($search['fields']['types'])) {
            $local_clauses = [];

            foreach ($types as $type) {
                if (in_array($type, $search['fields']['types'])) {
                    $clause = 'image_type ';
                    if ($type == 'none') {
                        $clause .= 'IS NULL';
                    } else {
                        $clause .= " = '" . $type . "'";
                    }

                    $local_clauses[] = $clause;
                }
            }

            if (count($local_clauses) > 0) {
                $clauses[] = implode(' OR ', $local_clauses);
            }
        }

        if (isset($search['fields']['user']) and $search['fields']['user'] != -1) {
            $clauses[] = 'user_id = ' . $search['fields']['user'];
        }

        if (isset($search['fields']['image_id'])) {
            $clauses[] = 'image_id = ' . $search['fields']['image_id'];
        }

        if (isset($search['fields']['filename'])) {
            if (count($search['image_ids']) == 0) {
                // a clause that is always false
                $clauses[] = '1 = 2 ';
            } else {
                $clauses[] = 'image_id ' . $this->conn->in($search['image_ids']);
            }
        }

        if (isset($search['fields']['ip'])) {
            $clauses[] = 'ip LIKE \'' . $search['fields']['ip'] . '\'';
        }

        $clauses = \Phyxo\Functions\Utils::prepend_append_array_items($clauses, '(', ')');

        $where_separator = implode(' AND ', $clauses);

        $query = 'SELECT date,time,user_id,ip,section,category_id,tag_ids,';
        $query .= 'image_id,image_type FROM ' . self::HISTORY_TABLE;
        $query .= ' WHERE ' . $where_separator;

        return $this->conn->db_query($query);
    }

    public function getDetailsFromNotSummarized()
    {
        $query = 'SELECT date,' . $this->conn->db_get_hour('time') . ' AS hour,MAX(id) AS max_id,';
        $query .= 'COUNT(1) AS nb_pages FROM ' . self::HISTORY_TABLE;
        $query .= ' WHERE summarized = \'' . $this->conn->boolean_to_db(false) . '\'';
        $query .= ' GROUP BY date, hour';
        $query .= ' ORDER BY date ASC, hour ASC';

        return $this->conn->db_query($query);
    }

    public function setSummarizedForUnsummarized(int $max_id)
    {
        $query = 'UPDATE ' . self::HISTORY_TABLE;
        $query .= ' SET summarized = \'' . $this->conn->boolean_to_db(true) . '\'';
        $query .= ' WHERE summarized = \'' . $this->conn->boolean_to_db(false) . '\'';
        $query .= ' AND id <= ' . $max_id;

        return $this->conn->db_query($query);
    }

    public function getMaxIdForUsers(array $user_ids)
    {
        $query = 'SELECT MAX(id) as history_id FROM ' . self::HISTORY_TABLE;
        $query .= ' WHERE user_id ' . $this->conn->in($user_ids);
        $query .= ' GROUP BY user_id;';

        return $this->conn->db_query($query);
    }

    public function findByIds(array $ids)
    {
        $query = 'SELECT user_id, date, time FROM ' . self::HISTORY_TABLE;
        $query .= ' WHERE id ' . $this->conn->in($ids);

        return $this->conn->db_query($query);
    }

    public function addHistory($datas) : int
    {
        return $this->conn->single_insert(self::HISTORY_TABLE, $datas);
    }

    public function deleteAll()
    {
        $query = 'DELETE FROM ' . self::HISTORY_TABLE;

        return $this->conn->db_query($query);
    }
}
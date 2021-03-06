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

$upgrade_description = 'add fields for users tags';

if (in_array($conf['dblayer'], ['mysql'])) {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::IMAGE_TAG_TABLE;
    $query .= ' ADD COLUMN validated enum("true","false") NOT NULL default "false",';
    $query .= ' ADD COLUMN created_by mediumint(8) unsigned DEFAULT NULL,';
    $query .= ' ADD COLUMN status smallint(3) DEFAULT 1';
    $conn->db_query($query);
} elseif ($conf['dblayer'] == 'pgsql') {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::IMAGE_TAG_TABLE . ' ADD COLUMN validated BOOLEAN default true';
    $conn->db_query($query);
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::IMAGE_TAG_TABLE . ' ADD COLUMN created_by INTEGER REFERENCES "phyxo_users" (id)';
    $conn->db_query($query);
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::IMAGE_TAG_TABLE . ' ADD COLUMN status INTEGER default 1';
    $conn->db_query($query);
} elseif ($conf['dblayer'] == 'sqlite') {
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::IMAGE_TAG_TABLE . ' ADD COLUMN validated" BOOLEAN default false';
    $conn->db_query($query);
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::IMAGE_TAG_TABLE . ' ADD COLUMN created_by INTEGER REFERENCES "phyxo_users" (id)';
    $conn->db_query($query);
    $query = 'ALTER TABLE ' . App\Repository\BaseRepository::IMAGE_TAG_TABLE . ' ADD COLUMN status INTEGER DEFAULT 1';
    $conn->db_query($query);
}

echo "\n" . $upgrade_description . "\n";

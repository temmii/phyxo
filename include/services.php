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

if(!defined("PHPWG_ROOT_PATH")) {
    die('Hacking attempt!');
}

// @TODO: need to merge all services using Pimple container

use Phyxo\Model\Repository\Tags;
use Phyxo\Model\Repository\Comments;
use Phyxo\Model\Repository\Users;

$services_container = new \CCMBenchmark\Ting\Services();
$services_container->get('ConnectionPool')->setConfig([
    'main' => [
        'namespace' => '\CCMBenchmark\Ting\Driver\Pgsql',
        'master' => [
            'host' => 'localhost', // @TODO: from conf
            'user' => 'phyxo',
            'password' => 'phyxo',
            'port' => 5432,
        ]
    ]
]);

$services_container
    ->get('MetadataRepository')
    ->batchLoadMetadata('Phyxo\Repository', __DIR__ . '/../src/Phyxo/Repository/*.php');

$services_container->set(
    'users',
    function($c) use ($conn) {
        return new Users($conn, 'Phyxo\Model\Entity\User', USERS_TABLE);
    }
);

$services_container->set(
    'tags',
    function($c) use ($conn) {
        return new Tags($conn, 'Phyxo\Model\Entity\Tag', TAGS_TABLE);
    }
);

$services_container->set(
    'comments',
    function($c) use ($conn) {
        return new Comments($conn, 'Phyxo\Model\Entity\Comment', COMMENTS_TABLE);
    }
);

$services = array();
$services['tags'] = $services_container->get('tags');
$services['comments'] = $services_container->get('comments');
$services['users'] = $services_container->get('users');

// @TODO : find a better place
add_event_handler('user_comment_check', array($services['comments'], 'userCommentCheck'));

// temporary hack for password_*
function pwg_password_verify($password, $hash, $user_id=null) {
    global $services;

    return $services['users']->passwordVerify($password, $hash, $user_id);
}

function pwg_password_hash($password) {
    global $services;

    return $services['users']->passwordHash($password);
}

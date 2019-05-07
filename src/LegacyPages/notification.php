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

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

include_once(__DIR__ . '/../../include/common.inc.php');

use App\Repository\UserFeedRepository;

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_notification');

// +-----------------------------------------------------------------------+
// |                          new feed creation                            |
// +-----------------------------------------------------------------------+

$page['feed'] = md5(uniqid(true));
(new UserFeedRepository($conn))->addUserFeed(['id' => $page['feed'], 'user_id' => $user['id']]);
$feed_url = \Phyxo\Functions\URL::get_root_url() . 'feed.php';
if ($userMapper->isGuest()) {
    $feed_image_only_url = $feed_url;
    $feed_url .= '?feed=' . $page['feed'];
} else {
    $feed_url .= '?feed=' . $page['feed'];
    $feed_image_only_url = $feed_url . '&amp;image_only';
}

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+

$title = \Phyxo\Functions\Language::l10n('Notification');

$template->assign(
    [
        'U_FEED' => $feed_url,
        'U_FEED_IMAGE_ONLY' => $feed_image_only_url,
    ]
);

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+
\Phyxo\Functions\Plugin::trigger_notify('loc_end_notification');
\Phyxo\Functions\Utils::flush_page_messages();

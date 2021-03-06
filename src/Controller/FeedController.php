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

namespace App\Controller;

use Phyxo\Conf;
use Phyxo\MenuBar;
use Phyxo\EntityManager;
use App\Repository\UserFeedRepository;
use App\Repository\BaseRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class FeedController extends CommonController
{
    public function notification(Request $request, Conf $conf, EntityManager $em, MenuBar $menuBar, TranslatorInterface $translator)
    {
        $tpl_params = [];
        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Notification');

        $feed_id = md5(uniqid(true));
        $em->getRepository(UserFeedRepository::class)->addUserFeed(['id' => $feed_id, 'user_id' => $this->getUser()->getId()]);
        if ($this->getUser()->isGuest()) {
            $tpl_params['U_FEED'] = $this->generateUrl('feed', ['feed_id' => $feed_id]);
            $tpl_params['U_FEED_IMAGE_ONLY'] = $this->generateUrl('feed', ['feed_id' => $feed_id]);
        } else {
            $tpl_params['U_FEED'] = $this->generateUrl('feed', ['feed_id' => $feed_id]);
            $tpl_params['U_FEED_IMAGE_ONLY'] = $this->generateUrl('feed_image_only', ['feed_id' => $feed_id]);
        }

        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('notification.html.twig', $tpl_params);
    }

    public function notificationSubscribe()
    {
        return new Response('Not yet');
    }

    public function notificationUnsubscribe()
    {
        return new Response('Not yet');
    }

    public function feed(string $feed_id, bool $image_only = false, Conf $conf, EntityManager $em, string $cacheDir, Notification $notification, TranslatorInterface $translator)
    {
        $result = $em->getRepository(UserFeedRepository::class)->findById($feed_id);
        $feed_row = $em->getConnection()->db_fetch_assoc($result);
        if (empty($feed_row)) {
            throw $this->createNotFoundException($translator->trans('Unknown feed identifier'));
        }

        $dbnow = $em->getRepository(BaseRepository::class)->getNow();

        $rss = new \UniversalFeedCreator();
        $rss->title = $conf['gallery_title'];
        $rss->title .= ' (as ' . $this->getUser()->getUsername() . ')';

        $rss->link = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $news = [];
        if (!$image_only) {
            $news = $notification->news($feed_row['last_check'], $dbnow, true, true);
            if (count($news) > 0) {
                $item = new \FeedItem();
                $item->title = $translator->trans('New on {date}', ['date' => \Phyxo\Functions\DateTime::format_date($dbnow)]);
                $item->link = $this->generateUrl('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

                // content creation
                $item->description = '<ul>';
                foreach ($news as $line) {
                    $item->description .= '<li>' . $line . '</li>';
                }
                $item->description .= '</ul>';
                $item->descriptionHtmlSyndicated = true;

                $item->date = $this->ts_to_iso8601(strtotime($dbnow));
                $item->author = $conf['rss_feed_author'];
                $item->guid = sprintf('%s', $dbnow);
                ;

                $rss->addItem($item);

                $em->getRepository(UserFeedRepository::class)->updateUserFeed(['last_check' => $dbnow], $feed_id);
            }
        }

        if (!empty($feed_id) and empty($news)) {// update the last check from time to time to avoid deletion by maintenance tasks
            if (!isset($feed_row['last_check']) or time() - strtotime($feed_row['last_check']) > 30 * 24 * 3600) {
                $em->getRepository(UserFeedRepository::class)->updateUserFeed(['last_check' => $em->getConnection()->db_get_recent_period_expression(-15, $dbnow)], $feed_id);
            }
        }

        $dates = $notification->get_recent_post_dates_array($conf['recent_post_dates']['RSS']);

        foreach ($dates as $date_detail) { // for each recent post date we create a feed item
            $item = new \FeedItem();
            $date = $date_detail['date_available'];
            $item->title = $notification->get_title_recent_post_date($date_detail);
            $item->link = $this->generateUrl(
                'calendar_categories_monthly_year_month_day',
                [
                    'date_type' => 'posted',
                    'view_type' => 'calendar',
                    'year' => substr($date, 0, 4),
                    'month' => substr($date, 5, 2),
                    'day' => substr($date, 8, 2)
                ]
            );

            $item->description .= '<a href="' . $this->generateUrl('homepage') . '">' . $conf['gallery_title'] . '</a><br> ';
            $item->description .= $notification->get_html_description_recent_post_date($date_detail, $conf['picture_ext']);

            $item->descriptionHtmlSyndicated = true;

            $item->date = $this->ts_to_iso8601(strtotime($date));
            $item->author = $conf['rss_feed_author'];
            $item->guid = sprintf('%s', 'pics-' . $date);
            ;

            $rss->addItem($item);
        }

        $fileName = $cacheDir . '/feed.xml';
        echo $rss->saveFeed('RSS2.0', $fileName, true);
    }

    /**
     * creates an ISO 8601 format date (2003-01-20T18:05:41+04:00) from Unix
     * timestamp (number of seconds since 1970-01-01 00:00:00 GMT)
     *
     * function copied from Dotclear project http://dotclear.net
     *
     * @param int timestamp
     * @return string ISO 8601 date format
     */
    protected function ts_to_iso8601($ts)
    {
        $tz = date('O', $ts);
        $tz = substr($tz, 0, -2) . ':' . substr($tz, -2);

        return date('Y-m-d\\TH:i:s', $ts) . $tz;
    }
}

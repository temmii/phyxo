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

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\Language;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class PhotosController extends AdminCommonController
{
    protected function setTabsheet(string $section = 'direct', bool $enable_synchronization = false)
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('direct', Language::l10n('Web Form'), $this->generateUrl('admin_photos_add', ['section' => 'direct']), 'fa-upload');
        if ($enable_synchronization) {
            $tabsheet->add('ftp', Language::l10n('FTP + Synchronization'), $this->generateUrl('admin_photos_add', ['section' => 'ftp']), 'fa-exchange');
        }
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function direct(Request $request, int $album_id = null, Template $template, EntityManager $em, Conf $conf, ParameterBagInterface $params, CsrfTokenManagerInterface $tokenManager)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $upload_max_filesize = min(
          \Phyxo\Functions\Utils::get_ini_size('upload_max_filesize'),
          \Phyxo\Functions\Utils::get_ini_size('post_max_size')
      );

        if ($upload_max_filesize == \Phyxo\Functions\Utils::get_ini_size('upload_max_filesize')) {
            $upload_max_filesize_shorthand = \Phyxo\Functions\Utils::get_ini_size('upload_max_filesize', false);
        } else {
            $upload_max_filesize_shorthand = \Phyxo\Functions\Utils::get_ini_size('post_max_filesize', false);
        }

        $tpl_params['upload_max_filesize'] = $upload_max_filesize;
        $tpl_params['upload_max_filesize_shorthand'] = $upload_max_filesize_shorthand;

        // what is the maximum number of pixels permitted by the memory_limit?
        if (\Phyxo\Image\Image::get_library() === 'GD') {
            $fudge_factor = 1.7;
            $available_memory = \Phyxo\Functions\Utils::get_ini_size('memory_limit') - memory_get_usage();
            $max_upload_width = round(sqrt($available_memory / (2 * $fudge_factor)));
            $max_upload_height = round(2 * $max_upload_width / 3);

            // we don't want dimensions like 2995x1992 but 3000x2000
            $max_upload_width = round($max_upload_width / 100) * 100;
            $max_upload_height = round($max_upload_height / 100) * 100;

            $max_upload_resolution = floor($max_upload_width * $max_upload_height / (1000000));

            // no need to display a limitation warning if the limitation is huge like 20MP
            if ($max_upload_resolution < 25) {
                $tpl_params['max_upload_width'] = $max_upload_width;
                $tpl_params['max_upload_height'] = $max_upload_height;
                $tpl_params['max_upload_resolution'] = $max_upload_resolution;
            }
        }

        //warn the user if the picture will be resized after upload
        if ($conf['original_resize']) {
            $tpl_params['original_resize_maxwidth'] = $conf['original_resize_maxwidth'];
            $tpl_params['original_resize_maxheight'] = $conf['original_resize_maxheight'];
        }

        $tpl_params['pwg_token'] = $tokenManager->getToken('authenticate');

        $unique_exts = array_unique(array_map('strtolower', $conf['upload_form_all_types'] ? $conf['file_ext'] : $conf['picture_ext']));

        $tpl_params['upload_file_types'] = implode(', ', $unique_exts);
        $tpl_params['file_exts'] = implode(',', $unique_exts);

        // we need to know the category in which the last photo was added
        $selected_category = [];
        if ($album_id) {
            // test if album really exists
            $album = $em->getRepository(CategoryRepository::class)->findById($album_id);
            if (!empty($album)) {
                $selected_category = [$album_id];
                $this->addFlash('selected_category', json_encode($selected_category));
            }
        } elseif ($this->get('session')->getFlashBag()->has('selected_category')) {
            $selected_category = json_decode($this->get('session')->getFlashBag()->get('selected_category'), true);
        } else {
            // we need to know the category in which the last photo was added
            $result = $em->getRepository(ImageRepository::class)->findCategoryWithLastImageAdded();
            if ($em->getConnection()->db_num_rows($result) > 0) {
                $row = $em->getConnection()->db_fetch_assoc($result);
                $selected_category = [$row['category_id']];
            }
        }

        // existing album
        $tpl_params['selected_category'] = $selected_category;

        // image level options
        $selected_level = $request->request->get('level') ? (int) $request->request->get('level') : 0;
        $tpl_params['level_options'] = \Phyxo\Functions\Utils::getPrivacyLevelOptions($conf['available_permission_levels']);
        $tpl_params['level_options_selected'] = [$selected_level];

        if (!function_exists('gd_info')) {
            $tpl_params['errors'][] = Language::l10n('GD library is missing');
        }


        if ($conf['use_exif'] && !function_exists('exif_read_data')) {
            $tpl_params['warnings'][] = Language::l10n('Exif extension not available, admin should disable exif use');
        }

        if (\Phyxo\Functions\Utils::get_ini_size('upload_max_filesize') > \Phyxo\Functions\Utils::get_ini_size('post_max_size')) {
            $tpl_params['warnings'][] = Language::l10n(
              'In your php.ini file, the upload_max_filesize (%sB) is bigger than post_max_size (%sB), you should change this setting',
              \Phyxo\Functions\Utils::get_ini_size('upload_max_filesize', false),
              \Phyxo\Functions\Utils::get_ini_size('post_max_size', false)
            );
        }
        $tpl_params['CACHE_KEYS'] = \Phyxo\Functions\Utils::getAdminClientCacheKeys(['categories'], $em);

        $tpl_params['ws'] = $this->generateUrl('ws');
        $tpl_params['csrf_token'] = $tokenManager->getToken('authenticate');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_photos_add');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_photos_add');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_photos_add');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Photo');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('direct', $conf['enable_synchronization']), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('photos_add_direct.tpl', $tpl_params);
    }
}

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

use App\DataMapper\CategoryMapper;
use App\DataMapper\ImageMapper;
use App\Repository\CategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\SiteRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteController extends AdminCommonController
{
    public function index(Request $request, EntityManager $em, Conf $conf, ParameterBagInterface $params, KernelInterface $kernel, CsrfTokenManagerInterface $csrfTokenManager,
                        TranslatorInterface $translator)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if ($request->isMethod('POST') && $request->request->get('galleries_url')) {
            $is_remote = \Phyxo\Functions\URL::url_is_remote($request->request->get('galleries_url'));
            if ($is_remote) {
                $this->addFlash('error', $translator->trans('remote sites not supported', [], 'admin'));
            } else {
                $url = preg_replace('/[\/]*$/', '', $request->request->get('galleries_url'));
                $url .= '/';
                if (!(strpos($url, '.') === 0)) {
                    $url = './' . $url;
                }

                // site must not exists

                if ($em->getRepository(SiteRepository::class)->isSiteExists($url)) {
                    $this->addFlash('error', $translator->trans('This site already exists', [], 'admin') . ' [' . $url . ']');
                } else {
                    $gallery_url = $kernel->getProjectDir() . '/' . $url;
                    if (!file_exists($gallery_url)) {
                        $this->addFlash('error', $translator->trans('Directory does not exist', [], 'admin') . ' [' . $gallery_url . ']');
                    } else {
                        $em->getRepository(SiteRepository::class)->addSite(['galleries_url' => $url]);
                        $this->addFlash('info', $url . ' ' . $translator->trans('created', [], 'admin'));
                    }
                }
            }

            return $this->redirectToRoute('admin_site');
        }

        $result = $em->getRepository(CategoryRepository::class)->findSitesDetail();
        $sites_detail = $em->getConnection()->result2array($result, 'site_id');

        $result = $em->getRepository(SiteRepository::class)->findAll();
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            $is_remote = \Phyxo\Functions\URL::url_is_remote($row['galleries_url']);

            $tpl_var = [
                'ID' => $row['id'],
                'NAME' => $row['galleries_url'],
                'TYPE' => $translator->trans($is_remote ? 'Remote' : 'Local', [], 'admin'),
                'CATEGORIES' => isset($sites_detail[$row['id']]['nb_categories']) ?? null,
                'IMAGES' => isset($sites_detail[$row['id']]['nb_images']) ?? null,
                'U_SYNCHRONIZE' => $this->generateUrl('admin_synchronize', ['site' => $row['id']]),
            ];

            $plugin_links = [];
            //$plugin_links is array of array composed of U_HREF, U_HINT & U_CAPTION
            $tpl_var['plugin_links'] = $plugin_links;

            $tpl_params['sites'][] = $tpl_var;
        }

        $tpl_params['csrf_token'] = $csrfTokenManager->getToken('authenticate');
        $tpl_params['F_ACTION'] = $this->generateUrl('admin_site');
        $tpl_params['F_ACTION_DELETE'] = $this->generateUrl('admin_site_delete');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_site');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_site');
        $tpl_params['PAGE_TITLE'] = $translator->trans('Site manager', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('site_manager.html.twig', $tpl_params);
    }

    public function delete(Request $request, EntityManager $em, ImageMapper $imageMapper, CategoryMapper $categoryMapper, TranslatorInterface $translator)
    {
        $site = $request->request->get('site');

        $result = $em->getRepository(SiteRepository::class)->findById($site);
        list($galleries_url) = $em->getConnection()->db_fetch_row($result);

        if ($galleries_url) {
            // destruction of the categories of the site
            $result = $em->getRepository(CategoryRepository::class)->findByField('site_id', $site);
            $category_ids = $em->getConnection()->result2array($result, null, 'id');
            $categoryMapper->deleteCategories($category_ids);

            // destruction of all photos physically linked to the category
            $result = $em->getRepository(ImageRepository::class)->findByFields('storage_category_id', $category_ids);
            $element_ids = $em->getConnection()->result2array($result, null, 'id');
            $imageMapper->deleteElements($element_ids);

            $em->getRepository(SiteRepository::class)->deleteSite($site);
            $this->addFlash('info', $galleries_url . ' ' . $translator->trans('deleted', [], 'admin'));
        }

        return $this->redirectToRoute('admin_site');
    }

    public function synchronize(Request $request, int $site, EntityManager $em, Conf $conf, ParameterBagInterface $params, TranslatorInterface $translator)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['U_PAGE'] = $this->generateUrl('admin_site');
        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_synchronize', ['site' => 1]);
        $tpl_params['PAGE_TITLE'] = $translator->trans('Synchronize', [], 'admin');
        $tpl_params = array_merge($this->menu($this->get('router'), $this->getUser(), $em, $conf, $params->get('core_version')), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        return $this->render('site_update.html.twig', $tpl_params);
    }
}

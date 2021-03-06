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

use Symfony\Component\HttpFoundation\Request;
use Phyxo\MenuBar;
use Phyxo\Conf;
use Phyxo\EntityManager;
use App\Repository\ImageRepository;
use App\DataMapper\TagMapper;
use App\Repository\BaseRepository;
use App\Repository\CategoryRepository;
use App\DataMapper\CategoryMapper;
use App\Repository\SearchRepository;
use App\DataMapper\SearchMapper;
use Phyxo\Image\ImageStandardParams;
use App\DataMapper\ImageMapper;
use App\Repository\TagRepository;
use Phyxo\Functions\DateTime;
use Phyxo\Functions\Utils;
use Phyxo\Functions\URL;
use Symfony\Contracts\Translation\TranslatorInterface;

class SearchController extends CommonController
{
    public function qsearch(Request $request, EntityManager $em, Conf $conf, MenuBar $menuBar, $themesDir, $phyxoVersion, $phyxoWebsite)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        if (!$request->get('q')) {
            return $this->createNotFoundException();
        }

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $search = ['q' => $request->get('q')];
        $search_id = $em->getRepository(SearchRepository::class)->findByRules(base64_encode(serialize($search)));

        if ($search_id !== false) {
            $em->getRepository(SearchRepository::class)->updateLastSeen($search_id);
        } else {
            $search_id = $em->getRepository(SearchRepository::class)->addSearch(base64_encode(serialize($search)));
        }

        return $this->redirectToRoute('search_results', ['search_id' => $search_id]);
    }

    public function search(Request $request, EntityManager $em, TagMapper $tagMapper, CategoryMapper $categoryMapper, Conf $conf,
        $themesDir, $phyxoVersion, $phyxoWebsite, MenuBar $menuBar, TranslatorInterface $translator)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params['PAGE_TITLE'] = $translator->trans('Search');

        $filter = [];
        $available_tags = $tagMapper->getAvailableTags($this->getUser(), $filter);

        if (count($available_tags) > 0) {
            usort($available_tags, '\Phyxo\Functions\Utils::tag_alpha_compare');
            $tpl_params['TAGS'] = $available_tags;
        }

        // authors
        $authors = [];
        $author_counts = [];
        $result = $em->getRepository(ImageRepository::class)->findGroupByAuthor($this->getUser(), $filter);
        while ($row = $em->getConnection()->db_fetch_assoc($result)) {
            if (!isset($author_counts[$row['author']])) {
                $author_counts[$row['author']] = 0;
            }

            $author_counts[$row['author']]++;
        }

        foreach ($author_counts as $author => $counter) {
            $authors[] = [
                'author' => $author,
                'counter' => $counter,
            ];
        }

        $tpl_params['AUTHORS'] = $authors;
        $month_list = [1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        foreach ($month_list as &$month) {
            $month = $translator->trans($month);
        }
        $month_list[0] = '------------';
        ksort($month_list);
        $tpl_params['month_list'] = $month_list;

        $where = [];
        $where[] = $em->getRepository(BaseRepository::class)->getSQLConditionFandF(
            $this->getUser(),
            $filter,
            [
                'forbidden_categories' => 'id',
                'visible_categories' => 'id'
            ]
        );

        $result = $em->getRepository(CategoryRepository::class)->findWithCondition($where);
        $categories = $em->getConnection()->result2array($result);
        $tpl_params = array_merge($tpl_params, $categoryMapper->displaySelectCategoriesWrapper($categories, [], 'category_options', true));

        $tpl_params['F_SEARCH_ACTION'] = $this->generateUrl('search');
        $tpl_params['month_list'] = $month_list;

        $search = [];
        if ($request->isMethod('POST')) {
            if ($request->request->get('search_allwords') && !preg_match('/^\s*$/', $request->request->get('search_allwords'))) {
                $fields = array_intersect($request->request->get('fields'), ['name', 'comment', 'file']);

                $drop_char_match = [
                    '-', '^', '$', ';', '#', '&', '(', ')', '<', '>', '`', '\'', '"', '|', ',', '@', '_',
                    '?', '%', '~', '.', '[', ']', '{', '}', ':', '\\', '/', '=', '\'', '!', '*'
                ];
                $drop_char_replace = [
                    ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', '', ' ',
                    ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', '', ' ', ' ', ' ', ' ', ' '
                ];

                // Split words
                $search['fields']['allwords'] = [
                    'words' => array_unique(
                        preg_split(
                            '/\s+/',
                            str_replace(
                                $drop_char_match,
                                $drop_char_replace,
                                $request->request->get('search_allwords')
                            )
                        )
                    ),
                    'mode' => $request->request->get('mode'),
                    'fields' => $fields,
                ];
            }

            if ($request->request->get('tags')) {
                $search['fields']['tags'] = [
                    'words' => $request->request->get('tags'),
                    'mode' => $request->request->get('tag_mode'),
                ];
            }

            if ($request->request->get('authors') && is_array($request->request->get('authors')) && count($request->request->get('authors')) > 0) {
                $authors = [];

                foreach ($request->request->get('authors') as $author) {
                    $authors[] = strip_tags($author);
                }

                $search['fields']['author'] = [
                    'words' => $authors,
                    'mode' => 'OR',
                ];
            }

            if ($request->request->get('cat')) {
                $search['fields']['cat'] = [
                    'words' => $_POST['cat'],
                    'sub_inc' => ($request->request->get('subcats-included') == 1) ? true : false,
                ];
            }

            // dates
            $type_date = $request->request->get('date_type');

            if ($request->request->get('start_year')) {
                $search['fields'][$type_date . '-after'] = [
                    'date' => sprintf(
                        '%d-%02d-%02d',
                        $request->request->get('start_year'),
                        $request->request->get('start_month') != 0 ? $request->request->get('start_month') : '01',
                        $request->request->get('start_day') != 0 ? $request->request->get('start_day') : '01'
                    ),
                    'inc' => true,
                ];
            }

            if ($request->request->get('end_year')) {
                $search['fields'][$type_date . '-before'] = [
                    'date' => sprintf(
                        '%d-%02d-%02d',
                        $request->request->get('end_year'),
                        $request->request->get('end_month') != 0 ? $request->request->get('end_month') : '12',
                        $request->request->get('end_day') != 0 ? $request->request->get('end_day') : '31'
                    ),
                    'inc' => true,
                ];
            }

            if (!empty($search)) {
                // default search mode : each clause must be respected
                $search['mode'] = 'AND';

                // register search rules in database, then they will be available on thumbnails page and picture page.
                $search_id = $em->getRepository(SearchRepository::class)->addSearch(base64_encode(serialize($search)));

                return $this->redirectToRoute('search_results', ['search_id' => $search_id]);
            } else {
                $tpl_params['errors'][] = $translator->trans('Empty query. No criteria has been entered.');
            }

            if ($request->request->get('start_day')) {
                $tpl_params['START_DAY_SELECTED'] = $request->request->get('start_day');
            }

            if ($request->request->get('start_month')) {
                $tpl_params['START_MONTH_SELECTED'] = $request->request->get('start_month');
            }

            if ($request->request->get('end_day')) {
                $tpl_params['END_DAY_SELECTED'] = $request->request->get('end_day');
            }

            if ($request->request->get('end_month')) {
                $tpl_params['END_MONTH_SELECTED'] = $request->request->get('end_month');
            }
        }

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        $tpl_params['F_SEARCH_ACTION'] = $this->generateUrl('search');

        return $this->render('search.html.twig', $tpl_params);
    }

    public function searchResults(Request $request, SearchMapper $searchMapper, CategoryMapper $categoryMapper, ImageMapper $imageMapper, Conf $conf,
        ImageStandardParams $image_std_params, MenuBar $menuBar, $themesDir, $phyxoVersion, $phyxoWebsite, $search_id, int $start = 0, TranslatorInterface $translator
    ) {
        $tpl_params = [];
        $this->image_std_params = $image_std_params;

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $tpl_params['PAGE_TITLE'] = $translator->trans('Search results');
        $tpl_params['TITLE'] = $translator->trans('Search results');
        $tpl_params['U_SEARCH_RULES'] = $this->generateUrl('search_rules', ['search_id' => $search_id]);

        $filter = [];
        $search_results = $searchMapper->getSearchResults($search_id, $this->getUser(), $filter, $super_order_by = true);
        $tpl_params['items'] = $search_results['items'];

        if (!empty($search_results['qsearch_details'])) {
            $cats = [];
            if (!empty($search_results['qsearch_details']['matching_cats_no_image'])) {
                $cats = array_merge($cats, $search_results['qsearch_details']['matching_cats_no_image']);
            }
            if ($search_results['qsearch_details']['matching_cats']) {
                $cats = array_merge($cats, $search_results['qsearch_details']['matching_cats']);
            }

            if (count($cats) > 0) {
                usort($cats, '\Phyxo\Functions\Utils::name_compare');
                $hints = [];
                foreach ($cats as $cat) {
                    $hints[] = $categoryMapper->getCatDisplayName([$cat]);
                }
                $tpl_params['category_search_results'] = $hints;
            }

            if (!empty($search_results['qsearch_details']['matching_tags'])) {
                foreach ($search_results['qsearch_details']['matching_tags'] as $tag) {
                    $tag['URL'] = $this->generateUrl('images_by_tags', ['tag_ids' => URL::tagToUrl($tag)]);
                    $tpl_params['tag_search_results'] = $tag;
                }
            }
        }

        if (count($tpl_params['items']) > 0) {
            $nb_image_page = $this->getUser()->getNbImagePage();

            $tpl_params['thumb_navbar'] = Utils::createNavigationBar(
                $this->get('router'),
                'search_results',
                ['search_id' => $search_id],
                count($tpl_params['items']),
                $start,
                $nb_image_page,
                $conf['paginate_pages_around']
            );

            $tpl_params = array_merge(
                $tpl_params,
                $imageMapper->getPicturesFromSelection(
                    array_slice($tpl_params['items'], $start, $nb_image_page),
                    $search_id,
                    'search',
                    $start
                )
            );

            if (!empty($search_results['qsearch_details']) && !empty($search_results['qsearch_details']['unmatched_terms'])) {
                $tpl_params['no_search_results'] = array_map('htmlspecialchars', $search_results['qsearch_details']['unmatched_terms']);
            }
        } else {
            if (!empty($search_results['qsearch_details']) && !empty($search_results['qsearch_details']['q'])) {
                $tpl_params['no_search_results'] = htmlspecialchars($search_results['qsearch_details']['q']); // @TODO: use template engine filter
            }
        }

        if ($request->cookies->has('category_view')) {
            $tpl_params['category_view'] = $request->cookies->get('category_view');
        }

        $tpl_params['START_ID'] = $start;
        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('thumbnails.html.twig', $tpl_params);
    }

    public function searchRules(Request $request, EntityManager $em, CategoryMapper $categoryMapper, SearchMapper $searchMapper, Conf $conf,
        string $themesDir, string $phyxoVersion, string $phyxoWebsite, int $search_id, MenuBar $menuBar, TranslatorInterface $translator)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['PAGE_TITLE'] = $translator->trans('Search rules');

        $tpl_params = array_merge($this->addThemeParams($conf), $tpl_params);
        $tpl_params = array_merge($tpl_params, $menuBar->getBlocks());

        $search = $searchMapper->getSearchArray($search_id);
        if (isset($search['q'])) {
            $tpl_params['search_words'] = $search['q'];
        } else {
            $tpl_params['INTRODUCTION'] = $search['mode'] === 'OR'? $translator->trans('At least one listed rule must be satisfied.') : $translator->trans('Each listed rule must be satisfied.');
        }

        if (isset($search['fields']['allwords'])) {
            $tpl_params['search_words'] = $translator->trans('searched words : {words}', ['words' => join(', ', $search['fields']['allwords']['words'])]);
        }

        if (isset($search['fields']['tags'])) {
            $tpl_params['SEARCH_TAGS_MODE'] = $search['fields']['tags']['mode'];

            $result = $em->getRepository(TagRepository::class)->findTags($search['fields']['tags']['words']);
            $tpl_params['search_tags'] = $em->getConnection()->result2array($result, 'name');
        }

        if (isset($search['fields']['author'])) {
            $tpl_params['search_words'] = $translator->trans('author(s) : {authors}', ['authors' => join(', ', array_map('strip_tags', $search['fields']['author']['words']))]);
        }

        if (isset($search['fields']['cat'])) {
            if ($search['fields']['cat']['sub_inc']) {
                // searching all the categories id of sub-categories
                $cat_ids = $em->getRepository(CategoryRepository::class)->getSubcatIds($search['fields']['cat']['words']);
            } else {
                $cat_ids = $search['fields']['cat']['words'];
            }

            $result = $em->getRepository(CategoryRepository::class)->findByIds($cat_ids);
            $categories = [];
            if (!empty($result)) {
                while ($row = $em->getConnection()->db_fetch_assoc($result)) {
                    $categories[] = $row;
                }
            }
            usort($categories, '\Phyxo\Functions\Utils::global_rank_compare');

            foreach ($categories as $category) {
                $tpl_params['search_categories'] = $categoryMapper->getCatDisplayNameCache($category['uppercats']);
            }
        }

        foreach (['date_available', 'date_creation'] as $datefield) {
            if ($datefield === 'date_available') {
                $lang_items = [
                    'date' => $translator->trans('posted on %s'),
                    'period' => $translator->trans('posted between %s (%s) and %s (%s)'),
                    'after' => $translator->trans('posted after %s (%s)'),
                    'before' => $translator->trans('posted before %s (%s)'),
                ];
            } elseif ($datefield === 'date_creation') {
                $lang_items = [
                    'date' => $translator->trans('created on %s'),
                    'period' => $translator->trans('created between %s (%s) and %s (%s)'),
                    'after' => $translator->trans('created after %s (%s)'),
                    'before' => $translator->trans('created before %s (%s)'),
                ];
            }

            $keys = [
                'date' => $datefield,
                'after' => $datefield . '-after',
                'before' => $datefield . '-before',
            ];

            if (isset($search['fields'][$keys['date']])) {
                $tpl_params[strtoupper($datefield)] = sprintf($lang_items['date'], DateTime::format_date($search['fields'][$keys['date']]));
            } elseif (isset($search['fields'][$keys['before']]) and isset($search['fields'][$keys['after']])) {
                $tpl_params[strtoupper($datefield)] = sprintf(
                    $lang_items['period'],
                    DateTime::format_date($search['fields'][$keys['after']]['date']),
                    $search['fields'][$keys['after']]['inc'] ? $translator->trans('included') : $translator->trans('excluded'),
                    DateTime::format_date($search['fields'][$keys['before']]['date']),
                    $search['fields'][$keys['before']]['inc'] ? $translator->trans('included') : $translator->trans('excluded')
                );
            } elseif (isset($search['fields'][$keys['before']])) {
                $tpl_params[strtoupper($datefield)] = sprintf(
                    $lang_items['before'],
                    DateTime::format_date($search['fields'][$keys['before']]['date']),
                    $search['fields'][$keys['before']]['inc'] ? $translator->trans('included') : $translator->trans('excluded')
                );
            } elseif (isset($search['fields'][$keys['after']])) {
                $tpl_params[strtoupper($datefield)] = sprintf(
                    $lang_items['after'],
                    DateTime::format_date($search['fields'][$keys['after']]['date']),
                    $search['fields'][$keys['after']]['inc'] ? $translator->trans('included') : $translator->trans('excluded')
                );
            }
        }

        $tpl_params = array_merge($tpl_params, $this->loadThemeConf($request->getSession()->get('_theme'), $conf));

        return $this->render('search_rules.html.twig', $tpl_params);
    }
}

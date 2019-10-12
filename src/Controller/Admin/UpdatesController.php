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

use App\DataMapper\UserMapper;
use App\Repository\UpgradeRepository;
use Phyxo\Conf;
use Phyxo\EntityManager;
use Phyxo\Functions\Language;
use Phyxo\TabSheet\TabSheet;
use Phyxo\Template\Template;
use Phyxo\Update\Updates;
use Phyxo\Upgrade;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class UpdatesController extends AdminCommonController
{
    protected function setTabsheet(string $section = 'core'): array
    {
        $tabsheet = new TabSheet();
        $tabsheet->add('core', Language::l10n('Phyxo Update'), $this->generateUrl('admin_updates'));
        $tabsheet->add('extensions', Language::l10n('Extensions Update'), $this->generateUrl('admin_updates_extensions'));
        $tabsheet->select($section);

        return ['tabsheet' => $tabsheet];
    }

    public function core(Request $request, int $step = 0, Template $template, Conf $conf, EntityManager $em, UserMapper $userMapper, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        /*
        STEP:
        0 = check is needed. If version is latest or check fail, we stay on step 0
        1 = new version on same branch AND new branch are available => user may choose upgrade.
        2 = upgrade on same branch
        3 = upgrade on different branch
         */
        $upgrade_to = isset($_GET['to']) ? $_GET['to'] : '';
        $obsolete_file = $params->get('install_dir') . '/obsolete.list';

        // +-----------------------------------------------------------------------+
        // |                                Step 0                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 0) {
            $tpl_params['CHECK_VERSION'] = false;
            $tpl_params['DEV_VERSION'] = false;

            $updater = new Updates($em->getConnection(), $userMapper, $params->get('core_version'));
            $updater->setUpdateUrl($params->get('update_url'));

            if (preg_match('/.*-dev$/', $params->get('core_version'), $matches)) {
                $tpl_params['DEV_VERSION'] = true;
            } elseif (preg_match('/(\d+\.\d+)\.(\d+)/', $params->get('core_version'), $matches)) {
                try {
                    $all_versions = $updater->getAllVersions();
                    $tpl_params['CHECK_VERSION'] = true;
                    $last_version = trim($all_versions[0]['version']);
                    $upgrade_to = $last_version;

                    if (version_compare($params->get('core_version'), $last_version, '<')) {
                        $new_branch = preg_replace('/(\d+\.\d+)\.\d+/', '$1', $last_version);
                        $actual_branch = $matches[1];

                        if ($new_branch === $actual_branch) {
                            $step = 2;
                        } else {
                            $step = 3;

                            // Check if new version exists in same branch
                            foreach ($all_versions as $version) {
                                $new_branch = preg_replace('/(\d+\.\d+)\.\d+/', '$1', $version);

                                if ($new_branch === $actual_branch) {
                                    if (version_compare($params->get('core_version'), $version, '<')) {
                                        $step = 1;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $tpl_params['LAST_ERROR_MESSAGE'] = $e->getMessage();
                }
            }
        }

        // +-----------------------------------------------------------------------+
        // |                                Step 1                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 1) {
            $tpl_params['MINOR_VERSION'] = $version;
            $tpl_params['MAJOR_VERSION'] = $last_version;
        }

        // +-----------------------------------------------------------------------+
        // |                                Step 2                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 2 && $userMapper->isWebmaster()) {
            if (isset($_POST['submit']) and isset($_POST['upgrade_to'])) {
                $zip = __DIR__ . '/../' . $conf['data_location'] . 'update' . '/' . $_POST['upgrade_to'] . '.zip';
                $updater->upgradeTo($_POST['upgrade_to']);
                $updater->download($zip);

                try {
                    $updater->upgrade($zip);
                    $updater->removeObsoleteFiles($obsolete_file, __DIR__ . '/..');

                    $userMapper->invalidateUserCache(true);
                    $template->delete_compiled_templates();
                    unlink(__DIR__ . '/../' . $conf['data_location'] . 'update');

                    $page['infos'][] = \Phyxo\Functions\Language::l10n('Update Complete');
                    $page['infos'][] = $upgrade_to;
                    $step = -1;
                } catch (Exception $e) {
                    $step = 0;
                    $message = $e->getMessage();
                    $message .= '<pre>';
                    $message .= implode("\n", $e->not_writable);
                    $message .= '</pre>';

                    $tpl_params['UPGRADE_ERROR'] = $message;
                }
            }
        }

        // +-----------------------------------------------------------------------+
        // |                                Step 3                                 |
        // +-----------------------------------------------------------------------+
        if ($step === 3 && $userMapper->isWebmaster()) {
            if (isset($_POST['submit']) and isset($_POST['upgrade_to'])) {
                $zip = __DIR__ . '/../' . $conf['data_location'] . 'update' . '/' . $_POST['upgrade_to'] . '.zip';
                $updater->upgradeTo($_POST['upgrade_to']);
                $updater->download($zip);

                try {
                    $updater->upgrade($zip);

                    $upgrade = new Upgrade($em, $conf);
                    $upgrade->deactivate_non_standard_plugins();
                    $upgrade->deactivate_non_standard_themes();

                    $tables = $em->getConnection()->db_get_tables($em->getConnection()->getPrefix());
                    $columns_of = $em->getConnection()->db_get_columns_of($tables);

                    $result = $em->getRepository(UpgradeRepository::class)->findAll();
                    $applied_upgrades = $em->getConnection()->result2array($result, null, 'id');

                    if (!in_array(142, $applied_upgrades)) {
                        $current_release = '1.0.0';
                    } elseif (!in_array(144, $applied_upgrades)) {
                        $current_release = '1.1.0';
                    } elseif (!in_array(145, $applied_upgrades)) {
                        $current_release = '1.2.0';
                    } elseif (in_array('validated', $columns_of[$em->getConnection()->getPrefix() . 'tags'])) {
                        $current_release = '1.3.0';
                    } elseif (!in_array(146, $applied_upgrades)) {
                        $current_release = '1.5.0';
                    } elseif (!in_array(147, $applied_upgrades)) {
                        $current_release = '1.6.0';
                    } elseif (!is_dir(__DIR__ . '/../src/LegacyPages')) {
                        $current_release = '1.8.0';
                    } else {
                        $current_release = '1.9.0';
                    }

                    $upgrade_file = __DIR__ . '/../install/upgrade_' . $current_release . '.php';
                    if (is_readable($upgrade_file)) {
                        ob_start();
                        include($upgrade_file);
                        ob_end_clean();
                    }

                    $updater->removeObsoleteFiles($obsolete_file, __DIR__ . '/..');

                    $fs = new Filesystem();
                    $fs->remove(__DIR__ . '/../' . $conf['data_location'] . 'update');
                    $fs->remove(__DIR__ . '/../var/cache');

                    \Phyxo\Functions\Utils::invalidate_user_cache(true);
                    $template->delete_compiled_templates();

                    file_get_contents('./'); // cache warmup
                    \Phyxo\Functions\Utils::redirect('./?now=' . time());
                } catch (Exception $e) {
                    $step = 0;
                    $message = $e->getMessage();
                    $message .= '<pre>';
                    $message .= implode("\n", $e->not_writable);
                    $message .= '</pre>';

                    $tpl_params['UPGRADE_ERROR'] = $message;
                }
            }
        }

        // +-----------------------------------------------------------------------+
        // |                        Process template                               |
        // +-----------------------------------------------------------------------+

        if (!$userMapper->isWebmaster()) {
            $tpl_params['errors'][] = Language::l10n('Webmaster status is required.');
        }

        $tpl_params['STEP'] = $step;
        $tpl_params['CORE_VERSION'] = $params->get('core_version');
        $tpl_params['UPGRADE_TO'] = $upgrade_to;
        $tpl_params['RELEASE_URL'] = $params->get('phyxo_website') . '/releases/' . $upgrade_to;

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_updates');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_updates');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Updates');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('core'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('updates_core.tpl', $tpl_params);
    }

    public function extensions(Request $request, Template $template, Conf $conf, EntityManager $em, ParameterBagInterface $params)
    {
        $tpl_params = [];

        $_SERVER['PUBLIC_BASE_PATH'] = $request->getBasePath();

        $tpl_params['ACTIVE_MENU'] = $this->generateUrl('admin_updates');
        $tpl_params['U_PAGE'] = $this->generateUrl('admin_updates_extensions');
        $tpl_params['PAGE_TITLE'] = Language::l10n('Updates');
        $tpl_params = array_merge($this->addThemeParams($template, $em, $conf, $params), $tpl_params);
        $tpl_params = array_merge($this->setTabsheet('extensions'), $tpl_params);

        if ($this->get('session')->getFlashBag()->has('error')) {
            $tpl_params['errors'] = $this->get('session')->getFlashBag()->get('error');
        }

        if ($this->get('session')->getFlashBag()->has('info')) {
            $tpl_params['infos'] = $this->get('session')->getFlashBag()->get('info');
        }

        return $this->render('updates_ext.tpl', $tpl_params);
    }
}
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

namespace Phyxo;

use App\Repository\UpgradeRepository;
use App\Repository\PluginRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserInfosRepository;

class Upgrade
{
    private $em, $conf;

    public function __construct(EntityManager $em, Conf $conf)
    {
        $this->em = $em;
        $this->conf = $conf;
    }

    public function deactivateNonStandardPlugins(): array
    {
        $standard_plugins = [];

        $result = $this->em->getRepository(PluginRepository::class)->findByStateAndExcludeIds('active', $standard_plugins);
        $plugins = [];
        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $plugins[] = $row['id'];
        }

        if (!empty($plugins)) {
            $this->em->getRepository(PluginRepository::class)->deactivateIds($plugins);
        }

        return $plugins;
    }

    public function deactivateNonStandardThemes(): array
    {
        $standard_themes = ['treflez'];

        $result = $this->em->getRepository(ThemeRepository::class)->findExcept($standard_themes);
        $theme_ids = [];
        $theme_names = [];

        while ($row = $this->em->getConnection()->db_fetch_assoc($result)) {
            $theme_ids[] = $row['id'];
            $theme_names[] = $row['name'];
        }

        if (!empty($theme_ids)) {
            $this->em->getRepository(ThemeRepository::class)->deleteByIds($theme_ids);

            // what is the default theme?
            $result = $this->em->getRepository(UserInfosRepository::class)->findByUserId($this->conf['default_user_id']);
            $user_infos = $this->em->getConnection()->db_fetch_assoc($result);

            // if the default theme has just been deactivated, let's set another core theme as default
            if (in_array($user_infos['theme'], $theme_ids)) {
                $this->em->getRepository(UserInfosRepository::class)->updateUserInfos(['theme' => 'treflez'], $this->conf['default_user_id']);
            }

            return $theme_names;
        }

        return [];
    }

    public function getAvailableUpgradeIds(string $root_dir): array
    {
        $upgrades_path = $root_dir . '/install/db';

        $available_upgrade_ids = [];

        if ($contents = opendir($upgrades_path)) {
            while (($node = readdir($contents)) !== false) {
                if (is_file($upgrades_path . '/' . $node) and preg_match('/^(.*?)-database\.php$/', $node, $match)) {
                    $available_upgrade_ids[] = $match[1];
                }
            }
        }
        natcasesort($available_upgrade_ids);

        return $available_upgrade_ids;
    }

    public function checkUpgradeFeed(string $root_dir): bool
    {
        // retrieve already applied upgrades
        $result = $this->em->getRepository(UpgradeRepository::class)->findAll();
        $applied = $this->em->getConnection()->result2array($result, null, 'id');

        // retrieve existing upgrades
        $existing = $this->getAvailableUpgradeIds($root_dir);

        // which upgrades need to be applied?
        return (count(array_diff($existing, $applied)) > 0);
    }
}

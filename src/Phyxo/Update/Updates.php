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

namespace Phyxo\Update;

use Phyxo\Plugin\Plugins;
use Phyxo\Theme\Themes;
use Phyxo\Language\Languages;
use PclZip;
use GuzzleHttp\Client;

class Updates
{
    private $versions = array(), $version = array();
    private $types = array();

    public function __construct(\CCMBenchmark\Ting\ContainerInterface $services, $page='updates') {
        $this->types = array('plugins', 'themes', 'languages');

        if (in_array($page, $this->types)) {
            $this->types = array($page);
        }
        $this->default_themes = array('elegant', 'legacy', 'default');
        $this->default_plugins = array();
        $this->default_languages = array();

        foreach ($this->types as $type) {
            $typeClassName = sprintf('\Phyxo\%s\%s', ucfirst(substr($type, 0, -1)), ucfirst($type));
            $this->$type = new $typeClassName($services);
        }
    }

    public function setUpdateUrl($url) {
        $this->update_url = $url;
    }

    public function getType($type) {
        if (!in_array($type, $this->types)) {
            return null;
        }

        return $this->$type;
    }

    public function getAllVersions() {
        try {
            $client = new Client(array('headers' => array('User-Agent' => 'Phyxo')));
            $response = $client->request('GET', $this->update_url);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                $this->versions = json_decode($response->getBody(), true);
                return $this->versions;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function upgradeTo($version, $release='stable') {
        foreach ($this->versions as $v) {
            if ($v['version']==$version && $v['release']==$release) {
                $this->version = $v;
            }
        }
    }

    public function download($zip_file) {
        @mkgetdir(dirname($zip_file)); // @TODO: remove arobase and use a fs library

        try {
            $client = new Client(array('headers' => array('User-Agent' => 'Phyxo')));
            $response = $client->request('GET', $this->getFileURL());
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                file_put_contents($zip_file, $response->getBody());
            }
        } catch (\Exception $e) {
        }
    }

    public function getFileURL() {
        return $this->version['href'];
    }

    public function upgrade($zip_file) {
        $zip = new PclZip($zip_file);
		$not_writable = array();
        $root = PHPWG_ROOT_PATH;

        foreach ($zip->listContent() as $file) {
            $filename = str_replace('phyxo/', '', $file['filename']);
            $dest = $dest_dir = $root.'/'.$filename;
			while (!is_dir($dest_dir = dirname($dest_dir)));

			if ((file_exists($dest) && !is_writable($dest)) ||
                (!file_exists($dest) && !is_writable($dest_dir))) {
				$not_writable[] = $filename;
				continue;
			}
        }
        if (!empty($not_writable)) {
            $e = new \Exception('Some files or directories are not writable');
            $e->not_writable = $not_writable;
            throw $e;
        }

        // @TODO: remove arobase ; extract try to make a touch on every file but sometimes failed.
        $result = @$zip->extract(PCLZIP_OPT_PATH, PHPWG_ROOT_PATH,
                                 PCLZIP_OPT_REMOVE_PATH, 'phyxo',
                                 PCLZIP_OPT_SET_CHMOD, 0755,
                                 PCLZIP_OPT_REPLACE_NEWER
        );
    }

    public function getServerExtensions($version=PHPWG_VERSION) {
        global $user;

        $get_data = array(
            'format' => 'json',
        );

        // Retrieve PEM versions
        $versions_to_check = array();
        $url = PEM_URL . '/api/get_version_list.php';

        try {
            $client = new Client(array('headers' => array('User-Agent' => 'Phyxo')));
            $response = $client->request('GET', $url, $get_data);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                $pem_versions = json_decode($response->getBody(), true);
            } else {
                throw new \Exception("Reponse from server is not readable");
            }

            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $version = $pem_versions[0]['name'];
            }
            $branch = get_branch_from_version($version);
            foreach ($pem_versions as $pem_version) {
                if (strpos($pem_version['name'], $branch) === 0) {
                    $versions_to_check[] = $pem_version['id'];
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        // Extensions to check
        $ext_to_check = array();
        foreach ($this->types as $type) {
            foreach ($this->getType($type)->getFsExtensions() as $ext) {
                if (isset($ext['extension'])) {
                    $ext_to_check[$ext['extension']] = $type;
                }
            }
        }

        // Retrieve PEM plugins infos
        $url = PEM_URL . '/api/get_revision_list.php';
        $get_data = array_merge($get_data, array(
            'last_revision_only' => 'true',
            'version' => implode(',', $versions_to_check),
            'lang' => substr($user['language'], 0, 2),
            'get_nb_downloads' => 'true',
            'format' => 'json'
        ));

        $post_data = array();
        if (!empty($ext_to_check)) {
            $post_data['extension_include'] = implode(',', array_keys($ext_to_check));
        }

        try {
            $client = new Client(array('headers' => array('User-Agent' => 'Phyxo')));
            $response = $client->request('GET', $url, $get_data);
            if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                $pem_exts = json_decode($response->getBody(), true);
            } else {
                throw new \Exception("Reponse from server is not readable");
            }
            if (!is_array($pem_exts)) {
                return array();
            }

            $servers = array();

            foreach ($pem_exts as $ext) {
                if (isset($ext_to_check[$ext['extension_id']])) {
                    $type = $ext_to_check[$ext['extension_id']];

                    if (!isset($servers[$type])) {
                        $servers[$type] = array();
                    }

                    $servers[$type][$ext['extension_id']] = $ext;

                    unset($ext_to_check[$ext['extension_id']]);
                }
            }

            $this->checkMissingExtensions($ext_to_check);
            return array();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return array();
    }

    public function checkCoreUpgrade() {
        $_SESSION['need_update'] = false;

        if (preg_match('/(\d+\.\d+)\.(\d+)$/', PHPWG_VERSION, $matches)) {
            try {
                $client = new Client(array('headers' => array('User-Agent' => 'Phyxo')));
                $response = $client->request('GET', PHPWG_URL.'/download/all_versions.php');
                if ($response->getStatusCode()==200 && $response->getBody()->isReadable()) {
                    $all_versions = json_decode($response->getBody(), true);
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }

            $new_version = trim($all_versions[0]['version']);
            $_SESSION['need_update'] = version_compare(PHPWG_VERSION, $new_version, '<');
        }
    }

    // Check all extensions upgrades
    public function checkExtensions() {
        global $conf;

        if (!$this->getServerExtensions()) {
            return false;
        }

        $_SESSION['extensions_need_update'] = array();

        foreach ($this->types as $type) {
            $ignore_list = array();

            foreach($this->getType($type)->getFsExtensions() as $ext_id => $fs_ext) {
                if (isset($fs_ext['extension']) and isset($server_ext[$fs_ext['extension']])) {
                    $ext_info = $server_ext[$fs_ext['extension']];

                    if (!safe_version_compare($fs_ext['version'], $ext_info['revision_name'], '>=')) {
                        if (in_array($ext_id, $conf['updates_ignored'][$type])) {
                            $ignore_list[] = $ext_id;
                        } else {
                            $_SESSION['extensions_need_update'][$type][$ext_id] = $ext_info['revision_name'];
                        }
                    }
                }
            }
            $conf['updates_ignored'][$type] = $ignore_list;
        }
        conf_update_param('updates_ignored', $conf['updates_ignored']);
    }

    // Check if extension have been upgraded since last check
    public function checkUpdatedExtensions() {
        foreach ($this->types as $type) {
            if (!empty($_SESSION['extensions_need_update'][$type])) {
                foreach($this->getType($type)->getFsExtensions() as $ext_id => $fs_ext) {
                    if (isset($_SESSION['extensions_need_update'][$type][$ext_id])
                        and safe_version_compare($fs_ext['version'], $_SESSION['extensions_need_update'][$type][$ext_id], '>=')) {
                        // Extension have been upgraded
                        $this->checkExtensions();
                        break;
                    }
                }
            }
        }
    }

    protected function checkMissingExtensions($missing) {
        foreach ($missing as $id => $type) {
            $default = 'default_'.$type;
            foreach ($this->getType($type)->getFsExtensions() as $ext_id => $ext) {
                if (isset($ext['extension']) and $id == $ext['extension']
                    and !in_array($ext_id, $this->$default)) {
                    $this->missing[$type][] = $ext;
                    break;
                }
            }
        }
    }
}

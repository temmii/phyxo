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

namespace Phyxo\Extension;

use GuzzleHttp\Client;
use PclZip;

class Extensions
{
    protected $directory_pattern = '';

    public function getJsonFromServer($url, $params=[]) {
        try {
            $client = new Client(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('GET', $url, ['query' => $params]);
            if ($response->getStatusCode()===200 && $response->getBody()->isReadable()) {
                return json_decode($response->getBody(), true);
            } else {
                throw new \Exception("Response is not readable");
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function download($params=[], $filename) {
        $url = PEM_URL . '/download.php';
        try {
            $client = new Client(['headers' => ['User-Agent' => 'Phyxo']]);
            $response = $client->request('GET', $url, ['query' => $params]);
            if ($response->getStatusCode()===200 && $response->getBody()->isReadable()) {
                file_put_contents($filename, $response->getBody());
            } else {
                throw new \Exception("Response is not readable");
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    protected function extractZipFiles($zip_file, $main_file, $extract_path='') {
        $zip = new PclZip($zip_file);
        if ($list = $zip->listContent()) {
            // find main file
            foreach ($list as $file) {
                if (basename($file['filename']) === $main_file && (!isset($main_filepath) || strlen($file['filename']) < strlen($main_filepath))) {
                    $main_filepath = $file['filename'];
                }
            }

            if (!empty($main_filepath)) {
                $root = basename(dirname($main_filepath)); // dirname($main_filepath) cannot be null throw Exception if needed
                $extract_path .= '/'.$root;

                if (!empty($this->directory_pattern)) {
                    if (!preg_match($this->directory_pattern, $root)) {
                        throw new \Exception(sprintf('Root directory (%s) of archive does not follow expected pattern %s', $root, $this->directory_pattern));
                    }
                }

                // @TODO: use native zip library ; use arobase before
                if ($results = @$zip->extract(PCLZIP_OPT_PATH, $extract_path, PCLZIP_OPT_REMOVE_PATH, $root, PCLZIP_OPT_REPLACE_NEWER)) {
                    $errors = array_filter($results, function($f) { return ($f['status'] !== 'ok' && $f['status']!=='filtered') && $f['status']!=='already_a_directory'; });
                    if (count($errors)>0) {
                        throw new \Exception("Error while extracting some files from archive");
                    }
                } else {
                    throw new \Exception("Error while extracting archive");
                }
            }
        } else {
            throw new \Exception("Can't read or extract archive.");
        }
    }
}

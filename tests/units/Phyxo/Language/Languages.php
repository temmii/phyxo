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

namespace tests\units\Phyxo\Language;

require_once __DIR__ . '/../../bootstrap.php';

use atoum;

define('LANGUAGES_TABLE', 'languages');

class Languages extends atoum
{
    private function getLocalLanguages() {
        return array(
            'aa_AA' => array(
                'name' => 'AA Language [AA]',
                'code' => 'aa_AA',
                'version' => '1.0.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=16',
                'author' => 'Nicolas',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '16'
            ),
            'gg_GG' => array(
                'name' => 'GG Language [GG]',
                'code' => 'gg_GG',
                'version' => '3.0.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=61',
                'author' => 'Jean',
                'extension' => '61'
            ),
            'ss_SS' => array(
                'name' => 'SS Language [SS]',
                'code' => 'ss_SS',
                'version' => '1.2.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=33',
                'author' => 'Jean',
                'extension' => '33'
            ),
            'tt_TT' => array(
                'name' => 'TT Language [TT]',
                'code' => 'tt_TT',
                'version' => '0.3.0',
                'uri' => 'http://ext.phyxo.net/extension_view.php?eid=99',
                'author' => 'Arthur',
                'author uri' => 'http://www.phyxo.net/',
                'extension' => '99'
            )
        );
    }

    public function testFsLanguages() {
        $services = new \CCMBenchmark\Ting\Services();
        $languages = new \Phyxo\Language\Languages($services);

        $this
            ->array($languages->getFsLanguages())
            ->isEqualTo($this->getLocalLanguages());
    }
}
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

namespace Phyxo\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class Plugin implements NotifyPropertyInterface
{
    use NotifyProperty;

    private $id;
    private $state;
    private $version;

    public function setId($id) {
        $this->propertyChanged('id', $this->id, (string) $id);
        $this->id = (string) $id;
    }

    public function getId() {
        return $this->id;
    }

    public function setState($state) {
        $this->propertyChanged('state', $this->state, (string) $state);
        $this->state = (string) $state;
    }

    public function getState() {
        return $this->state;
    }

    public function setVersion($version) {
        $this->propertyChanged('version', $this->version, (string) $version);
        $this->version = (string) $version;
    }

    public function getVersion() {
        return $this->version;
    }
}
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

class Theme implements NotifyPropertyInterface
{
    use NotifyProperty;

    private $id;
    private $name;
    private $version;

    public function setId($id) {
        $this->propertyChanged('id', $this->id, (string) $id);
        $this->id = (string) $id;
    }

    public function getId() {
        return $this->id;
    }

    public function setName($name) {
        $this->propertyChanged('name', $this->name, (string) $name);
        $this->name = (string) $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setVersion($version) {
        $this->propertyChanged('version', $this->version, (string) $version);
        $this->version = (string) $version;
    }

    public function getVersion() {
        return $this->version;
    }
}
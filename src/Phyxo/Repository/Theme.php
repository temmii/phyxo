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

namespace Phyxo\Repository;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;

class Theme extends Repository implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializer, array $options = []) {
        $metadata = new Metadata($serializer);
        $metadata->setEntity(\Phyxo\Entity\Theme::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('phyxo');
        $metadata->setTable('phyxo_themes');

        $metadata
            ->addField([
                'primary'       => true,
                'fieldName'     => 'Id',
                'columnName'    => 'id',
                'type'          => 'string'
            ])
            ->addField([
                'fieldName'  => 'Name',
                'columnName' => 'name',
                'type'       => 'string'
            ])
            ->addField([
                'fieldName'  => 'Version',
                'columnName' => 'version',
                'type'       => 'int'
            ]);

        return $metadata;
    }

    public function findOneNotEqual($id) {
        $query = $this->getQuery('SELECT id FROM '.$this->metadata->getTable() . ' WHERE id != :id');
        $query->setParams(['id' => $id]);
        $query->query();
    }

    public function add($id, $name, $version) {
        $query = $this->getQuery('INSERT INTO '. $this->metadata->getTable() .' (id,version) VALUES (:id, :name, :version)');
        $query->setParams(['id' => $id, 'name' => $name,'version' => $version]);
        $query->execute();
    }

    public function delete($id) {
        $query = $this->getQuery('DELETE FROM '. $this->metadata->getTable() .' WHERE id = :id');
        $query->setParams(['id' => $id]);
        $query->execute();
    }
}
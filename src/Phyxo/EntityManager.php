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

use Phyxo\DBLayer\iDBLayer;

class EntityManager
{
    private $conn;

    public function __construct(iDBLayer $conn)
    {
        $this->conn = $conn;
    }

    public function getConnection(): iDBLayer
    {
        return $this->conn;
    }

    public function getRepository($repository)
    {
        return new $repository($this->conn);
    }
}

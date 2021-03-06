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

namespace App\Entity;

class UserInfos implements \ArrayAccess
{
    private $infos = [];
    private $forbidden_categories = [], $image_access_list = [], $image_access_type = 'NOT IN';

    public function __construct(array $infos = [])
    {
        $this->infos = $infos;
    }

    public function asArray()
    {
        return $this->infos;
    }

    public function offsetExists($offset)
    {
        return isset($this->infos[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->infos[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->infos[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->infos[$offset]);
    }

    public function getTheme()
    {
        return $this->infos['theme'] ? $this->infos['theme'] : 'treflez'; // @TODO: inject defaultTheme
    }

    public function getNbImagePage()
    {
        return $this->infos['nb_image_page'] ?? null;
    }

    public function getNbTotalImages()
    {
        return $this->infos['nb_total_images'] ?? null;
    }

    public function getRecentPeriod()
    {
        return $this->infos['recent_period'] ?? null;
    }

    public function getShowNbHits()
    {
        return $this->infos['show_nb_hits'] ?? null;
    }

    public function getShowNbComments()
    {
        return $this->infos['show_nb_comments'] ?? null;
    }

    public function getLanguage() : string
    {
        return $this->infos['language'] ?? '';
    }

    public function getUserId()
    {
        return $this->infos['user_id'] ?? null;
    }

    public function getLevel()
    {
        return $this->infos['level'] ?? null;
    }

    public function getStatus()
    {
        return $this->infos['status'] ?? null;
    }

    public function wantExpand()
    {
        return $this->infos['expand'] ?? false;
    }

    public function hasEnabledHigh(): bool
    {
        return $this->infos['enabled_high'];
    }

    public function getLastPhotoDate()
    {
        return $this->infos['last_photo_date'] ?? null;
    }

    public function getForbiddenCategories(): array
    {
        return $this->forbidden_categories;
    }

    public function setForbiddenCategories(array $forbidden_categories = [])
    {
        $this->forbidden_categories = $forbidden_categories;
    }

    public function getImageAccessList(): array
    {
        return $this->image_access_list;
    }

    public function setImageAccessList(array $image_access_list = [])
    {
        $this->image_access_list = $image_access_list;
    }

    public function getImageAccessType(): string
    {
        return $this->image_access_type;
    }

    public function setImageAccessType(string $image_access_type = 'NOT IN')
    {
        $this->image_access_type = $image_access_type;
    }
}

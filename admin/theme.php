<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined("PHPWG_ROOT_PATH")) {
    die ("Hacking attempt!");
}

require_once(PHPWG_ROOT_PATH . '/vendor/autoload.php');

use Phyxo\Theme\Themes;

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (empty($_GET['theme'])) {
    die('Invalid theme URL');
}

$themes = new Themes($conn);
if (!in_array($_GET['theme'], array_keys($themes->getFsThemes()))) {
    die('Invalid theme');
}

$filename = PHPWG_THEMES_PATH.$_GET['theme'].'/admin/admin.inc.php';
if (is_file($filename)) {
    include_once($filename);
} else {
    die('Missing file '.$filename);
}

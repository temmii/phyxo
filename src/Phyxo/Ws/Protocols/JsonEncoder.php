<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

namespace Phyxo\Ws\Protocols;

class JsonEncoder extends ResponseEncoder
{
    public function encodeResponse($response) {
        $respClass = @get_class($response);
        if ($respClass == 'Phyxo\Ws\Error') {
            return json_encode(
                array(
                    'stat' => 'fail',
                    'err' => $response->code(),
                    'message' => $response->message(),
                )
            );
        }
        parent::flattenResponse($response);
        return json_encode(
            array(
                'stat' => 'ok',
                'result' => $response,
            )
        );
    }

    public function getContentType() {
        return 'text/plain';
    }
}

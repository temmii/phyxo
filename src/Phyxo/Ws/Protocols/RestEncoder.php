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

class RestEncoder extends ResponseEncoder
{
    public function encodeResponse($response) {
        $respClass = @get_class($response);
        if ($respClass == 'Phyxo\Ws\Error') {
            $ret = '<?xml version="1.0"?>';
            $ret .= '<rsp stat="fail">';
            $ret .= '<err code="'.$response->code().'" msg="'.htmlspecialchars($response->message()).'" />';
            $ret .= '</rsp>';
            return $ret;
        }

        $this->_writer = new XmlWriter();
        $this->encode($response);
        $ret = '<?xml version="1.0" encoding="'.get_pwg_charset().'" ?>';
        $ret .= '<rsp stat="ok">';
        $ret .= $this->_writer->getOutput();
        $ret .= '</rsp>';

        return $ret;
    }

    public function getContentType() {
        return 'text/xml';
    }

    public function encode_array($data, $itemName, $xml_attributes=array()) {
        foreach ($data as $item) {
            $this->_writer->start_element( $itemName );
            $this->encode($item, $xml_attributes);
            $this->_writer->end_element( $itemName );
        }
    }

    public function encode_struct($data, $skip_underscore, $xml_attributes=array()) {
        foreach ($data as $name => $value) {
            if (is_numeric($name)) {
                continue;
            }
            if ($skip_underscore and $name[0]=='_') {
                continue;
            }
            if (is_null($value)) {
                continue; // null means we dont put it
            }
            if ($name==WS_XML_ATTRIBUTES) {
                foreach ($value as $attr_name => $attr_value) {
                    $this->_writer->write_attribute($attr_name, $attr_value);
                }
                unset($data[$name]);
            } elseif ( isset($xml_attributes[$name])) {
                $this->_writer->write_attribute($name, $value);
                unset($data[$name]);
            }
        }

        foreach ($data as $name => $value) {
            if (is_numeric($name)) {
                continue;
            }
            if ($skip_underscore and $name[0]=='_') {
                continue;
            }
            if (is_null($value)) {
                continue; // null means we dont put it
            }
            $this->_writer->start_element($name);
            $this->encode($value);
            $this->_writer->end_element($name);
        }
    }

    public function encode($data, $xml_attributes=array()) {
        switch (gettype($data))
            {
            case 'null':
            case 'NULL':
                $this->_writer->write_content('');
                break;
            case 'boolean':
                $this->_writer->write_content($data ? '1' : '0');
                break;
            case 'integer':
            case 'double':
                $this->_writer->write_content($data);
                break;
            case 'string':
                $this->_writer->write_content($data);
                break;
            case 'array':
                $is_array = range(0, count($data) - 1) === array_keys($data);
                if ($is_array) {
                    $this->encode_array($data, 'item' );
                } else {
                    $this->encode_struct($data, false, $xml_attributes);
                }
                break;
            case 'object':
                switch (@get_class($data))
                    {
                    case 'Phyxo\Ws\NamedArray':
                        $this->encode_array($data->_content, $data->_itemName, $data->_xmlAttributes);
                        break;
                    case 'Phyxo\Ws\NamedStruct':
						$this->encode_struct($data->_content, false, $data->_xmlAttributes);
                        break;
                    default:
                        $this->encode_struct(get_object_vars($data), true);
                        break;
                    }
                break;
            default:
                trigger_error("Invalid type ". gettype($data)." ".@get_class($data), E_USER_WARNING );
            }
    }
}
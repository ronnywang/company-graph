<?php

class Cache extends Pix_Table
{
    public function init()
    {
        $this->_name = 'cache';
        $this->_primary = 'id';

        $this->_columns['id'] = array('type' => 'int');
        $this->_columns['data'] = array('type' => 'longtext');
        $this->_columns['version'] = array('type' => 'int');
    }

    public function get($id, $version = 0)
    {
        $c = Cache::find($id);
        if ($c and $c->version == $version) {
            return $c->data;
        }
        return null;
    }

    public function set($id, $data, $version = 0)
    {
        try {
            Cache::insert(array(
                'id' => intval($id),
                'data' => strval($data),
                'version' => $version,
            ));
        } catch (Pix_Table_DuplicateException $e) {
            Cache::find(intval($id))->update(array(
                'data' => strval($data),
                'version' => $version,
            ));
        }
    }
}

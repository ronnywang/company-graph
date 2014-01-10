<?php

class CompanyGraph extends Pix_Table
{
    public function init()
    {
        $this->_name = 'company_graph';
        $this->_primary = array('company_id', 'board_type', 'board_id');

        $this->_columns['company_id'] = array('type' => 'int');
        // 0 - board_id 是統一編號, 1 - board_id 是公司名稱的 crc32, 並且會在 board_name 記錄公司
        $this->_columns['board_type'] = array('type' => 'tinyint');
        $this->_columns['board_id'] = array('type' => 'int', 'unsigned' => true);
        $this->_columns['board_name'] = array('type' => 'varchar', 'size' => 64);
        // 擁有股份
        $this->_columns['amount'] = array('type' => 'int');

        $this->addIndex('board_company', array('board_type', 'board_id', 'company_id'), 'unique');
    }
}

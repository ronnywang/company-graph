<?php

include(__DIR__ . '/../init.inc.php');

class Importer
{
    public function main($filename)
    {
        if (!file_exists($filename)) {
            die("Usage: php import.php {filename}\n");
        }

        $fp = fopen($filename, 'r');
        while ($row = fgetcsv($fp)) {
            list($company_id, $board_type, $board_value, $amount) = $row;

            try {
                CompanyGraph::insert(array(
                    'company_id' => intval($company_id),
                    'board_type' => ('name' == $board_type) ? 1 : 0,
                    'board_id' => ('name' == $board_type) ? crc32($board_value) : $board_value,
                    'board_name' => ('name' == $board_type) ? $board_nme : '',
                    'amount' => intval(str_replace(',', '', $amount)),
                ));
            } catch (Pix_Table_DuplicateException $e) {
            }
        }
    }
}

$i = new Importer;
$i->main($_SERVER['argv'][1]);

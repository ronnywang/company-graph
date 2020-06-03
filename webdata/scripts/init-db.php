<?php

include(__DIR__ . '/../init.inc.php');
$tables = array(
    'CompanyGraph',
    'KeyValue',
);
foreach ($tables as $table) {
    $t = Pix_Table::getTable($table);
    $t->createTable();
}

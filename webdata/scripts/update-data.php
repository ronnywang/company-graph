<?php

include(__DIR__ . '/../init.inc.php');

$get_online_data_time = function(){
    $doc = new DOMDocument;
    @$doc->loadHTML(file_get_contents("http://ronnywang-twcompany.s3-website-ap-northeast-1.amazonaws.com/index.html"));

    $time = null;
    foreach ($doc->getElementsByTagName('tr') as $tr_dom) {
        $td_doms = $tr_dom->getElementsByTagName('td');
        if ($td_doms->item(0)->nodeValue == 'files/') {
            $time = strtotime($td_doms->item(1)->nodeValue);
        }
    }
    return $time;
};

$old_data_time = KeyValue::get('data_updated_at');

$new_data_time = $get_online_data_time();
if (is_null($new_data_time) or $new_data_time <= $old_data_time) {
    error_log("skip");
    exit;
}

mkdir("/tmp/company-graph-data");
for ($i = 0; $i < 10; $i ++) {
    system("wget -O /tmp/company-graph-data/{$i}.gz http://ronnywang-twcompany.s3-website-ap-northeast-1.amazonaws.com/files/{$i}0000000.jsonl.gz");
}

$output = fopen('/tmp/company-graph-data/graph.csv', 'w');
for ($i = 0; $i < 10; $i ++) {
    $fp = gzopen("/tmp/company-graph-data/{$i}.gz", 'r');
    while ($line = fgets($fp)) {
        $obj = json_decode($line);
        $showed = array();
        if (!$obj or !property_exists($obj, '董監事名單')) {
            continue;
        }

        $main_id = $obj->id;
        foreach ($obj->{'董監事名單'} as $record) {
            if (!$record->{'所代表法人'}) {
                continue;
            }
            if ($record->{'所代表法人'}[0]) {
                $board_type = 'id';
                $value = $record->{'所代表法人'}[0];
            } else {
                $board_type = 'name';
                $value = $record->{'所代表法人'}[1];
            }

            if (!$record->{'出資額'}) {
                continue;
            }
            $id = $main_id . $board_type . $value;
            if (array_key_exists($id, $showed)) {
                continue;
            }
            $showed[$id] = true;


            fputcsv($output, array(
                $main_id,
                $board_type,
                $value,
                '',
                str_replace(',', '', $record->{'出資額'}),
            ));
        }
    }
    fclose($fp);
}
fclose($output);

$fp = fopen('/tmp/company-graph-data/graph.csv', 'r');

$db = CompanyGraph::getDb();

try {
    $sql = "DROP TABLE company_graph_tmp";
    $db->query($sql);
} catch (Exception $e) {
}

$sql = "CREATE TABLE company_graph_tmp LIKE company_graph";
$db->query($sql);

$terms = array();
while ($row = fgetcsv($fp)) {
    list($company_id, $board_type, $board_value,, $amount) = $row;

    $terms[] = sprintf("(%d,%d,%d,'',%d)",
        intval($company_id),
        ('name' == $board_type) ? 1 : 0,
        ('name' == $board_type) ? crc32($board_value) : $board_value,
        intval($amount)
    );

    if (count($terms) >= 5000) {
        $sql = "INSERT INTO company_graph_tmp (company_id, board_type, board_id, board_name, amount) VALUES " . implode(',', $terms);
        $db->query($sql);
        $terms = array();
    }
}
fclose($fp);

if (count($terms)) {
    $sql = "INSERT INTO company_graph_tmp (company_id, board_type, board_id, board_name, amount) VALUES " . implode(',', $terms);
    $db->query($sql);
    $terms = array();
}
try {
    $db->query("DROP TABLE company_graph_old");
} catch (Exception $e) {
}

// 如果同一個月的話，就直接砍掉舊的，表示可能是抓到一半
if (!$old_data_time or date('Ym', $old_data_time) == date('Ym', $new_data_time)) {
    $db->query("RENAME TABLE company_graph TO company_graph_old");
    $db->query("RENAME TABLE company_graph_tmp TO company_graph");
} else {
    $db->query("RENAME TABLE company_graph TO company_graph_" . date('Ym', $old_data_time));
    $db->query("RENAME TABLE company_graph_tmp TO company_graph");
}
$db->query("TRUNCATE TABLE cache");
KeyValue::set('data_updated_at', $new_data_time);


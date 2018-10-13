<?php

class GraphLib
{
    protected static $_start_id;

    protected static function isForeigh($str)
    {
        return preg_match('#(馬來西亞商|加拿大商|英屬維京群島商|英屬開曼群島商|瑞士商|英屬維爾京群島商|瑞典商|荷蘭商|新加坡商|香港商|英商|美商|日商|法商|株式[會会]社)#u', $str);
    }

    protected static function isGoverment($str)
    {
        if (preg_match('#([縣市]政府)#u', $str)) {
            return true;
        } elseif (preg_match('#部$#', $str)) {
            return true;
        } else {
            return false;
        }
    }

    protected static function insertQueryPool($type, $id)
    {
        if (array_key_exists("{$type}-{$id}", self::$_mappings)) {
            return;
        }

        if ($id === '') {
            throw new Exception('test');
        }

        if ($type == 1) {
            $obj = new StdClass;
            $obj->id = count(self::$_nodes);
            if (self::isForeigh($id)) {
                $obj->cluster = '外商';
            } elseif (self::isGoverment($id)) {
                $obj->cluster = '政府';
            } else {
                $obj->cluster = '法人';
            }
            $obj->funder = null;
            $obj->amount = 0;
            $obj->size = 10000;
            $obj->no = '0';
            $obj->text = $id;
            self::$_mappings["1-{$id}"] = $obj->id;
            self::$_nodes[] = $obj;
        }

        self::$_query_pool["{$type}-{$id}"] = array($type, $id);
    }

    protected static function getDataFromQueryPool($final = false)
    {
        $query_pool = array_values(self::$_query_pool);
        self::$_query_pool = array();

        $unit_datas = array();

        $unit_ids = array();
        foreach ($query_pool as $idx => $type_id) {
            if ($type_id[0] == 0) {
                $unit_ids[] = $type_id[1];
            } else {
                $query_pool[$idx][1] = crc32($query_pool[$idx][1]);

            }
        }

        if ($unit_ids) {
            foreach (UnitData::search(1)->searchIn('id', $unit_ids) as $unitdata) {
                if (!array_key_exists($unitdata->id, $unit_datas)) {
                    $unit_datas[$unitdata->id] = new StdClass;
                }
                $unit_datas[$unitdata->id]->{self::$_columns[$unitdata->column_id]} = json_decode($unitdata->value);
            }
        }

        foreach ($unit_datas as $id => $unit) {
            $obj = new StdClass;

            if (self::$_start_id == intval($id)) {
                $obj->cluster = '目標';
            } else if (self::isForeigh($unit->{'公司名稱'})) {
                $obj->cluster = '外商';
            } else {
                $obj->cluster = '公司';
            }
            $obj->id = count(self::$_nodes);
            $obj->funder = self::showFunder($unit->{'董監事名單'}, $unit->{'公司名稱'});
            $obj->amount = intval(str_replace(',', '', $unit->{'資本總額(元)'}));
            $obj->size = max(1, str_replace(',', '', $obj->amount) / 1000000);
            $obj->no = $id;
            $obj->text = str_replace('股份有限公司', '', $unit->{'公司名稱'});
            self::$_mappings['0-' . intval($id)] = $obj->id;
            self::$_nodes[] = $obj;

            // 往下塞入 query_pool
            foreach ($unit->{'董監事名單'} as $row) {
                $p = $row->{'所代表法人'};

                $from_company = '0-' . $id;
                $to_holder = null;
                if (is_array($p)) {
                    if ($p[0]) {
                        if (!$final) self::insertQueryPool(0, intval($p[0]));
                        $to_holder = '0-' . intval($p[0]);
                    } else {
                        if (!$final) self::insertQueryPool(1, $p[1]);
                        $to_holder = '1-' . $p[1];
                    }
                } elseif (is_scalar($p)) { // 沒統編, 用名子
                    if (strlen(trim($p))) {
                        $p = trim($p);
                        if (!$final) self::insertQueryPool(1, $p);
                        $to_holder = '1-' . $p;
                    }
                } else {
                    var_dump($row);
                    exit;
                }
                if (!is_null($to_holder)) {
                    self::$_edges[$from_company . ':' . $to_holder] = array($from_company, $to_holder);
                }
            }
        }

        // 往上
        foreach (CompanyGraph::search(1)->searchIn(array('board_type', 'board_id'), $query_pool) as $company_graph) {
            if (!$final) self::insertQueryPool(0, intval($company_graph->company_id));
            $from_company = '0-' . $company_graph->company_id;
            $to_holder = $company_graph->board_type . '-' . $company_graph->board_id;

            self::$_edges[$from_company . ':' . $to_holder] = array($from_company, $to_holder);
        }
    }

    protected static $_nodes = array();
    protected static $_mappings = array();
    protected static $_edges = array();
    protected static $_query_pool = array();
    protected static $_columns = null;

    public static function parseCompany($id, $depth = 3)
    {
        self::insertQueryPool(0, intval($id));

        if (is_null(self::$_columns)) {
            self::$_columns = array();
            foreach (ColumnGroup::search(1) as $columngroup) {
                self::$_columns[$columngroup->id] = $columngroup->name;
            }
        }

        for ($d = 0; $d < $depth; $d ++) {
            self::getDataFromQueryPool(false);
        }
        self::getDataFromQueryPool(true);
        self::getDataFromQueryPool(true);

        self::$_edges = array_map(function($edge) {
            return array_map(function($node) {
                if (!array_key_exists($node, self::$_mappings)) {
                    return null;
                }
                return self::$_mappings[$node];
            }, $edge);
        }, self::$_edges);
        self::$_edges = array_filter(self::$_edges, function($a) { return !is_null($a[0]) and !is_null($a[1]); });
    }

    public static function showFunder($funders, $company_name)
    {
        foreach ($funders as $row) {
            $ret .= trim(preg_replace('/\s/', '', $row->{'職稱'}));
            $ret .= ':' . trim($row->{'姓名'});
            if (is_array($row->{'所代表法人'})) {
                $ret .= '(所代表法人:';
                $ret .= str_replace('股份有限公司', '', implode(',', $row->{'所代表法人'}) . ')');
            } elseif ($row->{'所代表法人'}) {
                $ret .= '(所代表法人:';
                $ret .= $row->{'所代表法人'} . ')';
            }
            if (FALSE !== strpos($company_name, '股份有限公司')) {
                $ret .= ', 股份數:' . $row->{'出資額'};
            } else {
                $ret .= ', 出資額:' . $row->{'出資額'};
            }
            $ret .= "\n";
        }
        return $ret;
    }

    public static function getJSONFromID($id)
    {
        self::$_start_id = intval($id);
        self::parseCompany($id);
        $result = array(self::$_nodes, array_values(self::$_edges));
        return $result;
    }
}

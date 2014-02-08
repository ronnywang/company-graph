<?php

class GraphLib
{
    protected static $_start_id;

    protected static $_datas = array();

    protected static function findData($id)
    {
        if (!array_key_exists($id, self::$_datas)) {
            self::$_datas[$id] = Unit::find($id)->getData();
        }
        return self::$_datas[$id];
    }

    protected static $_nodes = array();
    protected static $_mappings = array();
    protected static $_edges = array();

    public static function parseCompany($id, $unit, $depth = 1)
    {
        if (array_key_exists(intval($id), self::$_mappings)) {
            return self::$_nodes[self::$_mappings[intval($id)]];
        }

        $obj = new StdClass;
        $obj->children = null;

        if (self::$_start_id == intval($id)) {
            $obj->cluster = 1;
        } else {
            $obj->cluster = 0;
        }
        $obj->id = count(self::$_nodes);
        $obj->count = 0;
        $obj->funder = self::showFunder($unit->{'董監事名單'}, $unit->{'公司名稱'});
        $obj->amount = $unit->{'資本總額(元)'};
        $obj->size = max(1, str_replace(',', '', $obj->amount) / 1000000);
        $obj->no = $id;
        $obj->text = str_replace('股份有限公司', '', $unit->{'公司名稱'});
        self::$_mappings[intval($id)] = $obj->id;
        self::$_nodes[] = $obj;

        if ($depth > 2) {
            return $obj;
        }

        // 往下
        foreach (CompanyGraph::search(array('board_type' => 0, 'board_id' => $obj->no)) as $company_graph) {
            $id = $company_graph->company_id;
            self::parseCompany($id, self::findData($id), $depth);
        }

        // 往上
        foreach ($unit->{'董監事名單'} as $row) {
            $p = $row->{'所代表法人'};

            if (is_array($p)) {
                if ($p[0] and $fund_unit = self::findData($p[0])) { // 有統編
                    $fund_obj = self::parseCompany($p[0], $fund_unit, $depth + 1);
                } else { // 沒統編, 用名子
                    $id = '0-' . crc32($p[1]);
                    if (!$fund_obj = self::$_nodes[self::$_mappings[$id]]) {
                        $fund_obj = new StdClass;
                        $fund_obj->children = null;
                        $fund_obj->cluster = 0;
                        $fund_obj->id = count(self::$_nodes);
                        $fund_obj->count = 0;
                        $fund_obj->amount = 0;
                        $fund_obj->size = 0;
                        $fund_obj->no = '0';
                        $fund_obj->text = $p[1];
                        self::$_mappings[$id] = $fund_obj->id;
                        self::$_nodes[] = $fund_obj;
                    }
                }
                self::$_edges[$fund_obj->id . '-' . $obj->id] = array($fund_obj->id, $obj->id);
            }
        }

        return $obj;
    }

    public function showFunder($funders, $company_name)
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
        if ($result = Cache::get($id)) {
            return json_decode($result);
        }

        self::$_start_id = intval($id);
        $data = self::findData($id);
        if (!$data) {
            return null;
        }
        self::parseCompany($id, $data);
        $result = array(self::$_nodes, array_values(self::$_edges));
        Cache::set($id, json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }
}

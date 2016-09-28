<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Feature extends Base
{
    protected $tableName = "shop_feature";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    protected function getSettingsFetch()
    {
        return [
            "select" => 'sf.*, tr.name name, sfg_tr.name name_group, m_tr.name measure',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_feature_group sfg',
                    "condition" => 'sfg.id = sf.id_group'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_feature_translate tr',
                    "condition" => 'tr.id_feature = sf.id'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_feature_group_translate sfg_tr',
                    "condition" => 'sfg_tr.id_group = sf.id_group'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_measure_translate m_tr',
                    "condition" => 'm_tr.id_measure = sf.id_measure'
                ]
            ]
        ];
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    private function getValues()
    {
        return (new FeatureValue())->fetchByIdFeature($this->input["id"]);
    }

    protected function getAddInfo()
    {
        $result["measure"] = (new Measure())->fetch();
        $result["values"] = $this->getValues();
        return $result;
    }

    private function saveValues()
    {
        if (!isset($this->input["values"]))
            return;

        try {
            $idFeature = $this->input["id"];
            $values = $this->input["values"];
            $idsStore = "";
            foreach ($values as $value) {
                if ($value["id"] > 0) {
                    if (!empty($idsStore))
                        $idsStore .= ",";
                    $idsStore .= $value["id"];
                    $u = new DB('shop_feature_value_list');
                    $u->setValuesFields($value);
                    $u->save();
                }
            }

            if (!empty($idsStore)) {
                $u = new DB('shop_feature_value_list');
                $u->where("id_feature = {$idFeature} AND NOT (id IN (?))", $idsStore)->deleteList();
            } else {
                $u = new DB('shop_feature_value_list');
                $u->where("id_feature = ?", $idFeature)->deleteList();
            }

            $data = array();
            foreach ($values as $value)
                if (empty($value["id"]) || ($value["id"] <= 0)) {
                    $data[] = array('id_feature' => $idFeature, 'value' => $value["value"], 'color' => $value["color"],
                        'sort' => (int) $value["sort"], 'image' => $value["image"]);
                }
            if (!empty($data))
                DB::insertList('shop_feature_value_list', $data);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить значения параметра!";
            throw new Exception($this->error);
        }
    }

    public function saveAddInfo()
    {
        $this->saveValues();
        return true;
    }
}

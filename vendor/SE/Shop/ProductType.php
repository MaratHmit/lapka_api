<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class ProductType extends Base
{
    protected $tableName = "shop_type";

    private function getFeatures() {

        try {
            $id = $this->input["id"];
            $result = [];

            $u = new DB('shop_type_feature', 'stf');
            $u->select("sf.target, stf.id_feature id, tr.name, sf.type, sf.sort, sm_tr.name measure");
            $u->innerJoin('shop_feature sf', 'sf.id = stf.id_feature');
            $u->leftJoin('shop_feature_translate tr', 'tr.id_feature = sf.id');
            $u->leftJoin('shop_measure_translate sm_tr', 'sm_tr.id_measure = sf.id_measure');
            $u->groupBy("sf.id");
            $u->where('stf.id_type = ?', $id);
            $items = $u->getList();
            foreach ($items as $item) {
                $item["values"] = $this->getValuesByIdFeature($item["id"]);
                $result[] = $item;
            }
            return $result;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список параметров типа!";
        }
    }

    private function getValuesByIdFeature($idFeature)
    {
        return (new FeatureValue())->fetchByIdFeature($idFeature);
    }

    protected function getAddInfo()
    {
        $result["features"] = $this->getFeatures();
        return $result;
    }

    protected function saveFeatures()
    {
        if (!isset($this->input["features"]))
            return true;

        try {
            DB::saveManyToMany($this->input["id"], $this->input["features"],
                array("table" => "shop_type_feature", "key" => "id_type", "link" => "id_feature"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить параметры типа!";
            throw new Exception($this->error);
        }
    }

    protected function saveAddInfo()
    {
        if (!$this->input["id"])
            return false;

        return $this->saveFeatures();
    }

}
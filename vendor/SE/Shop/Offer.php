<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Offer extends Base
{
    protected $tableName = "shop_offer";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    public function fetchByIdProduct($idProduct)
    {
        if (!$idProduct)
            return [];

        $this->setFilters(["field" => "idProduct", "value" => $idProduct]);
        return $this->fetch();
    }

    protected function getSettingsFetch()
    {
        return [
            "select" => 'so.*, sop.value price, 
                (SELECT SUM(sfs.value) FROM shop_warehouse_stock sfs WHERE sfs.id_offer = so.id GROUP BY sfs.id_offer) count,
                sod.weight weight,
                GROUP_CONCAT(CONCAT_WS("\t", sof.id, sof.id_feature, sft.name, sfv.id, sfv.value) SEPARATOR "\n") params',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_offer_price sop',
                    "condition" => "sop.id_offer = so.id AND sop.id_typeprice = {$_SESSION["idTypePrice"]}"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_offer_feature sof',
                    "condition" => 'sof.id_offer = so.id'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_feature_translate sft',
                    "condition" => "sft.id_feature = sof.id_feature"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_feature_value sfv',
                    "condition" => "sfv.id = sof.id_value"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_offer_dimension sod',
                    "condition" => "sod.id_offer = so.id"
                ]
            ]
        ];
    }

    protected function correctValuesBeforeFetch($items = [])
    {
        foreach ($items as &$item) {
            $item["params"] = $this->getParamsByStr($item["params"]);
        }
        return $items;
    }

    protected function saveAddInfo()
    {
        return $this->savePrices() && $this->saveCounts() && $this->saveFeatures() && $this->saveDimension();
    }

    private function getParamsByStr($params)
    {
        $result = array();
        $items = explode("\n", $params);
        foreach ($items as $item) {
            $param = array();
            $values = explode("\t", $item);
            $param["id"] = $values[0];
            $param["idFeature"] = $values[1];
            $param["name"] = $values[2];
            $param["idValue"] = $values[3];
            $param["value"] = $values[4];
            $result[] = $param;
        }
        return $result;
    }

    private function savePrices()
    {
        try {
            $data["idCurrency"] = $_SESSION['idCurrency'];
            $data["idTypeprice"] = $_SESSION['idTypePrice'];
            $data["idOffer"] = $this->input["id"];
            $t = new DB("shop_offer_price", "sop");
            $t->select("id");
            $t->where("id_offer = :idOffer AND id_typeprice = :idTypeprice AND id_currency = :idCurrency", $data);
            if ($result = $t->fetchOne())
                $data["id"] = $result["id"];
            $data["value"] = (real) $this->input["price"];
            $t = new DB("shop_offer_price", "sop");
            $t->setValuesFields($data);
            $t->save();
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить цены торгового предложения!";
        }
    }

    private function saveCounts()
    {
        try {
            $data["idWarehouse"] = $_SESSION['idWarehouse'];
            $data["idOffer"] = $this->input["id"];
            $t = new DB("shop_warehouse_stock", "sws");
            $t->select("id");
            $t->where("id_warehouse = :idWarehouse AND id_offer = :idOffer", $data);
            if ($result = $t->fetchOne())
                $data["id"] = $result["id"];
            $data["value"] = (real) $this->input["count"];
            $t = new DB("shop_warehouse_stock", "sws");
            $t->setValuesFields($data);
            $t->save();
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить количество торгового предложения!";
        }
    }

    private function saveFeatures()
    {
        try {
            $params = $this->input["params"];
            $idOffer = $this->input["id"];
            $idsExist = [];
            foreach ($params as $param)
                if ($param["id"])
                    $idsExist[] = $param["id"];
            $idsExistStr = implode(",", $idsExist);
            $t = new DB('shop_offer_feature', 'sof');
            $t->where("id_offer = ?", $idOffer);
            if ($idsExist)
                $t->andWhere("NOT id IN (?)", $idsExistStr);
            $t->deleteList();
            foreach ($params as $param) {
                if (!empty($param['idValue'])) {
                    $param["idOffer"] = $idOffer;
                    $t = new DB('shop_offer_feature', 'sof');
                    $t->setValuesFields($param);
                    $t->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить параметры торгового предложения!";
        }
    }

    private function saveDimension()
    {
        try {
            $data["idOffer"] = $this->input["id"];
            $t = new DB("shop_offer_dimension", "sod");
            $t->select("id");
            $t->where("id_offer = ?", $data["idOffer"]);
            if ($result = $t->fetchOne())
                $data["id"] = $result["id"];
            $data["weight"] = $this->input["weight"];
            $t = new DB("shop_offer_dimension", "sod");
            $t->setValuesFields($data);
            $t->save();
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить размеры и вес торгового предложения!";
        }
    }

}

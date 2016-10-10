<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Offer extends Base
{
    protected $tableName = "shop_offer";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";
    private $codePrice = "retail";

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
                GROUP_CONCAT(CONCAT_WS("\t", sof.id_feature, sft.name, sfv.id, sfv.value) SEPARATOR "\n") params',
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
        return $this->savePrices() && $this->saveCounts() && $this->saveFeatures();
    }

    private function getParamsByStr($params)
    {
        $result = array();
        $items = explode("\n", $params);
        foreach ($items as $item) {
            $param = array();
            $values = explode("\t", $item);
            $param["idFeature"] = $values[0];
            $param["name"] = $values[1];
            $param["idValue"] = $values[2];
            $param["value"] = $values[3];
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
            $data["value"] = $this->input["price"];
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
            $data["value"] = $this->input["count"];
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

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить параметры торгового предложения!";
        }
    }

}

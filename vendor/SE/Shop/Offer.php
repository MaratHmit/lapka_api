<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Offer extends Base
{
    protected $tableName = "shop_offer";
    private $codePrice = "retail";
    private $idTypePrice;

    public function fetchByIdProduct($idProduct)
    {
        if (!$idProduct)
            return [];

        $this->init();
        $this->setFilters(["field" => "idProduct", "value" => $idProduct]);
        return $this->fetch();
    }

    public function init()
    {
        if (empty($_SESSION["idTypePrice"])) {
            $t = new DB("shop_typeprice");
            $t->select("id");
            $t->where("code = '?'", $this->codePrice);
            $result = $t->fetchOne();
            $_SESSION["idTypePrice"] = $result["id"];
        }
        $this->idTypePrice = $_SESSION["idTypePrice"];
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
                    "condition" => "sop.id_offer = so.id AND sop.id_typeprice = {$this->idTypePrice}"
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


}

<?php

namespace SE\Shop;

class Review extends Base
{
    protected $tableName = "shop_review";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sr.*, sp_tr.name product_name, u.name user_name,
                DATE_FORMAT(sr.date, "%d.%m.%Y %H:%i") date_display',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'user u',
                    "condition" => 'u.id = sr.id_user'
                ),
                array(
                    "type" => "inner",
                    "table" => 'shop_product_translate sp_tr',
                    "condition" => 'sp_tr.id_product = sr.id_product'
                )
            )
        );
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    public function fetchByIdProduct($idProduct)
    {
        if (!$idProduct)
            return array();

        $this->setFilters(array("field" => "idProduct", "value" => $idProduct));
        return $this->fetch();
    }

    protected function correctValuesBeforeSave()
    {
        if (!empty($this->input["date"]))
            $this->input["date"] = date("Y-m-d H:i:s", strtotime($this->input["date"]));
    }

}

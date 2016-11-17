<?php

namespace SE\Shop;

class Comment extends Base
{
    protected $tableName = "shop_commentary";

    protected function getSettingsFetch()
    {
        return [
            "select" => 'sc.*, spt.name product_name, 
                IF(sc.id_user IS NULL, sc.name, su.name) user_name,
                IF(sc.id_user IS NULL, sc.email, su.email) user_email,
                DATE_FORMAT(sc.date, "%d.%m.%Y %H:%i") date_display',
            "joins" => [
                [
                    "type" => "inner",
                    "table" => 'shop_product_translate spt',
                    "condition" => 'spt.id_product = sc.id_product'
                ],
                [
                    "type" => "left",
                    "table" => 'user su',
                    "condition" => 'su.id = sc.id_user'
                ]
            ]
        ];
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    public function fetchByIdProduct($idProduct)
    {
        if (!$idProduct)
            return [];

        $this->setFilters(["field" => "idProduct", "value" => $idProduct]);
        return $this->fetch();
    }

    protected function correctValuesBeforeSave()
    {
        if (!empty($this->input["date"]))
            $this->input["date"] = date("Y-m-d H:i:s", strtotime($this->input["date"]));
    }
}

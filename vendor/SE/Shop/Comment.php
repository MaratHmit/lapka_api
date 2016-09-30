<?php

namespace SE\Shop;

class Comment extends Base
{
    protected $tableName = "shop_commentary";

    protected function getSettingsFetch()
    {
        return [
            "select" => 'sc.*, spt.name name_product, sc.created_at `date`',
            "joins" => [
                "type" => "inner",
                "table" => 'shop_product_translate spt',
                "condition" => 'spt.id_product = sc.id_product'
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
}

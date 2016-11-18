<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Label extends Base
{
    protected $tableName = "shop_label";

    protected function getAddInfo()
    {
        $result = [];
        $result["products"] = $this->getProducts();
        return $result;
    }

    protected function saveAddInfo()
    {
        return $this->saveProducts();
    }

    private function getProducts()
    {
        $result = [];
        $id = $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_product_translate', 'spt');
        $u->select('spt.id_product id, spt.name');
        $u->innerJoin('shop_label_product slp', 'slp.id_product = spt.id_product');
        $u->where('slp.id_label = ?', $id);
        return $u->getList();
    }

    private function saveProducts()
    {
        if (!isset($this->input["products"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["products"],
                    array("table" => "shop_label_product", "key" => "id_label", "link" => "id_product"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить товары для метки!";
            throw new Exception($this->error);
        }
    }

}

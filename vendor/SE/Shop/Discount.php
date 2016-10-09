<?php

namespace SE\Shop;

use SE\DB as seTable;
use SE\Exception;

class Discount extends Base
{
    protected $tableName = "shop_discount";

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
            "select" => 'sd.*, sdl.id_product idProduct',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_discount_link sdl',
                    "condition" => 'sdl.id_discount = sd.id'
                ]
            ]
        ];
    }

    protected function getAddInfo()
    {
        $result["listGroupsProducts"] = $this->getListGroupsProducts($this->result["id"]);
        $result["listProducts"] = $this->getListProducts($this->result["id"]);
        $result['listContacts'] = $this->getListContacts($this->result["id"]);
        return $result;
    }

    private function getListProducts($id) {
        try {
            $u = new seTable('shop_discount_link', 'sdl');
            $u->select('sp.id, sp.code, sp.article, sp.name, sp.price, sp.curr');
            $u->innerJoin("shop_price sp", "sdl.id_price = sp.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("sp.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список товаров скидки!";
        }
    }

    private function getListGroupsProducts($id) {
        try {
            $u = new seTable('shop_discount_link', 'sdl');
            $u->select('sg.id, sg.code_gr, sg.name');
            $u->innerJoin("shop_group sg", "sdl.id_group = sg.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("sg.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп товаров скидки!";
        }
    }

    private function getListContacts($id) {
        try {
            $u = new seTable('shop_discount_link', 'sdl');
            $u->select('p.id, p.first_name, p.sec_name, p.last_name, p.email');
            $u->innerJoin("person p", "sdl.id_user = p.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("p.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список контактов скидки!";
        }
    }
}

<?php

namespace SE\Shop;

use SE\DB as DB;
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
        $result["categories"] = $this->getListGroupsProducts($this->result["id"]);
        $result["products"] = $this->getListProducts($this->result["id"]);
        $result['users'] = $this->getListContacts($this->result["id"]);
        return $result;
    }

    protected function saveAddInfo()
    {
        return $this->saveGroups() && $this->saveProducts() && $this->saveContacts();
    }

    private function getListProducts($id) {
        try {
            $u = new DB('shop_discount_link', 'sdl');
            $u->select('spt.id_product id, spt.name');
            $u->innerJoin("shop_product_translate spt", "sdl.id_product = spt.id_product");
            $u->where("sdl.id_discount = $id");
            $u->groupBy("spt.id_product");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список товаров скидки!";
        }
    }

    private function getListGroupsProducts($id) {
        try {
            $u = new DB('shop_discount_link', 'sdl');
            $u->select('sgt.id_group id, sgt.name');
            $u->innerJoin("shop_group_translate sgt", "sdl.id_group = sgt.id_group");
            $u->where("sdl.id_discount = $id");
            $u->groupBy("sgt.id_group");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп товаров скидки!";
        }
    }

    private function getListContacts($id) {
        try {
            $u = new DB('shop_discount_link', 'sdl');
            $u->select('u.*');
            $u->innerJoin("user u", "sdl.id_user = u.id");
            $u->where("sdl.id_discount = $id");
            $u->groupBy("u.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список контактов скидки!";
        }
    }

    private function saveContacts()
    {
        if (!isset($this->input["users"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["users"],
                    array("table" => "shop_discount_link", "key" => "id_discount", "link" => "id_user"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить пользователей скидки!";
            throw new Exception($this->error);
        }
    }

    private function saveProducts()
    {
        if (!isset($this->input["products"]))
            return true;

        try {
            writeLog($this->input);
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["products"],
                    array("table" => "shop_discount_link", "key" => "id_discount", "link" => "id_product"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить товары скидки!";
            throw new Exception($this->error);
        }
    }

    private function saveGroups()
    {
        if (!isset($this->input["categories"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["categories"],
                    array("table" => "shop_discount_link", "key" => "id_discount", "link" => "id_group"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить категории товаров дял скидки!";
            throw new Exception($this->error);
        }
    }
}

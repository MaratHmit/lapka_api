<?php

namespace SE\Shop;

use SE\DB;

class Brand extends Base
{
    protected $tableName = "shop_brand";

    public function getIdByName($name)
    {
        $t = new DB("shop_brand", "sb");
        $t->select("sb.id");
        $t->innerJoin("shop_brand_translate sbt", "sbt.id_brand = sb.id");
        $t->where("LOWER(sbt.name) LIKE '?'", strtolower(trim($name)));
        $result = $t->fetchOne();
        if (empty($result["id"])) {
            $data = ["name" => $name];
            $b = new Brand($data);
            $result = $b->save(false)->result;
        }
        return !empty($result["id"]) ? $result["id"] : null;
    }
}

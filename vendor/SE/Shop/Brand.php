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
        $t->where("sbt.name = '?'", $name);
        $result = $t->fetchOne();
        return !empty($result["id"]) ? $result["id"] : null;
    }
}

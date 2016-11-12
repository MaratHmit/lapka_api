<?php

namespace SE\Shop;

use SE\DB as DB;

class ImportProfile extends Base
{
    protected $tableName = "import_profile";

    public function saveByName()
    {
        $t = new DB("import_profile", "ip");
        $t->select("id");
        $t->where("name = '?'", $this->input["name"]);
        $result = $t->fetchOne();
        if (!empty($result["id"]))
            $this->input["id"] = $result["id"];
        return $this->save();
    }
}
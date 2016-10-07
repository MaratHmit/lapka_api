<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception as Exception;

class Service extends Base
{
    protected $tableName = "service";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    protected function getAddInfo()
    {
        return ["parameters" => $this->getParameters()];
    }

    private function getParameters()
    {
        $id = $this->input["id"];
        $t = new DB("service_parameter", "sp");
        $t->where("sp.id_service = ?", $id);
        return $t->getList();
    }

    protected function saveAddInfo()
    {
        return $this->saveParameters();
    }

    private function saveParameters()
    {
        try {
            $parameters = $this->input["parameters"];
            foreach ($parameters as $parameter) {
                $t = new DB("service_parameter", "sp");
                $t->setValuesFields($parameter);
                $t->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удается сохранить параметры!";
        }
        return false;
    }

}
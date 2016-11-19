<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Pet extends Base
{
    protected $sortOrder = "asc";
    protected $tableName = "pet";

    protected function getAddInfo()
    {
        $result["calculates"] = $this->getCalculates();
        return $result;
    }

    protected function saveAddInfo()
    {
        return $this->saveCalculates();
    }

    private function saveCalculates()
    {
        if (!isset($this->input["calculates"]))
            return true;

        $calculates = $this->input["calculates"];
        try {
            foreach ($calculates as $calculate) {
                $calculate["idPet"] = $this->input["id"];
                $petCalculate = new DB("pet_food_calculate", "pfc");
                $petCalculate->setValuesFields($calculate);
                $petCalculate->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удается сохранить формулы для возрастных групп!";
            throw new Exception($this->error);
        }
    }

    private function getCalculates()
    {
        $t = new DB("pet_food_calculate", "pfc");
        $t->where("pfc.id_pet = ?", $this->input["id"]);
        return $t->getList();
    }
}

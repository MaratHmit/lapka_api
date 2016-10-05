<?php

namespace SE\Shop;

use SE\DB as DB;

class Notice extends Base
{
    protected $tableName = "notice";

    public function getAddInfo()
    {
        return ["triggers" => $this->getTriggers()];
    }

    protected function correctValuesBeforeSave()
    {
        if (isset($this->input["idTrigger"]) && empty($this->input["idTrigger"]))
            $this->input["idTrigger"] = null;
    }

    private function getTriggers()
    {
        $result = [];
        $triggers = (new Trigger())->fetch();
        $u = new DB("notice_trigger");
        $u->select("id_trigger");
        $items = $u->getList();
        foreach ($triggers as $trigger) {
            $isChecked = false;
            foreach ($items as $item)
                if ($isChecked = ($trigger["id"] == $item["idTrigger"]))
                    break;
            $trigger["isChecked"] = $isChecked;
            $result[] = $trigger;
        }
        return $result;
    }
}
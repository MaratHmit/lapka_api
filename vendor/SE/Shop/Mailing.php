<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Mailing extends Base
{
    protected $tableName = "mailing";


    protected function getSettingsFetch()
    {
        return array(
            "select" => 'm.*,
                DATE_FORMAT(m.sender_date, "%d.%m.%Y %H:%i") sender_date_display'
        );
    }

    protected function correctValuesBeforeSave()
    {
        if (!empty($this->input["senderDate"]))
            $this->input["senderDate"] = date("Y-m-d H:i:s", strtotime($this->input["senderDate"]));
    }

    protected function getAddInfo()
    {
        return ["userGroups" => $this->getUserGroups()];
    }

    protected function saveAddInfo()
    {
        return $this->saveUserGroups();
    }

    private function getUserGroups()
    {
        $result = [];
        try {
            $groups = (new UserGroup())->fetch();
            $u = new DB("mailing_usergroup");
            $u->select("id_group");
            $u->where("id_mailing = ?", $this->input["id"]);
            $items = $u->getList();
            foreach ($groups as $group) {
                $isChecked = false;
                foreach ($items as $item)
                    if ($isChecked = ($group["id"] == $item["idGroup"]))
                        break;
                $group["isChecked"] = $isChecked;
                $result[] = $group;
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп пользователей для рассылки!";
            throw  new Exception($this->error);
        }
        return $result;
    }

    private function saveUserGroups()
    {
        $groups = $this->input["userGroups"];
        $groupNew = [];
        foreach ($groups as $group)
            if ($group["isChecked"])
                $groupNew[] = $group;
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $groupNew,
                    array("table" => "mailing_usergroup", "key" => "id_mailing", "link" => "id_group"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить группы пользоваталей для рассылки!";
            throw new Exception($this->error);
        }
    }

}

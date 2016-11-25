<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class UserGroup extends Base
{
    protected $tableName = "usergroup";

    protected function getSettingsFetch()
    {
        return [
            "select" => 'u.id, u.name, ue.id_sendpulse',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'usergroup_exchange ue',
                    "condition" => 'ue.id_group = u.id'
                ]
            ]
        ];
    }

    protected function saveAddInfo()
    {
        return $this->saveServiceInfo();
    }

    private function saveServiceInfo()
    {
        try {
            $service = new Service();
            $service->saveUserGroupSettings($this->input["id"], $this->input["name"]);
            return true;
        } catch (Exception $e) {
            $this->error = 'Не удаётся сохранить сервисную инфорамцию о группе!';
            throw new Exception($this->error);
        }
    }

}
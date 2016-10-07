<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception as Exception;
use TelegramBot\Api\BotApi as Telegram;

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

    private function testTelegram()
    {
        $userId = '262003605';
        $t = new DB('service_parameter', 'sp');
        $t->select('sp.value');
        $t->where('sp.code = "token" AND sp.id_service = ?', 3);
        $token = $t->fetchOne()["value"];
        $telegram = new Telegram($token);
        $telegram->sendMessage($userId, "Тест");
    }

}
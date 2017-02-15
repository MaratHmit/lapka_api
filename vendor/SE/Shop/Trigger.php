<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;
use TelegramBot\Api\BotApi;

class Trigger extends Base
{
    protected $tableName = "trigger";

    public function exec($event = null, $options = null)
    {
        if ($options) {
            $temp = [];
            foreach ($options as $key => $val)
                $temp[DB::strToCamelCase($key)] = $val;
            $options = $temp;
        }

        $event = empty($event) ? $this->input["event"] : $event;
        $options = empty($options) ? $this->input["options"] : $options;
        $notices = $this->getNotices($event);
        foreach ($notices as $notice)
            $this->notify($notice, $options);
    }

    public function getNotices($event)
    {
        try {
            $t = new DB("notice", "n");
            $t->select("n.*, nt.name, nt.subject, nt.content");
            $t->innerJoin("notice_translate nt", "nt.id_notice = n.id");
            $t->innerJoin("notice_trigger ntr", "ntr.id_notice = n.id");
            $t->innerJoin("`trigger` t", "t.id = ntr.id_trigger");
            $t->where("t.code = '?'", $event);
            $t->andWhere("n.is_active");
            $t->groupBy("n.id");

            return $t->getList();
        } catch (Exception $e) {
            $this->error = "Не удается прочитать уведомления!";
            throw new Exception($this->error);
        }
    }

    private function notify($notice, $options = array())
    {
        $macros = new MacrosHandler($options);
        foreach ($notice as $key => &$value)
            $value = $macros->exec($value);

        $result = false;
        $t = new DB("notice_log");
        $t->setValuesFields(["sender" => $notice['sender'],
            "recipient" => $notice['recipient'],
            "target" => $notice['target'],
            "content" => $notice['content']
        ]);
        $idNotice = $t->save();
        if ($idNotice) {
            switch ($notice['target']) {
                case 'email':
                    $result = (new Email($notice['subject'], $notice['recipient'], $notice['sender'], $notice['content']))->send();
                    break;
                case 'sms':
                    $result = $this->sendBySms($notice['recipient'], $notice['sender'], strip_tags($notice['content']));
                    break;
                case 'telegram':
                    $result = $this->sendByTelegram($notice['recipient'], strip_tags($notice['content']));
                    break;
            }
        }
        return $result;
    }

    private function sendByTelegram($target, $text)
    {
        $t = new DB('service', 's');
        $t->select('sp.code, sp.value');
        $t->innerJoin('service_parameter sp', 'sp.id_service = s.id');
        $t->where('s.code = "?"', "telegram");
        $items = $t->getList();
        $settings = array();
        foreach ($items as $item)
            $settings[$item['code']] = $item['value'];

        $telegram = new BotApi($settings["token"]);
        $targets = explode(";", $target);
        foreach ($targets as $target)
            $telegram->sendMessage(trim($target), $text);
    }

    private function sendBySms($target, $sender, $text)
    {

    }
}
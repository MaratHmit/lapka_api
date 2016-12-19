<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class NoticeLog extends Base
{
    protected $tableName = "notice_log";

    public function fetch()
    {
        $t = new DB("notice_log", "nl");
        $t->select("nl.*, DATE_FORMAT(nl.created_at, '%d.%m.%Y %H:%i') date_display");
        $items = $t->getList($this->limit, $this->offset);
        $count = $t->getListCount();
        foreach ($items as &$item)
            $item["content"] = strip_tags($item["content"]);
        $this->result["items"] = $items;
        $this->result["count"] = $count;
        return $items;
    }

}
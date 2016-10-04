<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class News extends Base
{
    protected $tableName = "news";

    protected function getSettingsInfo()
    {
        return [
            "select" => 'n.*, tr.name, tr.content, tr.description, ng.name nameGroup',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'news_group ng',
                    "condition" => 'ng.id = n.id_group'
                ],
                [
                    "type" => "left",
                    "table" => 'news_translate tr',
                    "condition" => 'tr.id_news = n.id'
                ]
            ]
        ];
    }

}

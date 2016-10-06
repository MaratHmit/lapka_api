<?php

namespace SE\Shop;

class Setting extends Base
{
    protected $tableName = "shop_setting";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    protected function getSettingsFetch()
    {
        return [
            "select" => 'ss.*, ssv.id id_value, ssv.value',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_setting_value ssv',
                    "condition" => 'ssv.id_setting = ss.id'
                ]
            ]
        ];
    }
}

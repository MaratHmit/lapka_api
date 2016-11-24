<?php

namespace SE\Shop;

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


}

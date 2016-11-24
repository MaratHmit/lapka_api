<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class PaySystem extends Base
{
    protected $tableName = "payment_system";
    protected $sortOrder = "asc";

    protected function saveAddInfo()
    {
        if (!empty($this->input["code"])) {
            $scriptPlugin = 'http://' . HOSTNAME . "/lib/merchant/result.php?payment=" . $this->input["code"];
            file_get_contents($scriptPlugin);
        }
        return $this->saveParams();
    }

    private function getParams()
    {
        $idPayment = $this->input["id"];
        $u = new DB('payment_system_secret', 'pss');
        $u->where('pss.id_system = ?', $idPayment);
        return $u->getList();
    }

    protected function getAddInfo()
    {
        $result["params"] = $this->getParams();
        $result["plugins"] = PaySystemPlugin::getPlugins();
        return $result;
    }

    private function saveParams()
    {
        try {
            $params = $this->input["params"];
            foreach ($params as $p) {
                $u = new DB("payment_system_secret", "pss");
                $u->setValuesFields(array('id' => $p["id"], 'value' => $p["value"]));
                $u->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить параметры платежной системы!";
            throw new Exception($this->error);
        }
    }

}
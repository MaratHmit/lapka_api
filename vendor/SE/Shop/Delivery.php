<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Delivery extends Base
{
    protected $tableName = "shop_delivery";
    protected $sortOrder = "asc";

    protected function getAddInfo()
    {
        return ["points" => $this->getPoints(), "periods" => $this->getPeriods() ];
    }

    protected function saveAddInfo()
    {
        return $this->savePoints() && $this->savePeriods();
    }

    private function getPoints()
    {
        try {
            $t = new DB("shop_delivery_point", "sdp");
            $t->select("sdp.id, sdp.address");
            return $t->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить пункты самовывоза!";
            return null;
        }
    }

    private function getPeriods()
    {
        try {
            $t = new DB("shop_delivery_period", "sdp");
            $t->select("sdp.id, sdp.name");
            return $t->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить периоды доставок!";
            return null;
        }
    }

    private function savePoints()
    {
        try {
            $points = $this->input["points"];

            $ids = [];
            foreach ($points as $point)
                if (!empty($point["id"]))
                    $ids[] = $point["id"];
            $idsStr = implode(",", $ids);
            $t = new DB("shop_delivery_point", "sdp");
            if ($idsStr)
                $t->where("NOT id IN ({$idsStr})");
            $t->deleteList();

            foreach ($points as $point) {
                $t = new DB("shop_delivery_point", "sdp");
                $t->setValuesFields($point);
                $t->save();
            }

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить пункты самовывоза!";
            throw new Exception($this->error);
        }
    }

    private function savePeriods()
    {
        try {
            $periods = $this->input["periods"];

            $ids = [];
            foreach ($periods as $period)
                if (!empty($period["id"]))
                    $ids[] = $period["id"];
            $idsStr = implode(",", $ids);
            $t = new DB("shop_delivery_period", "sdp");
            if ($idsStr)
                $t->where("NOT id IN ({$idsStr})");
            $t->deleteList();

            foreach ($periods as $period) {
                $t = new DB("shop_delivery_period", "sdp");
                $t->setValuesFields($period);
                $t->save();
            }

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить периоды для доставки!";
            throw new Exception($this->error);
        }

    }

}
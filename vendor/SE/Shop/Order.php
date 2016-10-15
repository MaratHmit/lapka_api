<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Order extends Base
{
    protected $tableName = "shop_order";

    public static function fetchByCompany($idCompany)
    {
        return (new Order(array("filters" => array("field" => "idCompany", "value" => $idCompany))))->fetch();
    }

    public static function checkStatusOrder($idOrder, $paymentType = null)
    {
        $u = new DB('shop_order', 'so');
        $u->select('(SUM((st.price - IFNULL(st.discount, 0)) * st.count) - IFNULL(so.discount, 0) +
                IFNULL(so.delivery_payee, 0)) sum_order');
        $u->innerJoin('shop_tovarorder st', 'st.id_order = so.id');
        $u->where('so.id = ?', $idOrder);
        $u->groupBy('so.id');
        $result = $u->fetchOne();
        $sumOrder = $result["sumOrder"];

        $u = new DB('shop_order_payee', 'sop');
        $u->select('SUM(sop.amount) sum_payee, MAX(sop.date) date_payee');
        $u->where(' sop.id_order = ?', $idOrder);
        $result = $u->fetchOne();
        $sumPayee = $result['sumPayee'];
        $datePayee = $result['datePayee'];

        if ($sumPayee >= $sumOrder) {
            $u = new DB('shop_order', 'so');
            $data["status"] = "Y";
            $data["isDelete"] = "N";
            $data["datePayee"] = $datePayee;
            if ($paymentType)
                $data["paymentType"] = $paymentType;
            $data["id"] = $idOrder;
            $u->setValuesFields($data);
            $u->save();
        };
    }

    public function info($id = null)
    {
        $result = parent::info($id);
        if (!$result) {
            $t = new DB("shop_order", "so");
            $t->select("MAX(id) max_id, MAX(num) max_num");
            $result = $t->fetchOne();
            $this->result["maxId"] = (int) $result["maxId"];
            $this->result["maxNum"] = (int) $result["maxNum"];
            return $result;
        }
    }

    protected function getSettingsFetch()
    {
        return [
            "select" => 'so.*, DATE_FORMAT(so.date, "%d.%m.%Y %H:%i") date_display,  
                u.name customer, u.phone customer_phone, u.email customer_email, 
                SUM((soi.price - soi.discount) * soi.count - so.discount) amount',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'user u',
                    "condition" => 'u.id = so.id_user'
                ],
                [
                    "type" => "inner",
                    "table" => 'shop_order_item soi',
                    "condition" => 'soi.id_order = so.id'
                ]
            ],
            "aggregation" => [
                "type" => "SUM",
                "field" => "amount",
                "name" => "totalAmount"
            ]
        ];
    }

    protected function getSettingsInfo()
    {
        return [
            "select" => 'so.*, DATE_FORMAT(so.date, "%d.%m.%Y %H:%i") date_display,  
                u.name customer, u.phone customer_phone, u.email customer_email, 
                SUM((soi.price - soi.discount) * soi.count) sum_products,
                0 sum_delivery',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'user u',
                    "condition" => 'u.id = so.id_user'
                ],
                [
                    "type" => "inner",
                    "table" => 'shop_order_item soi',
                    "condition" => 'soi.id_order = so.id'
                ]
            ]
        ];
    }

    protected function getAddInfo()
    {
        $result = array();
        $this->result["amount"] = (real) $this->result["amount"];
        $result["items"] = $this->getOrderItems();
        $result['payments'] = $this->getPayments();
//        $result['customFields'] = $this->getCustomFields($this->input["id"]);
//        $result['paid'] = $this->getPaidSum();
//        $result['surcharge'] = $this->result["amount"] - $result['paid'];
        return $result;
    }

    private function getPaidSum()
    {
        $idOrder = $this->result["id"];
        $u = new DB('shop_order_payee', 'sop');
        $u->select('SUM(amount) amount');
        $u->where("sop.id_order = ?", $idOrder);
        $result = $u->fetchOne();
        return (real)$result['amount'];
    }

    private function getOrderItems()
    {
        $result = [];
        $idOrder = $this->result["id"];
        $u = new DB('shop_order_item', 'soi');
        $u->select('soi.*, so.article, spt.name,
            GROUP_CONCAT(CONCAT_WS(": ", sft.name, sfv.value) SEPARATOR ", ") name_params');
        $u->leftJoin('shop_offer so', 'so.id = soi.id_offer');
        $u->leftJoin('shop_product_translate spt', 'spt.id_product = so.id_product');
        $u->leftJoin('shop_offer_feature sof', 'sof.id_offer = so.id');
        $u->leftJoin('shop_feature_translate sft', 'sft.id_feature = sof.id_feature');
        $u->leftJoin('shop_feature_value sfv', 'sfv.id = sof.id_value');
        $u->where("id_order = ?", $idOrder);
        $u->groupBy('soi.id');
        $items = $u->getList();
        foreach ($items as $item) {
            if ($item["nameParams"])
                $item["name"] .= " - {$item["nameParams"]}";
            $result[] = $item;
        }
        return $result;
    }

    private function getPayments()
    {
        return (new Payment())->fetchByOrder($this->input["id"]);
    }

    private function getCustomFields($idOrder)
    {
        $u = new DB('shop_userfields', 'su');
        $u->select("sou.id, sou.id_order, sou.value, su.id id_userfield, 
                    su.name, su.type, su.values, sug.id id_group, sug.name name_group");
        $u->leftJoin('shop_order_userfields sou', "sou.id_userfield = su.id AND id_order = {$idOrder}");
        $u->leftJoin('shop_userfield_groups sug', 'su.id_group = sug.id');
        $u->where('su.data = "order"');
        $u->groupBy('su.id');
        $u->orderBy('sug.sort');
        $u->addOrderBy('su.sort');
        $result = $u->getList();

        $groups = array();
        foreach ($result as $item) {
            $key = (int)$item["idGroup"];
            $group = key_exists($key, $groups) ? $groups[$key] : array();
            $group["id"] = $item["idGroup"];
            $group["name"] = empty($item["nameGroup"]) ? "Без категории" : $item["nameGroup"];
            if ($item['type'] == "date")
                $item['value'] = date('Y-m-d', strtotime($item['value']));
            if (!key_exists($key, $groups))
                $groups[$key] = $group;
            $groups[$key]["items"][] = $item;
        }
        return array_values($groups);
    }

    protected function correctValuesBeforeSave()
    {
        $t = new DB("shop_order", "so");
        $t->select("so.num");
        $t->where("so.num = ?", $this->input["num"]);
        if (!empty($this->input["id"]))
            $t->andWhere("so.id <> ?", $this->input["id"]);
        $result = $t->fetchOne();
        if ($result) {
            $this->error = "Заказ № " . $result["num"] . " уже существует!";
            return false;
        }

        if (empty($this->input["idCurrency"]))
            $this->input["idCurrency"] = $_SESSION["idCurrency"];
        if (empty($this->input["id"]) && empty($this->input["date"]))
            $this->input["date"] = date("Y-m-d H:i:s");
        return true;
    }

    protected function saveAddInfo()
    {
//        $this->saveDelivery();
//        $this->savePayments();
//        $this->saveCustomFields();
        return $this->saveItems();
    }

    private function saveItems()
    {
        try {
            $idOrder = $this->input["id"];
            $offers = $this->input["items"];
            $idsUpdate = null;
            foreach ($offers as $p)
                if ($p["id"]) {
                    if (!empty($idsUpdate))
                        $idsUpdate .= ',';
                    $idsUpdate .= $p["id"];
                }

            $u = new DB('shop_order_item', 'soi');
            if (!empty($idsUpdate))
                $u->where('NOT `id` IN (' . $idsUpdate . ') AND id_order = ?', $idOrder)->deleteList();
            else $u->where('id_order = ?', $idOrder)->deleteList();

            // новые товары/услуги заказа
            foreach ($offers as $p) {
                if (!$p["id"]) {
                    $data[] = array('id_order' => $idOrder, 'id_offer' => $p["idOffer"],
                        'price' => (float)$p["price"], 'discount' => (float)$p["discount"], 'count' => (float)$p["count"]);
                } else {
                    $u = new DB('shop_order_item', 'soi');
                    $u->setValuesFields($p);
                    $u->save();
                }
            }
            if (!empty($data))
                DB::insertList('shop_order_item', $data);
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить товары заказа!";
            throw new Exception($this->error);
        }
    }

    private function saveCustomFields()
    {
        if (!isset($this->input["customFields"]))
            return true;

        try {
            $idOrder = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = array();
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $field["idOrder"] = $idOrder;
                $u = new DB('shop_order_userfields', 'cu');
                $u->setValuesFields($field);
                $u->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доп. информацию о заказе!";
            throw new Exception($this->error);
        }
    }

    private function saveDelivery()
    {
        $input = $this->input;
        unset($input["ids"]);
        $idOrder = $input["id"];
        $p = new DB('shop_delivery', 'sd');
        $p->select("id");
        $p->where('id_order = ?', $idOrder);
        $result = $p->fetchOne();
        if ($result["id"])
            $input["id"] = $result["id"];
        $u = new DB('shop_delivery', 'sd');
        $u->setValuesFields($input);
        $u->save();
    }

    private function savePayments()
    {
        $payments = $this->input["payments"];
    }

    public function delete()
    {
        try {
            $input = $this->input;
            $input["isDelete"] = "Y";
            $u = new DB('shop_order', 'so');
            $u->setValuesFields($input);
            $u->save();
        } catch (Exception $e) {
            $this->error = "Не удаётся отменить заказ!";
        }
    }

}

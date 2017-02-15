<?php

namespace SE\Shop;

use SE\DB as DB;

class MacrosHandler extends Base
{
    public $user;
    public $order;

    private $currency;
    private $language;

    function __construct($input = null)
    {
        parent::__construct($input);

        $this->language = 'rus';
        if (!empty($input['id_order'])) {
            $this->order = $this->getOrderById($input['id_order']);
            $input['id_user'] = empty($input['id_user']) ? $this->order['id_user'] : $input['id_user'];
        }
        if (!empty($input['id_user']))
            $this->user = $this->getUserById($input['id_user']);
    }

    public function exec($text)
    {
        $text = $this->parseSystem($text);
        $text = $this->parseShopSettings($text);
        $text = $this->parseUser($text);
        $text = $this->parseCurrency($text);
        $text = $this->parseOrder($text);
        $text = $this->parseOptions($text);

        $text = preg_replace('/\[(.+?)\]/i', '', $text);
        return $text;
    }

    public function getUserById($id)
    {
        if (empty($id))
            return null;

        $u = new DB('user', 'u');
        $u->where('id = ?', $id);
        return $u->fetchOne();
    }

    public function getOrderById($id)
    {
        if (empty($id))
            return null;

        $u = new DB('shop_order', 'so');
        $u->where('id = ?', $id);
        $order = $u->fetchOne();

        $u = new DB('shop_order_item', 'soi');
        $u->select('soi.*, so.article, spt.name,
            GROUP_CONCAT(CONCAT_WS(": ", sft.name, sfv.value) SEPARATOR ", ") name_params');
        $u->leftJoin('shop_offer so', 'so.id = soi.id_offer');
        $u->leftJoin('shop_product_translate spt', 'spt.id_product = so.id_product');
        $u->leftJoin('shop_offer_feature sof', 'sof.id_offer = so.id');
        $u->leftJoin('shop_feature_translate sft', 'sft.id_feature = sof.id_feature');
        $u->leftJoin('shop_feature_value sfv', 'sfv.id = sof.id_value');
        $u->where("id_order = ?", $order["id"]);
        $u->groupBy('soi.id');

        $orderItems = [];
        $items = $u->getList();
        foreach ($items as $item) {
            if ($item["nameParams"])
                $item["name"] .= " - {$item["nameParams"]}";
            $orderItems[] = $item;
        }
        $order["items"] = $orderItems;

        return $order;
    }

    public function sumToString($sum)
    {
        $sum = se_formatMoney($sum, $this->currency, '');
        $sel = "zero,one,two,three,four,five,six,seven,eight,nine";
        $reg = array('edin', 'dec', 'des', 'sot', 'mel', 'thou', 'mill', 'wh', 'fr');
        foreach ($reg as $r) {
            $d[$r] = se_db_fields_item('word_number', "registr='$r'", $sel);
        }

        $sum = str_replace(array(' ', ','), array('', '.'), $sum);
        $des = explode('.', $sum);

        $c = utf8_strlen($des[0]);
        for ($i = 1; $i <= $c; $i++) {
            $numbers[$i] = utf8_substr($des[0], $c - $i, 1);
        }
        $result = '';
        if ($numbers[7] != '') $result .= $d['mill'][$numbers[7]] . ' ';
        if ($numbers[6] != '') $result .= $d['sot'][$numbers[6]] . ' ';
        if ($numbers[5] != '' && $numbers[5] != 1) $result .= $d['dec'][$numbers[5]] . ' ';
        if ($numbers[5] != '' && $numbers[5] == 1) $result .= $d['des'][$numbers[4]] . ' ' . $d['thou'][0] . " ";
        if ($numbers[4] != '' && $numbers[5] != 1) $result .= $d['mel'][$numbers[4]] . ' ' . $d['thou'][$numbers[4]] . " ";


        if ($numbers[3] != '') $result .= $d['sot'][$numbers[3]] . " ";
        if ($numbers[2] != '' && $numbers[2] != 1) $result .= $d['dec'][$numbers[2]] . " ";
        if ($numbers[2] != '' && $numbers[2] == 1) $result .= $d['des'][$numbers[1]] . " ";
        if ($numbers[1] != '' && $numbers[2] != 1) $result .= $d['edin'][$numbers[1]] . " ";
        if (!empty($result)) $result = $result . $d['wh'][0] . " ";
        $kop = $des[1];
        if (!empty($kop)) {
            while (utf8_strlen($kop) < 2)
                $kop .= "0";
            $result .= $kop . " " . $d['fr'][0];
        }

        $result = '<span style="Text-transform:uppercase;">' . utf8_substr($result, 0, 1) . '</span>' .
            utf8_substr($result, 1, utf8_strlen($result) - 1);
        return $result;
    }

    public function getMonth($m)
    {
        if ($this->language == 'rus' || $this->language == 'blr')
            $month = array('января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября',
                'октября', 'ноября', 'декабря');
        else
            $month = array('January', ' February', 'March', 'April', 'May', 'June', 'July', 'August', 'September',
                'October', 'November', 'December');
        return $month[$m - 1];
    }

    private function parseShopSettings($text)
    {
        $u = new DB('shop_setting', 'ss');
        $u->select('ss.code, ssv.value');
        $u->innerJoin('shop_setting_value ssv', 'ssv.id_setting = ss.id');
        $items = $u->getList();
        $settings = array();
        foreach ($items as $item)
            $settings[strtoupper($item['code'])] = $item['value'];
        foreach ($settings as $k => $v)
            $text = str_replace('[SETTING.' . $k . ']', stripslashes($v), $text);
        $text = preg_replace('/\[SETTING\.(.+?)\]/i', '', $text);

        return $text;
    }

    private function parseUser($text)
    {
        if (!$this->user)
            return $text;

        foreach ($this->user as $k => $v)
            $text = str_replace('[USER.' . strtoupper($k) . ']', stripslashes($v), $text);
        $text = preg_replace('/\[USER\.(.+?)\]/i', '', $text);

        return $text;
    }

    private function parseSystem($text)
    {
        if (!empty($this->input["password"]))
            $text = str_replace('[USER_PASSWORD]', $this->input["password"], $text);
        $text = str_replace('[NAME_SITE]', $_SERVER['HTTP_HOST'], $text);
        $text = str_replace('[DATE]', date("d.m.Y"), $text);
        $text = str_replace('[TIME]', date("H:i"), $text);
        $text = str_replace('[DATETIME]', date("d.m.Y H:i:s"), $text);

        return $text;
    }

    private function parseCurrency($text)
    {
        // Парсим пост выбора валюты с дефолными данными
        $res_ = '';
        while (preg_match('/\[POST\.(\w{1,}\:\w{1,})\]/i', $text, $res_math)) {
            $res_ = $res_math[1];
            $def = explode(':', $res_);
            if (isset($_POST[strtolower($def[0])])) {
                $res_ = htmlspecialchars(stripslashes(@$_POST[strtolower($def[0])]));
            } else if (!empty($def[1])) {
                $res_ = $def[1];
            }
            $text = str_replace($res_math[0], strtoupper($res_), $text);
        }

        // Парсим команду SELECTED
        while (preg_match('/\[SELECTED\:(\w{1,})\]/i', $text, $res_math)) {
            if (strtolower($res_) == strtolower($res_math[1])) {
                $text = str_replace($res_math[0], "selected", $text);
            } else {
                $text = str_replace($res_math[0], '', $text);
            }
        }

        // Парсим команду IF
        while (preg_match('/\[IF\((.+?)\)\]/m', $text, $res_math)) {
            list($def, $res) = explode(':', $res_math[1]);
            $sel = explode(',', $def);
            foreach ($sel as $if) {
                $if = explode('=', $if);
                if (strtolower($res_) == strtolower($if[1])) $res = $if[0];
            }
            $text = str_replace($res_math[0], $res, $text);
        }

        // Парсим команду выбор валюты и запиь ее в сессию
        while (preg_match('/\[SETCURRENCY\:(\w{1,})\]/m', $text, $res_math)) {
            if (isset($res_math[1])) {
                $this->currency = $res_math[1];
                $_SESSION['THISCURR'] = $this->curr;
            }
            $text = str_replace($res_math[0], '', $text);
        }

        // Парсим запросы
        while (preg_match('/\[POST\.(\w{1,})\]/i', $text, $res_math)) {
            if (isset($_POST[$res_math[1]])) {
                $res_ = htmlspecialchars(stripslashes($_POST[$res_math[1]]));
            } else {
                $res_ = '';
            }
            $text = str_replace($res_math[0], $res_, $text);
        }

        while (preg_match('/\[GET\.(\w{1,})\]/i', $text, $res_math)) {
            if (isset($_GET[$res_math[1]])) {
                $res_ = htmlspecialchars(stripslashes($_GET[$res_math[1]]));
            } else $res_ = '';
            $text = str_replace($res_math[0], $res_, $text);
        }

        return $text;
    }

    private function parseOrder($text)
    {
        if (strpos($text, '[ORDER.ITEMS]') !== false){
            $value_list = '<table border=0 cellpadding=3 cellspacing=1>';
            $value_list .= '<tr><td>№</td><td>Фото</td><td>Артикул</td><td>Наименование</td><td>Цена</td><td>Скидка</td><td>Кол-во</td><td>Сумма</td>';
            $value_list .= '</tr><ORDER_LIST><tr>';
            $value_list .= '<td>[ORDER.ITEM.NUM]</td>';
            $value_list .= '<td>[ORDER.ITEM.PHOTO]</td>';
            $value_list .= '<td>[ORDER.ITEM.ARTICLE]</td>';
            $value_list .= '<td>[ORDER.ITEM.NAME]</td>';
            $value_list .= '<td>[ORDER.ITEM.PRICE]</td><td>[ORDER.ITEM.DISCOUNT]</td>';
            $value_list .= '<td>[ORDER.ITEM.COUNT]</td><td>[ORDER.ITEM.SUM]</td>';
            $value_list .= '</tr></ORDER_LIST></table>';
            $text = str_replace('[ORDER.ITEMS]', $value_list, $text);
        }

        if (preg_match('/\<ORDER_LIST\>([\w\W]{1,})\<\/ORDER_LIST\>/i', $text, $resMath)) {
            $it = 0;
            $listText = null;
            foreach ($this->order["items"] as $orderItem) {
                $listIt = $resMath[1];
                $listIt = str_replace("[ORDER.ITEM.NUM]", ++$it, $listIt);
                $res = $orderItem;
                foreach ($res as $k => $v)
                    $listIt = str_replace("[ORDER.ITEM." . strtoupper($k) . "]",  $v, $listIt);
                $listText .= $listIt;
            }
            $text = str_replace($resMath[0], $listText, $text);
        }

        return $text;
    }

    private function parseOptions($text)
    {
        foreach ($this->input as $key => $value) {
            $text = str_replace("[{$key}]", $value, $text);
        }
        return $text;
    }
}
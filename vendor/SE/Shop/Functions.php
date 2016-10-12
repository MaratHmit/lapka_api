<?php

namespace SE\Shop;


class Functions extends Base
{

    public function Translit()
    {
        $vars = $this->input["vars"];
        $i = 0;
        $items = array();
        foreach ($vars as $var) {
            $items[] = $this->transliterationUrl($var);
            $i++;
        }

        $this->result['count'] = $i;
        $this->result['items'] = $items;

        return $items;
    }
}
<?php

namespace SE\Shop;

class PaySystemPlugin extends Base
{
    protected $isTableMode = false;

    public function fetch()
    {
        $this->result["items"] = PaySystemPlugin::getPlugins();
        return $this->result["items"];
    }

    static public function getPlugins()
    {
        $urlRoot = 'http://' . HOSTNAME;
        $buffer = file_get_contents($urlRoot . "/lib/merchant/getlist.php");
        $items = explode("|", $buffer);
        $plugins = array();
        foreach ($items as $item)
            if (!empty($item)) {
                $plugin['id'] = $item;
                $plugin['name'] = $item;
                $plugins[] = $plugin;
            }
        return $plugins;
    }


}
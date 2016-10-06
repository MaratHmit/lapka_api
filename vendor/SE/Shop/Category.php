<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Category extends Base
{
    protected $tableName = "shop_group";
    protected $sortOrder = "asc";
    protected $sortBy = "sort";
    protected $limit = null;
    protected $allowedSearch = false;

    private function getParentItem($item, $items)
    {
        foreach ($items as $it)
            if ($it["id"] == $item["idParent"])
                return $it;
    }

    private function getPathName($item, $items)
    {
        if (!$item["idParent"])
            return $item["name"];

        $parent = $this->getParentItem($item, $items);
        if (!$parent)
            return $item["name"];
        return $this->getPathName($parent, $items) . " / " . $item["name"];
    }

    public function getPatches($items)
    {
        $result = array();
        $search = strtolower($this->input["searchText"]);
        foreach ($items as $item) {
            if (empty($search) || mb_strpos(strtolower($item["name"]), $search) !== false) {
                $item["name"] = $this->getPathName($item, $items);
                $item["level"] = substr_count($item["name"], "/");
                $result[] = $item;
            }
        }
        return $result;
    }

    private function getTreeView($items, $idParent = null)
    {
        $result = array();
        foreach ($items as $item) {
            if ($item["idParent"] == $idParent) {
                $item["childs"] = $this->getTreeView($items, $item["id"]);
                $result[] = $item;
            }
        }
        return $result;
    }

    private function setIdMainParent($items)
    {
        $result = array();
        foreach ($items as $item) {
            if ($item['idsParents']) {
                $idsLevels = explode(";", $item['idsParents']);
                $idParent = 0;
                $level = 0;
                foreach ($idsLevels as $idLevel) {
                    $ids = explode(":", $idLevel);
                    if ($ids[0] >= $level) {
                        $idParent = $ids[1];
                        $level = $ids[0];
                    }
                }
                $item['idParent'] = $idParent;
            }
            $result[] = $item;
        }
        return $result;
    }

    protected function getSettingsFetch()
    {

        $result["select"] = "sg.*, tr.name name, 
                GROUP_CONCAT(CONCAT_WS(':', sgtp.level, sgt.id_parent) SEPARATOR ';') ids_parents,
                sgt.id_parent id_parent, sgt.level level";
        $joins[] = [
            "type" => "left",
            "table" => 'shop_group_translate tr',
            "condition" => 'sg.id = tr.id_group'
        ];
        $joins[] = [
            "type" => "left",
            "table" => 'shop_group_tree sgt',
            "condition" => 'sgt.id_child = sg.id AND sg.id <> sgt.id_parent'
        ];
        $joins[] = [
            "type" => "left",
            "table" => 'shop_group_tree sgtp',
            "condition" => 'sgtp.id_child = sgt.id_parent'
        ];
        $result["joins"] = $joins;

        return $result;
    }

    protected function getSettingsInfo()
    {
        return [
            "select" => "sg.*, tr.id id_translate, tr.name, tr.description, tr.content,
                tr.meta_title, tr.meta_keywords, tr.meta_description,               
                GROUP_CONCAT(CONCAT_WS(':', sgtp.level, sgt.id_parent) SEPARATOR ';') ids_parents,
                sgt.id_parent id_parent, sgt.level level",
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_group_translate tr',
                    "condition" => 'sg.id = tr.id_group'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_group_tree sgt',
                    "condition" => 'sgt.id_child = sg.id AND sg.id <> sgt.id_parent'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_group_tree sgtp',
                    "condition" => 'sgtp.id_child = sgt.id_parent'
                ]
            ]
        ];
    }

    public function info()
    {
        $result = parent::info();
        $this->result = $this->setIdMainParent(array($result))[0];
        $this->result["nameParent"] = $this->getNameParent();
        return $this->result;
    }

    protected function correctValuesBeforeFetch($items = array())
    {
        $items = $this->setIdMainParent($items);
        if ($this->input["isTree"] && empty($this->input["searchText"]))
            $result = $this->getTreeView($items);
        else $result = $this->getPatches($items);
        return $result;
    }

    public function getDiscounts($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_discount', 'sd');
        $u->select('sd.*');
        $u->innerJoin('shop_discount_link sdl', 'sdl.id_discount = sd.id');
        $u->where('sdl.id_group = ?', $id);
        $u->orderBy('sdl.sort');
        return $u->getList();
    }

    public function getImages($idCategory = null)
    {
        $result = [];
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_group_image', 'si');
        $u->select('si.id, si.id_image, tr.id id_translate, 
                    CONCAT(f.name, "/", img.name) image_path, si.is_main, tr.alt, si.sort');
        $u->innerJoin("image img", "img.id = si.id_image");
        $u->leftJoin("image_folder f", "f.id = img.id_folder");
        $u->leftJoin("image_translate tr", "tr.id_image = img.id AND tr.id_lang = {$this->idLang}");
        $u->where('si.id_group = ?', $id);
        $u->orderBy("si.sort");

        return $u->getList();
    }

    public function getDeliveries($idCategory = null)
    {
        $result = array();
        $id = $idCategory ? $idCategory : $this->input["id"];
        if (!$id)
            return $result;

        return $result;
    }

    protected function getChilds()
    {
        $idParent = $this->input["id"];
        $filter = array(
            array("field" => "idParent", "value" => $idParent),
            array("field" => "level", "value" => ++$this->result["level"]));
        $category = new Category(array("filters" => $filter));
        $result = $category->fetch();

        return $result;
    }

    private function getNameParent()
    {
        if (!$this->result["idParent"])
            return null;

        $db = new DB("shop_group", "sg");
        $db->select("tr.name");
        $db->innerJoin('shop_group_translate tr', 'tr.id_group = sg.id');
        $result = $db->getInfo($this->result["idParent"]);
        return $result["name"];
    }

    protected function getAddInfo()
    {
        $result = [];
        $result["discounts"] = $this->getDiscounts();
        $result["images"] = $this->getImages();
        $result["deliveries"] = $this->getDeliveries();
        $result["childs"] = $this->getChilds();
        $result["productTypes"] = (new ProductType())->fetch();
        return $result;
    }

    private function saveDiscounts()
    {
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["discounts"],
                    array("table" => "shop_discount_link", "key" => "id_group", "link" => "id_discount"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить скидки категории товара!";
            throw new Exception($this->error);
        }
    }

    static public function getLevel($id)
    {
        $level = 0;
        $sqlLevel = 'SELECT `level` FROM shop_group_tree WHERE id_parent = :id_parent AND id_child = :id_parent LIMIT 1';
        $sth = DB::prepare($sqlLevel);
        $params = array("id_parent" => $id);
        $answer = $sth->execute($params);
        if ($answer !== false) {
            $items = $sth->fetchAll(\PDO::FETCH_ASSOC);
            if (count($items))
                $level = $items[0]['level'];
        }
        return $level;
    }

    static public function saveIdParent($id, $idParent)
    {
        try {
            $level = 0;
            DB::query("DELETE FROM shop_group_tree WHERE id_child = {$id}");

            $sqlGroupTree = "INSERT INTO shop_group_tree (id_parent, id_child, `level`)
                                SELECT id_parent, :id, :level FROM shop_group_tree
                                WHERE id_child = :id_parent
                                UNION ALL
                                SELECT :id, :id, :level";
            $sthGroupTree = DB::prepare($sqlGroupTree);
            if (!empty($idParent)) {
                $level = self::getLevel($idParent);
                $level++;
            }
            $sthGroupTree->execute(array('id_parent' => $idParent, 'id' => $id, 'level' => $level));
        } catch (Exception $e) {
            throw new Exception("Не удаётся сохранить родителя группы!");
        }
    }

    protected function correctValuesBeforeSave()
    {
        if (isset($this->input["idType"]) && empty($this->input["idType"]))
            $this->input["idType"] = null;
    }

    protected function saveChilds()
    {
        $idParent = $this->input["id"];
        $oldChilds = $this->getChilds();
        $idsOldChilds = [];
        foreach ($oldChilds as $oldChild)
            $idsOldChilds[] = $oldChild["id"];
        $childs = $this->input["childs"];
        $idsChilds = [];
        foreach ($childs as $child)
            $idsChilds[] = $child["id"];
        $idsNewChilds =  array_diff($idsChilds, $idsOldChilds);
        $idsDelChilds = array_diff($idsOldChilds, $idsChilds);
        foreach ($idsNewChilds as $idNewChild)
            self::saveIdParent($idNewChild, $idParent);
        foreach ($idsDelChilds as $idDelChild)
            self::saveIdParent($idDelChild, null);
    }

    protected function saveAddInfo()
    {
        $this->input["ids"] = empty($this->input["ids"]) ? array($this->input["id"]) : $this->input["ids"];
        if (!$this->input["ids"])
            return false;

        $this->saveDiscounts();
        $this->saveListImages();
        $this->saveChilds();

        $group = (new Category($this->input))->info();
        if ($this->isNew || ($group["idParent"] != $this->input["idParent"]))
            self::saveIdParent($this->input["id"], $this->input["idParent"]);

        return true;
    }
}
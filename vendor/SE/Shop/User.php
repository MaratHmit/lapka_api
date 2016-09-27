<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class User extends Base
{
    protected $tableName = "user";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'u.*, ug.id_group id_group,
                0 count_orders, 0 amount_orders, 0 paid_orders',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => "user_usergroup ug",
                    "condition" => "u.id = ug.id_user"
                )
            )
        );
    }

    protected function getAddInfo()
    {
        $result['groups'] = $this->getGroups();
        $result["customFields"] = $this->getCustomFields();
        return $result;
    }

    private function getCustomFields()
    {
        $idUser = $this->input["id"];

        $u = new DB('shop_field', 'f');
        $u->select("fv.id, fv.id_user, fv.value, f.id id_field, 
                    ft.name, f.type, ft.values, fg.id id_group, fgt.name name_group");
        $u->innerJoin('shop_field_translate ft', 'ft.id_field = f.id');
        $u->leftJoin('shop_field_value fv', "fv.id_field = f.id AND id_user = {$idUser}");
        $u->leftJoin('shop_field_group fg', 'fg.id = f.id_group');
        $u->leftJoin('shop_field_group_translate fgt', 'fgt.id_group = fg.id');
        $u->where('f.data = "user"');
        $u->groupBy('f.id');
        $u->orderBy('fg.sort');
        $u->addOrderBy('f.sort');
        $result = $u->getList();

        $groups = array();
        foreach ($result as $item) {
            $isNew = true;
            $newGroup = array();
            $newGroup["id"] = $item["idGroup"];
            $newGroup["name"] = empty($item["nameGroup"]) ? "Без категории": $item["nameGroup"];
            foreach ($groups as $group)
                if ($group["id"] == $item["idGroup"]) {
                    $isNew = false;
                    $newGroup = $group;
                    break;
                }
            if ($item['type'] == "date" && $item['value'])
                $item['value'] = date('Y-m-d', strtotime($item['value']));
            $newGroup["items"][] = $item;
            if ($isNew)
                $groups[] = $newGroup;
        }
        return $groups;
    }

    private function getGroups()
    {
        $idUser = $this->input["id"];

        $u = new DB('usergroup', 'g');
        $u->select('g.*');
        $u->innerJoin('user_usergroup ug', 'g.id = ug.id_group');
        $u->where('ug.id_user = ?', $idUser);
        return $u->getList();
    }

    private function getLogin($name, $login, $id = 0)
    {
        if (empty($login))
            $login = strtolower(rus2translit($name));
        $loginN = $login;

        $u = new DB('user', 'u');
        $i = 2;
        while ($i < 1000) {
            if ($id)
                $result = $u->findList("u.login = '$loginN' AND id <> $id")->fetchOne();
            else $result = $u->findList("u.login = '$loginN'")->fetchOne();
            if ($result["id"])
                $loginN = $login . $i;
            else return $loginN;
            $i++;
        }
        return uniqid();
    }

    private function saveUserGroups($idUser)
    {
        try {
            DB::saveManyToMany($idUser, $this->input["groups"],
                array("table" => "user_usergroup", "key" => "id_user", "link" => "id_group"));

        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить группы контактов!";
            throw new Exception($this->error);
        }
    }

    private function saveCustomFields()
    {
        if (!isset($this->input["customFields"]))
            return true;

        try {
            $idUser = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = array();
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $field["idUser"] = $idUser;
                $u = new DB('shop_field_value', 'fv');
                $u->setValuesFields($field);
                $u->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доп. информацию о контакте!";
            throw new Exception($this->error);
        }
    }

    public function save($contact = null)
    {
        try {
            if ($contact)
                $this->input = $contact;
            DB::beginTransaction();

            $ids = array();
            if (empty($this->input["ids"]) && !empty($this->input["id"]))
                $ids[] = $this->input["id"];
            else $ids = $this->input["ids"];
            $isNew = empty($ids);
            if ($isNew) {
                $this->input["login"] = $this->getLogin($this->input["name"], $this->input["login"]);
                if (!empty($this->input["login"])) {
                    $u = new DB('user', 'u');
                    $u->setValuesFields($this->input);
                    $ids[] = $u->save();
                }
            } else {
                $u = new DB('user', 'u');
                if (!empty($this->input["login"]))
                    $this->input["login"] = $this->getLogin($this->input["name"], $this->input["login"], $ids[0]);
                $u->setValuesFields($this->input);
                $u->save();
            }

            if (!empty($ids)) {
                $this->saveUserGroups($ids[0]);
                $this->saveCustomFields();
            }
            DB::commit();
            $this->info();

            return $this;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся сохранить контакт!" : $this->error;
        }
    }


}

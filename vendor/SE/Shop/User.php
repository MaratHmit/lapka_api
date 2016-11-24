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
            "select" => "u.*, ug.id_group id_group,
                0 count_orders, 0 amount_orders, 0 paid_orders,
                CONCAT('/', IF(img_fld.name IS NULL, '', CONCAT(img_fld.name, '/')), img.name) image_path",
            "joins" => [
                [
                    "type" => "left",
                    "table" => "user_usergroup ug",
                    "condition" => "u.id = ug.id_user"
                ],
                [
                    "type" => "left",
                    "table" => 'image img',
                    "condition" => "u.id_image = img.id"
                ],
                [
                    "type" => "left",
                    "table" => 'image_folder img_fld',
                    "condition" => 'img.id_folder = img_fld.id'
                ]
            ]
        );
    }


    protected function getAddInfo()
    {
        $result['pets'] = $this->getPets();
        $result['groups'] = $this->getGroups();
        $result["customFields"] = $this->getCustomFields();
        return $result;
    }

    private function getPets()
    {
        $idUser = $this->input["id"];

        $u = new DB('pet_user', 'pu');
        $u->select("pu.id, p.id id_pet, pu.name, DATE_FORMAT(pu.birthday, '%d.%m.%Y') birthday, pu.weight, 
            CONCAT('/', IF(img_fld.name IS NULL, '', CONCAT(img_fld.name, '/')), img.name) image_path");
        $u->innerJoin('pet p', 'p.id = pu.id_pet');
        $u->leftJoin('image img', 'pu.id_image = img.id');
        $u->leftJoin('image_folder img_fld', 'img.id_folder = img_fld.id');
        $u->where('pu.id_user = ?', $idUser);
        $u->orderBy('pu.sort');
        $u->addOrderBy('pu.id');
        $u->groupBy('pu.id');

        return $this->correctValuesBeforeFetch($u->getList());
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
        $u->where('f.target = 0');
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
            $login = $this->rusToTransliteration($name);
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
            if (!empty($this->input['email'])) {
                $service = new Service();
                $service->saveUserSettings($this->input['email'], $this->input["groups"]);
            }
            return true;
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

    private function savePets()
    {
        if (!isset($this->input["pets"]))
            return true;

        try {
            $idUser = $this->input["id"];
            $pets = $this->input["pets"];
            foreach ($pets as $pet) {
                $u = new DB('pet_user', 'pu');
                $pet["idUser"] = $idUser;
                if (!empty($pet["imagePath"]))
                    $pet["idImage"] = $this->saveImage($pet["imagePath"]);
                else $pet["idImage"] = null;
                $pet["birthday"] = date("Y-m-d", strtotime($pet["birthday"]));
                $u->setValuesFields($pet);
                $u->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить домашних животных контакта!";
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
            if (isset($this->input["imagePath"]))
                $this->input["idImage"] = $this->saveImage();
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
                $this->savePets();
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

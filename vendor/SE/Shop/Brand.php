<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Brand extends Base
{
    protected $tableName = "shop_brand";

    public function getSettingsFetch()
    {
        return [
            "select" => 'sb.*, sbt.name name, CONCAT(f.name, "/", img.name) image_path',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_brand_translate sbt',
                    "condition" => 'sb.id = sbt.id_brand'
                ],
                [
                    "type" => "left",
                    "table" => 'image img',
                    "condition" => 'sb.id_image = img.id'
                ],
                [
                    "type" => "left",
                    "table" => 'image_folder f',
                    "condition" => 'img.id_folder = f.id'
                ]
            ]
        ];
    }

    public function getSettingsInfo()
    {
        return [
            "select" => "sb.*, sbt.id id_translate, 
                sbt.name name, sbt.description description, sbt.content content, 
                sbt.meta_title meta_title, sbt.meta_keywords meta_keywords, sbt.meta_description meta_description,
                CONCAT('/', IF(f.name IS NULL, '', CONCAT(f.name, '/')), img.name) image_path",
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_brand_translate sbt',
                    "condition" => 'sb.id = sbt.id_brand'
                ],
                [
                    "type" => "left",
                    "table" => 'image img',
                    "condition" => 'sb.id_image = img.id'
                ],
                [
                    "type" => "left",
                    "table" => 'image_folder f',
                    "condition" => 'img.id_folder = f.id'
                ]
            ]
        ];
    }

    private function getUrl($id, $name, $url)
    {
        if (empty($url))
            $url = $this->transliterationUrl($name);
        $u = new DB('shop_brand', 'sb');
        $u->select('sb.id, sb.url');
        $i = 2;
        $code_n = $url;
        while ($i < 1000) {
            $s = "sb.url = '$code_n'";
            if ($id)
                $s .= " AND sb.id <> $id";
            $result = $u->findList($s)->fetchOne();
            if ($result["id"])
                $code_n = $url . $i;
            else return $code_n;
            $i++;
        }
    }

    public function save()
    {
        try {
            DB::beginTransaction();
            $this->input["url"] = $this->getUrl($this->input["id"], $this->input["name"], $this->input["url"]);
            if (isset($this->input["imagePath"]))
                $this->input["idImage"] = $this->saveImage();
            $u = new DB('shop_brand', 'sb');
            $u->setValuesFields($this->input);
            $brandTranslate = $this->input;
            $this->input["id"] = $u->save();
            if (!empty($this->input["idTranslate"]))
                $brandTranslate["id"] = $this->input["idTranslate"];
            $brandTranslate["idBrand"] = $this->input["id"];
            $u = new DB('shop_brand_translate', 'sbt');
            $u->setValuesFields($brandTranslate);
            $u->save();
            $this->info();
            DB::commit();
            return $this;
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = "Не удаётся сохранить бренд!";
        }
    }
}

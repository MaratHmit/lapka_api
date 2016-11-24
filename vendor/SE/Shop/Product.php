<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Product extends Base
{
    protected $tableName = "shop_product";

    protected function getSettingsFetch()
    {
        return [
            "select" => 'sp.*, tr.available_info available_info,
                tr.name name, so.article article,
                sop.value price, sbt.name name_brand, sgt.name name_group,
                CONCAT(f.name, "/", img.name) image_path, spg.id_group id_group,
                SUM(sws.value) count',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_product_translate tr',
                    "condition" => 'tr.id_product = sp.id'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_brand_translate sbt',
                    "condition" => 'sbt.id_brand = sp.id_brand'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_offer so',
                    "condition" => "so.id_product = sp.id"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_warehouse_stock sws',
                    "condition" => "sws.id_offer = so.id"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_offer_price sop',
                    "condition" => "sop.id_offer = so.id AND sop.id_typeprice = {$_SESSION["idTypePrice"]}"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_currency sc',
                    "condition" => "sc.id = sop.id_currency"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_product_group spg',
                    "condition" => "spg.id_product = sp.id"
                ],
                [
                    "type" => "left",
                    "table" => "shop_group_translate sgt",
                    "condition" => "sgt.id_group = spg.id_group"
                ],
                [
                    "type" => "left",
                    "table" => "shop_product_image spi",
                    "condition" => "spi.id_product = sp.id AND spi.is_main"
                ],
                [
                    "type" => "left",
                    "table" => 'image img',
                    "condition" => 'img.id = spi.id_image'
                ],
                [
                    "type" => "left",
                    "table" => 'image_folder f',
                    "condition" => 'img.id_folder = f.id'
                ]
            ]
        ];
    }

    protected function getSettingsInfo()
    {
        return [
            "select" => 'sp.*, 
                tr.name name, tr.content, tr.description, tr.available_info,
                tr.meta_title, tr.meta_keywords, tr.meta_description,   
                sbt.name name_brand, st.name name_type, smt.name name_measure',
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'shop_product_translate tr',
                    "condition" => 'tr.id_product = sp.id'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_brand_translate sbt',
                    "condition" => 'sbt.id_brand = sp.id_brand'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_offer so',
                    "condition" => "so.id_product = sp.id"
                ],
                [
                    "type" => "left",
                    "table" => 'shop_type st',
                    "condition" => 'st.id = sp.id_type'
                ],
                [
                    "type" => "left",
                    "table" => 'shop_measure_translate smt',
                    "condition" => 'smt.id_measure = sp.id_measure'
                ],
            ]
        ];
    }

    public function getImages($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_product_image', 'si');
        $u->select('si.id, si.id_image, tr.id id_translate, 
                    CONCAT(f.name, "/", img.name) image_path, si.is_main, tr.alt, si.sort');
        $u->innerJoin("image img", "img.id = si.id_image");
        $u->leftJoin("image_folder f", "f.id = img.id_folder");
        $u->leftJoin("image_translate tr", "tr.id_image = img.id AND tr.id_lang = {$this->idLang}");
        $u->where('si.id_product = ?', $id);
        $u->orderBy("si.sort");

        return $u->getList();
    }

    public function getSpecifications($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        try {
            $u = new DB('shop_product_feature', 'spf');
            $u->select('spf.id, sfg.id id_group, sfg_tr.name group_name, sf.id id_feature, sf_tr.name,
						sf.type, spf.id_value, spf.value, sfv.color, sfg.sort index_group');
            $u->innerJoin('shop_feature sf', 'sf.id = spf.id_feature');
            $u->innerJoin('shop_feature_translate sf_tr', 'sf_tr.id_feature = sf.id');
            $u->leftJoin('shop_feature_value sfv', 'sfv.id = spf.id_value');
            $u->leftJoin('shop_feature_group sfg', 'sfg.id = sf.id_group');
            $u->leftJoin('shop_feature_group_translate sfg_tr', 'sfg_tr.id_group = sfg.id');
            $u->where('spf.id_product = ?', $id);
            $u->orderBy('sfg.sort');
            $u->addOrderBy('sf.sort');
            $items = $u->getList();
            $result = array();
            foreach ($items as $item) {
                if ($item["type"] == "number")
                    $item["value"] = (real)$item["value"];
                elseif ($item["type"] == "bool")
                    $item["value"] = (bool)$item["value"];
                $result[] = $item;
            }
            return $result;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить характеристики товара!";
        }
    }

    public function getSimilarProducts($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_product_translate', 'spt');
        $u->select('spt.id_product id, spt.name');
        $u->innerJoin('shop_product_related spr', 'spt.id_product = spr.id_product OR spt.id_product = spr.id_related');
        $u->where('`type` = 2 AND (spr.id_product = ? OR spr.id_related = ?) AND spt.id_product <> ?', $id);
        return $u->getList();
    }

    public function getAccompanyingProducts($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_product_translate', 'spt');
        $u->select('spt.id_product id, spt.name');
        $u->innerJoin('shop_product_related spr', 'spt.id_product = spr.id_product');
        $u->where('`type` = 1 AND spt.id_product = ?', $id);
        return $u->getList();
    }

    public function getComments($idProduct = null)
    {
        return (new Comment())->fetchByIdProduct($idProduct ? $idProduct : $this->input["id"]);
    }

    public function getReviews($idProduct = null)
    {
        return (new Review())->fetchByIdProduct($idProduct ? $idProduct : $this->input["id"]);
    }

    public function getGroups($idProduct = null)
    {
        $result = array();
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_product_group', 'spg');
        $u->select('spg.id id_key, sgt.id_group id, sgt.name, spg.is_main');
        $u->innerJoin('shop_group_translate sgt', 'sgt.id_group = spg.id_group');
        $u->where('spg.id_product = ?', $id);
        return $u->getList();
    }

    public function getOffers($idProduct = null)
    {
        return (new Offer())->fetchByIdProduct($idProduct ? $idProduct : $this->input["id"]);
    }

    public function getDiscounts($idProduct = null)
    {
        return (new Discount())->fetchByIdProduct($idProduct ? $idProduct : $this->input["id"]);
    }

    public function getLabels($idProduct = null)
    {
        $idProduct = $idProduct ? $idProduct : $this->input["id"];
        $result = [];
        $labels = (new Label())->fetch();
        $u = new DB("shop_label_product");
        $u->select("id_label");
        $u->where("id_product = ?", $idProduct);
        $items = $u->getList();
        foreach ($labels as $label) {
            $isChecked = false;
            foreach ($items as $item)
                if ($isChecked = ($label["id"] == $item["idLabel"]))
                    break;
            $label["isChecked"] = $isChecked;
            $result[] = $label;
        }
        return $result;
    }

    public function importByKeyField($key)
    {
        if (empty($key))
            $key = "article";

        try {
            if (empty($this->input[$key]))
                return true;

            $t = new DB("shop_offer", "so");
            $t->select("so.*");
            $t->where("so.{$key} = '?'", $this->input[$key]);
            $offers = $t->getList();
            foreach ($offers as $offer) {
                if (empty($this->input["id"]) && !empty($offer["idProduct"])) {
                    $this->input["id"] = $offer["idProduct"];
                    $this->input["ids"][] = $offer["idProduct"];
                }
                if (!empty($this->input["price"]))
                    $offer["price"] = $this->input["price"];
                if (!empty($this->input["count"]))
                    $offer["count"] = $this->input["count"];
                $this->input["offers"][] = $offer;
            }
            if (!empty($this->input["id"]) && !empty($this->input["images"])) {
                foreach ($this->input["images"] as &$image) {
                    $idProduct = $this->input["id"];
                    $idImage = $this->saveImage($image["imagePath"]);
                    $t = new DB("shop_product_image", "spi");
                    $t->select("spi.id");
                    $t->where("id_product = {$idProduct} AND id_image = {$idImage}");
                    $result = $t->fetchOne();
                    if (!empty($result["id"]))
                        $image["id"] = $result["id"];
                }
            }
            if (empty($this->input["idGroup"]) && !empty($this->input["catalog0"]))
                $this->input["idGroup"] = Category::getIdGroupByColumns($this->input);
            return $this->save();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить ид. товара по заданному ключу!";
            throw new Exception($this->error);
        }
    }

    protected function correctValuesBeforeFetch($items = [])
    {
        $items = parent::correctValuesBeforeFetch($items);
        foreach ($items as &$item) {
            $item["countDisplay"] = $item["isUnlimited"] ? $item["availableInfo"] : (float)$item["count"];
        }
        return $items;
    }

    protected function getAddInfo()
    {
        $result = array();
        $result["groups"] = $this->getGroups();
        $result["images"] = $this->getImages();
        $result["offers"] = $this->getOffers();
        $result["specifications"] = $this->getSpecifications();
        $result["similarProducts"] = $this->getSimilarProducts();
        $result["accompanyingProducts"] = $this->getAccompanyingProducts();
        $result["comments"] = $this->getComments();
        $result["reviews"] = $this->getReviews();
        $result["discounts"] = $this->getDiscounts();
        $result["measures"] = (new Measure())->fetch();
        $result["productTypes"] = (new ProductType())->fetch();
        $result["labels"] = $this->getLabels();
        return $result;
    }

    private function getIdSpecificationGroup($name)
    {
        if (empty($name))
            return null;

        $u = new DB('shop_feature_group');
        $u->select('id');
        $u->where('name = "?"', $name);
        $result = $u->fetchOne();
        if (!empty($result["id"]))
            return $result["id"];

        $u = new DB('shop_feature_group');
        $u->setValuesFields(array("name" => $name));
        return $u->save();
    }

    private function getIdFeature($idGroup, $name)
    {
        $u = new DB('shop_feature', 'sf');
        $u->select('id');
        $u->where('name = "?"', $name);
        if ($idGroup)
            $u->andWhere('id_feature_group = ?', $idGroup);
        else $u->andWhere('id_feature_group IS NULL');
        $result = $u->fetchOne();
        if (!empty($result["id"]))
            return $result["id"];

        $u = new DB('shop_feature', 'sf');
        $data = array();
        if ($idGroup)
            $data["idFeatureGroup"] = $idGroup;
        $data["name"] = $name;
        return $u->save();
    }

    public function getSpecificationByName($specification)
    {
        $idGroup = $this->getIdSpecificationGroup($specification->nameGroup);
        $specification->idFeature = $this->getIdFeature($idGroup, $specification->name);
        return $specification;
    }

    protected function correctValuesBeforeSave()
    {
        if ($this->isNew && !empty($this->input["idGroup"]))
            $this->input["idType"] = $this->getDefaultIdType();
        if (!empty($this->input["brand"]))
            $this->input["idBrand"] = (new Brand())->getIdByName($this->input["brand"]);

        return true;
    }

    protected function saveAddInfo()
    {
        if (!$this->input["ids"])
            return false;

        return $this->createDefaultOffer() && $this->saveListImages() && $this->saveGroups() &&
        $this->saveOffers() && $this->saveSpecifications() && $this->saveAccompanyingProducts() &&
        $this->saveSimilarProducts() && $this->saveComments() && $this->saveReviews() && $this->saveLabels() &&
            $this->saveDiscounts();
    }

    private function getDefaultIdType()
    {
        try {
            $t = new DB("shop_group", "sg");
            $t->select("sg.id_type");
            $t->where("sg.id = ?", $this->input["idGroup"]);
            return $t->fetchOne()["idType"];
        } catch (Exception $e) {

        }
        return null;
    }

    private function saveSpecifications()
    {
        if (!isset($this->input["specifications"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $specifications = $this->input["specifications"];
            foreach ($idsProducts as $idProduct) {
                foreach ($specifications as $specification) {
                    $specification["idProduct"] = $idProduct;
                    $u = new DB('shop_product_feature');
                    $u->setValuesFields($specification);
                    $u->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить спецификации товара!";
            throw new Exception($this->error);
        }
    }

    private function saveSimilarProducts()
    {
        if (!isset($this->input["similarProducts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["similarProducts"],
                    array("table" => "shop_product_related", "key" => "id_product", "link" => "id_related"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить похожие товары!";
            throw new Exception($this->error);
        }
    }

    private function saveAccompanyingProducts()
    {
        if (!isset($this->input["accompanyingProducts"]))
            return true;

        try {

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить сопутствующие товары!";
            throw new Exception($this->error);
        }
    }

    private function saveComments()
    {
        if (!isset($this->input["comments"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $comments = $this->input["comments"];
            $idsStr = implode(",", $idsProducts);
            $idsExists = array();
            foreach ($comments as $comment)
                if ($comment["id"])
                    $idsExists[] = $comment["id"];
            $idsExists = implode(",", $idsExists);
            $u = new DB('shop_commentary');
            if (!$idsExists)
                $u->where('id_product IN (?)', $idsStr)->deleteList();
            else $u->where("NOT id IN ({$idsExists}) AND id_product IN (?)", $idsStr)->deleteList();
            foreach ($comments as $comment) {
                foreach ($idsProducts as $idProduct) {
                    $comment["idProduct"] = $idProduct;
                    $u = new DB('shop_reviews');
                    $u->setValuesFields($comment);
                    $u->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить комментарии товара!";
            throw new Exception($this->error);
        }
    }

    private function saveReviews()
    {
        if (!isset($this->input["reviews"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $reviews = $this->input["reviews"];
            $idsStr = implode(",", $idsProducts);
            $idsExists = array();
            foreach ($reviews as $review)
                if ($review["id"])
                    $idsExists[] = $review["id"];
            $idsExists = implode(",", $idsExists);
            $u = new DB('shop_review');
            if (!$idsExists)
                $u->where('id_product IN (?)', $idsStr)->deleteList();
            else $u->where("NOT id IN ({$idsExists}) AND id_product IN (?)", $idsStr)->deleteList();
            foreach ($reviews as $review) {
                foreach ($idsProducts as $idProduct) {
                    $review["idProduct"] = $idProduct;
                    $u = new DB('shop_review');
                    $u->setValuesFields($review);
                    $u->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить отзывы товара!";
            throw new Exception($this->error);
        }
    }

    private function saveDiscounts()
    {
        if (!isset($this->input["discounts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["discounts"],
                    array("table" => "shop_discount_link", "key" => "id_product", "link" => "id_discount"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить скидки товара!";
            throw new Exception($this->error);
        }
    }

    private function saveGroups()
    {
        if (!empty($this->input["idGroup"]) && !isset($this->input["groups"]))
            $this->input["groups"][] = ["id" => $this->input["idGroup"]];
        if (!isset($this->input["groups"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $groups = $this->input["groups"];
            $idsStr = implode(",", $idsProducts);
            $idsGroups = [];
            $i = 0;
            foreach ($groups as $group) {
                $idsGroups[] = $group["id"];
                if (!$group["idKey"])
                    foreach ($idsProducts as $idProduct)
                        $data[] = array('id_product' => $idProduct, 'id_group' => $group["id"], 'is_main' => !$i);
                $i++;
            }
            $idsGroupsStr = implode(",", $idsGroups);
            $u = new DB('shop_product_group', 'spg');
            $u->where('id_product IN (?)', $idsStr);
            if ($idsGroups)
                $u->andWhere("NOT id_group IN (?)", $idsGroupsStr);
            $u->deleteList();
            if (!empty($data))
                DB::insertList('shop_product_group', $data, true);
            foreach ($idsProducts as $idProduct) {
                $i = 0;
                foreach ($groups as $group) {
                    if ($group["idKey"]) {
                        $data = [];
                        $data["idGroup"] = $group["id"];
                        $data["id"] = $group["idKey"];
                        $data["idProduct"] = $idProduct;
                        $data["isMain"] = !$i;
                        $u = new DB('shop_product_group', 'spg');
                        $u->setValuesFields($data);
                        $u->save();
                    }
                    $i++;
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить категории товара!";
            throw new Exception($this->error);
        }
    }

    private function createDefaultOffer()
    {
        if (!$this->isNew)
            return true;

        try {
            $idOffer = null;
            $idType = !empty($this->input["idType"]) ? $this->input["idType"] : null;
            $article = !empty($this->input["article"]) ? $this->input["article"] : null;
            $idsProducts = $this->input["ids"];
            foreach ($idsProducts as $idProduct) {
                $dataOffer = ["idProduct" => $idProduct, "article" => $article];
                $u = new DB('shop_offer', 'so');
                $u->setValuesFields($dataOffer);
                $dataOffer["id"] = $idOffer = $u->save();
                if (!empty($this->input["price"]))
                    $dataOffer["price"] = $this->input["price"];
                if (!empty($this->input["count"]))
                    $dataOffer["count"] = $this->input["count"];
                (new Offer($dataOffer))->save(false);
            }
            if ($idType && $idOffer) {
                $t = new DB("shop_type_feature", "stf");
                $t->select("sf.id id_feature, sfv.id id_value");
                $t->innerJoin("shop_feature sf", "sf.id = stf.id_feature AND sf.target = 1");
                $t->innerJoin("(SELECT id, id_feature FROM shop_feature_value GROUP BY id_feature ORDER BY sort) sfv", "sfv.id_feature = sf.id");
                $items = $t->getList();
                foreach ($items as $item) {
                    $item["idOffer"] = $idOffer;
                    $u = new DB("shop_offer_feature", "sof");
                    $u->setValuesFields($item);
                    $u->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся создать параметры товара по умолчанию!";
            throw new Exception($e->getMessage());
        }
    }

    private function saveOffers()
    {
        $idsProducts = $this->input["ids"];
        $offers = $this->input["offers"];
        if (!count($offers))
            return true;

        try {
            $idsOld = [];
            foreach ($offers as $offer) {
                if (!empty($offer["id"]))
                    $idsOld[] = $offer["id"];
            }
            $idsProductsStr = implode(",", $idsProducts);
            $idsOldStr = implode(",", $idsOld);
            $t = new DB("shop_offer", "so");
            $t->where("id_product IN (?)", $idsProductsStr);
            if ($idsOldStr)
                $t->andWhere("NOT id IN (?)", $idsOldStr);
            $t->deleteList();

            foreach ($idsProducts as $idProduct) {
                foreach ($offers as $offer) {
                    $offer["idProduct"] = $idProduct;
                    $this->error = (new Offer($offer))->save(false)->getError();
                    if ($this->error)
                        throw new Exception($this->error);
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить параметры товара!";
        }
        return false;
    }

    private function saveLabels()
    {
        $labels = $this->input["labels"];
        $labelsNew = [];
        foreach ($labels as $label)
            if ($label["isChecked"])
                $labelsNew[] = $label;
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $labelsNew,
                    array("table" => "shop_label_product", "key" => "id_product", "link" => "id_label"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить ярлыки товара!";
            throw new Exception($this->error);
        }
    }


    private function getGroup53($groups, $idGroup)
    {
        if (!$idGroup)
            return null;

        foreach ($groups as $group) {
            if ($group["id"] == $idGroup)
                return $group["name"];
        }
        return null;
    }

    public function export()
    {
        $fileName = "export_products.csv";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";

        $limit = 1000;
        $offset = 0;
        $countColumns = 3;
        $columns = [];

        try {
            $stmt = DB::query("SELECT sg.id
                  FROM `shop_group` `sg` 
                  LEFT JOIN shop_group_translate tr ON sg.id = tr.id_group 
                  LEFT JOIN shop_group_tree sgt ON sgt.id_child = sg.id AND sg.id <> sgt.id_parent 
                  LEFT JOIN shop_group_tree sgtp ON sgtp.id_child = sgt.id_parent 
                  WHERE NOT sg.id IN (SELECT sgt1.id_parent FROM shop_group_tree sgt1 WHERE sgt1.id_child <> sgt1.id_parent)
                  GROUP BY sg.id 
                  ORDER BY sg.id");
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            $items = $stmt->fetchAll();
            $groups = (new Category())->fetch();
            foreach ($items as &$item) {
                foreach ($groups as $group)
                    if ($group["id"] == $item["id"]) {
                        $item["path"] = $group["name"];
                        $path = explode(" | ", $group["name"]);
                        for ($i = 0; $i < $countColumns; $i++)
                            $item["catalog" . $i] = trim($path[$i]);
                        break;
                    }
            }
            if (count($items)) {
                $fp = fopen($filePath, 'w');
                $header = array_keys($items[0]);
                $headerCSV = [];
                foreach ($header as $col) {
                    $col = iconv('utf-8', 'CP1251', $col);
                    $headerCSV[] = $col;
                }
                fputcsv($fp, $headerCSV, ";");

                foreach ($items as $row) {
                    $out = [];
                    foreach ($row as $key => $r)
                        $out[] = iconv('utf-8', 'CP1251', $r);
                    fputcsv($fp, $out, ";");
                }

                fclose($fp);
            }

            if (file_exists($filePath) && filesize($filePath)) {
                $this->result['url'] = $urlFile;
                $this->result['name'] = $fileName;
            } else throw new Exception();
        } catch (Exception $e) {
            $this->error = "Не удаётся экспортировать товары!";
            throw new Exception($this->error);
        }
    }

}

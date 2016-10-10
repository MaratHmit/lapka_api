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
            "select" => 'sp.*, tr.name name, so.article article,
                sop.value price, sbt.name name_brand, sgt.name name_group,
                CONCAT(f.name, "/", img.name) image_path, spg.id_group id_group',
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
            $u->select('sfg.id id_group, sfg_tr.name group_name, sf_tr.name,
						sf.type, spf.value, sfv.color, sfg.sort index_group');
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
        $u->where('`type` = 2');
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
        $u->where('`type` = 1');
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

    protected function saveAddInfo()
    {
        if (!$this->input["ids"])
            return false;

        return $this->createDefaultOffer() && $this->saveListImages() && $this->saveGroups() &&
            $this->saveOffers();
    }

    private function saveSpecifications()
    {
        if (!isset($this->input["specifications"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $isAddSpecifications = $this->input["isAddSpecifications"];
            $specifications = $this->input["specifications"];
            $idsStr = implode(",", $idsProducts);
            if (!$isAddSpecifications) {
                $u = new DB('shop_modifications_feature', 'smf');
                $u->where('id_modification IS NULL AND id_price IN (?)', $idsStr)->deleteList();
            }

            $m = new DB('shop_modifications_feature', 'smf');
            $m->select('id');
            foreach ($specifications as $specification) {
                foreach ($idsProducts as $idProduct) {
                    if ($isAddSpecifications) {
                        if (is_string($specification["valueString"]))
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
							           value_string = '{$specification["valueString"]}'");
                        if (is_bool($specification["valueBool"]))
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
							           value_bool = '{$specification["valueBool"]}'");
                        if (is_numeric($specification["valueNumber"]))
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
							           value_number = '{$specification["valueNumber"]}'");
                        if (is_numeric($specification["idValue"]))
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND 
									   id_value = {$specification["idValue"]}");
                        $result = $m->fetchOne();
                        if ($result["id"])
                            continue;
                    }
                    if ($specification["type"] == "number")
                        $specification["valueNumber"] = $specification["value"];
                    elseif ($specification["type"] == "string")
                        $specification["valueString"] = $specification["value"];
                    elseif ($specification["type"] == "bool")
                        $specification["valueBool"] = $specification["value"];
                    elseif (empty($specification["idValue"]))
                        continue;
                    $data[] = array('id_price' => $idProduct, 'id_feature' => $specification["idFeature"],
                        'id_value' => $specification["idValue"],
                        'value_number' => $specification["valueNumber"],
                        'value_bool' => $specification["valueBool"], 'value_string' => $specification["valueString"]);
                }
            }
            if (!empty($data))
                DB::insertList('shop_modifications_feature', $data);
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
            $idsProducts = $this->input["ids"];
            $products = $this->input["similarProducts"];
            $idsExists = array();
            foreach ($products as $p)
                if ($p["id"])
                    $idsExists[] = $p["id"];
            $idsExists = array_diff($idsExists, $idsProducts);
            $idsExistsStr = implode(",", $idsExists);
            $idsStr = implode(",", $idsProducts);
            $u = new DB('shop_sameprice', 'ss');
            if ($idsExistsStr)
                $u->where("((NOT id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR 
                           ((NOT id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr)->deleteList();
            else $u->where('id_price IN (?) OR id_acc IN (?)', $idsStr)->deleteList();
            $idsExists = array();
            if ($idsExistsStr) {
                $u->select("id_price, id_acc");
                $u->where("((id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR 
                            ((id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr);
                $objects = $u->getList();
                foreach ($objects as $item) {
                    $idsExists[] = $item["idAcc"];
                    $idsExists[] = $item["idPrice"];
                }
            };
            $data = array();
            foreach ($products as $p)
                if (empty($idsExists) || !in_array($p["id"], $idsExists))
                    foreach ($idsProducts as $idProduct)
                        $data[] = array('id_price' => $idProduct, 'id_acc' => $p["id"]);
            if (!empty($data))
                DB::insertList('shop_sameprice', $data);
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
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["accompanyingProducts"],
                    array("table" => "shop_accomp", "key" => "id_price", "link" => "id_acc"));
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
            $u = new DB('shop_comm', 'sc');
            $u->where('id_price IN (?)', $idsStr)->deleteList();
            foreach ($comments as $c) {
                $showing = 'N';
                $isActive = 'N';
                if ($c["isShowing"])
                    $showing = 'Y';
                if ($c["isActive"])
                    $isActive = 'Y';
                foreach ($idsProducts as $idProduct)
                    $data[] = array('id_price' => $idProduct, 'date' => $c["date"], 'name' => $c["name"],
                        'email' => $c["email"], 'commentary' => $c["commentary"], 'response' => $c["response"],
                        'showing' => $showing, 'is_active' => $isActive);
            }
            if (!empty($data))
                DB::insertList('shop_comm', $data);
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
            $u = new DB('shop_reviews');
            if (!$idsExists)
                $u->where('id_price IN (?)', $idsStr)->deleteList();
            else $u->where("NOT id IN ({$idsExists}) AND id_price IN (?)", $idsStr)->deleteList();
            foreach ($reviews as $review) {
                foreach ($idsProducts as $idProduct) {
                    $review["idPrice"] = $idProduct;
                    $u = new DB('shop_reviews');
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

    private function saveCrossGroups()
    {
        if (!isset($this->input["crossGroups"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $groups = $this->input["crossGroups"];
            $idsStr = implode(",", $idsProducts);
            if (CORE_VERSION == "5.3") {
                $u = new DB('shop_price_group', 'spg');
                $u->where('NOT is_main AND id_price in (?)', $idsStr)->deleteList();
                foreach ($groups as $group)
                    foreach ($idsProducts as $idProduct)
                        $data[] = array('id_price' => $idProduct, 'id_group' => $group["id"], 'is_main' => 0);
                if (!empty($data)) {
                    DB::insertList('shop_price_group', $data);
                }
            } else
                foreach ($idsProducts as $id)
                    DB::saveManyToMany($id, $groups,
                        array("table" => "shop_group_price", "key" => "price_id", "link" => "group_id"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить дополнительные категории товара!";
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
                    array("table" => "shop_discount_links", "key" => "id_price", "link" => "discount_id"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить скидки товара!";
            throw new Exception($this->error);
        }
    }

    private function saveGroups()
    {
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
                DB::insertList('shop_product_group', $data);
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

        $idType = !empty($this->input["idType"]) ? $this->input["idType"] : null;
        if (!empty($this->input["idGroup"]) && empty($idType)) {

        }

        $idsProducts = $this->input["ids"];
        foreach ($idsProducts as $idProduct) {
            $offer = ["idProduct" => $idProduct];
            $u = new DB('shop_offer', 'so');
            $u->setValuesFields($offer);
            $offer["idOffer"] = $u->save();
        }
        return true;
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

        //        $u = new DB('shop_offer', 'so');
//        $u->select('so.*, sop.value price,
//            (SELECT SUM(sfs.value) FROM shop_warehouse_stock sfs WHERE sfs.id_offer = so.id GROUP BY sfs.id_offer) count,
//            GROUP_CONCAT(CONCAT_WS("\t", sof.id_feature, sft.name, sfv.id, sfv.value) SEPARATOR "\n") params');
//        $u->leftJoin('shop_offer_price sop', "sop.id_offer = so.id AND sop.id_typeprice = {$this->idTypePrice}");
//        $u->leftJoin('shop_offer_feature sof', 'sof.id_offer = so.id');
//        $u->leftJoin('shop_feature_translate sft', 'sft.  id_feature = sof.id_feature');
//        $u->leftJoin('shop_feature_value sfv', 'sfv.id = sof.id_value');
//        $u->where('so.id_product = ?', $id);
//        $u->groupBy('so.id');

    }

    private function getGroup($groups, $idGroup)
    {
        if (!$idGroup)
            return null;

        foreach ($groups as $group) {
            if ($group["id"] == $idGroup) {
                if ($group['upid'])
                    return $this->getGroup($groups, $group['upid']) . "/" . $group["name"];
                else return $group["name"];
            }
        }
        return null;
    }

}

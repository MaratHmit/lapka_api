<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Base
{
    protected $result;
    protected $input;
    protected $error;
    protected $statusAnswer = 200;
    protected $isTableMode = true;
    protected $limit = 100;
    protected $offset = 0;
    protected $sortBy = "id";
    protected $sortOrder = "desc";
    protected $sortFieldName = "sort";
    protected $availableFields;
    protected $filterFields;
    protected $protocol = 'http';
    protected $search;
    protected $filters = [];
    protected $hostname;
    protected $urlImages;
    protected $dirImages = "images";
    protected $tableName;
    protected $tableAlias;
    protected $imageSize = 256;
    protected $imagePreviewSize = 64;
    protected $allowedSearch = true;
    protected $availableSigns = array("=", "<=", "<", ">", ">=", "IN");
    protected $isNew;
    protected $idLang = 1;

    private $patterns = [];

    function __construct($input = null)
    {
        $this->idLang = empty($_SESSION["idLang"]) ? $this->idLang : $_SESSION["idLang"];
        $input = empty($input) || is_array($input) ? $input : json_decode($input, true);
        $input["idLang"] = $this->idLang;
        $this->input = $input;
        $this->hostname = HOSTNAME;
        $this->limit = !empty($input["limit"]) && $this->limit ? (int)$input["limit"] : $this->limit;
        $this->offset = !empty($input["offset"]) ? (int)$input["offset"] : $this->offset;
        $this->sortOrder = !empty($input["sortOrder"]) ? $input["sortOrder"] : $this->sortOrder;
        $this->sortBy = !empty($input["sortBy"]) ? $input["sortBy"] : $this->sortBy;
        $this->search = !empty($input["searchText"]) && $this->allowedSearch ? $input["searchText"] : null;
        $this->filters = empty($this->input["filters"]) || !is_array($this->input["filters"]) ?
            [] : $this->input["filters"];
        if (!empty($this->input["id"]) && empty($this->input["ids"]))
            $this->input["ids"] = array($this->input["id"]);
        $this->isNew = empty($this->input["id"]) && empty($this->input["ids"]);
        if (empty($this->tableAlias) && !empty($this->tableName)) {
            $worlds = explode("_", $this->tableName);
            foreach ($worlds as $world)
                $this->tableAlias .= $world[0];
        }
    }

    public function init()
    {

    }

    function __set($name, $value)
    {
        if (is_array($this->input))
            $this->input[$name] = $value;
    }

    function __get($name)
    {
        if (is_array($this->input) && isset($this->input[$name]))
            return $this->input[$name];
    }

    public function initConnection($connection)
    {
        try {
            DB::initConnection($connection);
            $this->init();
            return true;
        } catch (Exception $e) {
            $this->error = 'Не удаётся подключиться к базе данных!';
            return false;
        }
    }

    public function output()
    {
        if (!empty($this->error) && $this->statusAnswer == 200)
            $this->statusAnswer = 500;
        switch ($this->statusAnswer) {
            case 200: {
                echo json_encode($this->result);
                exit;
            }
            case 404: {
                header("HTTP/1.1 404 Not found");
                echo $this->error;
                exit;
            }
            case 500: {
                header("HTTP/1.1 500 Internal Server Error");
                echo $this->error;
                exit;
            }
        }
    }

    public function getError()
    {
        return $this->error;
    }

    public function setFilters($filters)
    {
        $this->filters = empty($filters) || !is_array($filters) ? [] : $filters;
    }

    private function createTableForInfo($settings)
    {
        $u = new DB($this->tableName, $this->tableAlias);
        $u->select($settings["select"]);

        if (!empty($settings["joins"])) {
            if (!empty($settings["joins"]["type"]))
                $settings["joins"] = array($settings["joins"]);
            foreach ($settings["joins"] as $join) {
                $join["type"] = strtolower(trim($join["type"]));
                if ($join["type"] == "inner")
                    $u->innerJoin($join["table"], $join["condition"]);
                if ($join["type"] == "left")
                    $u->leftJoin($join["table"], $join["condition"]);
                if ($join["type"] == "right")
                    $u->rightJoin($join["table"], $join["condition"]);
            }
        }
        return $u;
    }

    public function fetch()
    {
        $settingsFetch = $this->getSettingsFetch();
        $settingsFetch["select"] = $settingsFetch["select"] ? $settingsFetch["select"] : "*";
        $this->patterns = $this->getPatternsBySelect($settingsFetch["select"]);
        try {
            $u = $this->createTableForInfo($settingsFetch);
            $searchFields = $u->getFields();
            if (!empty($this->patterns)) {
                $this->sortBy = key_exists($this->sortBy, $this->patterns) ?
                    $this->patterns[$this->sortBy] : $this->sortBy;
                foreach ($this->patterns as $key => $field)
                    $searchFields[$key] = array("Field" => $field, "Type" => "text");
            }
            if (!empty($this->search) || !empty($this->filters))
                $u->where($this->getWhereQuery($searchFields));
            $u->groupBy();
            $u->orderBy($this->sortBy, $this->sortOrder == 'desc');
            $this->result["items"] = $this->correctValuesBeforeFetch($u->getList($this->limit, $this->offset));
            $this->result["count"] = $u->getListCount();
            if (!empty($settingsFetch["aggregation"])) {
                if (!empty($settingsFetch["aggregation"]["type"]))
                    $settingsFetch["aggregation"] = array($settingsFetch["aggregation"]);
                foreach ($settingsFetch["aggregation"] as $aggregation) {
                    $query = "{$aggregation["type"]}({$aggregation["field"]})";
                    $this->result[$aggregation["name"]] = $u->getListAggregation($query);
                }
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список объектов!";
        }

        return $this->result["items"];
    }

    public function info($id = null)
    {
        $id = empty($id) ? $this->input["id"] : $id;
        $this->input["id"] = $id;
        $settingsInfo = $this->getSettingsInfo();
        try {
            $u = $this->createTableForInfo($settingsInfo);
            $this->result = $u->getInfo($id);
            if (!$this->result["id"]) {
                $this->error = "Объект с запрошенными данными не найден!";
                $this->statusAnswer = 404;
            } else {
                if ($addInfo = $this->getAddInfo()) {
                    $this->result = array_merge($this->result, $addInfo);
                }
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию об объекте!";
        }
        return $this->result;
    }

    protected function getAddInfo()
    {
        return [];
    }

    public function delete()
    {
        try {
            if ($this->input["ids"] && !empty($this->tableName)) {
                $ids = implode(",", $this->input["ids"]);

                $u = new DB($this->tableName);
                $u->where('id IN (?)', $ids)->deleteList();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся произвести удаление!";
        }
    }

    protected function saveTranslate($tableName, $data)
    {
        try {
            $translateTable = "{$tableName}_translate";
            if (DB::existTable($translateTable)) {
                $t = new DB($translateTable);
                $linkName = $t->getColumns()[1];
                $data[$linkName] = $data["id"];
                unset($data["id"]);
                unset($data["ids"]);
                $t->where("{$linkName} = ?", $data[$linkName]);
                $t->andWhere('id_lang = ?', $this->idLang);
                $result = $t->fetchOne();
                if ($result["id"])
                    $data["id"] = $result["id"];
                $t->setValuesFields($data);
                $t->save();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить информацию о наименование объекта";
        }
    }

    public function save($isTransactionMode = true)
    {
        try {
            $this->correctValuesBeforeSave();
            if ($isTransactionMode)
                DB::beginTransaction();
            $u = new DB($this->tableName);
            if (isset($this->input["imagePath"]))
                $this->input["idImage"] = $this->saveImage();
            if ($this->isNew && !isset($this->input[$this->sortFieldName]) &&
                in_array($this->sortFieldName, $u->getColumns())
            )
                $this->input[$this->sortFieldName] = $this->getNewSortIndex();
            if (($this->isNew || isset($this->input["url"])) &&
                in_array("url", $u->getColumns())
            ) {
                if (empty($this->input["url"]))
                    $this->input["url"] = $this->transliterationUrl($this->input["name"]);
                $this->input["url"] = $this->getUrl($this->input["url"], $this->input["id"]);
            }
            $u->setValuesFields($this->input);
            $this->input["id"] = $u->save();
            if ($this->input["id"])
                $this->saveTranslate($this->tableName, $this->input);
            if (empty($this->input["ids"]) && $this->input["id"])
                $this->input["ids"] = array($this->input["id"]);
            if ($this->input["id"] && $this->saveAddInfo()) {
                $this->info();
                if ($isTransactionMode)
                    DB::commit();
                return $this;
            } else throw new Exception("Не удаётся сохранить дополнительную информацию!");
        } catch (Exception $e) {
            if ($isTransactionMode)
                DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся сохранить информацию об объекте!" : $this->error;
        }
        return $this;
    }

    public function getIdImageFolder($dir)
    {
        $dir = trim($dir, "/");
        if (empty($dir))
            return null;

        $u = new DB("image_folder", "imf");
        $u->where("name = '?'", $dir);
        $result = $u->fetchOne();
        if (empty($result["id"])) {
            $u = new DB("image_folder");
            $u->setValuesFields(["name" => $dir]);
            return $u->save();
        }
        return $result["id"];
    }

    public function saveImage($imagePath = null)
    {
        $imagePath = empty($imagePath) ? $this->input["imagePath"] : $imagePath;
        if (empty($imagePath))
            return null;

        $file = basename($imagePath);
        $dir = dirname($imagePath);
        $idFolder = $this->getIdImageFolder($dir);
        $u = new DB("image");
        $u->where("name = '?'", $file);
        if ($idFolder)
            $u->andWhere("id_folder = ?", $idFolder);
        else $u->andWhere("id_folder IS NULL");
        $result = $u->fetchOne();
        if (empty($result["id"])) {
            $u = new DB("image");
            $u->setValuesFields(["name" => $file, "idFolder" => $idFolder]);
            return $u->save();
        }
        return $result["id"];
    }
    
    public function saveListImages($tableImages = null, $fieldLinkName = null)
    {
        $tableImages = empty($tableName) ? $this->tableName . "_image" : $tableImages;
        $images = $this->input["images"];
        if (!isset($images) || !DB::existTable($tableImages))
            return true;
        
        try {
            if (empty($fieldLinkName)) {
                $t = new DB("{$tableImages}");
                $fieldLinkName = $t->getColumns()[1];
            }
            $idsItems = $this->input["ids"];
            $idsStore = "";
            foreach ($images as $image) {
                if ($image["id"]) {
                    if (!empty($idsStore))
                        $idsStore .= ",";
                    $idsStore .= $image["id"];
                    $u = new DB("{$tableImages}", 'si');
                    $u->setValuesFields($image);
                    $u->save();
                }
                if (!empty($image["idImage"])) {
                    if ($image["idTranslate"])
                        $data["id"] = $image["idTranslate"];
                    else {
                        $data["idImage"] = $image["idImage"];
                        $data["idLang"] = $this->idLang;
                    }
                    $data["alt"] = $image["alt"];
                    $data["title"] = $image['title'];
                    $u = new DB('image_translate');
                    $u->setValuesFields($data);
                    $u->save();
                }
            }

            $idsStr = implode(",", $idsItems);
            if (!empty($idsStore)) {
                $u = new DB("{$tableImages}", 'sgi');
                $u->where("{$fieldLinkName} IN ($idsStr) AND NOT (id IN (?))", $idsStore)->deleteList();
            } else {
                $u = new DB("{$tableImages}", 'sgi');
                $u->where("{$fieldLinkName} IN (?)", $idsStr)->deleteList();
            }

            $data = [];
            $i = 0;
            foreach ($images as $image)
                if (empty($image["id"])) {
                    foreach ($idsItems as $idItem) {
                        if ($idImage = $this->saveImage($image["imagePath"]))
                            $data[] = ["{$fieldLinkName}" => $idItem, 'id_image' => $idImage, 'sort' => (int)$image["sort"],
                                'is_main' => isset($image["isMain"]) ? (bool) $image["isMain"] : !$i++];
                        $dataTranslate[] = ['id_image' => $idImage, 'id_lang' => $this->idLang,
                            'title' => $image["title"], 'alt' => $image["alt"]];
                    }
                }

            if (!empty($data))
                DB::insertList("{$tableImages}", $data);
            if (!empty($dataTranslate))
                DB::insertList('image_translate', $dataTranslate);

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить изображения!";
            throw new Exception($this->error);
        }
    }

    public function sort()
    {
        if (empty($this->tableName))
            return;

        try {
            $sortIndexes = $this->input["indexes"];
            foreach ($sortIndexes as $index) {
                $u = new DB($this->tableName);
                $index["position"] = $index["sort"];
                $u->setValuesFields($index);
                $u->save();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся произвести сортировку элементов!";
        }
    }

    protected function getNewSortIndex()
    {
        $u = new DB($this->tableName);
        $u->select("MAX({$this->sortFieldName}) sort");
        $result = $u->fetchOne();
        return ++$result["sort"];
    }

    protected function correctValuesBeforeSave()
    {
        return true;
    }

    protected function correctValuesBeforeFetch($items = [])
    {
        foreach ($items as &$item) {
            if (!empty($item["imagePath"])) {
                $item["imageUrl"] = $this->protocol . "://" . HOSTNAME . "/" . $this->dirImages . "/" . $item["imagePath"];
                $item["imageUrlPreview"] = $item["imageUrl"];
            }
        }
        return $items;
    }

    protected function saveAddInfo()
    {
        return true;
    }

    protected function getSettingsFetch()
    {
        $result = [];
        $select = ["`{$this->tableAlias}`.*"];
        $joins = [];
        if ($translate = $this->getSettingsFetchTranslate()) {
            $select[] = $translate["select"];
            $joins = array_merge($joins, $translate["joins"]);
        }
        if ($image = $this->getSettingsFetchImage()) {
            $select[] = $image["select"];
            $joins = array_merge($joins, $image["joins"]);
        }
        if ($select)
            $result["select"] = implode(", ", $select);
        if ($joins)
            $result["joins"] = $joins;

        return $result;
    }

    protected function getSettingsFetchTranslate()
    {
        $translateTable = "{$this->tableName}_translate";
        if (DB::existTable($translateTable)) {
            $alias = DB::getAliasByTableName($translateTable);
            $t = new DB($translateTable);
            $columns = $t->getColumns();
            $linkName = $columns[1];
            $selectFields = [];
            for ($i = 3; $i < count($columns); ++$i)
                $selectFields[] = "{$alias}.{$columns[$i]} `{$columns[$i]}`";
            $selectFields = implode(", ", $selectFields);
            return [
                "select" => "{$selectFields}",
                "joins" => [
                    [
                        "type" => "left",
                        "table" => "{$translateTable} {$alias}",
                        "condition" => "{$alias}.{$linkName} = {$this->tableAlias}.id AND {$alias}.id_lang = {$this->idLang}"
                    ]
                ]
            ];
        }

        return [];
    }

    protected function getSettingsFetchImage()
    {
        $columns = (new DB($this->tableName))->getColumns();
        if (!in_array("id_image", $columns))
            return [];

        return [
            "select" => "CONCAT('/', IF(img_fld.name IS NULL, '', CONCAT(img_fld.name, '/')), img.name) image_path",
            "joins" => [
                [
                    "type" => "left",
                    "table" => 'image img',
                    "condition" => "{$this->tableAlias}.id_image = img.id"
                ],
                [
                    "type" => "left",
                    "table" => 'image_folder img_fld',
                    "condition" => 'img.id_folder = img_fld.id'
                ]
            ]
        ];
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    protected function getPatternsBySelect($selectQuery)
    {
        $result = [];
        preg_match_all('/\w+[.]+\w+\s\w+/', $selectQuery, $matches);
        if (count($matches) && count($matches[0])) {
            foreach ($matches[0] as $match) {
                $match = explode(" ", $match);
                if (count($match) == 2) {
                    $key = DB::strToCamelCase($match[1]);
                    $result[$key] = $match[0];
                }
            }
        }
        return $result;
    }

    protected function getSearchQuery($searchFields = [])
    {
        $result = [];
        $searchItem = trim($this->search);
        if (empty($searchItem))
            return $result;
        if (is_string($searchItem))
            $searchItem = trim(DB::quote($searchItem), "'");

        foreach ($searchFields as $field) {
            if (strpos($field["Field"], ".") === false)
                $field["Field"] = $this->tableAlias . "." . $field["Field"];
            $time = strtotime($searchItem);
            // текст
            if ((strpos($field["Type"], "char") !== false) || (strpos($field["Type"], "text") !== false)) {
                $result[] = "{$field["Field"]} LIKE '%{$searchItem}%'";
                continue;
            }
            // дата
            if ($field["Type"] == "date") {
                if ($time) {
                    $searchItem = date("Y-m-d", $time);
                    $result[] = "{$field["Field"]} = '$searchItem'";
                }
                continue;
            }
            // время
            if ($field["Type"] == "time") {
                if ($time) {
                    $searchItem = date("H:i:s", $time);
                    $result[] = "{$field["Field"]} = '$searchItem'";
                }
                continue;
            }
            // дата и время
            if ($field["Type"] == "datetime") {
                if ($time) {
                    $searchItem = date("Y-m-d H:i:s", $time);
                    $result[] = "{$field["Field"]} = '$searchItem'";
                }
                continue;
            }
            // число
            if (strpos($field["Type"], "int") !== false && is_int($searchItem)) {
                $result[] = "{$field["Field"]} = {$searchItem}";
                continue;
            }
        }
        return implode(" OR ", $result);
    }

    protected function getFilterQuery()
    {
        $where = [];
        $filters = [];
        if (!empty($this->filters["field"]))
            $filters[] = $this->filters;
        else $filters = $this->filters;
        foreach ($filters as $filter) {
            if (key_exists($filter["field"], $this->patterns))
                $field = $this->patterns[$filter["field"]];
            else {
                $field = DB::strToUnderscore($filter["field"]);
                $field = $this->tableAlias . ".`{$field}`";
            }
            $sign = empty($filter["sign"]) || !in_array($filter["sign"], $this->availableSigns) ?
                "=" : $filter["sign"];
            if ($sign == "IN") {
                $values = explode(",", $filter["value"]);
                $filter['value'] = null;
                foreach ($values as $value) {
                    if ($filter['value'])
                        $filter['value'] .= ",";
                    $value = trim($value);
                    $filter['value'] .= "'{$value}'";
                }
                $value = "({$filter['value']})";
            } else $value = !isset($filter["value"]) ? null : "'{$filter['value']}'";
            if (!$field || !$value)
                continue;
            $where[] = "{$field} {$sign} {$value}";
        }
        return implode(" AND ", $where);
    }

    protected function getWhereQuery($searchFields = [])
    {
        $query = null;
        $searchQuery = $this->getSearchQuery($searchFields);
        $filterQuery = $this->getFilterQuery();
        if ($searchQuery)
            $query = $searchQuery;
        if ($filterQuery) {
            if (!empty($query))
                $query = "({$query}) AND ";
            $query .= $filterQuery;
        }
        return $query;
    }

    public function getArrayFromCsv($file, $csvSeparator = ";")
    {
        if (!file_exists($file))
            return null;

        $result = [];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $i = 0;
            $keys = [];
            while (($row = fgetcsv($handle, 10000, $csvSeparator)) !== FALSE) {
                if (!$i) {
                    foreach ($row as &$item)
                        $keys[] = iconv('CP1251', 'utf-8', $item);
                } else {
                    $object = [];
                    $j = 0;
                    foreach ($row as &$item) {
                        $object[$keys[$j]] = iconv('CP1251', 'utf-8', $item);
                        $j++;
                    }
                    $result[] = $object;
                }
                $i++;
            }
            fclose($handle);
        }
        return $result;
    }

    public function post()
    {
        $countFiles = count($_FILES);
        $ups = 0;
        $items = [];
        $dir = DOCUMENT_ROOT . "/files";
        $url = !empty($_POST["url"]) ? $_POST["url"] : null;
        if (!file_exists($dir) || !is_dir($dir))
            mkdir($dir);

        if ($url) {
            $content = file_get_contents($url);
            if (empty($content)) {
                $this->error = "Не удается загрузить данные по указанному URL!";
            } else {
                $items[] = array("url" => $url, "name" => array_pop(explode("/", $url)));
                $this->result['items'] = $items;
            }
        } else {
            for ($i = 0; $i < $countFiles; $i++) {
                $file = empty($_FILES["file"]) ? $_FILES["file$i"]['name'] : $_FILES["file"]['name'];
                $uploadFile = $dir . '/' . $file;
                $fileTemp = empty($_FILES["file"]) ? $_FILES["file$i"]['tmp_name'] : $_FILES["file"]['tmp_name'];
                $urlFile = 'http://' . HOSTNAME . "/files/{$file}";
                if (!filesize($fileTemp) || move_uploaded_file($fileTemp, $uploadFile)) {
                    $items[] = array("url" => $urlFile, "name" => $file);
                    $ups++;
                }
            }
            if ($ups == $countFiles)
                $this->result['items'] = $items;
            else $this->error = "Не удается загрузить файлы!";
        }

        return $items;
    }

    public function getUrl($url, $id = null)
    {
        $urlNew = $url;
        $id = (int)$id;
        $u = new DB($this->tableName);
        $i = 1;
        while ($i < 10) {
            $data = $u->findList("url = '$urlNew' AND id <> {$id}")->fetchOne();
            if ($data["id"])
                $urlNew = $url . "-$i";
            else return $urlNew;
            $i++;
        }
        return uniqid();
    }

    public function rusToTransliteration($string)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ь' => "", 'ы' => 'y', 'ъ' => "",
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V',
            'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
            'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'I', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R',
            'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ь' => "", 'Ы' => 'Y', 'Ъ' => "",
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            '«' => '', '»' => '', '"' => '',
            '`' => '', '\'' => '',
        );
        return strtr($string, $converter);
    }

    public function transliterationUrl($string)
    {
        $string = str_replace(array(' ', '*', '–', '°', '№', '%20', ',', '.', '!', '?', '&', '(', ')', '<', '>', '{', '}', ' ', '_', '/', ':'), '-', $string);
        $string = str_replace(array('`', "'", "’"), '', $string);

        $result = $str = preg_replace('~[^-A-Za-z0-9_]+~u', '-', $this->rusToTransliteration($string));
        while (strpos($result, '--') !== false) {
            $result = str_replace('--', '-', $result);
        }
        if (strlen($result)) {
            if (substr($result, 0, 1) == '-') {
                $result = substr($result, 1, strlen($result) - 1);
            }
            if (substr($result, strlen($result) - 1, 1) == '-') {
                $result = substr($result, 0, strlen($result) - 1);
            }
        }
        return strtolower($result);
    }

}
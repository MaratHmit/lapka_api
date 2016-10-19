<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Import extends Base
{
    private $encoding;
    private $separator;
    private $dirFiles;
    private $maxHeaderRows = 25;
    private $catalogCols = [
        ["title" => "Корневая категория", "name" => "catalog0"],
        ["title" => "Подкатегория 1", "name" => "catalog1"],
        ["title" => "Подкатегория 2", "name" => "catalog2"],
        ["title" => "Подкатегория 3", "name" => "catalog3"],
        ["title" => "Подкатегория 4", "name" => "catalog4"]
    ];
    private $productsCols = [
        ["title" => "Артикул", "name" => "article"],
        ["title" => "Название товара", "name" => "name"],
        ["title" => "Цена продажи"],
        ["title" => "Цена закупки"],
        ["title" => "Остаток"],
        ["title" => "Вес (гр.)"],
        ["title" => "Скидка"],
        ["title" => "Краткое описание"],
        ["title" => "Полное описание"],
        ["title" => "Изображение"],
        ["title" => "Тег title"],
        ["title" => "Мета-тег keywords"],
        ["title" => "Мета-тег description"]
    ];
    private $featureCols = [];

    function __construct($input)
    {
        parent::__construct($input);
        $this->dirFiles = DOCUMENT_ROOT . "/files";
        $this->encoding = $_POST["encoding"];
        $this->separator = $_POST["separator"];
    }

    public function post()
    {
        $items = parent::post();
        if (count($items))
            $this->result = array_merge(["encoding" => $this->encoding, "separator" => $this->separator],
                $this->getFileFields($items[0]["name"]));
    }

    public function exec()
    {

    }

    private function getFileFields($fileName)
    {
        $filePath = "{$this->dirFiles}/{$fileName}";
        $ext = end(explode(".", $fileName));
        if ($ext != "csv")
            $filePath = $this->getConvertFile($filePath);
        $str = file_get_contents($filePath);
        $this->encoding = $this->encoding == "auto" ? mb_detect_encoding($str, "auto") : $this->encoding;
        $this->separator = $this->separator == "auto" ? $this->getSeparator($filePath) : $this->separator;
        $fields = $this->getFields();
        $result["fileImport"] = basename($filePath);
        $result["cols"] = $this->getColsFromCsv($filePath, $fields);
        return $result;
    }

    private function getConvertFile($filePath)
    {
        $ext = end(explode(".", $filePath));
        $typeDoc = $ext == "xls" ? 'Excel5' : 'Excel2007';
        $reader = \PHPExcel_IOFactory::createReader($typeDoc);
        $reader->setReadDataOnly(true);
        $excel = $reader->load($filePath);
        $fileCSV = str_replace(".{$ext}", ".csv", $filePath);
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'CSV');
        $writer->save($fileCSV);
        return $fileCSV;
    }

    private function getSeparator($file)
    {
        $handle = fopen($file, "r");
        $i = 0;
        $buffer = null;
        while (!feof($handle) && $i++ < $this->maxHeaderRows) {
            $buffer = fgets($handle, 4096);
        }
        fclose($handle);
        $countTab = substr_count($buffer, "\t");
        $countSemicolon = substr_count($buffer, ";");
        $countComma = substr_count($buffer, ",");
        $max = max([$countTab, $countSemicolon, $countComma]);
        if ($max == $countTab)
            return "\t";
        if ($max == $countSemicolon)
            return ";";
        return ",";
    }

    private function getColsFromCsv($file, $fields)
    {
        $count = 0;
        if (($handle = fopen($file, "r")) !== false) {
            $i = 0;
            while (($row = fgetcsv($handle, 16000, $this->separator)) !== false && $i++ < $this->maxHeaderRows) {
                if (count($row) > $count)
                    $count = count($row);
            }
        }
        fclose($handle);
        $cols = [];
        for ($i = 0; $i < $count; $i++)
            $cols[] = ["id" => $i, "title" => "Столбец № {$i}", "fields" => $fields];
        return $cols;
    }

    private function getFields()
    {
        $this->featureCols = $this->getFeatureCols();
        $result = [
            ["title" => "Категория", "items" => $this->catalogCols],
            ["title" => "Товар", "items" => $this->productsCols],
            ["title" => "Свойства", "items" => $this->featureCols]
        ];

        return $result;
    }

    private function getFeatureCols()
    {
        $result = [];
        $t = new DB("shop_feature", "sf");
        $t->select("sft.name");
        $t->innerJoin("shop_feature_translate sft", "sft.id_feature = sf.id");
        $items = $t->getList();
        foreach ($items as $item)
            $result[] = ["title" => $item["name"]];
        return $result;
    }
}
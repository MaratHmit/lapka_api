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
    private $catalogCols = ["Корневая категория", "Подкатегория 1", "Подкатегория 2", "Подкатегория 3", "Подкатегория 4"];
    private $productsCols = ["Артикул", "Название товара", "Цена продажи", "Цена закупки", "Остаток", "Вес (гр.)", "Скидка",
        "Краткое описание", "Полное описание", "Изображение", "Тег title", "Мета-тег keywords", "Мета-тег description"];
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
            $this->result = $this->getFileFields($items[0]["name"]);
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
        $result["fileName"] = basename($filePath);
        $result["count"] = $this->getCountColsFromCsv($filePath);
        $result["fields"] = $this->getFields();
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

    private function getCountColsFromCsv($file)
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
        return $count;
    }

    private function getFields()
    {
        $this->featureCols = $this->getFeatureCols();
        $result = ["Категория" => $this->catalogCols, "Товар" => $this->productsCols, "Свойства" => $this->featureCols];

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
            $result[] = $item["name"];
        return $result;
    }
}
<?php

namespace SE\Shop;

use SE\DB as DB;

class ImageFolder extends Base
{
    protected $tableName = 'image_folder';
    protected $tableAlias = 'f';

    private $imagesFolder = "images";

    public function fetch()
    {
        $protocol = $this->protocol;

        $this->input["path"] = empty($this->input["path"]) ? "/" : $this->input["path"];
        $path = DOCUMENT_ROOT . "/" . $this->imagesFolder . $this->input["path"];
        $iterator = new \RecursiveDirectoryIterator($path);
        $fileList = [];
        $fileListKey = [];
        foreach ($iterator as $entry) {
            if ($entry->getFilename() == '.' || $entry->getFilename() == '..')
                continue;
            $fileInfo["name"] = $entry->getFilename();
            $fileInfo["isDir"] = $entry->isDir();
            if (!$fileInfo["isDir"]) {
                $fileInfo["url"] = $protocol . "://" . HOSTNAME . "/" . $this->imagesFolder . $this->input["path"] . $fileInfo["name"];
                $fileInfo["urlPreview"] = $protocol . "://" . HOSTNAME . "/" . $this->imagesFolder . $this->input["path"] . $fileInfo["name"];
            }
            $fileListKey[$fileInfo["name"]] = $fileInfo;
        }
        ksort($fileListKey);
        foreach ($fileListKey as $file)
            $fileList[] = $file;
        $this->result["items"] = $fileList;
    }

    public function save()
    {
        $this->input["path"] = empty($this->input["path"]) ? "/" : $this->input["path"];
        $path = DOCUMENT_ROOT . "/" . $this->imagesFolder . $this->input["path"];
        $cmd = $this->input["cmd"] ? $this->input["cmd"] : "create";
        $name = $this->input["name"];
        if ($cmd == "create" && !empty($name)) {
            $path .= "/{$name}";
            if (!mkdir($path))
                $this->error = "Не удаётся создать папку с именем: {$name}!";
        }
        if ($cmd == "rename" && !empty($name)) {
            $newName = $path . "/" . $this->input["newName"];
            $path .= "/{$name}";
            if (!rename($path, $newName) || !$this->renameInBase($path, $newName, is_dir($newName)))
                $this->error = "Не удаётся переименовать указанный файл или папку";
        }
    }

    private function renameInBase($name, $newName, $idDir)
    {
        return true;
    }

    private function removeDirectory($dir)
    {
        if ($objList = glob($dir . "/*")) {
            foreach ($objList as $obj) {
                is_dir($obj) ? $this->removeDirectory($obj) : unlink($obj);
            }
        }
        rmdir($dir);
    }

    public function delete()
    {
        $this->input["path"] = empty($this->input["path"]) ? "/" : $this->input["path"];
        $path = DOCUMENT_ROOT . "/" . $this->imagesFolder . $this->input["path"];
        $files = $this->input["files"];
        foreach ($files as $file) {
            $file = substr($file, -1) == "/" ? $path . $file : $path . "/" . $file;
            if (!is_dir($file))
                unlink($file);
            else $this->removeDirectory($file);
        }
    }


}
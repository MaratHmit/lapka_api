<?php

namespace SE;

class Exception extends \PDOException
{
    public function __construct($message = "", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        writeLog("Exception: " . iconv("CP1251", "UTF-8", $message));
    }
}
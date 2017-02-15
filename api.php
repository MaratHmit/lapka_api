<?php

require_once API_ROOT . "vendor/autoload.php";

class Api
{

    /**
     * Вызов методов API
     * @param string $object объект API.
     * @param string $method метод API.
     * @param array $data данные для API (массив).
     * @return mixed
     */

    static public function exec($object, $method, $data = null)
    {
        try {
            if ($method == "PRINT")
                $method = "printDoc";

            $class = 'SE\Shop\\' . $object;
            if (!class_exists($class))
                throw new Exception("Запрашиваемый объект не найден!");

            $object = new $class($data);

            if ($object->initConnection()) {

                if (empty($_SESSION['isInitApi'])) {
                    $_SESSION['isInitApi'] = true;
                    $_SESSION['hostname'] = HOSTNAME;
                    $_SESSION['idLang'] = 1;
                    $_SESSION['idCurrency'] = SE\Shop\Auth::getIdCurrency();
                    $_SESSION["idTypePrice"] = SE\Shop\Auth::getIdTypePrice();
                    $_SESSION["idWarehouse"] = 1;
                }

                $object->$method();
            }
            return $object->getResult();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
<?php

class DB {

    private static $writeDBconnection;
    private static $readDBconnection;

    public static function connectWriteDB()
    {
        if (self::$writeDBconnection === null) {
            self::$writeDBconnection = new PDO('mysql:host=localhost;dbname=tasksdb;utf8','root','');
            self::$writeDBconnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$writeDBconnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return self::$writeDBconnection;
    }


    public static function connectReadDB()
    {
        if (self::$readDBconnection === null) {
            self::$readDBconnection = new PDO('mysql:host=localhost;dbname=tasksdb;utf8','root','');
            self::$readDBconnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$readDBconnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }

        return self::$readDBconnection;
    }
}


?>
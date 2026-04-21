<?php

namespace App\Config;

use PDO;

class Database {

    public static function PDO() {
        return new PDO(
            "mysql:host=db;dbname={$_ENV['DB_NAME']};charset=utf8",
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );
    }
}
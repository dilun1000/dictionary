<?php

namespace App\Models;

use App\Core\Model\Model;

use PDO;

class AppModel extends Model
{
    public function __construct()
    {
        $config = require __DIR__ . '/../../config/db.php';


        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
            $config['user'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        parent::__construct($pdo);
    }
   
}
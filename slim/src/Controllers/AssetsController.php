<?php

namespace App\Controllers;
use PDO;
use App\Config\Database;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AssetController {

    // GET ACTIVOS
    public function getAssets(Request $request, Response $response) {

        //$asset = $request->getAttribute('asset');

        $params = $request->getQueryParams();

        $name = $params['name'] ?? null;
        $min_price = $params['min_price'] ?? null;
        $max_price = $params['max_price'] ?? null;

        $sql = "SELECT * FROM assets WHERE 1=1";
        $values = [];

        if(!empty($name)) {
            $name = trim($name);
            $sql .= " AND LOWER(name) LIKE LOWER(?)";
            $search = "%$name%";
            $values[] = $search;
        }

        if($min_price !== null && is_numeric($min_price)) {
            $sql .= " AND current_price >= ?";
            $values[] = $min_price;
        }

        if($max_price !== null && is_numeric($max_price)) {
            $sql .= " AND current_price <= ?";
            $values[] = $max_price;
        }

        $pdo = Database::PDO();

        //$stmt = $pdo->query("SELECT * FROM assets");

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode($assets));
        return $response;

    }
}
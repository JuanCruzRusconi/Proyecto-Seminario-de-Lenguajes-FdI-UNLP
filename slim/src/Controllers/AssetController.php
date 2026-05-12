<?php

namespace App\Controllers;
use PDO;
use App\Config\Database;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AssetController {

    // GET ACTIVOS
    public function getAssets(Request $request, Response $response) {

        $params = $request->getQueryParams();

        $name = $params['name'] ?? null;
        $min_price = $params['min_price'] ?? null;
        $max_price = $params['max_price'] ?? null;

        $sql = "SELECT id, name, current_price FROM assets WHERE 1=1";
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
        return $response->withStatus(200);

    }

    // GET HISTORIAL ACTIVO
    public function getAssetHistory(Request $request, Response $response, $args) {
        
        $assetId = $args['asset_id'];
        $quantity = (int)$args['quantity'];

        if(!is_numeric($assetId)) {
            $response->getBody()->write(json_encode(["error" => "ID de activo inválido."]));
            return $response->withStatus(400);
        }

        if($quantity <= 0) $quantity = 1;

        $quantity = min($quantity, 5);

        $pdo = Database::PDO();

        $stmt = $pdo->prepare("SELECT transaction_type, quantity, price_per_unit, total_amount, transaction_date FROM transactions WHERE asset_id = ? ORDER BY transaction_date DESC LIMIT $quantity");
        $stmt->execute([$assetId]);

        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if(empty($history)) {
            $response->getBody()->wirte(json_encode(["error" => "No existen movimientos de ese activo."]));
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode($history));
        return $response->withStatus(200);
    }
}
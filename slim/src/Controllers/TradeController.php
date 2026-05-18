<?php
namespace App\Controllers;
use PDO;
use App\Config\Database;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class TradeController {

    // POST COMPRA
    public function postBuy(Request $request, Response $response) {

        $user = $request->getAttribute('user');

        $data = $request->getParsedBody();

        $assetId = $data['asset_id'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if(!is_numeric($assetId)) {
            $response->getBody()->write(json_encode(["error" => "ID de activo inválido."]));
            return $response->withStatus(400);
        }

        if(!is_numeric($quantity) || $quantity <= 0) {
            $response->getBody()->write(json_encode(["error" => "La cantidad debe ser mayor a 0."]));
            return $response->withStatus(400);
        }

        $quantity = (float)$quantity;

        $pdo = Database::PDO();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
            $stmt->execute([$assetId]);

            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            // VALIDAR EXISTENCIA DE ACTIVO;
            if(!$asset) {
                $pdo->rollback();

                $response->getBody()->write(json_encode(["error" => "Activo no encontrado."]));
                return $response->withStatus(404);
            }

            $currentPrice = (float)$asset['current_price'];

            $totalCost = $currentPrice * $quantity;

            // VALIDAD BALANCE DE USUARIO
            if($user['balance'] < $totalCost) {
                $pdo->rollBack();
                
                $response->getBody()->write(json_encode(["error" => "Saldo insuficiente para procesar la compra."]));
                return $response->withStatus(409);
            }

            // RESTAR BALANCE
            $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$totalCost, $user['id']]);

            // VERIFICACION DE PORTFOLIO
            $stmt = $pdo->prepare("SELECT * FROM portfolio WHERE user_id = ? AND asset_id = ?");
            $stmt->execute([$user['id'], $assetId]);

            $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);

            // ACTUALIZAR
            if($portfolio) {
                $stmt = $pdo->prepare("UPDATE portfolio SET quantity = quantity + ? WHERE user_id = ? AND asset_id = ?");
                $stmt->execute([$quantity, $user['id'], $assetId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO portfolio (user_id, asset_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $assetId, $quantity]);
            }

            // REGISTRAR TRANSACCION
            $stmt = $pdo->prepare("INSERT INTO transactions (
                user_id, asset_id, transaction_type, quantity, price_per_unit, total_amount) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user['id'], $assetId, 'buy', $quantity, $currentPrice, $totalCost]);

            $pdo->commit();

            $response->getBody()->write(json_encode([
                "mensaje" => "Compra realizada correctamente.",
                "activo" => $asset['name'],
                "cantidad" => $quantity,
                "precio unitario" => $currentPrice,
                "total" => round($totalCost, 2)
            ]));
            return $response->withStatus(200);

        } catch (PDOException $e) {
            $pdo->rollBack();

            $response->getBody()->write(json_encode([
                "error" => "Error al procesar la compra.",
                "detalle" => $e->getMessage()
            ]));
            return $response->withStatus(500);
        }
    }

    // POST VENTA
    public function postSell(Request $request, Response $response) {

        $user = $request->getAttribute('user');

        $data = $request->getParsedBody();

        $assetId = $data['asset_id'] ?? null;
        $quantity = $data['quantity'] ?? null;

        if(!is_numeric($assetId)) {
            $response->getBody()->write(json_encode(["error" => "ID de activo inválido."]));
            return $response->withStatus(400);
        }

        if(!is_numeric($quantity) || $quantity <= 0) {
            $response->getBody()->write(json_encode(["error" => "La cantidad debe ser mayor a 0."]));
            return $response->withStatus(400);
        }

        $quantity = (float)$quantity;

        $pdo = Database::PDO();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
            $stmt->execute([$assetId]);

            $asset = $stmt->fetch(PDO::FETCH_ASSOC);

            // EXISTENCIA DE ACTIVO
            if(!$asset) {
                $pdo->rollback();

                $response->getBody()->write(json_encode(["error" => "Activo no encontrado."]));
                return $response->withStatus(404);
            }

            // VERIFICAR PORTFOLIO
            $stmt = $pdo->prepare("SELECT * FROM portfolio WHERE user_id = ? AND asset_id = ?");
            $stmt->execute([$user['id'], $assetId]);

            $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);

            // VALIDAR PORTFOLIO
            if(!$portfolio) {
                $pdo->rollBack();

                $response->getBody()->write(json_encode(["error" => "No se encuentra este activo en el portfolio."]));
                return $response->withStatus(404);
            }

            // VALIDAR CANTIDAD
            if($portfolio['quantity'] < $quantity) {
                $pdo->rollBack();

                $response->getBody()->write(json_encode(["error" => "No posee el monto ingresado para realizar la venta."]));
                return $response->withStatus(409);
            }

            $currentPrice = (float)$asset['current_price'];

            // TOTAL
            $totalGain = $currentPrice * $quantity;

            // NUEVO BALANCE
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$totalGain, $user['id']]);

            // ACTUALIZAR PORTFOLIO
            $stmt = $pdo->prepare("UPDATE portfolio SET quantity = quantity - ? WHERE user_id = ? AND asset_id = ?");
            $stmt->execute([$quantity, $user['id'], $assetId]);

            // GUARDAR TRANSACCIOM
            $stmt = $pdo->prepare("INSERT INTO transactions (
                user_id, asset_id, transaction_type, quantity, price_per_unit, total_amount) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user['id'], $assetId, 'sell', $quantity, $currentPrice, $totalGain]);
            
            $pdo->commit();

            $response->getBody()->write(json_encode([
                "mensaje" => "Venta realizada correctamente.",
                "activo" => $asset['name'],
                "cantidad" => $quantity,
                "precio unitario" => $currentPrice,
                "total" => round($totalGain, 2)
            ]));
            return $response->withStatus(200);

        } catch (PDOException $e) {
            $pdo->rollBack();

            $response->getBody()->write(json_encode([
                "error" => "Error al procesar la venta.",
                "detalle" => $e->getMessage()
            ]));
            return $response->withStatus(500);
        }

    }

}
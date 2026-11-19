<?php
require '../config/db.php';

$stmt = $pdo->query(
    'SELECT p.id, p.name, p.description, p.price, p.stock, p.image, u.name AS seller '
    . 'FROM products p JOIN users u ON u.id = p.seller_id ORDER BY p.id DESC'
);
$products = array_map(static function (array $product): array {
    $product['id'] = (int) $product['id'];
    $product['price'] = (float) $product['price'];
    $product['stock'] = (int) $product['stock'];
    $product['image_url'] = $product['image'] !== '' ? APP_BASE . '/uploads/' . rawurlencode($product['image']) : null;
    return $product;
}, $stmt->fetchAll());

json_response(['data' => $products]);
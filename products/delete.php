<?php
require '../config/db.php';
require_role('seller');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit('Use POST to delete a product.');
}
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare('DELETE FROM products WHERE id = ? AND seller_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
header('Location: /EL3/seller/index.php');
exit;
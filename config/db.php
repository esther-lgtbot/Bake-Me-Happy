<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

define('APP_BASE', '/EL3');
define('COD_DELIVERY_FEE', 30.00);

$host = getenv('DB_HOST') ?: 'localhost';
$database = getenv('DB_NAME') ?: 'el3';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database connection failed. Start MySQL in XAMPP and check the database settings in config/db.php.');
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_BASE . '/auth/login.php');
        exit;
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh, and try again.');
    }
}

function save_uploaded_image(string $field): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK || $_FILES[$field]['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeds the 5 MB limit.');
    }

    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Upload a JPG, PNG, WEBP, or GIF image.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = __DIR__ . '/../uploads/' . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $destination)) {
        throw new RuntimeException('The image could not be saved. Check the uploads folder permissions.');
    }
    return $filename;
}
?>
<?php
// API bootstrap: centralize DB, auth and schema checks for APIs
header('Content-Type: application/json; charset=utf-8');
// Start session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/schema_validator.php';

$database = new Database();
$pdo = $database->getConnection();

// Fail early if DB connection missing
if ($pdo === null) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Run a quick schema validation (non-fatal for public endpoints)
// APIs that require migrations should check and exit if not present
function api_require_schema_or_exit(PDO $pdo, array $required = []) {
    $missing = validate_schema_requirements($pdo, $required);
    if (!empty($missing)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database schema not ready', 'missing' => $missing]);
        exit;
    }
}

// Helper wrappers for role enforcement
function api_require_admin(): void {
    requireRole([ROLE_ADMIN]);
}

function api_require_roles(array $roles): void {
    requireRole($roles);
}

// Export $pdo to callers
return; // leave variables in included scope

?>

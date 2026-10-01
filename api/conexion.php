<?php
/* api/conexion.php — Conexión Blindada Compatible con InfinityFree */

// 1. Manejo de Sesión Segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Cabeceras CORS y Seguridad HTTP
$origen = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origen");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Manejo de peticiones preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Carga Híbrida de Credenciales (.env con respaldo directo)
function cargarCredenciales() {
    $credenciales = [
        'DB_HOST' => 'sql206.infinityfree.com',
        'DB_USER' => 'if0_42616785',
        'DB_PASS' => 'Jpa6GHnJos8',
        'DB_NAME' => 'if0_42616785_databasesapa'
    ];

    $rutaEnv = __DIR__ . '/.env';
    if (file_exists($rutaEnv)) {
        $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas as $linea) {
            if (strpos(trim($linea), '#') === 0) continue;
            if (strpos($linea, '=') !== false) {
                list($k, $v) = explode('=', $linea, 2);
                $credenciales[trim($k)] = trim($v);
            }
        }
    }
    return $credenciales;
}

// 4. Conexión Persistente a MySQL
function db() {
    static $c = null;
    if ($c === null) {
        $creds = cargarCredenciales();
        $c = new mysqli($creds['DB_HOST'], $creds['DB_USER'], $creds['DB_PASS'], $creds['DB_NAME']);
        
        if ($c->connect_errno) {
            http_response_code(500);
            echo json_encode(['error' => 'Fallo de conexión a MySQL: ' . $c->connect_error]);
            exit;
        }
        $c->set_charset('utf8mb4');
    }
    return $c;
}

// 5. Helpers Globales de Entrada y Salida
function salida($data) {
    echo json_encode($data);
    exit;
}

function entrada() {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    if (is_array($d)) return $d;
    return !empty($_POST) ? $_POST : [];
}

/* Consulta SELECT segura */
function consultar($sql) {
    $db = db();
    $res = $db->query($sql);
    if ($res === false) salida(['error' => 'Error en consulta SQL: ' . $db->error]);
    $rows = [];
    if ($res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    return $rows;
}

/* Consulta preparada INSERT/UPDATE/DELETE contra SQLi y XSS */
function q($sql, $types = '', $params = []) {
    $db = db();
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        salida(['error' => 'Error de preparación SQL: ' . $db->error]);
    }
    
    if ($types !== '' && count($params)) {
        $refs = [];
        foreach ($params as $i => $v) {
            if (is_string($params[$i])) {
                $params[$i] = htmlspecialchars($params[$i], ENT_QUOTES, 'UTF-8');
            }
            $refs[$i] = &$params[$i];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    
    if (!$stmt->execute()) {
        http_response_code(500);
        salida(['error' => 'Error de ejecución SQL: ' . $stmt->error]);
    }
    return $stmt;
}
?>
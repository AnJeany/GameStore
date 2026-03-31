<?php
// ============================================
//  backend/index.php  —  Entry point API
//  Mọi request đều đi qua file này
// ============================================

// ----- CORS headers (cho phép frontend gọi API) -----
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trả về 200 cho preflight request của browser
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ----- Autoload -----
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/GameController.php';
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/AdminController.php';

// ----- Khởi tạo DB (dùng global để controller con dùng được) -----
$db = (new Database())->getConnection();
$GLOBALS['db'] = $db;

// ----- Parse URL -----
// Laragon: http://localhost/gamestore/backend/index.php/api/games
// hoặc dùng .htaccess rewrite: http://localhost/gamestore/api/games
$requestUri    = $_SERVER['REQUEST_URI'];
$scriptName    = dirname($_SERVER['SCRIPT_NAME']);
$path          = str_replace($scriptName, '', parse_url($requestUri, PHP_URL_PATH));
$path          = '/' . trim($path, '/');
$method        = $_SERVER['REQUEST_METHOD'];

// ----- Router -----
// Tách path thành segments: /api/games/5 → ['api','games','5']
$segments = explode('/', trim($path, '/'));

// Hàm lấy segment theo index
$seg = fn(int $i) => $segments[$i] ?? '';

// Chỉ xử lý /api/...
if ($seg(0) !== 'api') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Endpoint không tồn tại']);
    exit;
}

$resource    = $seg(1);  // games | auth | cart | library | dev | admin
$subResource = $seg(2);  // id | action
$action      = $seg(3);  // approve | reject | ...

try {
    // ============ AUTH ============
    if ($resource === 'auth') {
        $ctrl = new AuthController($db);
        match (true) {
            $method === 'POST' && $subResource === 'register' => $ctrl->register(),
            $method === 'POST' && $subResource === 'login'    => $ctrl->login(),
            $method === 'GET'  && $subResource === 'me'       => $ctrl->me(AuthMiddleware::authenticate()),
            default => notFound()
        };
    }

    // ============ GAMES (public) ============
    elseif ($resource === 'games') {
        $ctrl = new GameController($db);
        match (true) {
            $method === 'GET' && !$subResource        => $ctrl->index(),
            $method === 'GET' && is_numeric($subResource) => $ctrl->show((int)$subResource),
            default => notFound()
        };
    }

    // ============ DEV ============
    elseif ($resource === 'dev') {
        $payload = AuthMiddleware::requireRole('dev', 'admin');
        $ctrl    = new GameController($db);
        match (true) {
            $method === 'GET'  && $subResource === 'games' => $ctrl->devGames($payload),
            $method === 'POST' && $subResource === 'games' => $ctrl->store($payload),
            default => notFound()
        };
    }

    // ============ CART ============
    elseif ($resource === 'cart') {
        $payload = AuthMiddleware::requireRole('user', 'dev', 'admin');
        $ctrl    = new CartController($db);
        match (true) {
            $method === 'GET'    && !$subResource                  => $ctrl->index($payload),
            $method === 'POST'   && $subResource === 'add'         => $ctrl->add($payload),
            $method === 'DELETE' && $subResource === 'remove'      => $ctrl->remove($payload),
            $method === 'POST'   && $subResource === 'checkout'    => $ctrl->checkout($payload),
            default => notFound()
        };
    }

    // ============ LIBRARY ============
    elseif ($resource === 'library') {
        $payload = AuthMiddleware::requireRole('user', 'dev', 'admin');
        require_once __DIR__ . '/controllers/CartController.php';
        $ctrl = new LibraryController($db);
        $ctrl->index($payload);
    }

    // ============ ADMIN ============
    elseif ($resource === 'admin') {
        $payload = AuthMiddleware::requireRole('admin');
        $ctrl    = new AdminController($db);
        match (true) {
            $method === 'GET'  && $subResource === 'stats'                                     => $ctrl->stats(),
            $method === 'GET'  && $subResource === 'users'                                     => $ctrl->users(),
            $method === 'PUT'  && $subResource === 'users' && $action === 'toggle'             => $ctrl->toggleUser((int)$seg(2) ?: (int)$subResource),
            $method === 'PUT'  && $subResource === 'users' && $action === 'role'               => $ctrl->updateUserRole((int)$seg(2) ?: (int)$subResource),
            $method === 'GET'  && $subResource === 'games' && $action  === 'pending'           => $ctrl->pendingGames(),
            $method === 'PUT'  && $subResource === 'games' && $action  === 'approve'           => $ctrl->approveGame((int)$seg(2)),
            $method === 'PUT'  && $subResource === 'games' && $action  === 'reject'            => $ctrl->rejectGame((int)$seg(2)),
            default => notFound()
        };
    }

    else {
        notFound();
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi server: ' . $e->getMessage()
    ]);
}

function notFound(): never {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Endpoint không tồn tại']);
    exit;
}

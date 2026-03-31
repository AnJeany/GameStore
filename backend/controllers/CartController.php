<?php
// ============================================
//  controllers/CartController.php
// ============================================

require_once __DIR__ . '/../models/Order.php';

class CartController {
    private Order $order;

    public function __construct(PDO $db) {
        $this->order = new Order($db);
    }

    // GET /api/cart
    public function index(array $payload): void {
        $items = $this->order->getCart($payload['id']);
        $total = array_sum(array_column($items, 'price'));
        $this->respond(200, true, 'OK', ['items' => $items, 'total' => $total]);
    }

    // POST /api/cart/add   body: { game_id: 1 }
    public function add(array $payload): void {
        $data   = json_decode(file_get_contents('php://input'), true);
        $gameId = (int)($data['game_id'] ?? 0);

        if (!$gameId) { $this->respond(400, false, 'game_id không hợp lệ'); return; }

        // Kiểm tra đã sở hữu chưa
        if ($this->order->owns($payload['id'], $gameId)) {
            $this->respond(409, false, 'Bạn đã sở hữu game này');
            return;
        }

        $ok = $this->order->addToCart($payload['id'], $gameId);
        if ($ok) {
            $this->respond(200, true, 'Đã thêm vào giỏ hàng');
        } else {
            $this->respond(409, false, 'Game đã có trong giỏ hàng');
        }
    }

    // DELETE /api/cart/remove   body: { game_id: 1 }
    public function remove(array $payload): void {
        $data   = json_decode(file_get_contents('php://input'), true);
        $gameId = (int)($data['game_id'] ?? 0);

        $this->order->removeFromCart($payload['id'], $gameId);
        $this->respond(200, true, 'Đã xóa khỏi giỏ hàng');
    }

    // POST /api/cart/checkout
    public function checkout(array $payload): void {
        $result = $this->order->checkout($payload['id']);
        $code   = $result['success'] ? 200 : 400;
        $this->respond($code, $result['success'], $result['message'] ?? '', $result);
    }

    private function respond(int $code, bool $success, string $message, array $data = []): void {
        http_response_code($code);
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    }
}


// ============================================
//  controllers/LibraryController.php
// ============================================

class LibraryController {
    private Order $order;

    public function __construct(PDO $db) {
        $this->order = new Order($db);
    }

    // GET /api/library
    public function index(array $payload): void {
        $games = $this->order->getLibrary($payload['id']);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'OK', 'games' => $games]);
    }
}

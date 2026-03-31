<?php
// ============================================
//  controllers/AdminController.php
// ============================================

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Game.php';
require_once __DIR__ . '/../models/Order.php';

class AdminController {
    private User  $userModel;
    private Game  $gameModel;
    private Order $orderModel;

    public function __construct(PDO $db) {
        $this->userModel  = new User($db);
        $this->gameModel  = new Game($db);
        $this->orderModel = new Order($db);
    }

    // GET /api/admin/stats — Tổng quan dashboard
    public function stats(): void {
        $userCount  = $this->userModel->countAll();
        $gameStats  = $this->gameModel->getStats();
        $revenue    = $this->orderModel->getRevenueStats();
        $recentOrders = $this->orderModel->getRecentOrders(5);

        $this->respond(200, true, 'OK', [
            'stats' => [
                'users'         => $userCount,
                'games'         => $gameStats,
                'revenue'       => $revenue,
                'recent_orders' => $recentOrders,
            ]
        ]);
    }

    // GET /api/admin/users
    public function users(): void {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;
        $users  = $this->userModel->getAll($limit, $offset);
        $total  = $this->userModel->countAll();

        $this->respond(200, true, 'OK', [
            'users' => $users,
            'total' => $total,
        ]);
    }

    // PUT /api/admin/users/{id}/toggle — Khóa/mở tài khoản
    public function toggleUser(int $id): void {
        $user = $this->userModel->findById($id);
        if (!$user) { $this->respond(404, false, 'User không tồn tại'); return; }

        $newStatus = $user['is_active'] ? 0 : 1;
        $this->userModel->setActive($id, $newStatus);
        $msg = $newStatus ? 'Đã mở khóa tài khoản' : 'Đã khóa tài khoản';
        $this->respond(200, true, $msg);
    }

    // PUT /api/admin/users/{id}/role   body: { role: "dev" }
    public function updateUserRole(int $id): void {
        $data = json_decode(file_get_contents('php://input'), true);
        $role = $data['role'] ?? '';

        if (!in_array($role, ['user', 'dev', 'admin'])) {
            $this->respond(400, false, 'Role không hợp lệ');
            return;
        }
        $this->userModel->updateRole($id, $role);
        $this->respond(200, true, 'Đã cập nhật role');
    }

    // GET /api/admin/games/pending — Game chờ duyệt
    public function pendingGames(): void {
        $games = $this->gameModel->getPending();
        $this->respond(200, true, 'OK', ['games' => $games]);
    }

    // PUT /api/admin/games/{id}/approve
    public function approveGame(int $id): void {
        $this->gameModel->updateStatus($id, 'approved');
        $this->respond(200, true, 'Đã duyệt game');
    }

    // PUT /api/admin/games/{id}/reject
    public function rejectGame(int $id): void {
        $this->gameModel->updateStatus($id, 'rejected');
        $this->respond(200, true, 'Đã từ chối game');
    }

    private function respond(int $code, bool $success, string $message, array $data = []): void {
        http_response_code($code);
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    }
}

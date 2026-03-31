<?php
// ============================================
//  models/Order.php
//  Xử lý Cart, Order, Library
// ============================================

class Order {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // ---- CART ----

    public function getCart(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT ci.id AS cart_item_id, g.id, g.title, g.price, g.cover_image, g.genre
             FROM cart_items ci
             JOIN games g ON ci.game_id = g.id
             WHERE ci.user_id = ? AND g.status = 'approved'"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function addToCart(int $userId, int $gameId): bool {
        // Không thêm nếu đã sở hữu
        if ($this->owns($userId, $gameId)) return false;

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO cart_items (user_id, game_id) VALUES (?, ?)"
        );
        return $stmt->execute([$userId, $gameId]);
    }

    public function removeFromCart(int $userId, int $gameId): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM cart_items WHERE user_id = ? AND game_id = ?"
        );
        return $stmt->execute([$userId, $gameId]);
    }

    public function clearCart(int $userId): void {
        $this->db->prepare("DELETE FROM cart_items WHERE user_id = ?")->execute([$userId]);
    }

    // ---- ORDER & CHECKOUT ----

    public function checkout(int $userId): array {
        $cartItems = $this->getCart($userId);
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Giỏ hàng trống'];
        }

        // Lọc game chưa sở hữu (phòng race condition)
        $newItems = array_filter($cartItems, fn($item) => !$this->owns($userId, $item['id']));
        if (empty($newItems)) {
            return ['success' => false, 'message' => 'Bạn đã sở hữu tất cả game trong giỏ'];
        }

        $total = array_sum(array_column($newItems, 'price'));

        $this->db->beginTransaction();
        try {
            // Tạo order
            $stmt = $this->db->prepare(
                "INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'paid')"
            );
            $stmt->execute([$userId, $total]);
            $orderId = (int)$this->db->lastInsertId();

            // Thêm order items + thêm vào library
            $itemStmt = $this->db->prepare(
                "INSERT INTO order_items (order_id, game_id, price) VALUES (?, ?, ?)"
            );
            $libStmt = $this->db->prepare(
                "INSERT IGNORE INTO library (user_id, game_id) VALUES (?, ?)"
            );

            foreach ($newItems as $item) {
                $itemStmt->execute([$orderId, $item['id'], $item['price']]);
                $libStmt->execute([$userId, $item['id']]);
            }

            // Xóa giỏ hàng
            $this->clearCart($userId);

            $this->db->commit();
            return [
                'success'  => true,
                'order_id' => $orderId,
                'total'    => $total,
                'items'    => count($newItems),
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Thanh toán thất bại: ' . $e->getMessage()];
        }
    }

    // ---- LIBRARY ----

    public function getLibrary(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT g.id, g.title, g.cover_image, g.genre, g.description, l.purchased_at
             FROM library l
             JOIN games g ON l.game_id = g.id
             WHERE l.user_id = ?
             ORDER BY l.purchased_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function owns(int $userId, int $gameId): bool {
        $stmt = $this->db->prepare(
            "SELECT id FROM library WHERE user_id = ? AND game_id = ?"
        );
        $stmt->execute([$userId, $gameId]);
        return (bool)$stmt->fetch();
    }

    // ---- ADMIN STATS ----

    public function getRevenueStats(): array {
        $row = $this->db->query(
            "SELECT
                COUNT(*)           AS total_orders,
                SUM(total_amount)  AS total_revenue,
                AVG(total_amount)  AS avg_order_value
             FROM orders WHERE status = 'paid'"
        )->fetch();
        return $row;
    }

    public function getRecentOrders(int $limit = 10): array {
        $stmt = $this->db->prepare(
            "SELECT o.id, o.total_amount, o.created_at, u.username
             FROM orders o JOIN users u ON o.user_id = u.id
             WHERE o.status = 'paid'
             ORDER BY o.created_at DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}

<?php
// ============================================
//  models/Game.php
// ============================================

class Game {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    // Lấy danh sách game approved (có tìm kiếm + lọc genre)
    public function getAll(string $search = '', string $genre = '', int $limit = 20, int $offset = 0): array {
        $sql = "SELECT g.*, u.username AS dev_name
                FROM games g
                JOIN users u ON g.dev_id = u.id
                WHERE g.status = 'approved'";
        $params = [];

        if ($search) {
            $sql .= ' AND (g.title LIKE ? OR g.description LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($genre) {
            $sql .= ' AND g.genre = ?';
            $params[] = $genre;
        }

        $sql .= ' ORDER BY g.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countAll(string $search = '', string $genre = ''): int {
        $sql = "SELECT COUNT(*) FROM games g WHERE g.status = 'approved'";
        $params = [];

        if ($search) {
            $sql .= ' AND (g.title LIKE ? OR g.description LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($genre) {
            $sql .= ' AND g.genre = ?';
            $params[] = $genre;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            "SELECT g.*, u.username AS dev_name
             FROM games g JOIN users u ON g.dev_id = u.id
             WHERE g.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findBySlug(string $slug): array|false {
        $stmt = $this->db->prepare(
            "SELECT g.*, u.username AS dev_name
             FROM games g JOIN users u ON g.dev_id = u.id
             WHERE g.slug = ?"
        );
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    // Dev: lấy game của mình
    public function getByDev(int $devId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM games WHERE dev_id = ? ORDER BY created_at DESC"
        );
        $stmt->execute([$devId]);
        return $stmt->fetchAll();
    }

    // Dev: tạo game mới
    public function create(array $data): int {
        $slug = $this->makeSlug($data['title']);
        $stmt = $this->db->prepare(
            "INSERT INTO games (dev_id, title, slug, description, price, cover_image, genre)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['dev_id'],
            $data['title'],
            $slug,
            $data['description'] ?? '',
            $data['price'],
            $data['cover_image'] ?? null,
            $data['genre'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    // Admin: lấy game chờ duyệt
    public function getPending(): array {
        $stmt = $this->db->prepare(
            "SELECT g.*, u.username AS dev_name FROM games g
             JOIN users u ON g.dev_id = u.id
             WHERE g.status = 'pending' ORDER BY g.created_at ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Admin: duyệt / từ chối
    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("UPDATE games SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    // Thống kê cho Admin
    public function getStats(): array {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status='approved') AS approved,
                SUM(status='pending')  AS pending,
                SUM(status='rejected') AS rejected
             FROM games"
        )->fetch();
        return $row;
    }

    // Tạo slug từ title
    private function makeSlug(string $title): string {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', trim($slug));
        // Thêm timestamp để tránh trùng
        return $slug . '-' . time();
    }
}

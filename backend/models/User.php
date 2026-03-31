<?php
// ============================================
//  models/User.php
// ============================================

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function findByEmail(string $email): array|false {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById(int $id): array|false {
        $stmt = $this->db->prepare(
            'SELECT id, username, email, role, avatar, is_active, created_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function emailExists(string $email): bool {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    }

    public function usernameExists(string $username): bool {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return (bool)$stmt->fetch();
    }

    public function create(string $username, string $email, string $password, string $role = 'user'): int {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
        return (int)$this->db->lastInsertId();
    }

    // Admin: lấy danh sách tất cả user
    public function getAll(int $limit = 50, int $offset = 0): array {
        $stmt = $this->db->prepare(
            'SELECT id, username, email, role, is_active, created_at FROM users
             ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public function countAll(): int {
        return (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    // Admin: khóa / mở tài khoản
    public function setActive(int $id, int $status): bool {
        $stmt = $this->db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    // Admin: đổi role
    public function updateRole(int $id, string $role): bool {
        $stmt = $this->db->prepare('UPDATE users SET role = ? WHERE id = ?');
        return $stmt->execute([$role, $id]);
    }
}

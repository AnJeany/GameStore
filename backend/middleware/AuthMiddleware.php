<?php
// ============================================
//  middleware/AuthMiddleware.php
//  Kiểm tra JWT và phân quyền role
// ============================================

require_once __DIR__ . '/../config/jwt.php';

class AuthMiddleware {

    // Lấy payload từ token trong header Authorization
    public static function authenticate(): array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            self::unauthorized('Token không tồn tại hoặc sai định dạng');
        }

        $token = substr($authHeader, 7);
        $payload = JWT::verify($token);

        if (!$payload) {
            self::unauthorized('Token không hợp lệ hoặc đã hết hạn');
        }

        return $payload;
    }

    // Yêu cầu role cụ thể (truyền 1 hoặc nhiều role)
    public static function requireRole(string ...$roles): array {
        $payload = self::authenticate();

        if (!in_array($payload['role'], $roles)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Bạn không có quyền thực hiện hành động này'
            ]);
            exit;
        }

        return $payload;
    }

    // Helper: trả về 401
    private static function unauthorized(string $message): never {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

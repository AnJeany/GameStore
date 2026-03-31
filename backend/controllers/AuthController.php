<?php
// ============================================
//  controllers/AuthController.php
// ============================================

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/jwt.php';

class AuthController {
    private User $user;

    public function __construct(PDO $db) {
        $this->user = new User($db);
    }

    // POST /api/auth/register
    public function register(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        $username = trim($data['username'] ?? '');
        $email    = trim($data['email']    ?? '');
        $password = $data['password']      ?? '';
        $role     = in_array($data['role'] ?? '', ['user', 'dev']) ? $data['role'] : 'user';

        // Validation
        if (!$username || !$email || !$password) {
            $this->respond(400, false, 'Vui lòng điền đầy đủ thông tin');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(400, false, 'Email không hợp lệ');
            return;
        }
        if (strlen($password) < 6) {
            $this->respond(400, false, 'Mật khẩu tối thiểu 6 ký tự');
            return;
        }
        if ($this->user->emailExists($email)) {
            $this->respond(409, false, 'Email đã được sử dụng');
            return;
        }
        if ($this->user->usernameExists($username)) {
            $this->respond(409, false, 'Username đã được sử dụng');
            return;
        }

        $id = $this->user->create($username, $email, $password, $role);
        $token = JWT::generate(['id' => $id, 'username' => $username, 'role' => $role]);

        $this->respond(201, true, 'Đăng ký thành công', [
            'token' => $token,
            'user'  => ['id' => $id, 'username' => $username, 'email' => $email, 'role' => $role]
        ]);
    }

    // POST /api/auth/login
    public function login(): void {
        $data     = json_decode(file_get_contents('php://input'), true);
        $email    = trim($data['email']    ?? '');
        $password = $data['password']      ?? '';

        if (!$email || !$password) {
            $this->respond(400, false, 'Vui lòng nhập email và mật khẩu');
            return;
        }

        $user = $this->user->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->respond(401, false, 'Email hoặc mật khẩu không đúng');
            return;
        }
        if (!$user['is_active']) {
            $this->respond(403, false, 'Tài khoản đã bị khóa');
            return;
        }

        $token = JWT::generate([
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role']
        ]);

        $this->respond(200, true, 'Đăng nhập thành công', [
            'token' => $token,
            'user'  => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ]
        ]);
    }

    // GET /api/auth/me
    public function me(array $payload): void {
        $user = (new User($GLOBALS['db']))->findById($payload['id']);
        if (!$user) { $this->respond(404, false, 'Không tìm thấy user'); return; }
        $this->respond(200, true, 'OK', ['user' => $user]);
    }

    private function respond(int $code, bool $success, string $message, array $data = []): void {
        http_response_code($code);
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    }
}

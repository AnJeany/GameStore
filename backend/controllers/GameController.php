<?php
// ============================================
//  controllers/GameController.php
// ============================================

require_once __DIR__ . '/../models/Game.php';
require_once __DIR__ . '/../models/Order.php';

class GameController {
    private Game  $game;
    private Order $order;

    public function __construct(PDO $db) {
        $this->game  = new Game($db);
        $this->order = new Order($db);
    }

    // GET /api/games?search=&genre=&page=1
    public function index(): void {
        $search = $_GET['search'] ?? '';
        $genre  = $_GET['genre']  ?? '';
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 12;
        $offset = ($page - 1) * $limit;

        $games = $this->game->getAll($search, $genre, $limit, $offset);
        $total = $this->game->countAll($search, $genre);

        $this->respond(200, true, 'OK', [
            'games'      => $games,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => (int)ceil($total / $limit),
            ]
        ]);
    }

    // GET /api/games/{id}
    public function show(int $id): void {
        $game = $this->game->findById($id);
        if (!$game || $game['status'] !== 'approved') {
            $this->respond(404, false, 'Game không tồn tại');
            return;
        }
        $this->respond(200, true, 'OK', ['game' => $game]);
    }

    // POST /api/games  (Dev only)
    public function store(array $payload): void {
        $data = json_decode(file_get_contents('php://input'), true);

        $title = trim($data['title'] ?? '');
        $price = (float)($data['price'] ?? 0);

        if (!$title) {
            $this->respond(400, false, 'Tên game không được để trống');
            return;
        }
        if ($price < 0) {
            $this->respond(400, false, 'Giá không hợp lệ');
            return;
        }

        $id = $this->game->create([
            'dev_id'      => $payload['id'],
            'title'       => $title,
            'description' => $data['description'] ?? '',
            'price'       => $price,
            'cover_image' => $data['cover_image'] ?? null,
            'genre'       => $data['genre'] ?? null,
        ]);

        $this->respond(201, true, 'Game đã được gửi duyệt thành công', ['game_id' => $id]);
    }

    // GET /api/dev/games  (Dev: xem game của mình)
    public function devGames(array $payload): void {
        $games = $this->game->getByDev($payload['id']);
        $this->respond(200, true, 'OK', ['games' => $games]);
    }

    private function respond(int $code, bool $success, string $message, array $data = []): void {
        http_response_code($code);
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    }
}

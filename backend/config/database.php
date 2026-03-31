<?php
// ============================================
//  config/database.php
//  Kết nối MySQL dùng PDO
// ============================================

class Database {
    private string $host     = '127.0.0.1';
    private string $port     = '3307';
    private string $dbname   = 'gamestore';
    private string $username = 'root';
    private string $password = '';          // Laragon mặc định không có password
    private string $charset  = 'utf8mb4';

    private ?PDO $connection = null;

    public function getConnection(): PDO {
        if ($this->connection === null) {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        }
        return $this->connection;
    }
}

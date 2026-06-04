<?php
class UmbraDB {
    private PDO $pdo;

    public function __construct() {
        $dbPath = __DIR__ . '/../../data/umbra.db';
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->init();
    }

    private function init(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id       INTEGER PRIMARY KEY,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS config (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
        ");

        $count = $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count == 0) {
            $hash = password_hash('umbra', PASSWORD_DEFAULT);
            $this->pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)")
                ->execute(['admin', $hash]);
        }

        $defaults = [
            'key_youtube'      => '',
            'key_facebook'     => '',
            'key_tiktok'       => '',
            'enabled_youtube'  => '1',
            'enabled_facebook' => '1',
            'enabled_tiktok'   => '1',
            'restream'         => '1',
            'ingest_key'       => bin2hex(random_bytes(16)),
        ];
        foreach ($defaults as $k => $v) {
            $this->pdo->prepare(
                "INSERT OR IGNORE INTO config (key, value) VALUES (?, ?)"
            )->execute([$k, $v]);
        }
    }

    public function getUser(string $username): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function get(string $key): ?string {
        $stmt = $this->pdo->prepare("SELECT value FROM config WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['value'] : null;
    }

    public function set(string $key, string $value): void {
        $this->pdo->prepare(
            "INSERT INTO config (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value"
        )->execute([$key, $value]);
    }

    public function getAllConfig(): array {
        $rows = $this->pdo->query("SELECT key, value FROM config")->fetchAll(PDO::FETCH_ASSOC);
        $out  = [];
        foreach ($rows as $r) $out[$r['key']] = $r['value'];
        return $out;
    }
}

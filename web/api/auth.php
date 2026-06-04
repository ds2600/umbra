<?php
class UmbraAuth {
    public function __construct(private UmbraDB $db) {}

    public function login(string $username, string $password): array {
        if (empty($username) || empty($password)) return ['success' => false];
        $user = $this->db->getUser($username);
        if (!$user || !password_verify($password, $user['password'])) {
            password_verify('dummy', '$2y$10$abcdefghijklmnopqrstuuABC123456789012345678901234567890');
            return ['success' => false];
        }
        return ['success' => true];
    }
}

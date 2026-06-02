<?php
class UmbraAuth {
    public function __construct(private UmbraDB $db) {}

    public function login(string $username, string $password): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Missing credentials'];
        }
        $user = $this->db->getUser($username);
        if (!$user || !password_verify($password, $user['password'])) {
            // Constant-time guard against timing attacks
            password_verify('dummy', '$2y$10$abcdefghijklmnopqrstuuABC123456789012345678901234567890');
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        return ['success' => true];
    }
}

<?php

class Database
{
    private $host;
    private $db_name;
    private $username;
    private $password;

    public function __construct()
    {
        $this->host = $_ENV['DB_HOST'];
        $this->db_name = $_ENV['DB_NAME'];
        $this->username = $_ENV['DB_USER'];
        $this->password = $_ENV['DB_PASS'];
    }

    public function getConnection()
    {
        try {
            $conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );

            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            return $conn;

        } catch (PDOException $e) {
            error_log("DB Connection Failed: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Service unavailable']);
            exit;
        }
    }
}
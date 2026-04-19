<?php

class OfficeLocation
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getDefault(): array|false
    {
        // Asumsi hanya ada 1 kantor pusat, mengambil data pertama
        $stmt = $this->db->query("SELECT * FROM office_locations ORDER BY id ASC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM office_locations ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

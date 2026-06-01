<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

class ShiftService
{
    private Shift $shiftModel;

    public function __construct(PDO $db)
    {
        $this->shiftModel = new Shift($db);
    }

    public function getAll(array $filters = []): array
    {
        return $this->shiftModel->all($filters);
    }

    public function getById(int $id): array
    {
        $shift = $this->shiftModel->findById($id);
        if (!$shift) {
            throw new \InvalidArgumentException('Shift not found');
        }
        return $shift;
    }

    public function create(array $data): int
    {
        $this->validate($data);

        if ($this->shiftModel->findByName($data['name'])) {
            throw new \InvalidArgumentException('Shift name already exists');
        }

        $id = $this->shiftModel->create($data);
        if (!$id) {
            throw new \RuntimeException('Failed to create shift');
        }
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $shift = $this->shiftModel->findById($id);
        if (!$shift) {
            throw new \InvalidArgumentException('Shift not found');
        }

        $this->validate($data);

        $existing = $this->shiftModel->findByName($data['name']);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new \InvalidArgumentException('Shift name already exists');
        }

        return $this->shiftModel->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $shift = $this->shiftModel->findById($id);
        if (!$shift) {
            throw new \InvalidArgumentException('Shift not found');
        }

        if ($this->shiftModel->isUsedBySchedules($id)) {
            throw new \RuntimeException('Shift is still used in schedules and cannot be deleted');
        }

        return $this->shiftModel->delete($id);
    }

    public function importFromExcel(array $file): array
    {
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'text/csv',
            'application/csv',
        ];

        if (!in_array($file['type'], $allowedMimes, true)) {
            throw new \InvalidArgumentException('File must be Excel (.xlsx, .xls) or CSV (.csv)');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed with error code ' . $file['error']);
        }

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();

        // Raw values, letter-indexed columns (A, B, C, D)
        $rows = $sheet->toArray(null, false, true, true);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $isHeader = true;

        foreach ($rows as $rowIndex => $row) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            $name       = trim((string) ($row['A'] ?? ''));
            $startTime  = $this->normalizeTime($row['B'] ?? '');
            $endTime    = $this->normalizeTime($row['C'] ?? '');
            $isOvernight = (int) ($row['D'] ?? 0);

            // Skip fully empty rows
            if ($name === '' && ($row['B'] ?? '') === '' && ($row['C'] ?? '') === '') {
                continue;
            }

            try {
                $data = [
                    'name'        => $name,
                    'start_time'  => $startTime,
                    'end_time'    => $endTime,
                    'is_overnight' => $isOvernight,
                ];
                $this->validate($data);

                if ($this->shiftModel->findByName($name)) {
                    $skipped++;
                    $errors[] = "Row {$rowIndex}: name '{$name}' already exists (skipped)";
                    continue;
                }

                $this->shiftModel->create($data);
                $imported++;
            } catch (\InvalidArgumentException $e) {
                $skipped++;
                $errors[] = "Row {$rowIndex}: " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    private function normalizeTime(mixed $value): string
    {
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            // Excel stores time as fraction of a day (e.g. 0.5 = 12:00)
            $totalMinutes = (int) round((float) $value * 24 * 60);
            $hours   = intdiv($totalMinutes, 60) % 24;
            $minutes = $totalMinutes % 60;
            return sprintf('%02d:%02d', $hours, $minutes);
        }
        return trim((string) $value);
    }

    private function validate(array $data): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Shift name is required');
        }

        if (empty($data['start_time'])) {
            throw new \InvalidArgumentException('start_time is required');
        }

        if (empty($data['end_time'])) {
            throw new \InvalidArgumentException('end_time is required');
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time'])) {
            throw new \InvalidArgumentException('start_time must be in HH:MM or HH:MM:SS format');
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['end_time'])) {
            throw new \InvalidArgumentException('end_time must be in HH:MM or HH:MM:SS format');
        }
    }
}

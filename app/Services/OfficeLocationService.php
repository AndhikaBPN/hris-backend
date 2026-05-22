<?php

class OfficeLocationService
{
    private OfficeLocation $model;

    public function __construct(PDO $db)
    {
        $this->model = new OfficeLocation($db);
    }

    public function getAll(array $filters = []): array
    {
        return $this->model->all($filters);
    }

    public function getById(int $id): array
    {
        $location = $this->model->findById($id);
        if (!$location) {
            throw new \InvalidArgumentException('Office location not found');
        }
        return $location;
    }

    public function create(array $data): int
    {
        $this->validate($data);

        $id = $this->model->create([
            'name'          => trim($data['name']),
            'latitude'      => (float) $data['latitude'],
            'longitude'     => (float) $data['longitude'],
            'radius_meters' => (int) $data['radius_meters'],
        ]);

        if (!$id) {
            throw new \RuntimeException('Failed to create office location');
        }

        return $id;
    }

    public function update(int $id, array $data): bool
    {
        if (!$this->model->findById($id)) {
            throw new \InvalidArgumentException('Office location not found');
        }

        $this->validate($data);

        return $this->model->update($id, [
            'name'          => trim($data['name']),
            'latitude'      => (float) $data['latitude'],
            'longitude'     => (float) $data['longitude'],
            'radius_meters' => (int) $data['radius_meters'],
        ]);
    }

    public function delete(int $id): bool
    {
        if (!$this->model->findById($id)) {
            throw new \InvalidArgumentException('Office location not found');
        }

        return $this->model->delete($id);
    }

    private function validate(array $data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name is required');
        }

        if (!isset($data['latitude']) || !is_numeric($data['latitude'])) {
            throw new \InvalidArgumentException('latitude must be a valid number');
        }

        if (!isset($data['longitude']) || !is_numeric($data['longitude'])) {
            throw new \InvalidArgumentException('longitude must be a valid number');
        }

        $lat = (float) $data['latitude'];
        $lon = (float) $data['longitude'];

        if ($lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException('latitude must be between -90 and 90');
        }

        if ($lon < -180 || $lon > 180) {
            throw new \InvalidArgumentException('longitude must be between -180 and 180');
        }

        if (!isset($data['radius_meters']) || (int) $data['radius_meters'] <= 0) {
            throw new \InvalidArgumentException('radius_meters must be a positive integer');
        }
    }
}

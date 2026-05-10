<?php

namespace App\Core\DataTransfer;

/**
 * Data Transfer Object for individual elements
 */
class ElementDTO
{
    private string $id;
    private string $elementType;
    private float $x;
    private float $y;
    private float $z;
    private float $width;
    private float $height;
    private float $rotation;
    private float $zIndex;
    private ?string $parentId;
    private array $data;
    private array $style;
    private array $tags;
    private array $zones;
    private bool $isBookable;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? uniqid('el_');
        $this->elementType = $data['element_type'] ?? 'unknown';
        $this->x = (float)($data['x'] ?? 0);
        $this->y = (float)($data['y'] ?? 0);
        $this->z = (float)($data['z'] ?? 0);
        $this->width = (float)($data['width'] ?? 0);
        $this->height = (float)($data['height'] ?? 0);
        $this->rotation = (float)($data['rotation'] ?? 0);
        $this->zIndex = (float)($data['z_index'] ?? 0);
        $this->parentId = $data['parent_id'] ?? null;
        $this->data = $data['data'] ?? $data['data_json'] ?? [];
        $this->style = $data['style'] ?? $data['style_json'] ?? [];
        $this->tags = $data['tags'] ?? [];
        $this->zones = $data['zones'] ?? [];
        $this->isBookable = $data['is_bookable'] ?? $this->determineIfBookable();
        $this->createdAt = $data['created_at'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? null;
    }

    private function determineIfBookable(): bool
    {
        return in_array($this->elementType, ['seat', 'table', 'standing_zone'], true);
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getType(): string { return $this->elementType; }
    public function getX(): float { return $this->x; }
    public function getY(): float { return $this->y; }
    public function getZ(): float { return $this->z; }
    public function getWidth(): float { return $this->width; }
    public function getHeight(): float { return $this->height; }
    public function getRotation(): float { return $this->rotation; }
    public function getZIndex(): float { return $this->zIndex; }
    public function getParentId(): ?string { return $this->parentId; }
    public function getData(): array { return $this->data; }
    public function getStyle(): array { return $this->style; }
    public function getTags(): array { return $this->tags; }
    public function getZones(): array { return $this->zones; }
    public function isBookable(): bool { return $this->isBookable; }
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }

    // Setters
    public function setX(float $x): void { $this->x = $x; }
    public function setY(float $y): void { $this->y = $y; }
    public function setZ(float $z): void { $this->z = $z; }
    public function setWidth(float $width): void { $this->width = $width; }
    public function setHeight(float $height): void { $this->height = $height; }
    public function setRotation(float $rotation): void { $this->rotation = $rotation; }
    public function setZIndex(float $zIndex): void { $this->zIndex = $zIndex; }
    public function setData(array $data): void { $this->data = $data; }
    public function setStyle(array $style): void { $this->style = $style; }
    public function setTags(array $tags): void { $this->tags = $tags; }
    public function setZones(array $zones): void { $this->zones = $zones; }
    public function setBookable(bool $isBookable): void { $this->isBookable = $isBookable; }

    /**
     * Get a specific data field
     */
    public function getDataField(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get label
     */
    public function getLabel(): ?string
    {
        return $this->data['label'] ?? null;
    }

    /**
     * Get seat row
     */
    public function getRow(): ?string
    {
        return $this->data['row'] ?? null;
    }

    /**
     * Get seat number
     */
    public function getSeatNumber(): ?string
    {
        return $this->data['seat_number'] ?? null;
    }

    /**
     * Check if element has a specific tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Check if element is in a specific zone
     */
    public function isInZone(string $zoneId): bool
    {
        return in_array($zoneId, $this->zones, true);
    }

    /**
     * Get center point of element
     */
    public function getCenter(): array
    {
        return [
            'x' => $this->x + ($this->width / 2),
            'y' => $this->y + ($this->height / 2),
            'z' => $this->z + ($this->height / 2),
        ];
    }

    /**
     * Get bounding box
     */
    public function getBounds(): array
    {
        return [
            'minX' => $this->x,
            'maxX' => $this->x + $this->width,
            'minY' => $this->y,
            'maxY' => $this->y + $this->height,
            'minZ' => $this->z,
            'maxZ' => $this->z + ($this->height / 2),
        ];
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'element_type' => $this->elementType,
            'x' => $this->x,
            'y' => $this->y,
            'z' => $this->z,
            'width' => $this->width,
            'height' => $this->height,
            'rotation' => $this->rotation,
            'z_index' => $this->zIndex,
            'parent_id' => $this->parentId,
            'data' => $this->data,
            'style' => $this->style,
            'tags' => $this->tags,
            'zones' => $this->zones,
            'is_bookable' => $this->isBookable,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Convert to JSON string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Create from JSON string
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return new self($data);
    }
}
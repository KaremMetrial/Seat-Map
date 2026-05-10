<?php

namespace App\Core\DataTransfer;

/**
 * Data Transfer Object for Seat Map data
 * Optimized for frontend consumption
 */
class SeatMapDTO
{
    private string $id;
    private string $name;
    private int $width;
    private int $height;
    private array $elements;
    private array $metadata;
    private array $zones;
    private array $pricingTiers;
    private string $version;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->width = $data['width'] ?? 800;
        $this->height = $data['height'] ?? 600;
        $this->elements = $data['elements'] ?? [];
        $this->metadata = $data['metadata'] ?? [];
        $this->zones = $data['zones'] ?? [];
        $this->pricingTiers = $data['pricing_tiers'] ?? [];
        $this->version = $data['version'] ?? '1.0';
        $this->createdAt = $data['created_at'] ?? now()->toDateTimeString();
        $this->updatedAt = $data['updated_at'] ?? now()->toDateTimeString();
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }
    public function getElements(): array { return $this->elements; }
    public function getMetadata(): array { return $this->metadata; }
    public function getZones(): array { return $this->zones; }
    public function getPricingTiers(): array { return $this->pricingTiers; }
    public function getVersion(): string { return $this->version; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'version' => $this->version,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'metadata' => $this->metadata,
            'zones' => $this->zones,
            'pricing_tiers' => $this->pricingTiers,
            'elements' => array_map(function ($element) {
                return $element instanceof ElementDTO ? $element->toArray() : $element;
            }, $this->elements),
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
     * Get elements grouped by type
     */
    public function getElementsByType(): array
    {
        $grouped = [];
        foreach ($this->elements as $element) {
            $type = $element instanceof ElementDTO ? $element->getType() : ($element['element_type'] ?? 'unknown');
            $grouped[$type][] = $element;
        }
        return $grouped;
    }

    /**
     * Get bookable elements only
     */
    public function getBookableElements(): array
    {
        return array_filter($this->elements, function ($element) {
            if ($element instanceof ElementDTO) {
                return $element->isBookable();
            }
            return in_array($element['element_type'] ?? '', ['seat', 'table', 'standing_zone']);
        });
    }

    /**
     * Get elements by zone
     */
    public function getElementsByZone(string $zoneId): array
    {
        return array_filter($this->elements, function ($element) use ($zoneId) {
            $elementZones = $element instanceof ElementDTO 
                ? $element->getZones() 
                : ($element['zones'] ?? []);
            return in_array($zoneId, $elementZones);
        });
    }

    /**
     * Apply a filter callback to elements
     */
    public function filterElements(callable $callback): array
    {
        return array_filter($this->elements, $callback);
    }

    /**
     * Get element by ID
     */
    public function getElementById(string $id): ?array
    {
        foreach ($this->elements as $element) {
            $elementId = $element instanceof ElementDTO ? $element->getId() : ($element['id'] ?? null);
            if ($elementId === $id) {
                return $element instanceof ElementDTO ? $element->toArray() : $element;
            }
        }
        return null;
    }

    /**
     * Get statistics about the seat map
     */
    public function getStatistics(): array
    {
        $totalElements = count($this->elements);
        $bookableElements = count($this->getBookableElements());
        $elementsByType = $this->getElementsByType();

        $typeCounts = [];
        foreach ($elementsByType as $type => $elements) {
            $typeCounts[$type] = count($elements);
        }

        return [
            'total_elements' => $totalElements,
            'bookable_elements' => $bookableElements,
            'elements_by_type' => $typeCounts,
            'zones_count' => count($this->zones),
            'pricing_tiers_count' => count($this->pricingTiers),
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
<?php

namespace App\Core\Geometry;

/**
 * Axis-Aligned Bounding Box
 */
class BoundingBox
{
    public float $minX;
    public float $minY;
    public float $minZ;
    public float $maxX;
    public float $maxY;
    public float $maxZ;

    public function __construct(
        float $minX = INF,
        float $minY = INF,
        float $minZ = INF,
        float $maxX = -INF,
        float $maxY = -INF,
        float $maxZ = -INF
    ) {
        $this->minX = $minX;
        $this->minY = $minY;
        $this->minZ = $minZ;
        $this->maxX = $maxX;
        $this->maxY = $maxY;
        $this->maxZ = $maxZ;
    }

    public function expand(Vector3 $point): void
    {
        $this->minX = min($this->minX, $point->x);
        $this->minY = min($this->minY, $point->y);
        $this->minZ = min($this->minZ, $point->z);
        $this->maxX = max($this->maxX, $point->x);
        $this->maxY = max($this->maxY, $point->y);
        $this->maxZ = max($this->maxZ, $point->z);
    }

    public function intersects(BoundingBox $other): bool
    {
        return !(
            $this->maxX < $other->minX ||
            $this->minX > $other->maxX ||
            $this->maxY < $other->minY ||
            $this->minY > $other->maxY ||
            $this->maxZ < $other->minZ ||
            $this->minZ > $other->maxZ
        );
    }

    public function contains(Vector3 $point): bool
    {
        return $point->x >= $this->minX && $point->x <= $this->maxX &&
               $point->y >= $this->minY && $point->y <= $this->maxY &&
               $point->z >= $this->minZ && $point->z <= $this->maxZ;
    }

    public function getCenter(): Vector3
    {
        return new Vector3(
            ($this->minX + $this->maxX) / 2,
            ($this->minY + $this->maxY) / 2,
            ($this->minZ + $this->maxZ) / 2
        );
    }

    public function getSize(): Vector3
    {
        return new Vector3(
            $this->maxX - $this->minX,
            $this->maxY - $this->minY,
            $this->maxZ - $this->minZ
        );
    }
}

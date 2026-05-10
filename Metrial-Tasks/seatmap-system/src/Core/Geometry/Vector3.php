<?php

namespace App\Core\Geometry;

/**
 * 3D Vector with full math operations
 */
class Vector3
{
    public float $x;
    public float $y;
    public float $z;

    public function __construct(float $x = 0, float $y = 0, float $z = 0)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    public function add(Vector3 $other): Vector3
    {
        return new Vector3(
            $this->x + $other->x,
            $this->y + $other->y,
            $this->z + $other->z
        );
    }

    public function subtract(Vector3 $other): Vector3
    {
        return new Vector3(
            $this->x - $other->x,
            $this->y - $other->y,
            $this->z - $other->z
        );
    }

    public function multiply(float $scalar): Vector3
    {
        return new Vector3(
            $this->x * $scalar,
            $this->y * $scalar,
            $this->z * $scalar
        );
    }

    public function dot(Vector3 $other): float
    {
        return $this->x * $other->x + $this->y * $other->y + $this->z * $other->z;
    }

    public function cross(Vector3 $other): Vector3
    {
        return new Vector3(
            $this->y * $other->z - $this->z * $other->y,
            $this->z * $other->x - $this->x * $other->z,
            $this->x * $other->y - $this->y * $other->x
        );
    }

    public function length(): float
    {
        return sqrt($this->x * $this->x + $this->y * $this->y + $this->z * $this->z);
    }

    public function normalize(): Vector3
    {
        $len = $this->length();
        if ($len === 0) return new Vector3();
        return new Vector3($this->x / $len, $this->y / $len, $this->z / $len);
    }

    public function distanceTo(Vector3 $other): float
    {
        $dx = $this->x - $other->x;
        $dy = $this->y - $other->y;
        $dz = $this->z - $other->z;
        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    public function lerp(Vector3 $to, float $t): Vector3
    {
        return new Vector3(
            $this->x + ($to->x - $this->x) * $t,
            $this->y + ($to->y - $this->y) * $t,
            $this->z + ($to->z - $this->z) * $t
        );
    }

    public function toArray(): array
    {
        return [$this->x, $this->y, $this->z];
    }
}

<?php

namespace App\Core\Geometry;

/**
 * 4x4 Transformation Matrix
 */
class Matrix4x4
{
    private array $m;

    public function __construct(array $values = null)
    {
        // Row-major order
        $this->m = $values ?? [
            1, 0, 0, 0,
            0, 1, 0, 0,
            0, 0, 1, 0,
            0, 0, 0, 1
        ];
    }

    public static function identity(): Matrix4x4
    {
        return new self();
    }

    public static function translation(float $x, float $y, float $z): Matrix4x4
    {
        $m = self::identity();
        $m->m[12] = $x;
        $m->m[13] = $y;
        $m->m[14] = $z;
        return $m;
    }

    public static function rotationX(float $angle): Matrix4x4
    {
        $c = cos($angle);
        $s = sin($angle);
        $m = self::identity();
        $m->m[5] = $c;
        $m->m[6] = -$s;
        $m->m[9] = $s;
        $m->m[10] = $c;
        return $m;
    }

    public static function rotationY(float $angle): Matrix4x4
    {
        $c = cos($angle);
        $s = sin($angle);
        $m = self::identity();
        $m->m[0] = $c;
        $m->m[2] = $s;
        $m->m[8] = -$s;
        $m->m[10] = $c;
        return $m;
    }

    public static function rotationZ(float $angle): Matrix4x4
    {
        $c = cos($angle);
        $s = sin($angle);
        $m = self::identity();
        $m->m[0] = $c;
        $m->m[1] = -$s;
        $m->m[4] = $s;
        $m->m[5] = $c;
        return $m;
    }

    public static function scale(float $x, float $y, float $z): Matrix4x4
    {
        $m = self::identity();
        $m->m[0] = $x;
        $m->m[5] = $y;
        $m->m[10] = $z;
        return $m;
    }

    public function multiply(Matrix4x4 $other): Matrix4x4
    {
        $result = new self();
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $sum = 0;
                for ($k = 0; $k < 4; $k++) {
                    $sum += $this->m[$i * 4 + $k] * $other->m[$k * 4 + $j];
                }
                $result->m[$i * 4 + $j] = $sum;
            }
        }
        return $result;
    }

    public function transformVector(Vector3 $v): Vector3
    {
        $x = $this->m[0] * $v->x + $this->m[4] * $v->y + $this->m[8] * $v->z + $this->m[12];
        $y = $this->m[1] * $v->x + $this->m[5] * $v->y + $this->m[9] * $v->z + $this->m[13];
        $z = $this->m[2] * $v->x + $this->m[6] * $v->y + $this->m[10] * $v->z + $this->m[14];
        $w = $this->m[3] * $v->x + $this->m[7] * $v->y + $this->m[11] * $v->z + $this->m[15];

        if ($w !== 1 && $w !== 0) {
            $x /= $w;
            $y /= $w;
            $z /= $w;
        }

        return new Vector3($x, $y, $z);
    }

    public function inverse(): Matrix4x4
    {
        // Simplified inverse for affine transforms
        $inv = new self();
        // Implementation would use Gaussian elimination or adjugate
        return $inv;
    }

    public function transpose(): Matrix4x4
    {
        $result = new self();
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $result->m[$j * 4 + $i] = $this->m[$i * 4 + $j];
            }
        }
        return $result;
    }

    public function toArray(): array
    {
        return $this->m;
    }
}

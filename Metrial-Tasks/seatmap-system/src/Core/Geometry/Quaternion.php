<?php

namespace App\Core\Geometry;

/**
 * Quaternion for smooth rotations
 */
class Quaternion
{
    public function __construct(
        public float $x = 0,
        public float $y = 0,
        public float $z = 0,
        public float $w = 1
    ) {}

    public static function fromAxisAngle(Vector3 $axis, float $angle): Quaternion
    {
        $halfAngle = $angle / 2;
        $s = sin($halfAngle);
        return new Quaternion(
            $axis->x * $s,
            $axis->y * $s,
            $axis->z * $s,
            cos($halfAngle)
        );
    }

    public function multiply(Quaternion $q): Quaternion
    {
        return new Quaternion(
            $this->w * $q->x + $this->x * $q->w + $this->y * $q->z - $this->z * $q->y,
            $this->w * $q->y - $this->x * $q->z + $this->y * $q->w + $this->z * $q->x,
            $this->w * $q->z + $this->x * $q->y - $this->y * $q->x + $this->z * $q->w,
            $this->w * $q->w - $this->x * $q->x - $this->y * $q->y - $this->z * $q->z
        );
    }

    public function slerp(Quaternion $to, float $t): Quaternion
    {
        $cosHalfTheta = $this->w * $to->w + $this->x * $to->x + $this->y * $to->y + $this->z * $to->z;

        if (abs($cosHalfTheta) >= 1.0) {
            return new Quaternion($this->x, $this->y, $this->z, $this->w);
        }

        $halfTheta = acos($cosHalfTheta);
        $sinHalfTheta = sqrt(1.0 - $cosHalfTheta * $cosHalfTheta);

        if (abs($sinHalfTheta) < 0.001) {
            return new Quaternion(
                ($this->x * 0.5 + $to->x * 0.5),
                ($this->y * 0.5 + $to->y * 0.5),
                ($this->z * 0.5 + $to->z * 0.5),
                ($this->w * 0.5 + $to->w * 0.5)
            );
        }

        $ratioA = sin((1 - $t) * $halfTheta) / $sinHalfTheta;
        $ratioB = sin($t * $halfTheta) / $sinHalfTheta;

        return new Quaternion(
            $this->x * $ratioA + $to->x * $ratioB,
            $this->y * $ratioA + $to->y * $ratioB,
            $this->z * $ratioA + $to->z * $ratioB,
            $this->w * $ratioA + $to->w * $ratioB
        );
    }

    public function toMatrix(): Matrix4x4
    {
        $x2 = $this->x + $this->x;
        $y2 = $this->y + $this->y;
        $z2 = $this->z + $this->z;
        $xx = $this->x * $x2;
        $xy = $this->x * $y2;
        $xz = $this->x * $z2;
        $yy = $this->y * $y2;
        $yz = $this->y * $z2;
        $zz = $this->z * $z2;
        $wx = $this->w * $x2;
        $wy = $this->w * $y2;
        $wz = $this->w * $z2;

        return new Matrix4x4([
            1 - ($yy + $zz), $xy - $wz, $xz + $wy, 0,
            $xy + $wz, 1 - ($xx + $zz), $yz - $wx, 0,
            $xz - $wy, $yz + $wx, 1 - ($xx + $yy), 0,
            0, 0, 0, 1
        ]);
    }

    public function normalize(): Quaternion
    {
        $len = sqrt($this->x * $this->x + $this->y * $this->y + $this->z * $this->z + $this->w * $this->w);
        if ($len === 0) return new Quaternion();
        return new Quaternion(
            $this->x / $len,
            $this->y / $len,
            $this->z / $len,
            $this->w / $len
        );
    }
}

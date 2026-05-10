<?php

namespace App\Core\Geometry;

/**
 * Represents a 3D geometric transformation
 */
class Transform
{
    private float $translateX = 0;
    private float $translateY = 0;
    private float $translateZ = 0;
    private float $rotateX = 0;
    private float $rotateY = 0;
    private float $rotateZ = 0;
    private float $scaleX = 1;
    private float $scaleY = 1;
    private float $scaleZ = 1;

    /**
     * Create a new transform
     */
    public function __construct(
        float $translateX = 0,
        float $translateY = 0,
        float $translateZ = 0,
        float $rotateX = 0,
        float $rotateY = 0,
        float $rotateZ = 0,
        float $scaleX = 1,
        float $scaleY = 1,
        float $scaleZ = 1
    ) {
        $this->translateX = $translateX;
        $this->translateY = $translateY;
        $this->translateZ = $translateZ;
        $this->rotateX = $rotateX;
        $this->rotateY = $rotateY;
        $this->rotateZ = $rotateZ;
        $this->scaleX = $scaleX;
        $this->scaleY = $scaleY;
        $this->scaleZ = $scaleZ;
    }

    // Getters and Setters
    public function getTranslateX(): float { return $this->translateX; }
    public function setTranslateX(float $value): void { $this->translateX = $value; }
    
    public function getTranslateY(): float { return $this->translateY; }
    public function setTranslateY(float $value): void { $this->translateY = $value; }
    
    public function getTranslateZ(): float { return $this->translateZ; }
    public function setTranslateZ(float $value): void { $this->translateZ = $value; }
    
    public function getRotateX(): float { return $this->rotateX; }
    public function setRotateX(float $value): void { $this->rotateX = $value; }
    
    public function getRotateY(): float { return $this->rotateY; }
    public function setRotateY(float $value): void { $this->rotateY = $value; }
    
    public function getRotateZ(): float { return $this->rotateZ; }
    public function setRotateZ(float $value): void { $this->rotateZ = $value; }
    
    public function getScaleX(): float { return $this->scaleX; }
    public function setScaleX(float $value): void { $this->scaleX = $value; }
    
    public function getScaleY(): float { return $this->scaleY; }
    public function setScaleY(float $value): void { $this->scaleY = $value; }
    
    public function getScaleZ(): float { return $this->scaleZ; }
    public function setScaleZ(float $value): void { $this->scaleZ = $value; }

    /**
     * Create a translation transform
     */
    public static function translation(float $x, float $y, float $z = 0): self
    {
        return new self($x, $y, $z);
    }

    /**
     * Create a rotation transform (in degrees)
     */
    public static function rotation(float $x = 0, float $y = 0, float $z = 0): self
    {
        return new self(0, 0, 0, $x, $y, $z);
    }

    /**
     * Create a scale transform
     */
    public static function scale(float $x = 1, float $y = 1, float $z = 1): self
    {
        return new self(0, 0, 0, 0, 0, 0, $x, $y, $z);
    }

    /**
     * Combine this transform with another
     */
    public function combine(Transform $other): Transform
    {
        // Note: In a real implementation, we'd use proper matrix multiplication
        // This is a simplified version for demonstration
        return new self(
            $this->translateX + $other->translateX,
            $this->translateY + $other->translateY,
            $this->translateZ + $other->translateZ,
            $this->rotateX + $other->rotateX,
            $this->rotateY + $other->rotateY,
            $this->rotateZ + $other->rotateZ,
            $this->scaleX * $other->scaleX,
            $this->scaleY * $other->scaleY,
            $this->scaleZ * $other->scaleZ
        );
    }

    /**
     * Apply this transform to a point
     */
    public function applyToPoint(float $x, float $y, float $z = 0): array
    {
        // Simplified transformation - in reality we'd use transformation matrices
        // Apply scale
        $x *= $this->scaleX;
        $y *= $this->scaleY;
        $z *= $this->scaleZ;
        
        // Apply rotation (simplified - assuming rotation around Z axis primarily)
        $radZ = deg2rad($this->rotateZ);
        $cosZ = cos($radZ);
        $sinZ = sin($radZ);
        $xRotated = $x * $cosZ - $y * $sinZ;
        $yRotated = $x * $sinZ + $y * $cosZ;
        $x = $xRotated;
        $y = $yRotated;
        
        // Apply translation
        $x += $this->translateX;
        $y += $this->translateY;
        $z += $this->translateZ;
        
        return [$x, $y, $z];
    }
}
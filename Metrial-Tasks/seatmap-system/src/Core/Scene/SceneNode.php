<?php

namespace App\Core\Scene;

use App\Core\Geometry\Matrix4x4;
use App\Core\Geometry\Vector3;
use App\Core\Geometry\BoundingBox;

/**
 * Scene Node for hierarchical scene graph
 */
class SceneNode
{
    public string $id;
    public ?string $parentId;
    public array $children = [];
    public Matrix4x4 $localTransform;
    public Matrix4x4 $worldTransform;
    public bool $visible = true;
    public ?object $component = null;
    public array $userData = [];

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->parentId = null;
        $this->localTransform = Matrix4x4::identity();
        $this->worldTransform = Matrix4x4::identity();
    }

    public function addChild(SceneNode $child): void
    {
        $child->parentId = $this->id;
        $this->children[] = $child;
    }

    public function removeChild(string $childId): void
    {
        $this->children = array_filter(
            $this->children,
            fn($c) => $c->id !== $childId
        );
    }

    public function setPosition(Vector3 $position): void
    {
        $this->localTransform->m[12] = $position->x;
        $this->localTransform->m[13] = $position->y;
        $this->localTransform->m[14] = $position->z;
    }

    public function setRotation(Matrix4x4 $rotation): void
    {
        // Preserve translation
        $this->localTransform->m[0] = $rotation->m[0];
        $this->localTransform->m[1] = $rotation->m[1];
        $this->localTransform->m[2] = $rotation->m[2];
        $this->localTransform->m[4] = $rotation->m[4];
        $this->localTransform->m[5] = $rotation->m[5];
        $this->localTransform->m[6] = $rotation->m[6];
        $this->localTransform->m[8] = $rotation->m[8];
        $this->localTransform->m[9] = $rotation->m[9];
        $this->localTransform->m[10] = $rotation->m[10];
    }

    public function setScale(Vector3 $scale): void
    {
        // Extract rotation and apply new scale
        $this->localTransform->m[0] *= $scale->x;
        $this->localTransform->m[5] *= $scale->y;
        $this->localTransform->m[10] *= $scale->z;
    }

    public function getPosition(): Vector3
    {
        return new Vector3(
            $this->worldTransform->m[12],
            $this->worldTransform->m[13],
            $this->worldTransform->m[14]
        );
    }

    public function getWorldPosition(): Vector3
    {
        return new Vector3(
            $this->worldTransform->m[12],
            $this->worldTransform->m[13],
            $this->worldTransform->m[14]
        );
    }

    public function getBoundingBox(): BoundingBox
    {
        $box = new BoundingBox();
        if ($this->component && method_exists($this->component, 'getBoundingBox')) {
            $localBox = $this->component->getBoundingBox();
            $corners = $this->getBoxCorners($localBox);
            foreach ($corners as $corner) {
                $worldCorner = $this->worldTransform->transformVector($corner);
                $box->expand($worldCorner);
            }
        }
        return $box;
    }

    private function getBoxCorners(BoundingBox $box): array
    {
        return [
            new Vector3($box->minX, $box->minY, $box->minZ),
            new Vector3($box->maxX, $box->minY, $box->minZ),
            new Vector3($box->maxX, $box->maxY, $box->minZ),
            new Vector3($box->minX, $box->maxY, $box->minZ),
            new Vector3($box->minX, $box->minY, $box->maxZ),
            new Vector3($box->maxX, $box->minY, $box->maxZ),
            new Vector3($box->maxX, $box->maxY, $box->maxZ),
            new Vector3($box->minX, $box->maxY, $box->maxZ),
        ];
    }
}

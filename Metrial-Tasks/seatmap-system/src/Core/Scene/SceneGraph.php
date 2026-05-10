<?php

namespace App\Core\Scene;

use App\Core\Geometry\Matrix4x4;
use App\Core\Geometry\Vector3;
use App\Core\Geometry\BoundingBox;

/**
 * Scene Graph for hierarchical scene management
 */
class SceneGraph
{
    private array $nodes = [];
    private ?string $rootId = null;
    private array $dirtyNodes = [];

    public function createNode(string $id): SceneNode
    {
        $node = new SceneNode($id);
        $this->nodes[$id] = $node;
        
        if ($this->rootId === null) {
            $this->rootId = $id;
        }
        
        $this->markDirty($id);
        return $node;
    }

    public function getNode(string $id): ?SceneNode
    {
        return $this->nodes[$id] ?? null;
    }

    public function removeNode(string $id): void
    {
        if (isset($this->nodes[$id])) {
            // Remove children recursively
            foreach ($this->nodes[$id]->children as $child) {
                $this->removeNode($child->id);
            }
            
            // Remove from parent
            if ($this->nodes[$id]->parentId && isset($this->nodes[$this->nodes[$id]->parentId])) {
                $this->nodes[$this->nodes[$id]->parentId]->removeChild($id);
            }
            
            unset($this->nodes[$id]);
            unset($this->dirtyNodes[$id]);
        }
    }

    public function markDirty(string $id): void
    {
        $this->dirtyNodes[$id] = true;
        $this->markDescendantsDirty($id);
    }

    private function markDescendantsDirty(string $id): void
    {
        if (!isset($this->nodes[$id])) return;
        
        foreach ($this->nodes[$id]->children as $child) {
            $this->dirtyNodes[$child->id] = true;
            $this->markDescendantsDirty($child->id);
        }
    }

    public function updateWorldTransforms(): void
    {
        if ($this->rootId && isset($this->nodes[$this->rootId])) {
            $this->updateNodeWorldTransform($this->nodes[$this->rootId], null);
        }
        $this->dirtyNodes = [];
    }

    private function updateNodeWorldTransform(SceneNode $node, ?Matrix4x4 $parentWorld): void
    {
        if ($parentWorld) {
            $node->worldTransform = $parentWorld->multiply($node->localTransform);
        } else {
            $node->worldTransform = $node->localTransform;
        }

        foreach ($node->children as $child) {
            $this->updateNodeWorldTransform($child, $node->worldTransform);
        }
    }

    public function traverse(callable $visitor, ?string $startId = null): void
    {
        $startId = $startId ?? $this->rootId;
        if ($startId && isset($this->nodes[$startId])) {
            $this->traverseNode($this->nodes[$startId], $visitor);
        }
    }

    private function traverseNode(SceneNode $node, callable $visitor): void
    {
        $visitor($node);
        foreach ($node->children as $child) {
            $this->traverseNode($child, $visitor);
        }
    }

    public function findNodesByType(string $type): array
    {
        $results = [];
        $this->traverse(function ($node) use ($type, &$results) {
            if ($node->component && get_class($node->component) === $type) {
                $results[] = $node;
            }
        });
        return $results;
    }

    public function getNodesInFrustum(array $frustumPlanes): array
    {
        $results = [];
        $this->traverse(function ($node) use ($frustumPlanes, &$results) {
            if (!$node->visible) return;
            
            $box = $node->getBoundingBox();
            if ($this->boxInFrustum($box, $frustumPlanes)) {
                $results[] = $node;
            }
        });
        return $results;
    }

    private function boxInFrustum(BoundingBox $box, array $planes): bool
    {
        foreach ($planes as $plane) {
            $corner = new Vector3(
                $plane->normal->x > 0 ? $box->maxX : $box->minX,
                $plane->normal->y > 0 ? $box->maxY : $box->minY,
                $plane->normal->z > 0 ? $box->maxZ : $box->minZ
            );
            
            if ($plane->distanceToPoint($corner) < 0) {
                return false;
            }
        }
        return true;
    }

    public function getNodesInSphere(Vector3 $center, float $radius): array
    {
        $results = [];
        $this->traverse(function ($node) use ($center, $radius, &$results) {
            if (!$node->visible) return;
            
            $box = $node->getBoundingBox();
            if ($this->boxIntersectsSphere($box, $center, $radius)) {
                $results[] = $node;
            }
        });
        return $results;
    }

    private function boxIntersectsSphere(BoundingBox $box, Vector3 $center, float $radius): bool
    {
        $closest = new Vector3(
            max($box->minX, min($center->x, $box->maxX)),
            max($box->minY, min($center->y, $box->maxY)),
            max($box->minZ, min($center->z, $box->maxZ))
        );
        
        return $center->distanceTo($closest) <= $radius;
    }

    public function pickRay(Vector3 $origin, Vector3 $direction): ?array
    {
        $closestHit = null;
        $closestDistance = INF;

        $this->traverse(function ($node) use ($origin, $direction, &$closestHit, &$closestDistance) {
            if (!$node->visible || !$node->component) return;
            
            $invWorld = $node->worldTransform->inverse();
            $localOrigin = $invWorld->transformVector($origin);
            $localDirection = $invWorld->transformVector($direction);
            
            if (method_exists($node->component, 'intersectRay')) {
                $hit = $node->component->intersectRay($localOrigin, $localDirection);
                if ($hit && $hit['distance'] < $closestDistance) {
                    $closestDistance = $hit['distance'];
                    $closestHit = [
                        'node' => $node,
                        'distance' => $hit['distance'],
                        'point' => $node->worldTransform->transformVector($hit['point']),
                        'normal' => $node->worldTransform->transformVector($hit['normal'])->normalize()
                    ];
                }
            }
        });

        return $closestHit;
    }
}

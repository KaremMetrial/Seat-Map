<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventElementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->element_type,
            'x' => (float) $this->x,
            'y' => (float) $this->y,
            'width' => (float) $this->width,
            'height' => (float) $this->height,
            'rotation' => (float) $this->rotation,
            'z_index' => $this->z_index,
            'parent_id' => $this->parent_id,
            'data' => $this->data_json,
            'style' => $this->style_json,
            'is_bookable' => $this->is_bookable,
            'zone_id' => $this->zone_id,
            'status' => $this->booking_status ?? 'available',
        ];
    }
}
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'start_at' => $this->event->start_at,
                'canvas' => [
                    'width' => $this->event->template->canvas_width,
                    'height' => $this->event->template->canvas_height,
                    'background_image' => $this->event->template->background_image,
                    'background_color' => $this->event->template->background_color,
                ],
            ],
            'elements' => EventElementResource::collection($this->elements),
            'zones' => $this->event->template->zones,
        ];
    }
}
<?php


namespace Marketredesign\MrdAuth0Laravel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatasetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'dss_url' => $this['dss_url'],
            'created_at' => $this['created_at'],
            'updated_at' => $this['updated_at'],
            'modules' => $this['modules'],
        ];
    }
}

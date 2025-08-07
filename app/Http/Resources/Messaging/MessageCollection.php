<?php
// MessageCollection.php
namespace App\Http\Resources\Messaging;

use Illuminate\Http\Resources\Json\ResourceCollection;

class MessageCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->transform(function ($message) {
                return new MessageResource($message);
            }),
        ];
    }
}

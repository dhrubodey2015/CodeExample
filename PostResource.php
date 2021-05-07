<?php

namespace App\Http\Resources;

use App\Models\Lock;
use Illuminate\Http\Request;

class PostResource extends BaseJsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     *
     * @return array
     */
    public function toArray($request): array
    {
        $lock = ($this->lock && $this->lock->state_id == Lock::STATE_ACTIVE_ID
            ? new LockResource($this->lock)
            : null);
        $historyCreate = $this->history()
            ->where('action', 'create')
            ->first();
        $historyEdit = $this->history()
            ->where('action', 'update')
            ->orderBy('created_at', 'desc')
            ->first();

        return $this->filterFields([
            'id' => $this->id,
            'external_link' => $this->external_link,
            'external_source' => new ExternalSourceResource(
                $this->externalSource
            ),
            'item_type' => new ItemTypeResource($this->itemType),
            'state' => $this->state(),
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => /*html_entity_decode*/ ($this->body),
            'content' => $this->content,
            'short' => $this->short,
            'comment' => $this->comment,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'images' => ItemImageResource::collection($this->images),
            'keywords' => ItemKeywordResource::collection($this->keywords),
            'tags' => ItemTagResource::collection($this->tags),
            'lock' => new LockResource($lock),
            'created' => new HistoryResource($historyCreate),
            'edited' => new HistoryResource($historyEdit),
            'publications' => PublicationResource::collection(
                $this->publications
            ),
        ]);
    }
}

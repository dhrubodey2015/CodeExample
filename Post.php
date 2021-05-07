<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends BaseModel
{
    use SoftDeletes;

    const STATE_SUBMITTED_ID = 0;
    const STATE_ARCHIVED_ID = 1;
    const STATE_MOCKUP_ID = 2;
    const STATE_PUBLICATION_ID = 3;
    const STATE_PUBLISHED_ID = 4;

    const STATE_SUBMITTED = [
        'id' => self::STATE_SUBMITTED_ID,
        'slug' => 'submitted',
        'name' => 'Submitted'
    ];
    const STATE_ARCHIVED = [
        'id' => self::STATE_ARCHIVED_ID,
        'slug' => 'archived',
        'name' => 'Archived'
    ];
    const STATE_MOCKUP = [
        'id' => self::STATE_MOCKUP_ID,
        'slug' => 'mockup',
        'name' => 'Mockup'
    ];
    const STATE_PUBLICATION = [
        'id' => self::STATE_PUBLICATION_ID,
        'slug' => 'publication',
        'name' => 'Publication'
    ];
    const STATE_PUBLISHED = [
        'id' => self::STATE_PUBLISHED_ID,
        'slug' => 'publication',
        'name' => 'Publication'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'state_id',
        'external_source_id',
        'external_link',
        'item_type_id',
        'title',
        'slug',
        'body',
        'content',
        'short',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'comment',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Returns user model relation.
     *
     * @return HasOne
     */
    public function itemType(): HasOne
    {
        return $this->hasOne(ItemType::class, 'id', 'item_type_id');
    }

    /**
     * Returns user model relation.
     *
     * @return BelongsTo
     */
    public function externalSource(): BelongsTo
    {
        return $this->belongsTo(ExternalSource::class);
    }

    /**
     * Returns the state of the section.
     *
     * @return array
     */
    public function state()
    {
        if ($this->publication && $this->publication->publish_at) {
            return self::STATE_PUBLISHED;
        }

        switch ($this->state_id) {
            case self::STATE_SUBMITTED_ID:
                return self::STATE_SUBMITTED;

            case self::STATE_ARCHIVED_ID:
                return self::STATE_ARCHIVED;

            case self::STATE_MOCKUP_ID:
                return self::STATE_MOCKUP;

            case self::STATE_PUBLICATION_ID:
                return self::STATE_PUBLICATION;

            case self::STATE_PUBLISHED_ID:
                return self::STATE_PUBLISHED;
        }
    }

    /**
     * Returns related model images.
     *
     * @return MorphMany
     */
    public function images(): morphMany
    {
        return $this->morphMany(ItemImage::class, 'imageable');
    }

    /**
     * Returns related model keywords.
     *
     * @return MorphMany
     */
    public function keywords(): morphMany
    {
        return $this->morphMany(ItemKeyword::class, 'keywordable');
    }

    /**
     * Returns related model tags.
     *
     * @return MorphMany
     */
    public function tags(): morphMany
    {
        return $this->morphMany(ItemTag::class, 'taggable');
    }

    /**
     * Returns related model history.
     *
     * @return MorphMany
     */
    public function history(): morphMany
    {
        return $this->morphMany(History::class, 'historable');
    }

    /**
     * Get the post's lock info.
     */
    public function lock(): MorphOne
    {
        return $this->morphOne(Lock::class, 'lockable')
            ->where('state_id', Lock::STATE_ACTIVE_ID);
    }

    /**
     * Get the post's publication info.
     *
     * @return MorphMany
     */
    public function publications(): MorphMany
    {
        return $this->morphMany(Publication::class, 'publicable');
    }

    public function scopePublished($query)
    {
        return $query;
    }
}

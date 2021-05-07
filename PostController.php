<?php

namespace App\Http\Controllers;

use App\Helpers\StorageHelper;
use App\Http\Requests\PostRequest;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\History;
use App\Models\ItemImage;
use App\Models\ItemKeyword;
use App\Models\ItemTag;
use App\Models\ItemType;
use App\Models\Lock;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Post;
use App\Models\Publication;
use App\Models\Section;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param PostRequest $request
     *
     * @return ResourceCollection
     */
    public function index(PostRequest $request): ResourceCollection
    {
        $posts = Post::query()
            ->latest();

        foreach ($request->get('filters', []) as $filter => $value) {
            if ($posts->hasNamedScope($filter)) {
                $posts->{$filter}();
            }
        }

        if ($request->has('state_id')) {
            $posts->where('state_id', $request->get('state_id'));
        }

        if ($request->has('waiting_list')
            && $request->get('waiting_list') == false) {
            $posts->whereHas('publications', function ($query) {
                $query->where('is_published', true)
                    ->whereDate('publish_at', '>', Carbon::now());
            })->latest();
        }

        return PostResource::collection($posts->paginate());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param PostRequest $request
     *
     * @return PostResource
     */
    public function store(PostRequest $request): PostResource
    {
        $requestData = $this->getRequestData($request);

        if ($request->has('body')) {
            /** @TODO: Process encoding htmlentities */
            $requestData['body'] = ($request->input('body'));
        }

        $post = Post::create($requestData);
        $post->history()->save(new History([
            'action' => 'create',
            'user_id' => auth()->user()->id,
        ]));

        $this->saveRelations($request, $post);

        return new PostResource($post);
    }

    /**
     * Display the specified resource.
     *
     * @param PostRequest $request
     * @param Post $post
     *
     * @return PostResource
     */
    public function show(PostRequest $request, Post $post): PostResource
    {
        return new PostResource($post);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param PostRequest $request
     * @param Post $post
     *
     * @return PostResource|JsonResponse
     */
    public function update(PostRequest $request, Post $post)
    {
        $requestData = $this->getRequestData($request);

        if ($request->has('body')) {
            $requestData['body'] = /*htmlentities*/
                ($request->get('body'));
        }

        $post->update($requestData);

        $this->saveRelations($request, $post);

        return new PostResource($post);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param PostRequest $request
     * @param Post $post
     *
     * @return JsonResponse
     * @throws Exception
     */
    public function destroy(PostRequest $request, Post $post): JsonResponse
    {
        if ($post->publications()->count() == 0) {
            $post->forceDelete();
        } else {
            $post->publications()
                ->delete();

            $post->delete();
            $post->history()->save(new History([
                'action' => 'delete',
                'user_id' => auth()->user()->id,
            ]));
        }

        return response()->json(['success' => true], Response::HTTP_OK);
    }

    /**
     * Retrieve the post data from the request.
     *
     * @param PostRequest $request
     *
     * @return array
     */
    protected function getRequestData(PostRequest $request)
    {
        $requestData = $request->only(
            'state_id',
            'external_link',
            'external_source_id',
            'item_type_id',
            'title',
            'body',
            'content',
            'short',
            'meta_title',
            'meta_description',
            'meta_keywords',
            'comment'
        );

        if ($request->has('title')) {
            $requestData['slug'] = Str::slug($request->get('title'));
        }

        return $requestData;
    }

    /**
     * Save post relations.
     *
     * @param PostRequest $request
     * @param Post $post
     */
    protected function saveRelations(PostRequest $request, Post $post)
    {
        if ($request->has('images')) {
            $this->saveImages($request, $post);
        }

        if ($request->has('lock')) {
            if ($post->lock) {
                if ($post->lock->user->id === auth()->user()->id) {
                    $post->lock->update([
                        'state_id' => ($request->get('lock') == true
                            ? Lock::STATE_ACTIVE_ID
                            : Lock::STATE_INACTIVE_ID)
                    ]);
                }
            } else {
                $post->lock = $post->lock()->save(new Lock([
                    'user_id' => auth()->user()->id,
                    'state_id' => ($request->get('lock') == true
                        ? Lock::STATE_ACTIVE_ID
                        : Lock::STATE_INACTIVE_ID)
                ]));
            }
        }

        if ($request->has('keywords')) {
            $post->keywords()
                ->delete();

            if (count($request->get('keywords')) > 0) {
                $keywordIds = array_unique($request->get('keywords'));
                $keywords = [];

                foreach ($keywordIds as $keywordId) {
                    $keywords[] = new ItemKeyword(['keyword_id' => $keywordId]);
                }

                $post->keywords()->saveMany($keywords);
            }
        }

        if ($request->has('tags')) {
            $post->tags()
                ->where('taggable_id', $post->id)
                ->delete();

            if (count($request->get('tags')) > 0) {
                $tagIds = array_unique($request->get('tags'));
                $tags = [];

                foreach ($tagIds as $tagId) {
                    $tags[] = new ItemTag(['tag_id' => $tagId]);
                }

                $post->tags()->saveMany($tags);
            }
        }

        if ($request->has('publish')) {
            $this->processPublish($request, $post);
        }

        if ($request->has('publications')) {
            $publications = $request->get('publications', []);

            foreach ($publications as $publication) {
                if (!array_key_exists('page_block_id', $publication)) {
                    throw new BadRequestException('Page block id is required.');
                }

                PageBlock::findOrFail($publication['page_block_id']);
            }

            $post->publications()
                ->forceDelete();

            foreach ($publications as $publication) {
                $post->publications()->save(new Publication($publication));
            }
        }

        $post->history()->save(new History([
            'action' => 'update',
            'user_id' => auth()->user()->id,
        ]));
    }

    /**
     * Save post images.
     *
     * @param PostRequest $request
     * @param Post $post
     *
     * @return JsonResponse
     */
    protected function saveImages(PostRequest $request, Post $post)
    {
        try {
            $storageHelper = new StorageHelper();

            foreach ($request->get('images') as $index => $requestImageItem) {
                if (isset($requestImageItem['image'])
                    && isset($requestImageItem['image']['path'])
                    && is_string($requestImageItem['image']['path'])) {
                    $postImage = $post->images()
                        ->where('cols_count', $requestImageItem['cols_count'])
                        ->where('rows_count', $requestImageItem['rows_count'])
                        ->first();

                    if ($postImage) {
                        $postImage->image()->delete();
                    }

                    $image = $storageHelper->imageUpload(
                        $requestImageItem['image']['path']
                    );

                    $post->images()->save(new ItemImage([
                        'image_id' => $image->id,
                        'cols_count' => $requestImageItem['cols_count'],
                        'rows_count' => $requestImageItem['rows_count']
                    ]));
                }
            }
        } catch (FileException $exception) {
            return response()->json([
                'error' => ['message' => $exception->getMessage()]
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Save a new publication of the item.
     *
     * @param PostRequest $request
     * @param Post $post
     *
     * @return JsonResponse
     */
    protected function processPublish(PostRequest $request, Post $post)
    {
        try {
            $publishData = $request->get('publish');

            if (!isset($publishData['section_id'])) {
                throw new BadRequestException(
                    'Post publication request data is not correct.'
                );
            }

            $section = Section::findOrFail($publishData['section_id']);
            $category = isset($publishData['category_id'])
                ? Category::findOrFail($publishData['category_id'])
                : null;
            $itemType = isset($publishData['item_type_id'])
                ? ItemType::findOrFail($publishData['item_type_id'])
                : null;
            $isFeatured = isset($publishData['is_featured'])
                ? $publishData['is_featured']
                : false;

            $publicationBlocks = Page::getPublicationBlocks(
                $section,
                $category,
                $itemType,
                $isFeatured
            );

            foreach ($publicationBlocks as $publicationBlock) {
                $publishedCount = $post->publications()
                    ->where('page_block_id', $publicationBlock->id)
                    ->count();

                if ($publishedCount == 0) {
                    $post->publications()->save(new Publication([
                        'page_block_id' => $publicationBlock->id,
                    ]));
                }
            }
        } catch (FileException $exception) {
            return response()->json([
                'error' => ['message' => $exception->getMessage()]
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}

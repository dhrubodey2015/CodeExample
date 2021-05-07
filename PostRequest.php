<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can(
            $this->route()->getActionMethod(),
            $this->route('post') ?? Post::class
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if (!in_array($this->method(), [
            Request::METHOD_POST,
            Request::METHOD_PUT,
            Request::METHOD_PATCH,
        ])) {
            return [];
        }

        $rules = [
            'external_link' => ['active_url'],
            'external_source_id' => 'nullable|exists:external_sources,id',
            'item_type_id' => 'nullable|exists:item_types,id',
            'image_id' => 'nullable|exists:files,id',
            'image_vertical_id' => 'nullable|exists:files,id',
            'image_horizontal_id' => 'nullable|exists:files,id',
            'image_square_id' => 'nullable|exists:files,id',
            'title' => [
                'string',
                'nullable',
                'max:255'
            ],
            'slug' => [
                'string',
                'nullable',
                'max:255',
            ],
            'body' => 'string|nullable',
            'content' => 'string|nullable',
            'short' => 'string|nullable',
            'meta_title' => 'string|nullable|max:255',
            'meta_description' => 'string|nullable',
            'meta_keywords' => 'string|nullable',
            'state_id' => 'numeric|min:0',
            'lock' => 'boolean|nullable',
            'categories' => 'array|nullable',
            'keywords' => 'array|nullable',
            'tags' => 'array|nullable',
            'images' => 'array|nullable',
        ];

        if ($this->method() == Request::METHOD_POST
            || $this->has('external_link')) {
            $rules['external_link'] = 'required';
        }

        if ($this->method() == Request::METHOD_PUT) {
            $rules['title'][] = 'unique:posts,title,' . $this->post->id;
            $rules['slug'][] = 'unique:posts,slug,' . $this->post->id;
        }

        return $rules;
    }
}

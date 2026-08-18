<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\Api\V1\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $tags = Tag::query()
            ->where('user_id', $user->id)
            ->orderBy('name');

        if ($request->routeIs('api.v1.*')) {
            return TagResource::collection($tags->paginate())->response();
        }

        return response()->json($tags->get());
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        if ($request->routeIs('api.v1.*')) {
            return (new TagResource($tag))->response()->setStatusCode(201);
        }

        return response()->json($tag, 201);
    }

    public function show(Request $request, Tag $tag): JsonResponse
    {
        abort_unless($tag->user_id === $request->user()?->id, 404);

        if ($request->routeIs('api.v1.*')) {
            return (new TagResource($tag))->response();
        }

        return response()->json($tag);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        abort_unless($tag->user_id === $request->user()?->id, 404);

        $tag->update($request->validated());

        $tag = $tag->fresh();

        if ($request->routeIs('api.v1.*')) {
            return (new TagResource($tag))->response();
        }

        return response()->json($tag);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        abort_unless($tag->user_id === $request->user()?->id, 404);

        $tag->delete();

        return response()->json(status: 204);
    }
}

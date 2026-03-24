<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
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
            ->orderBy('name')
            ->get();

        return response()->json($tags);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = Tag::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($tag, 201);
    }

    public function show(Request $request, Tag $tag): JsonResponse
    {
        abort_unless($tag->user_id === $request->user()?->id, 404);

        return response()->json($tag);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        abort_unless($tag->user_id === $request->user()?->id, 404);

        $tag->update($request->validated());

        return response()->json($tag->fresh());
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        abort_unless($tag->user_id === $request->user()?->id, 404);

        $tag->delete();

        return response()->json(status: 204);
    }
}

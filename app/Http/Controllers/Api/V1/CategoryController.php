<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->orderBy('name');

        if ($request->routeIs('api.v1.*')) {
            return CategoryResource::collection($categories->paginate())->response();
        }

        return response()->json($categories->get());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        if ($request->routeIs('api.v1.*')) {
            return (new CategoryResource($category))->response()->setStatusCode(201);
        }

        return response()->json($category, 201);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        abort_unless($category->user_id === $request->user()?->id, 404);

        if ($request->routeIs('api.v1.*')) {
            return (new CategoryResource($category))->response();
        }

        return response()->json($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        abort_unless($category->user_id === $request->user()?->id, 404);

        $category->update($request->validated());

        $category = $category->fresh();

        if ($request->routeIs('api.v1.*')) {
            return (new CategoryResource($category))->response();
        }

        return response()->json($category);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_unless($category->user_id === $request->user()?->id, 404);

        $category->delete();

        return response()->json(status: 204);
    }
}

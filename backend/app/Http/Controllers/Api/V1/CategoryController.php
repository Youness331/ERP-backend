<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    use Paginates;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query()
            ->withCount('products')
            ->search($request->query('search'));

        if ($request->boolean('tree')) {
            $query->whereNull('parent_id')->with('children');
        }

        return CategoryResource::collection(
            $query->orderBy('name')->paginate($this->perPage($request))
        );
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        return new CategoryResource(Category::create($request->validated()));
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('products')->load('children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Category $category): Response
    {
        if ($category->products()->exists()) {
            throw new BusinessException('Impossible de supprimer une catégorie contenant des produits.');
        }

        $category->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use DataTables;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        if (!request()->ajax()) {
            return view('categories.index');
        }

        $query = Category::currentUser()
            ->select(
                'id', 'type', 'name'
            );

        return DataTables::eloquent($query)
            ->make(true);
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create($request->all());

        return to_route('categories.index')->with('success', "Categoría \"{$category->name}\" creada");
    }

    public function create() {
        return view('categories.create');
    }

}

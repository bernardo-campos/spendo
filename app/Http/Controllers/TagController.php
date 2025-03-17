<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Models\Tag;
use DataTables;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        if (!request()->ajax()) {
            return view('tags.index');
        }

        $query = Tag::currentUser()
            ->select(
                'id', 'type', 'name'
            );

        return DataTables::eloquent($query)
            ->make(true);
    }

    public function store(TagRequest $request)
    {
        $tag = Tag::create($request->all());

        return to_route('tags.index')->with('success', "Etiqueta \"{$tag->name}\" creada");
    }

    public function create() {
        return view('tags.create');
    }
}

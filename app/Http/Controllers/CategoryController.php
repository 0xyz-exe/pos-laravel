<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index() { return view('categories.index', ['categories' => Category::withCount('products')->orderBy('name')->paginate(20)]); }
    public function create() { return view('categories.form', ['category' => new Category]); }
    public function store(Request $request) { $category = Category::create($this->validated($request)); return redirect()->route('categories.index')->with('success', $category->name.' created.'); }
    public function edit(Category $category) { return view('categories.form', compact('category')); }
    public function update(Request $request, Category $category) { $category->update($this->validated($request, $category)); return redirect()->route('categories.index')->with('success', 'Category updated.'); }
    public function destroy(Category $category) { $category->delete(); return redirect()->route('categories.index')->with('success', 'Category deleted; products remain uncategorized.'); }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate(['name' => ['required','string','max:100', Rule::unique('categories','name')->ignore($category)], 'slug' => ['nullable','string','max:120', Rule::unique('categories','slug')->ignore($category)] ]);
        $data['slug'] = Str::slug(($data['slug'] ?? '') ?: $data['name']);
        validator($data, ['slug' => ['required','max:120', Rule::unique('categories','slug')->ignore($category)]])->validate();
        return $data;
    }
}

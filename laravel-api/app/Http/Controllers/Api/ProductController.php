<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductReplica;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $cacheKey = 'products:index';

        $products = Cache::get($cacheKey);
        $source = 'redis-cache';
        $databaseSource = 'not-queried';

        if (!$products) {
            $products = ProductReplica::query()
                ->with(['category', 'images'])
                ->latest()
                ->get();

            Cache::put($cacheKey, $products, now()->addMinutes(10));

            $source = 'database';
            $databaseSource = 'product-replica';
        }

        return response()
            ->json([
                'message' => 'Daftar produk berhasil diambil',
                'data' => $products,
            ])
            ->header('X-Data-Source', $source)
            ->header('X-Database-Source', $databaseSource);
    }

    private function clearProductCache(?int $productId = null): void
    {
        Cache::forget('products:index');


        if ($productId) {
            Cache::forget('products:show:' . $productId);
        }
    }

    public function show(Product $product)
    {
        $cacheKey = 'products:show:' . $product->id;

        $cachedProduct = Cache::get($cacheKey);
        $source = 'redis-cache';
        $databaseSource = 'not queried';

        if (!$cachedProduct) {
            $replicaProduct = ProductReplica::query()
                ->with(['category', 'images'])
                ->findOrFail($product->id);

            Cache::put(
                $cacheKey,
                $replicaProduct,
                now()->addMinutes(10)
            );

            $cachedProduct = $replicaProduct;
            $source = 'database';
            $databaseSource = 'product-replica';
        }

        return response()
            ->json([
                'message' => 'Detail produk berhasil diambil',
                'data' => $cachedProduct,
            ])
            ->header('X-Data-Source', $source)
            ->header('X-Database-Source', $databaseSource);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('product_db.categories', 'id'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'condition' => ['required', 'in:new,used,sealed,pre_order'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = Product::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $this->makeUniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'condition' => $validated['condition'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->clearProductCache();

        return response()->json([
            'message' => 'Produk berhasil dibuat',
            'data' => $product->load('category'),
        ], 201);
        
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('product_db.categories', 'id'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'condition' => ['required', 'in:new,used,sealed,pre_order'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product->update([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $this->makeUniqueSlug($validated['name'], $product->id),
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'condition' => $validated['condition'],
            'is_active' => $validated['is_active'] ?? $product->is_active,
        ]);

        $this->clearProductCache($product->id);

        return response()->json([
            'message' => 'Produk berhasil diperbarui',
            'data' => $product->fresh()->load('category'),
        ]);
        
    }

    public function destroy(Product $product)
    {
        $product->delete();

        $this->clearProductCache($product->id);

        return response()->json([
            'message' => 'Produk berhasil dihapus',
        ]);
    }

    private function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (
            Product::query()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}

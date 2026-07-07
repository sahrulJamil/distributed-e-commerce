<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class ProductImageController extends Controller
{
    private function clearProductCache(int $productId): void
    {
        Cache::forget('products:index');
        Cache::forget('products:show:' . $productId);
    }
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:180'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $isPrimary = $request->boolean('is_primary');

        // ganti status utama
        if ($isPrimary) {
            $product->images()
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $path = $request->file('image')->store('products', 'public');

        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => $path,
            'alt_text' => $validated['alt_text'] ?? null,
            'sort_order' => $product->images()->count(),
            'is_primary' => $isPrimary,
        ]);

        $this->clearProductCache($product->id);

        return response()->json([
            'message' => 'Gambar produk berhasil diunggah',
            'data' => [
                ...$image->toArray(),
                'image_url' => asset('storage/' . $image->image_path),
            ],
        ], 201);
    }

    public function destroy(Product $product, ProductImage $image)
    {
        if ($image->product_id !== $product->id) {
            return response()->json([
                'message' => 'Gambar tidak ditemukan pada produk ini',
            ], 404);
        }

        Storage::disk('public')->delete($image->image_path);

        $image->delete();

        $this->clearProductCache($product->id);

        return response()->json([
            'message' => 'Gambar produk berhasil dihapus',
        ]);
    }
}
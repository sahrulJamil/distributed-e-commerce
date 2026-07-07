<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        // Produk berasal dari product_db.
        $product = Product::find($validated['product_id']);

        if (!$product || !$product->is_active) {
            return response()->json([
                'message' => 'Produk tidak ditemukan atau tidak aktif',
            ], 404);
        }

        if ($product->stock < $validated['quantity']) {
            return response()->json([
                'message' => 'Stok produk tidak mencukupi',
            ], 422);
        }

        // Cari cart aktif milik user, jika belum ada maka dibuat.
        $cart = Cart::firstOrCreate(
            [
                'user_id' => $user->id,
                'status' => 'active',
            ]
        );

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $validated['quantity'];

            if ($newQuantity > $product->stock) {
                return response()->json([
                    'message' => 'Jumlah produk di keranjang melebihi stok tersedia',
                ], 422);
            }

            $cartItem->update([
                'quantity' => $newQuantity,
            ]);
        } else {
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Produk berhasil ditambahkan ke keranjang',
            'data' => [
                'cart_id' => $cart->id,
                'cart_item_id' => $cartItem->id,
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
            ],
        ], 201);
    }

    public function show(Request $request)
    {
        $user = $request->user();

        $cart = Cart::with('items')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Keranjang masih kosong',
                'data' => [
                    'items' => [],
                    'total_price' => 0,
                ],
            ]);
        }

        // Ambil seluruh product_id dari cart_items.
        $productIds = $cart->items->pluck('product_id');

        // Query ke product_db sekali saja, bukan satu query per item.
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->with('images')
            ->get()
            ->keyBy('id');

        $items = $cart->items->map(function (CartItem $item) use ($products) {
            $product = $products->get($item->product_id);

            // Antisipasi jika produk sudah nonaktif atau dihapus.
            if (!$product) {
                return [
                    'cart_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => null,
                    'quantity' => $item->quantity,
                    'subtotal' => 0,
                ];
            }

            $image = $product->images->firstWhere('is_primary', true)
                ?? $product->images->first();

            return [
                'cart_item_id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'price' => $product->price,
                'stock' => $product->stock,
                'quantity' => $item->quantity,
                'subtotal' => $product->price * $item->quantity,
                'image' => $image ? [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => asset('storage/' . $image->image_path),
                ] : null,
            ];
        });

        return response()->json([
            'message' => 'Keranjang berhasil diambil',
            'data' => [
                'cart_id' => $cart->id,
                'items' => $items,
                'total_price' => $items->sum('subtotal'),
            ],
        ]);
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        // Pastikan cart item memang milik cart aktif user yang login.
        $cart = Cart::where('id', $cartItem->cart_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Item keranjang tidak ditemukan atau bukan milik Anda',
            ], 404);
        }

        // Ambil produk dari product_db untuk cek stok terbaru.
        $product = Product::find($cartItem->product_id);

        if (!$product || !$product->is_active) {
            return response()->json([
                'message' => 'Produk tidak ditemukan atau tidak aktif',
            ], 404);
        }

        if ($validated['quantity'] > $product->stock) {
            return response()->json([
                'message' => 'Jumlah produk melebihi stok tersedia',
                'available_stock' => $product->stock,
            ], 422);
        }

        $cartItem->update([
            'quantity' => $validated['quantity'],
        ]);

        return response()->json([
            'message' => 'Jumlah produk di keranjang berhasil diperbarui',
            'data' => [
                'cart_id' => $cart->id,
                'cart_item_id' => $cartItem->id,
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
            ],
        ]);
    }

    public function removeItem(Request $request, CartItem $cartItem)
    {
        $user = $request->user();

        // Pastikan item tersebut memang ada di cart aktif milik user login.
        $cart = Cart::where('id', $cartItem->cart_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Item keranjang tidak ditemukan atau bukan milik Anda',
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'Item berhasil dihapus dari keranjang',
            'data' => [
                'cart_id' => $cart->id,
                'cart_item_id' => $cartItem->id,
            ],
        ]);
    }
}
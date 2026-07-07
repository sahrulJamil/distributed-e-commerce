<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Transaction as TransactionModel;
use App\Models\TransactionDetail;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CheckoutController extends Controller
{
    public function store(Request $request, ActivityLogService $activityLogService)
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer'],
        ]);

        $user = $request->user();

        // Validasi alamat di user_db dan pastikan milik user login.
        $address = Address::query()
            ->where('id', $validated['address_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$address) {
            return response()->json([
                'message' => 'Alamat tidak ditemukan atau bukan milik Anda',
            ], 404);
        }

        $snapshots = [];
        $stockDeducted = false;

        try {
            $transaction = DB::connection('transaction_db')->transaction(function () use (
                $user,
                $address,
                &$snapshots,
                &$stockDeducted
            ) {
                /*
                 * Lock cart aktif agar satu cart tidak diproses checkout
                 * oleh dua request secara bersamaan.
                 */
                $cart = Cart::query()
                    ->with('items')
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (!$cart || $cart->items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => ['Keranjang masih kosong.'],
                    ]);
                }

                $productIds = $cart->items
                    ->pluck('product_id')
                    ->unique()
                    ->values();

                /*
                 * Ambil produk dari product_db, lock stoknya,
                 * cek ketersediaan, lalu kurangi stok.
                 */
                DB::connection('product_db')->transaction(function () use (
                    $productIds,
                    $cart,
                    &$snapshots
                ) {
                    $products = Product::query()
                        ->whereIn('id', $productIds)
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    foreach ($cart->items as $cartItem) {
                        $product = $products->get($cartItem->product_id);

                        if (!$product || !$product->is_active) {
                            throw ValidationException::withMessages([
                                'cart' => [
                                    "Produk ID {$cartItem->product_id} sudah tidak tersedia atau tidak aktif.",
                                ],
                            ]);
                        }

                        if ($product->stock < $cartItem->quantity) {
                            throw ValidationException::withMessages([
                                'cart' => [
                                    "Stok produk {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}.",
                                ],
                            ]);
                        }

                        $subtotal = round(
                            (float) $product->price * $cartItem->quantity,
                            2
                        );

                        /*
                         * Snapshot disimpan agar riwayat transaksi tidak berubah
                         * walaupun nama atau harga produk nanti berubah.
                         */
                        $snapshots[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'product_price' => $product->price,
                            'quantity' => $cartItem->quantity,
                            'subtotal' => $subtotal,
                        ];

                        $product->decrement('stock', $cartItem->quantity);
                    }
                });

                $stockDeducted = true;

                $totalPrice = collect($snapshots)->sum('subtotal');

                $transaction = TransactionModel::create([
                    'user_id' => $user->id,
                    'address_id' => $address->id,
                    'invoice_number' => 'INV-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5)),
                    'transaction_date' => now(),
                    'total_price' => $totalPrice,
                    'status' => 'pending',
                ]);

                foreach ($snapshots as $snapshot) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $snapshot['product_id'],
                        'product_name' => $snapshot['product_name'],
                        'product_price' => $snapshot['product_price'],
                        'quantity' => $snapshot['quantity'],
                        'subtotal' => $snapshot['subtotal'],
                    ]);
                }

                $cart->update([
                    'status' => 'checked_out',
                ]);

                return $transaction->load('details');
            });

            Cache::forget('products:index');

            foreach ($snapshots as $snapshot) {
                Cache::forget('products:show:' . $snapshot['product_id']);
            }

            try {
                $activityLogService->log(
                    userId: $user->id,
                    action: 'checkout_created',
                    description: 'User berhasil melakukan checkout',
                    metadata: [
                        'transaction_id' => $transaction->id,
                        'invoice_number' => $transaction->invoice_number,
                        'address_id' => $address->id,
                        'total_price' => $transaction->total_price,
                        'items_count' => count($snapshots),
                        'items' => collect($snapshots)->map(fn ($snapshot) => [
                            'product_id' => $snapshot['product_id'],
                            'product_name' => $snapshot['product_name'],
                            'quantity' => $snapshot['quantity'],
                            'subtotal' => $snapshot['subtotal'],
                        ])->values()->all(),
                    ],
                    request: $request,
                );
            } catch (Throwable $e) {
                report($e);
            }

            return response()->json([
                'message' => 'Checkout berhasil dibuat',
                'data' => $transaction,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            /*
             * Bila stok sudah terpotong di product_db tetapi gagal membuat
             * transaksi di transaction_db, stok dikembalikan.
             */
            if ($stockDeducted && !empty($snapshots)) {
                DB::connection('product_db')->transaction(function () use ($snapshots) {
                    foreach ($snapshots as $snapshot) {
                        Product::query()
                            ->where('id', $snapshot['product_id'])
                            ->increment('stock', $snapshot['quantity']);
                    }
                });
            }

            report($e);

            return response()->json([
                'message' => 'Checkout gagal diproses',
            ], 500);
        }
    }
}
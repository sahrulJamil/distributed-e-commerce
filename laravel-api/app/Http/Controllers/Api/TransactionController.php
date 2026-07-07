<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = Transaction::query()
            ->with('details')
            ->where('user_id', $request->user()->id)
            ->latest('transaction_date')
            ->get();

        return response()->json([
            'message' => 'Riwayat transaksi berhasil diambil',
            'data' => $transactions,
        ]);
    }

    public function show(Request $request, Transaction $transaction)
    {
        $user = $request->user();

        // User tidak boleh melihat transaksi milik user lain.
        if ($transaction->user_id !== $user->id) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan atau bukan milik Anda',
            ], 404);
        }

        $transaction->load('details');

        // Address berasal dari user_db, jadi diambil manual.
        $address = Address::query()
            ->where('id', $transaction->address_id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'message' => 'Detail transaksi berhasil diambil',
            'data' => [
                'transaction' => $transaction,
                'shipping_address' => $address,
            ],
        ]);
    }
}
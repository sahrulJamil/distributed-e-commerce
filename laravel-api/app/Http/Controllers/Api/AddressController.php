<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar alamat berhasil diambil',
            'data' => $addresses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'village' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $isDefault = $request->boolean('is_default');

        /*
         * Kalau alamat baru dijadikan default,
         * default lama milik user ini dimatikan.
         */
        if ($isDefault) {
            Address::where('user_id', $user->id)
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                ]);
        }

        $address = Address::create([
            'user_id' => $user->id,
            'recipient_name' => $validated['recipient_name'],
            'phone' => $validated['phone'],
            'label' => $validated['label'] ?? null,
            'address' => $validated['address'],
            'village' => $validated['village'] ?? null,
            'district' => $validated['district'] ?? null,
            'city' => $validated['city'],
            'province' => $validated['province'],
            'postal_code' => $validated['postal_code'] ?? null,
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'message' => 'Alamat berhasil ditambahkan',
            'data' => $address,
        ], 201);
    }

    public function update(Request $request, Address $address)
    {
        $user = $request->user();

        // Pastikan alamat memang milik user yang sedang login.
        if ($address->user_id !== $user->id) {
            return response()->json([
                'message' => 'Alamat tidak ditemukan atau bukan milik Anda',
            ], 404);
        }

        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'village' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            Address::where('user_id', $user->id)
                ->where('id', '!=', $address->id)
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                ]);
        }

        $address->update([
            'recipient_name' => $validated['recipient_name'],
            'phone' => $validated['phone'],
            'label' => $validated['label'] ?? null,
            'address' => $validated['address'],
            'village' => $validated['village'] ?? null,
            'district' => $validated['district'] ?? null,
            'city' => $validated['city'],
            'province' => $validated['province'],
            'postal_code' => $validated['postal_code'] ?? null,
            'is_default' => $isDefault,
        ]);

        return response()->json([
            'message' => 'Alamat berhasil diperbarui',
            'data' => $address->fresh(),
        ]);
    }

    public function destroy(Request $request, Address $address)
    {
        $user = $request->user();

        if ($address->user_id !== $user->id) {
            return response()->json([
                'message' => 'Alamat tidak ditemukan atau bukan milik Anda',
            ], 404);
        }

        $isOnlyAddress = Address::where('user_id', $user->id)->count() === 1;

        if ($address->is_default && $isOnlyAddress) {
            return response()->json([
                'message' => 'Alamat default terakhir tidak dapat dihapus. Tambahkan alamat lain terlebih dahulu.',
            ], 422);
        }

        $addressId = $address->id;
        $wasDefault = $address->is_default;

        $address->delete();

        if ($wasDefault) {
            $replacementAddress = Address::where('user_id', $user->id)
                ->latest()
                ->first();

            if ($replacementAddress) {
                $replacementAddress->update([
                    'is_default' => true,
                ]);
            }
        }

        return response()->json([
            'message' => 'Alamat berhasil dihapus',
            'data' => [
                'address_id' => $addressId,
            ],
        ]);
    }
}
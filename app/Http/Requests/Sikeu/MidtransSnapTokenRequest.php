<?php

namespace App\Http\Requests\Sikeu;

use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Foundation\Http\FormRequest;

class MidtransSnapTokenRequest extends FormRequest
{
    /**
     * Otorisasi Request Snap Token:
     * Pengguna harus login dan memiliki izin SIKEU (baca tagihan / bayar / kelola gateway)
     * ATAU merupakan mahasiswa/calon mahasiswa pemilik tagihan tersebut.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // 1. Jika user memiliki permission SIKEU
        if (
            $user->can('sikeu.paymentgateway.manage') ||
            $user->can('sikeu.pembayaran.create') ||
            $user->can('sikeu.tagihan.read')
        ) {
            return true;
        }

        // 2. Jika user adalah mahasiswa atau calon mahasiswa pemilik tagihan
        $tagihanId = $this->input('tagihan_id');
        if ($tagihanId) {
            $tagihan = TagihanMahasiswa::find($tagihanId);
            if ($tagihan) {
                if (! empty($user->mahasiswa_id) && $tagihan->mahasiswa_id == $user->mahasiswa_id) {
                    return true;
                }
                if (! empty($user->calon_mahasiswa_id) && $tagihan->calon_mahasiswa_id == $user->calon_mahasiswa_id) {
                    return true;
                }
                if ($tagihan->calonMahasiswa?->user_id === $user->id) {
                    return true;
                }
                // Relasi user ke mahasiswa jika ada
                if ($tagihan->mahasiswa && isset($tagihan->mahasiswa->user_id) && $tagihan->mahasiswa->user_id == $user->id) {
                    return true;
                }
            }
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'tagihan_id' => 'required|integer|exists:sikeu_tagihan_mahasiswa,id',
            'first_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'tagihan_id.required' => 'ID tagihan mahasiswa wajib diisi.',
            'tagihan_id.exists' => 'Tagihan mahasiswa tidak ditemukan dalam sistem.',
            'email.email' => 'Format email pelanggan tidak valid.',
        ];
    }
}

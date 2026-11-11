<?php

namespace App\Http\Requests\Spmb;

use App\Models\Spmb\PendaftaranCalonMhs;
use Illuminate\Foundation\Http\FormRequest;

class SpmbPaymentListRequest extends FormRequest
{
    /**
     * Tagihan hanya boleh dibaca oleh calon pemilik akun atau petugas SPMB.
     * Kepemilikan diperiksa dari relasi pendaftaran.user_id, bukan role statis.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->can('spmb.manage') || $user->can('spmb.pendaftaran.read')) {
            return true;
        }

        return PendaftaranCalonMhs::query()->where('user_id', $user->id)->exists();
    }

    public function rules(): array
    {
        return [
            'tahap' => 'nullable|in:pendaftaran,daftar_ulang', 'search' => 'nullable|string|max:200',
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|in:id,nomor_tagihan,created_at', 'sort_order' => 'nullable|in:asc,desc',
        ];
    }
}

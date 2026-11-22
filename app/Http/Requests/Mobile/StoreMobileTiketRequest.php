<?php

namespace App\Http\Requests\Mobile;

use App\Models\Sinapra\MaintenanceLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMobileTiketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sinapra.maintenance.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'judul' => 'required|string|max:150',
            'deskripsi_kerusakan' => 'required|string',
            'prioritas' => ['nullable', 'string', Rule::in(MaintenanceLog::ALL_PRIORITAS)],
            'aset_id' => 'nullable|exists:sinapra_aset,id',
            'ruangan_id' => 'nullable|exists:sinapra_ruangan,id',
            'foto' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul laporan kerusakan sarana wajib diisi.',
            'deskripsi_kerusakan.required' => 'Deskripsi kerusakan wajib diisi secara rinci.',
            'prioritas.in' => 'Prioritas perbaikan harus salah satu dari: rendah, sedang, tinggi, atau darurat.',
            'aset_id.exists' => 'Aset sarana yang dipilih tidak ditemukan.',
            'ruangan_id.exists' => 'Ruangan yang dipilih tidak ditemukan.',
        ];
    }
}

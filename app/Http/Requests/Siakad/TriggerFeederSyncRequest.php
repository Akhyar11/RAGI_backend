<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class TriggerFeederSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type' => 'required|in:mahasiswa,biodata_mahasiswa,riwayat_pendidikan_mahasiswa,dosen,pull_dosen,mata_kuliah,kelas,penugasan_dosen,ajar_dosen',
        ];
    }
}

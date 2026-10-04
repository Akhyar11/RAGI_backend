<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class AsetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('harga_perolehan') || $this->input('harga_perolehan') === null || $this->input('harga_perolehan') === '') {
            $this->merge(['harga_perolehan' => 0]);
        }
    }

    public function rules(): array
    {
        $asetId = $this->route('aset') ? $this->route('aset')->id : null;

        return [
            'kategori_id' => 'required|exists:sinapra_kategori_aset,id',
            'ruangan_id' => 'nullable|exists:sinapra_ruangan,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'penanggung_jawab_pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
            'kode_aset' => 'required|string|max:100|unique:sinapra_aset,kode_aset,' . $asetId,
            'nama' => 'required|string|max:150',
            'merk' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'nomor_seri' => 'nullable|string|max:100',
            'tanggal_perolehan' => 'nullable|date',
            'harga_perolehan' => 'nullable|numeric|min:0',
            'nilai_buku' => 'nullable|numeric|min:0',
            'kondisi' => 'required|in:baik,rusak_ringan,rusak_berat',
            'status' => 'required|in:tersedia,dipinjam,maintenance,dihapuskan',
            'is_borrowable' => 'nullable|boolean',
            'is_lab_asset' => 'nullable|boolean',
        ];
    }
}

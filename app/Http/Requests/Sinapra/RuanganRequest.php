<?php

namespace App\Http\Requests\Sinapra;

use Illuminate\Foundation\Http\FormRequest;

class RuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $hasAdaAc = $this->has('ada_ac');
        $hasJumlahAc = $this->has('jumlah_ac');
        $jumlahAc = $hasJumlahAc ? (int) $this->input('jumlah_ac') : ($hasAdaAc && $this->boolean('ada_ac') ? 1 : 0);
        $adaAc = $hasAdaAc ? $this->boolean('ada_ac') : ($jumlahAc > 0);
        if ($adaAc && $jumlahAc === 0) {
            $jumlahAc = 1;
        }

        $hasAdaProyektor = $this->has('ada_proyektor');
        $hasJumlahProyektor = $this->has('jumlah_proyektor');
        $jumlahProyektor = $hasJumlahProyektor ? (int) $this->input('jumlah_proyektor') : ($hasAdaProyektor && $this->boolean('ada_proyektor') ? 1 : 0);
        $adaProyektor = $hasAdaProyektor ? $this->boolean('ada_proyektor') : ($jumlahProyektor > 0);
        if ($adaProyektor && $jumlahProyektor === 0) {
            $jumlahProyektor = 1;
        }

        $hasAdaWifi = $this->has('ada_wifi');
        $hasJumlahWifi = $this->has('jumlah_wifi');
        $jumlahWifi = $hasJumlahWifi ? (int) $this->input('jumlah_wifi') : ($hasAdaWifi && $this->boolean('ada_wifi') ? 1 : 0);
        $adaWifi = $hasAdaWifi ? $this->boolean('ada_wifi') : ($jumlahWifi > 0);
        if ($adaWifi && $jumlahWifi === 0) {
            $jumlahWifi = 1;
        }

        $this->merge([
            'ada_ac' => $adaAc,
            'jumlah_ac' => $adaAc ? $jumlahAc : 0,
            'ada_proyektor' => $adaProyektor,
            'jumlah_proyektor' => $adaProyektor ? $jumlahProyektor : 0,
            'ada_wifi' => $adaWifi,
            'jumlah_wifi' => $adaWifi ? $jumlahWifi : 0,
        ]);
    }

    public function rules(): array
    {
        $ruanganId = $this->route('ruangan') ? $this->route('ruangan')->id : null;

        return [
            'gedung_id' => 'required|exists:sinapra_gedung,id',
            'tipe_ruangan_id' => 'nullable|exists:sinapra_master_tipe_ruangan,id',
            'program_studi_id' => 'nullable|exists:siakad_program_studi,id',
            'kode' => 'required|string|max:50|unique:sinapra_ruangan,kode,' . $ruanganId,
            'nama' => 'required|string|max:150',
            'lantai' => 'required|integer|min:1',
            'tipe' => 'nullable|string|max:50',
            'kapasitas' => 'required|integer|min:0',
            'ada_ac' => 'boolean',
            'jumlah_ac' => 'nullable|integer|min:0',
            'ada_proyektor' => 'boolean',
            'jumlah_proyektor' => 'nullable|integer|min:0',
            'ada_wifi' => 'boolean',
            'jumlah_wifi' => 'nullable|integer|min:0',
            'status' => 'required|string|max:50',
        ];
    }
}

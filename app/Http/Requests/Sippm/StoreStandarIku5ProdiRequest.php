<?php

namespace App\Http\Requests\Sippm;

use Illuminate\Foundation\Http\FormRequest;

class StoreStandarIku5ProdiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sippm.iku5.create');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('prodi_id') && !$this->has('unit_kerja_id')) {
            $merge['unit_kerja_id'] = (int) $this->input('prodi_id');
        }
        if ($this->has('target_scopus') && !$this->has('target_publikasi_scopus')) {
            $merge['target_publikasi_scopus'] = (int) $this->input('target_scopus');
        }
        if ($this->has('target_sinta') && !$this->has('target_publikasi_sinta')) {
            $merge['target_publikasi_sinta'] = (int) $this->input('target_sinta');
        }
        if ($this->has('target_hki') && !$this->has('target_hki_paten')) {
            $merge['target_hki_paten'] = (int) $this->input('target_hki');
        }
        if ($this->has('target_internal') && !$this->has('target_buku_isbn')) {
            $merge['target_buku_isbn'] = (int) $this->input('target_internal');
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'unit_kerja_id' => 'required|integer|exists:simpeg_unit_kerja,id',
            'tahun_akademik' => 'required|string|max:10',
            'target_publikasi_scopus' => 'required|integer|min:0',
            'target_publikasi_sinta' => 'required|integer|min:0',
            'target_hki_paten' => 'required|integer|min:0',
            'target_buku_isbn' => 'required|integer|min:0',
        ];
    }
}

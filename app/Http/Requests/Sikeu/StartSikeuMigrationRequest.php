<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSikeuMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sikeu.akuntansi.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'source'      => ['nullable', 'string', Rule::in(config('sikeu.migrator.sources', ['all', 'indonusa', 'sikeudb']))],
            'type'        => ['nullable', 'string', Rule::in(config('sikeu.migrator.types', ['all', 'jurnal', 'pembayaran']))],
            'mode'        => ['nullable', 'string', Rule::in(config('sikeu.migrator.modes', ['direct_db', 'api']))],
            'auth_scheme' => ['nullable', 'string', Rule::in(config('sikeu.migrator.auth_schemes', ['bearer', 'header_key']))],
            'limit'       => 'nullable|integer|min:0',
            'batch_size'  => 'nullable|integer|min:1|max:5000',
            'since'       => 'nullable|date',
            'incremental' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'source.in' => 'Pilihan source tidak didukung.',
            'type.in'   => 'Pilihan type tidak didukung.',
            'mode.in'   => 'Pilihan mode migrator harus berupa direct_db atau api.',
        ];
    }
}

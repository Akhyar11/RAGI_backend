<?php

namespace App\Http\Requests\Sikeu;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request untuk aksi posting saldo awal. Payload tidak diperlukan karena
 * posting selalu memakai draft yang tersimpan.
 */
class StoreSaldoAwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sikeu.akuntansi.manage');
    }

    public function rules(): array
    {
        return [];
    }
}

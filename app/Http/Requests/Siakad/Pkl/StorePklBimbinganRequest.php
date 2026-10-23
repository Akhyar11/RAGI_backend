<?php

namespace App\Http\Requests\Siakad\Pkl;

use Illuminate\Foundation\Http\FormRequest;

class StorePklBimbinganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('siakad.pkl.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'tanggal' => 'required|date',
            'aktivitas' => 'required|string|min:5',
            'target_capaian' => 'nullable|string',
            'kendala' => 'nullable|string',
            'file_lampiran' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }
}

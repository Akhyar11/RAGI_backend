<?php

namespace App\Http\Requests\Siakad;

use Illuminate\Foundation\Http\FormRequest;

class DecisionNilaiKoreksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && (
            $user->hasPermission('siakad.nilai.koreksi.approve')
            || $user->isAdmin()
            || $user->getSiakadProdiIds()->isNotEmpty()
        );
    }

    public function rules(): array
    {
        return [
            'catatan_reviewer' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}

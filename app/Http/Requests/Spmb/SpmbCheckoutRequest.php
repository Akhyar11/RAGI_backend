<?php

namespace App\Http\Requests\Spmb;

use App\Models\Sikeu\TagihanMahasiswa;
use Illuminate\Foundation\Http\FormRequest;

class SpmbCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bill = TagihanMahasiswa::findOrFail($this->route('id'));
        $user = $this->user();

        return $bill->source_system === 'SPMB' && $user && ($bill->calonMahasiswa?->user_id === $user->id
            || $user->can('sikeu.paymentgateway.manage') || $user->can('sikeu.pembayaran.create'));
    }

    public function rules(): array
    {
        return ['amount' => 'prohibited', 'nominal' => 'prohibited', 'payment_gateway_config_id' => 'prohibited'];
    }
}

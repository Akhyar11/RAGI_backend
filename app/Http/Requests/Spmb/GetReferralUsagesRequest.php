<?php

namespace App\Http\Requests\Spmb;

use App\Models\Spmb\ReferralUsage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetReferralUsagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    ReferralUsage::STATUS_CLAIMED,
                    ReferralUsage::STATUS_QUALIFIED,
                    ReferralUsage::STATUS_REWARDED,
                    ReferralUsage::STATUS_CANCELLED,
                ]),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'status', 'referral_code'])],
            'sort_order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

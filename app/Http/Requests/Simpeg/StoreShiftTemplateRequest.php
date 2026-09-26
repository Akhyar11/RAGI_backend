<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:simpeg_shift_templates,name',
            'description' => 'nullable|string',
            'late_tolerance_minutes' => 'nullable|integer|min:0|max:120',
            'early_leave_tolerance_minutes' => 'nullable|integer|min:0|max:120',
            'max_early_clock_in_minutes' => 'nullable|integer|min:0|max:240',
            'max_late_clock_in_minutes' => 'nullable|integer|min:0|max:720',
            'max_early_clock_out_minutes' => 'nullable|integer|min:0|max:720',
            'max_late_clock_out_minutes' => 'nullable|integer|min:0|max:720',
            'applies_national_holidays' => 'nullable|boolean',
            'is_active' => 'required|boolean',
            'days' => 'nullable|array|size:7',
            'days.*.day_of_week' => 'required|integer|min:0|max:6',
            'days.*.start_time' => 'nullable|string',
            'days.*.end_time' => 'nullable|string',
            'days.*.is_day_off' => 'required|boolean',
            'days.*.max_late_clock_in_minutes' => 'nullable|integer|min:0|max:720',
            'days.*.max_early_clock_in_minutes' => 'nullable|integer|min:0|max:240',
            'days.*.max_early_clock_out_minutes' => 'nullable|integer|min:0|max:720',
            'days.*.max_late_clock_out_minutes' => 'nullable|integer|min:0|max:720',
        ];
    }
}

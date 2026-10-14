<?php

namespace App\Http\Requests\Simpeg;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresensiSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'face_score_threshold' => 'required|numeric|min:0.1|max:1.0',
            'gps_accuracy_threshold_meters' => 'required|numeric|min:5|max:500',
            // Toleransi waktu & kebijakan libur kini dikelola per shift template,
            // sehingga opsional di level global (hanya fallback bila pegawai tanpa shift).
            'late_tolerance_minutes' => 'sometimes|nullable|integer|min:0|max:120',
            'max_early_clock_in_minutes' => 'sometimes|nullable|integer|min:0|max:720',
            'max_late_clock_in_minutes' => 'sometimes|nullable|integer|min:0|max:720',
            'max_early_clock_out_minutes' => 'sometimes|nullable|integer|min:0|max:720',
            'max_late_clock_out_minutes' => 'sometimes|nullable|integer|min:0|max:720',
            'early_leave_tolerance_minutes' => 'sometimes|nullable|integer|min:0|max:120',
            'applies_national_holidays' => 'sometimes|nullable|boolean',
        ];
    }
}

<?php

namespace App\Http\Requests\Simpeg;

use App\Models\Simpeg\CampusAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampusAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return $user->hasPermission('simpeg.pegawai.create')
            || $user->hasPermission('simpeg.pegawai.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'category' => ['required', Rule::in(CampusAnnouncement::CATEGORIES)],
            'target_role' => ['required', Rule::in(CampusAnnouncement::TARGET_ROLES)],
            'content' => 'required|string',
            'is_agenda' => 'nullable|boolean',
            'event_date' => 'nullable|date',
            'event_time' => 'nullable|string|max:50',
            'event_location' => 'nullable|string|max:255',
            'event_url' => 'nullable|string|max:500',
            'priority' => ['nullable', Rule::in(CampusAnnouncement::PRIORITIES)],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul pengumuman wajib diisi.',
            'category.required' => 'Kategori pengumuman wajib dipilih.',
            'category.in' => 'Kategori pengumuman tidak valid.',
            'target_role.required' => 'Target penerima pengumuman wajib dipilih.',
            'target_role.in' => 'Target penerima tidak valid.',
            'content.required' => 'Isi konten pengumuman wajib diisi.',
        ];
    }
}

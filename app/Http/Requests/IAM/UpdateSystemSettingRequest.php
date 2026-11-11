<?php

namespace App\Http\Requests\IAM;

use App\Services\IAM\RestrictedRoleService;
use App\Services\IAM\SpmbAdminRecipientService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user?->can('iam.settings.update')) {
            return true;
        }
        $settings = $this->input('settings');

        return $user && is_array($settings) && count($settings) > 0
            && collect($settings)->every(fn ($setting) => is_array($setting)
                && ($setting['key'] ?? null) === RestrictedRoleService::SETTING_KEY)
            && app(RestrictedRoleService::class)->canViewRestrictedRoles($user);
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->input('settings', []) as $index => $setting) {
                if ($setting['key'] !== SpmbAdminRecipientService::SETTING_KEY) {
                    continue;
                }
                try {
                    app(SpmbAdminRecipientService::class)->validateValue($setting['value'] ?? null);
                } catch (ValidationException $exception) {
                    $validator->errors()->add('settings.'.$index.'.value', collect($exception->errors())->flatten()->first());
                }
            }
        }];
    }
}

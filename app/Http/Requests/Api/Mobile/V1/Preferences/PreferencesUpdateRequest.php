<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Mobile\V1\Preferences;

use App\Http\Requests\Api\Mobile\V1\BaseRequest;
use Illuminate\Validation\Rule;

class PreferencesUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $currencyInputs = ['XOF'];
        $languages = array_keys((array) config('localization.supported', ['en' => 'English', 'fr' => 'Français']));
        $languageInputs = array_values(array_unique(array_merge($languages, [
            'English',
            'French',
            'Français',
            'Francais',
        ])));

        return [
            'country' => ['sometimes', 'string', 'max:80'],
            'currency' => ['sometimes', 'string', Rule::in($currencyInputs)],
            'size' => ['sometimes', 'string', 'max:20'],
            'language' => ['sometimes', 'string', Rule::in($languageInputs)],
            'notifications' => ['sometimes', 'array'],
            'notifications.push' => ['sometimes', 'boolean'],
            'notifications.email' => ['sometimes', 'boolean'],
            'notifications.sms' => ['sometimes', 'boolean'],
        ];
    }
}

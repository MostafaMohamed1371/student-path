<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Legacy\Concerns\RespondsWithLegacySuccess;
use App\Http\Controllers\Controller;
use App\Services\Notifications\UserNotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use RespondsWithLegacySuccess;

    public function __construct(
        private readonly UserNotificationPreferenceService $preferences,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->legacySuccess($this->preferences->forUser($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $merged = $this->preferences->update($request->user(), $validated);

        return $this->legacySuccess($merged, 'Notification settings updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [];
        foreach ($this->preferences->defaultPreferences() as $group => $keys) {
            if (! is_array($keys)) {
                continue;
            }
            $rules[$group] = ['sometimes', 'array'];
            foreach (array_keys($keys) as $key) {
                $rules["{$group}.{$key}"] = ['sometimes', 'boolean'];
            }
        }

        return $rules;
    }
}

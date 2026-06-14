<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Api\Legacy\Concerns\RespondsWithLegacySuccess;
use App\Http\Controllers\Controller;
use App\Models\SupportComplaint;
use App\Models\User;
use App\Services\Support\SupportContactService;
use App\Support\SupportComplaintReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Legacy contract: GET /api/support/info, categories; POST /api/support/complaint.
 */
class LegacySupportController extends Controller
{
    use RespondsWithLegacySuccess;

    public function info(Request $request, SupportContactService $supportContact): JsonResponse
    {
        $cfg = config('mobile_legacy_api.support', []);
        $school = $supportContact->schoolForUser($this->optionalAuthenticatedUser($request));

        return $this->legacySuccess([
            'contactMethods' => $supportContact->contactMethodsFor($school),
            'faqs' => $cfg['faqs'] ?? [],
        ]);
    }

    public function categories(): JsonResponse
    {
        $items = config('mobile_legacy_api.support.categories', []);

        return $this->legacySuccess($items);
    }

    public function complaint(Request $request): JsonResponse
    {
        $allowedIds = collect(config('mobile_legacy_api.support.categories', []))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $validated = $request->validate([
            'category_id' => ['required', 'string', 'max:64', Rule::in($allowedIds)],
            'details' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $paths = [];
        if ($request->hasFile('attachment')) {
            $paths[] = $request->file('attachment')->store('support-complaints', 'local');
        }

        $complaint = SupportComplaint::query()->create([
            'user_id' => $request->user()->id,
            'category_id' => $validated['category_id'],
            'details' => $validated['details'],
            'attachments' => $paths !== [] ? $paths : null,
            'complaint_number' => null,
            'status' => 'RECEIVED',
        ]);

        $complaintNumber = SupportComplaintReference::format((int) $complaint->id, $complaint->created_at);
        $complaint->forceFill(['complaint_number' => $complaintNumber])->save();

        $ar = app()->getLocale() === 'ar';
        $msg = $ar
            ? 'تم إرسال طلبك بنجاح، رقم المراجعة هو '.$complaint->complaint_number
            : 'Your request was submitted successfully. Reference: '.$complaint->complaint_number;

        return $this->legacySuccess([
            'complaintNumber' => $complaint->complaint_number,
            'status' => $complaint->status,
            'submittedAt' => $complaint->created_at?->toIso8601String(),
            'attachmentCount' => count($paths),
        ], $msg, 201);
    }

    private function optionalAuthenticatedUser(Request $request): ?User
    {
        $user = $request->user('sanctum') ?? $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $token = trim((string) $request->bearerToken());
        if ($token === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $tokenUser = $accessToken?->tokenable;

        return $tokenUser instanceof User ? $tokenUser : null;
    }
}

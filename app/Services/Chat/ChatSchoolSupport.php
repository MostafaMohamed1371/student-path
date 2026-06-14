<?php

namespace App\Services\Chat;

use App\Enums\PhoneAccountType;
use App\Models\ChatConversation;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;

class ChatSchoolSupport
{
    public function schoolIdForAppUser(User $user): ?int
    {
        return $user->scopingSchoolId();
    }

    public function isSchoolStaffUser(User $user): bool
    {
        return $user->school_id !== null
            && ! $user->is_admin
            && $user->phone_account_type === PhoneAccountType::School->value;
    }

    public function isChatStaff(User $user): bool
    {
        return $user->is_admin || $this->isSchoolStaffUser($user);
    }

    public function canStaffAccessConversation(User $staff, ChatConversation $conversation): bool
    {
        if ($staff->is_admin) {
            return true;
        }

        if (! $this->isSchoolStaffUser($staff)) {
            return false;
        }

        $schoolId = $staff->scopingSchoolId();
        if ($schoolId === null || $conversation->school_id === null) {
            return false;
        }

        return (int) $conversation->school_id === $schoolId;
    }

    public function defaultStaffUserForSchool(?int $schoolId): ?User
    {
        if ($schoolId === null) {
            return null;
        }

        return User::query()
            ->where('school_id', $schoolId)
            ->where('is_admin', false)
            ->where('phone_account_type', PhoneAccountType::School->value)
            ->orderBy('id')
            ->first();
    }

    public function resolveParticipantId(User $appUser, ?int $requestedParticipantId): ?int
    {
        $schoolId = $this->schoolIdForAppUser($appUser);

        if ($requestedParticipantId !== null) {
            $staff = User::query()->find($requestedParticipantId);
            if ($staff === null || ! $this->isSchoolStaffUser($staff)) {
                return null;
            }
            if ($schoolId !== null && (int) $staff->school_id !== $schoolId) {
                return null;
            }

            return (int) $staff->id;
        }

        return $this->defaultStaffUserForSchool($schoolId)?->id;
    }

    public function displayNameForSchool(?int $schoolId): string
    {
        if ($schoolId === null) {
            return (string) config('chat.support_display_name', 'Support');
        }

        $school = School::query()->find($schoolId);
        if ($school === null) {
            return (string) config('chat.support_display_name', 'Support');
        }

        $name = trim((string) ($school->name_en ?? $school->name ?? ''));

        return $name !== '' ? $name : (string) config('chat.support_display_name', 'Support');
    }

    /**
     * @return Collection<int, User>
     */
    public function staffRecipientsFor(ChatConversation $conversation, User $sender): Collection
    {
        $schoolId = $conversation->school_id !== null ? (int) $conversation->school_id : null;

        if ($schoolId === null) {
            return collect();
        }

        if ($conversation->participant_id) {
            $participant = User::query()
                ->whereKey($conversation->participant_id)
                ->where('school_id', $schoolId)
                ->where('is_admin', false)
                ->first();

            if ($participant !== null && (int) $participant->id !== (int) $sender->id) {
                return collect([$participant]);
            }
        }

        return User::query()
            ->where('school_id', $schoolId)
            ->where('is_admin', false)
            ->where('phone_account_type', PhoneAccountType::School->value)
            ->where('id', '!=', $sender->id)
            ->get(['id', 'name', 'is_admin', 'school_id']);
    }
}

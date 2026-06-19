<?php

namespace App\Services\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class SupportComplaintAttachmentStore
{
    /**
     * @return list<string> Stored paths on the local disk.
     */
    public function storeFromRequest(Request $request): array
    {
        $paths = [];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            if ($file instanceof UploadedFile) {
                $paths[] = $file->store('support-complaints', 'local');
            }
        }

        foreach ($request->file('attachments', []) as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $file->store('support-complaints', 'local');
            }
        }

        return array_values($paths);
    }

    public function maxAttachments(): int
    {
        return max(1, (int) config('mobile_legacy_api.support.complaint_max_attachments', 5));
    }

    public function maxAttachmentSizeKb(): int
    {
        return max(1, (int) config('mobile_legacy_api.support.complaint_attachment_max_kb', 5120));
    }

    /**
     * @return array<string, list<string>>
     */
    public function validationRules(): array
    {
        $maxKb = $this->maxAttachmentSizeKb();
        $maxCount = $this->maxAttachments();

        return [
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:'.$maxKb],
            'attachments' => ['nullable', 'array', 'max:'.$maxCount],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png', 'max:'.$maxKb],
        ];
    }
}

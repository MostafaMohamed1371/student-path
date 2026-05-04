<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\AppliesApiSchoolScoping;
use App\Http\Controllers\Api\V1\Concerns\FormatsParentApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SchoolResource;
use App\Models\Grade;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    use AppliesApiSchoolScoping;
    use FormatsParentApiResponse;

    public function schools(Request $request): JsonResponse
    {
        $request->validate([
            'format' => ['nullable', 'in:full,minimal'],
        ]);

        $q = School::query();
        $this->applyApiScopeToSchoolsQuery($q, $request->user());
        $schools = $q->orderBy('name_en')->orderBy('name_ar')->get();

        if ($request->query('format') === 'minimal') {
            return $this->parentSuccess(
                $schools->map(fn (School $s) => [
                    'id' => $s->id,
                    'name' => (string) ($s->name_en ?: $s->name_ar),
                ])->values()->all()
            );
        }

        return $this->parentSuccess(SchoolResource::collection($schools)->toArray($request));
    }

    public function grades(): JsonResponse
    {
        $grades = Grade::query()->orderBy('sort_order')->orderBy('name')->get();

        return $this->parentSuccess(
            $grades->map(fn (Grade $g) => [
                'id' => $g->id,
                'name' => $g->name,
            ])->values()->all()
        );
    }
}

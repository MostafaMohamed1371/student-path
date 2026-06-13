@extends('dashboard.layout')

@section('title', __('dashboard.menu_guardians'))

@section('content')
    @php($title = __('dashboard.menu_guardians'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(auth()->user()?->canMutateSchoolRoster())
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.guardians.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_guardian') }}</a>
        </div>
        @endif

        @include('dashboard.partials.school_driver_filter')

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.guardian_name') }}</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.phone') }}</th>
                        <th>{{ __('dashboard.id_card_number') }}</th>
                        <th>{{ __('dashboard.children_count') }}</th>
                        <th>{{ __('dashboard.status') }}</th>
                        @if(auth()->user()?->canMutateSchoolRoster())
                            <th>{{ __('dashboard.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($guardians as $group)
                        @php($guardian = $group->primary)
                        <tr>
                            <td>{{ $guardian->id }}</td>
                            <td>
                                {{ $guardian->full_name }}
                                @if($group->isMultiSchool())
                                    <span class="badge ok" style="margin-inline-start:6px;">{{ __('dashboard.guardian_multi_school_badge', ['count' => count($group->schoolLabels)]) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($group->schoolLabels !== [])
                                    {{ implode(', ', $group->schoolLabels) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $guardian->phone }}</td>
                            <td>{{ $guardian->id_card_number ?: '—' }}</td>
                            <td>{{ $group->studentsCount }}</td>
                            <td>
                                <span class="badge {{ $guardian->status === 'active' ? 'ok' : 'off' }}">
                                    {{ $guardian->status === 'active' ? __('dashboard.active') : __('dashboard.inactive') }}
                                </span>
                            </td>
                            @if(auth()->user()?->canMutateSchoolRoster())
                            <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                @if($group->isMultiSchool())
                                    @foreach($group->records as $record)
                                        <a href="{{ route('dashboard.guardians.edit', $record) }}" class="btn-muted" style="text-decoration:none;">
                                            {{ __('dashboard.edit') }}@if($record->school?->name_en) ({{ $record->school->name_en }})@endif
                                        </a>
                                    @endforeach
                                @else
                                    <a href="{{ route('dashboard.guardians.edit', $guardian) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                @endif
                                @if($group->records->count() === 1)
                                    <form method="post" action="{{ route('dashboard.guardians.destroy', $guardian) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                    </form>
                                @endif
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->canMutateSchoolRoster() ? 8 : 7 }}">{{ __('dashboard.no_guardians') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($guardians->total() > 0)
                <div style="margin-top:16px;">{{ $guardians->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection

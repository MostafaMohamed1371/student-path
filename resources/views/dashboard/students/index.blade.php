@extends('dashboard.layout')

@section('title', __('dashboard.menu_students'))

@section('content')
    @php($title = __('dashboard.menu_students'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(auth()->user()?->canMutateSchoolRoster())
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.students.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_student') }}</a>
        </div>
        @endif

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.student_name') }}</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.grade') }}</th>
                        <th>{{ __('dashboard.student_phone') }}</th>
                        <th>{{ __('dashboard.guardian_name') }}</th>
                        <th>{{ __('dashboard.phone') }}</th>
                        <th>{{ __('dashboard.status') }}</th>
                        @if(auth()->user()?->canMutateSchoolRoster())
                            <th>{{ __('dashboard.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($students as $student)
                        <tr>
                            <td>{{ $student->id }}</td>
                            <td>{{ $student->full_name }}</td>
                            <td>{{ $student->school?->name_en ?: '—' }}</td>
                            <td>{{ $student->grade }}</td>
                            <td>{{ $student->student_phone }}</td>
                            <td>{{ $student->guardian?->full_name ?: '—' }}</td>
                            <td>{{ $student->guardian?->phone ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $student->status === 'active' ? 'ok' : 'off' }}">
                                    {{ $student->status === 'active' ? __('dashboard.active') : __('dashboard.inactive') }}
                                </span>
                            </td>
                            @if(auth()->user()?->canMutateSchoolRoster())
                            <td style="display:flex;gap:8px;">
                                <a href="{{ route('dashboard.students.edit', $student) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                <form method="post" action="{{ route('dashboard.students.destroy', $student) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->canMutateSchoolRoster() ? 9 : 8 }}">{{ __('dashboard.no_students') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">{{ $students->links() }}</div>
        </section>
    @endcomponent
@endsection

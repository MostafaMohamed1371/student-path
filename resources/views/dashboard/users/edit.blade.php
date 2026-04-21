@extends('dashboard.layout')

@section('title', __('dashboard.edit_user'))

@section('content')
    @php($title = __('dashboard.edit_user'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if ($errors->any())
            <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
        @endif

        <section class="card">
            <form method="post" action="{{ route('dashboard.users.update', $user) }}" enctype="multipart/form-data">
                @include('dashboard.users._form', [
                    'user' => $user,
                    'passwordRequired' => false,
                    'submitLabel' => __('dashboard.update'),
                    'method' => 'put',
                ])
            </form>
        </section>
    @endcomponent
@endsection

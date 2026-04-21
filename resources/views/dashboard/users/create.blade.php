@extends('dashboard.layout')

@section('title', __('dashboard.add_user'))

@section('content')
    @php($title = __('dashboard.add_user'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if ($errors->any())
            <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
        @endif

        <section class="card">
            <form method="post" action="{{ route('dashboard.users.store') }}" enctype="multipart/form-data">
                @include('dashboard.users._form', [
                    'passwordRequired' => true,
                    'submitLabel' => __('dashboard.create'),
                    'method' => 'post',
                ])
            </form>
        </section>
    @endcomponent
@endsection

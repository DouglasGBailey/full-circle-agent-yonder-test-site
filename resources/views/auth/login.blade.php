@extends('layouts.app')

@section('title', 'Sign in - Saltmarsh Hotel test site')
@section('description', 'Sign in with a Full Circle Agent pilot test account to book test rooms and restaurant tables in the Saltmarsh Hotel chat. Nothing is charged.')
@section('path', '/login')

@section('content')
<section class="section">
    <div class="container container--narrow">
        <h1 class="page-title">Sign in</h1>
        <p class="lead">Use the test account you were given. There is no sign-up: accounts are handed out for the pilot.</p>

        @if ($errors->any())
            <div class="alert" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="form" novalidate>
            @csrf
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="username" required maxlength="120" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field__error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="200" @error('password') aria-invalid="true" @enderror>
            </div>
            <div class="hp" aria-hidden="true">
                <label for="website">Leave this empty</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <button type="submit" class="btn btn--primary btn--block">Sign in</button>
        </form>
    </div>
</section>
@endsection

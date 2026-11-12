@extends('layouts.app')

@section('title', 'Login Panitia — Live Draw Kamar')

@section('body')
  <main class="login">
    <div class="panel">
      <h1 style="font-size: 22px; margin: 0 0 4px">Login Panitia</h1>
      <p class="sub" style="margin: 0">Hanya panitia yang bisa menjalankan undian.</p>

      <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="username">Username</label>
        <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" autocapitalize="none" />
        @error('username')<div class="err">{{ $message }}</div>@enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password" />

        <label class="chk"><input type="checkbox" name="remember" value="1" /> Ingat saya</label>

        <div class="row"><button class="btn pri" type="submit">Masuk</button></div>
      </form>
    </div>
    <p class="foot"><a href="{{ route('draw.viewer') }}">← Kembali ke tampilan penonton</a></p>
  </main>
@endsection

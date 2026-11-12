@extends('layouts.app')

@section('body')
  @include('draw._header')
  <main>
    @include('draw._app')
  </main>
  <footer class="foot"><a href="{{ route('login') }}">Login panitia</a></footer>
@endsection

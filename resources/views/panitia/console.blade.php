@extends('layouts.app')

@section('title', 'Konsol Panitia — Live Draw Kamar')

@section('body')
  @include('panitia._topbar')
  @include('draw._header')
  <main>
    @include('draw._app')
  </main>
@endsection

@extends('errors.layout')
@section('title', 'Sessione scaduta')
@section('code', '419')
@section('heading', 'Sessione scaduta')
@section('message', 'La pagina è rimasta aperta troppo a lungo. Ricaricala e riprova.')
@section('action')<a class="btn" href="{{ url()->previous() }}">Ricarica</a>@endsection

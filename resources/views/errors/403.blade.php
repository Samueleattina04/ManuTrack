@extends('errors.layout')
@section('title', 'Accesso negato')
@section('code', '403')
@section('heading', 'Accesso negato')
@section('message', 'Il tuo ruolo non permette di aprire questa pagina. Se ti serve, chiedi all\'amministratore.')
@section('action')<a class="btn" href="{{ url('/') }}">Torna alla panoramica</a>@endsection

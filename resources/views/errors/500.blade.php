@extends('errors.layout')
@section('title', 'Errore')
@section('code', '500')
@section('heading', 'Qualcosa non ha funzionato')
@section('message', 'L\'errore è stato registrato. Riprova tra poco; se si ripete, avvisa l\'amministratore.')
@section('action')<a class="btn" href="{{ url('/') }}">Torna alla panoramica</a>@endsection

@extends('errors.layout')
@section('title', 'Pagina non trovata')
@section('code', '404')
@section('heading', 'Pagina non trovata')
@section('message', 'L\'indirizzo non esiste o non è più disponibile.')
@section('action')<a class="btn" href="{{ url('/') }}">Torna alla panoramica</a>@endsection

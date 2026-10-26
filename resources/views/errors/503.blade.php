@extends('errors.layout')
@section('title', 'Aggiornamento in corso')
@section('code', '503 · manutenzione')
@section('heading', 'Aggiornamento in corso')
@section('message')<span class="spin" aria-hidden="true"></span>ManuTrack si sta aggiornando. Di solito bastano pochi secondi: la pagina si ricarica da sola.@endsection
@section('action')<script>setTimeout(function () { location.reload(); }, 15000);</script>@endsection

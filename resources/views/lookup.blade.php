@extends('layouts.app')

@section('content')
    <h1>Lookup</h1>
    @livewire('lookup-tool')
@endsection

@section('commands')
    @livewire('command-box')
@endsection


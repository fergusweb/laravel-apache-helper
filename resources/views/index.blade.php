@extends('layouts.app')

@php
    $LookupStatusPages = app(\App\Services\LookupStatusPages::class);
    $data = $LookupStatusPages->scrape();
    $data = $LookupStatusPages->parse($data);
    $data = $LookupStatusPages->lookupWithAPI($data);
@endphp

@section('content')
    <h1>Overview</h1>

    <h2>Scraping servers:</h2>
    <ul>
        @php
            foreach ($LookupStatusPages->status_pages as $url) {
                echo "<li><a href=\"$url\" target=\"_blank\">$url</a></li>";
            }
        @endphp
    </ul>

    <h2>Current Apache Traffic</h2>
    @livewire(TableResultsIps::class, ['results' => $data])

    @php
        //echo '<pre>', print_r($data, true), '</pre>';
    @endphp
@endsection

@section('commands')
    @livewire('command-box')
@endsection


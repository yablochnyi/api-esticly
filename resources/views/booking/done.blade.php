@extends('booking.layout')

@php
    $title = __('booking.done_title');
@endphp

@section('body')
    <div class="wrap">
        <div style="height:6px"></div>
        <h1 class="title" style="margin:0 0 14px">{{ __('booking.done_title') }}</h1>
        <div class="card">
            <div style="font-size:18px;font-weight:900">{{ __('booking.done_created') }} ✅</div>
            <div style="height:8px"></div>
            <div class="muted">{{ __('booking.done_message') }}</div>
        </div>

        <div style="height:14px"></div>
        <a class="btn btn-primary" href="{{ route('booking.landing', ['slug' => $org->booking_slug, 'lang' => ($lang ?? app()->getLocale())]) }}">{{ __('booking.back_to_landing') }}</a>
    </div>
@endsection


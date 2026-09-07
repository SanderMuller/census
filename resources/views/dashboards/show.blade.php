@extends('census::layout', ['title' => $name])

@php
    $routeName = config('census.route.name');
@endphp

@section('content')
    <div class="ms-bar">
        <h1>
            {{ $name }}
        </h1>

        <div class="ms-actions">
            @if($editable)
                <a href="{{ route($routeName . 'dashboards.edit', ['dashboard' => $slug]) }}">
                    Edit
                </a>
            @endif

            <a href="{{ route($routeName . 'dashboards.index') }}">
                All dashboards
            </a>
        </div>
    </div>

    @if($description !== null)
        <p class="ms-muted">
            {{ $description }}
        </p>
    @endif

    @unless($complete)
        <p class="ms-muted">
            This page ran out of its query budget, so the cards below it were skipped. Nothing was
            cached, so reloading tries again.
        </p>
    @endunless

    @if($groups->isEmpty())
        <p class="ms-muted">
            No stats yet.
        </p>
    @endif

    @foreach($groups as $group => $stats)
        <h2>
            {{ $group }}
        </h2>

        <div class="ms-grid">
            @foreach($stats as $stat)
                @include('census::partials.stat-card', ['stat' => $stat])
            @endforeach
        </div>
    @endforeach
@endsection

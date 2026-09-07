@extends('model-stats::layout', ['title' => 'Model stats — ' . class_basename($blueprint->class)])

@php
    $routeName = config('model-stats.route.name');
@endphp

@section('content')
    <div class="ms-bar">
        <h1>
            {{ $audience->readsSchema ? $blueprint->class : $blueprint->displayName() }}
        </h1>

        <div class="ms-actions">
            @if($novaUrl !== null)
                <a href="{{ $novaUrl }}">
                    Open in Nova
                </a>
            @endif

            <a href="{{ route($routeName . 'show', ['model' => $blueprint->slug, 'fresh' => 1]) }}">
                Recalculate
            </a>

            <a href="{{ route($routeName . 'index') }}">
                All models
            </a>
        </div>
    </div>

    @if($audience->readsSchema)
        <p class="ms-muted">
            Table <code>{{ $blueprint->table }}</code> —
            {{ count($blueprint->columns) }} columns, {{ count($blueprint->relations) }} relations.
            Nothing below is configured for this model; every stat is derived from a cast, a column
            type or a relation type. Measured {{ $calculatedAt->diffForHumans() }}.
        </p>
    @else
        <p class="ms-muted">
            {{ $blueprint->description ?? 'Measured from live data.' }}
            Measured {{ $calculatedAt->diffForHumans() }}.
        </p>
    @endif

    @foreach($groups as $group => $stats)
        <h2>
            {{ $group }}
        </h2>

        <div class="ms-grid">
            @foreach($stats as $stat)
                <div class="ms-card">
                    <p class="ms-card-label">
                        {{ $stat->label }}
                    </p>

                    @if($stat->value !== null)
                        <p class="ms-card-value">
                            {{ number_format($stat->value) }}
                        </p>
                    @endif

                    @if($stat->breakdown !== [])
                        <table>
                            @foreach($stat->breakdown as $row)
                                <tr>
                                    <td>
                                        {{ $row['label'] }}
                                    </td>

                                    <td class="ms-muted ms-numeric">
                                        {{ number_format($row['count']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    @if($stat->isUnavailable() && $stat->note === null)
                        <p class="ms-muted">
                            No rows.
                        </p>
                    @endif

                    @if($stat->note !== null)
                        <p class="ms-muted">
                            {{ $stat->note }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    @if($audience->readsSchema)
    <h2>
        Relations found by reflection
    </h2>

    <table>
        <thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Related</th>
            <th>Foreign key</th>
        </tr>
        </thead>

        <tbody>
        @foreach($blueprint->relations as $relation)
            <tr>
                <td>
                    {{ $relation->name }}
                </td>

                <td class="ms-muted">
                    {{ $relation->type }}
                </td>

                <td class="ms-muted">
                    {{ $relation->relatedClass ?? '(polymorphic)' }}
                </td>

                <td class="ms-muted">
                    <code>{{ $relation->foreignKeyName ?? $relation->morphTypeColumn ?? $relation->pivotTable ?? '—' }}</code>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
@endsection

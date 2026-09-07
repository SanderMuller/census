@extends('census::layout', ['title' => 'Model stats'])

@section('content')
    <div class="ms-bar">
        <h1>
            Model stats
        </h1>
    </div>

    <p class="ms-muted">
        Every discovered model backed by a table, largest first. Row counts here are the optimiser's
        estimate from <code>information_schema</code> — no <code>COUNT(*)</code> runs on this page.
        Open a model to compute its stats.
    </p>

    <div x-data="{ filter: '' }">
        <label class="ms-bar">
            <input aria-label="Filter models"
                   class="ms-search"
                   placeholder="Filter {{ count($models) }} models by class or table name…"
                   type="search"
                   x-model="filter">
        </label>

        <table>
            <thead>
            <tr>
                <th>Model</th>

                @if($audience->readsSchema)
                    <th>Table</th>

                    <th class="ms-numeric">Rows (approx.)</th>
                @endif
            </tr>
            </thead>

            <tbody>
            @foreach($models as $model)
                {{-- The haystack rides on a data attribute rather than a JS string literal: a class
                     name is full of backslashes, and `\v` in `\Video` is a vertical tab to the
                     parser Alpine compiles the expression with. --}}
                <tr data-filter="{{ Str::lower($model['name'] . ' ' . $model['table']) }}"
                    x-show="filter === '' || $el.dataset.filter.includes(filter.toLowerCase())">
                    <td>
                        <a href="{{ route(config('census.route.name') . 'show', ['model' => $model['slug']]) }}">
                            {{ $model['name'] }}
                        </a>
                    </td>

                    @if($audience->readsSchema)
                        <td class="ms-muted">
                            <code>{{ $model['table'] }}</code>
                        </td>

                        <td class="ms-muted ms-numeric">
                            {{ $model['approximate_rows'] === null ? '—' : number_format($model['approximate_rows']) }}
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection

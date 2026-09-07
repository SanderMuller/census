@extends('census::layout', ['title' => $dashboard === null ? 'New dashboard' : 'Edit ' . $dashboard->name])

@php
    $routeName = config('census.route.name');
    $isNew = $dashboard === null;
@endphp

@section('content')
    <div class="ms-bar">
        <h1>
            {{ $isNew ? 'New dashboard' : 'Edit ' . $dashboard->name }}
        </h1>

        <a href="{{ route($routeName . 'dashboards.index') }}">
            All dashboards
        </a>
    </div>

    <form method="POST"
          action="{{ $isNew
              ? route($routeName . 'dashboards.store')
              : route($routeName . 'dashboards.update', ['dashboard' => $dashboard->slug]) }}">
        @csrf

        @unless($isNew)
            @method('PUT')
        @endunless

        <label>
            Name

            <input class="ms-search"
                   name="name"
                   required
                   type="text"
                   value="{{ old('name', $dashboard->name ?? '') }}">
        </label>

        <label>
            Description

            <input class="ms-search"
                   name="description"
                   type="text"
                   value="{{ old('description', $dashboard->description ?? '') }}">
        </label>

        <fieldset>
            <legend>
                Audiences
            </legend>

            @foreach($audienceChoices as $choice)
                <label>
                    <input @checked(in_array($choice, old('audiences', $dashboard->audiences ?? []), true))
                           name="audiences[]"
                           type="checkbox"
                           value="{{ $choice }}">

                    {{ $choice }}
                </label>
            @endforeach
        </fieldset>

        {{-- Stats are posted as a flat list of references. The picker that builds them is host UI;
             the contract is model + kind + target, the same three fields the database stores. --}}
        <fieldset>
            <legend>
                Stats
            </legend>

            @foreach(old('stats', array_map(fn ($r) => $r->toArray(), $dashboard?->references() ?? [])) as $index => $stat)
                <div>
                    <input name="stats[{{ $index }}][model]" type="hidden" value="{{ $stat['model'] }}">
                    <input name="stats[{{ $index }}][kind]" type="hidden" value="{{ $stat['kind'] }}">
                    <input name="stats[{{ $index }}][target]" type="hidden" value="{{ $stat['target'] }}">

                    <span class="ms-muted">
                        {{ class_basename($stat['model']) }} — {{ $stat['kind'] }}{{ $stat['target'] ? ': ' . $stat['target'] : '' }}
                    </span>
                </div>
            @endforeach
        </fieldset>

        <button type="submit">
            {{ $isNew ? 'Create' : 'Save' }}
        </button>
    </form>

    @unless($isNew)
        <form method="POST"
              action="{{ route($routeName . 'dashboards.destroy', ['dashboard' => $dashboard->slug]) }}">
            @csrf
            @method('DELETE')

            <button type="submit">
                Delete this dashboard
            </button>
        </form>
    @endunless
@endsection

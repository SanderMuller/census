@extends('census::layout', ['title' => 'Dashboards'])

@php
    $routeName = config('census.route.name');
@endphp

@section('content')
    <div class="ms-bar">
        <h1>
            Dashboards
        </h1>

        <div class="ms-actions">
            @if($canCreate)
                <a href="{{ route($routeName . 'dashboards.create') }}">
                    New dashboard
                </a>
            @endif

            <a href="{{ route($routeName . 'index') }}">
                All models
            </a>
        </div>
    </div>

    @if($fixed === [] && $userDashboards === [])
        <p class="ms-muted">
            No dashboards yet.
        </p>
    @endif

    @if($fixed !== [])
        <h2>
            Built in
        </h2>

        <table>
            <tbody>
            @foreach($fixed as $dashboard)
                <tr>
                    <td>
                        <a href="{{ route($routeName . 'dashboards.show', ['dashboard' => $dashboard['slug']]) }}">
                            {{ $dashboard['name'] }}
                        </a>
                    </td>

                    <td class="ms-muted">
                        {{ $dashboard['description'] }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($userDashboards !== [])
        <h2>
            Created here
        </h2>

        <table>
            <tbody>
            @foreach($userDashboards as $dashboard)
                <tr>
                    <td>
                        <a href="{{ route($routeName . 'dashboards.show', ['dashboard' => $dashboard->slug]) }}">
                            {{ $dashboard->name }}
                        </a>
                    </td>

                    <td class="ms-numeric">
                        <a href="{{ route($routeName . 'dashboards.edit', ['dashboard' => $dashboard->slug]) }}">
                            Edit
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection

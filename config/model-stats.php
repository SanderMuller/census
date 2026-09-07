<?php declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Where the models live
    |--------------------------------------------------------------------------
    |
    | Namespace prefix => directory holding those classes. `app/Models` is the
    | Laravel default; add a root for every place this application keeps models.
    |
    */

    'source_roots' => [
        'App\\Models\\' => app_path('Models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | The dashboard bypasses no authorization of its own — `middleware` is the
    | only gate. Give it whatever the host application uses to recognise the
    | people allowed to read schema-level data.
    |
    */

    'route' => [
        'enabled' => true,
        'prefix' => 'model-stats',
        'name' => 'model-stats.',
        'domain' => null,
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Query budget
    |--------------------------------------------------------------------------
    |
    | A `whereDoesntHave` against a table with tens of millions of rows is a
    | table scan. Every read is capped, and a read that overruns renders as a
    | skipped note rather than taking the page down.
    |
    */


    /*
    |--------------------------------------------------------------------------
    | Audiences
    |--------------------------------------------------------------------------
    |
    | Checked in order; the first whose `ability` the viewer passes wins. An
    | `ability` of null means the route middleware is already the gate.
    |
    | `reads_schema` marks the developer audience: it sees every model, column
    | and relation. Every other audience sees only what a `#[ModelStats]` or
    | `#[PublishedColumn]` attribute published to it, so a newly added model or
    | column is invisible to them until someone opts it in.
    |
    */

    'audiences' => [
        'developer' => [
            'label' => 'Developer',
            'reads_schema' => true,
            'ability' => null,
        ],
    ],

    'timeout_ms' => 3000,
    'breakdown_limit' => 25,
    'cache_minutes' => 15,

    /*
    |--------------------------------------------------------------------------
    | Nova
    |--------------------------------------------------------------------------
    |
    | Where the host application keeps its Nova resources. Leave empty, or omit
    | Nova entirely, and the dashboard simply shows no link.
    |
    */

    'nova' => [
        'namespaces' => ['App\\Nova\\'],
    ],

];

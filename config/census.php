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
        'prefix' => 'census',
        'name' => 'census.',
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
    | and relation. Every other audience sees only what a `#[StatsFor]` or
    | `#[StatsForColumn]` attribute published to it, so a newly added model or
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

    /*
    |--------------------------------------------------------------------------
    | Model, column and relation selection
    |--------------------------------------------------------------------------
    |
    | The rule, applied identically at all three levels:
    |
    |     whitelist = config_whitelist union attribute_whitelist
    |     usable    = (whitelist or everything) minus both blacklists
    |
    | The two whitelists merge into one list, so one entry anywhere makes that
    | level opt-in. An empty merge means everything. Narrowing runs last and is
    | absolute: nothing re-includes a blacklisted name, for any audience.
    |
    | Column and relation entries are `[modelPattern, targetPattern]` pairs
    | rather than delimited strings, because a fully qualified class name
    | contains backslashes and no separator character is safe. `*` wildcards
    | work on either side.
    |
    */

    'models' => [
        'whitelist' => [],
        'blacklist' => [],
    ],

    /*
     * Conservative defaults: a distribution over a credential column is the one leak worth blocking
     * out of the box. They stay narrow on purpose. A wider glob such as `*token*` would also swallow
     * `aggregate_used_tokens`, which is a legitimate metric — so widen these per application rather
     * than reaching for the broadest pattern that fits.
     */
    'columns' => [
        'whitelist' => [],
        'blacklist' => [
            ['*', 'password'],
            ['*', '*_password'],
            ['*', 'remember_token'],
            ['*', '*_token'],
            ['*', '*_secret'],
        ],
    ],

    'relations' => [
        'whitelist' => [],
        'blacklist' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboards
    |--------------------------------------------------------------------------
    |
    | Where the code-defined dashboards live, discovered the way models are.
    |
    | `page_budget_ms` caps a whole dashboard render rather than one stat. A page
    | holding several models would otherwise multiply the per-stat cap below.
    | When the budget is spent the remaining cards render as skipped, and a
    | partial result is never cached.
    |
    */

    'dashboard_roots' => [
        'App\\Census\\Dashboards\\' => app_path('Census/Dashboards'),
    ],

    'page_budget_ms' => 15000,

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

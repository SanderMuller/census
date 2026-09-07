# Laravel Model Stats

Turns an application's Eloquent schema into a dashboard, without configuring a single metric. Point it
at a Laravel application and it discovers the models, reads their casts and relations, and derives the
stats each one earns.

## Why this and not Metabase

Metabase already auto-generates dashboards from a database schema, foreign keys included, and serves
non-technical users well. Three things it cannot do, because they do not exist in the database:

- **It reads Eloquent, not just SQL.** A cast turns a stored `3` into `Status::Published`. A typed
  relation says `hasMany` where the schema shows only an index. An accessor names an enum that a
  custom cast hides. Metabase sees `3`.
- **It runs inside the application's own authentication and authorization.** The same session, the same
  policies, the same tenant scope. A support agent sees exactly the rows the application would already
  let them see — no second permission system to keep in sync, no second copy of the data.
- **It ships with the code.** A model gains a column, the dashboard gains a stat, in the same pull
  request. No separate deployment, no drift.

**Where the advantage is thin, say so.** For an executive asking "how many records were published this
month", Metabase draws that trend from `status` and `created_at` with no code at all. This package earns
its place for that audience only through the vocabulary and access control it inherits from the
application — not through the chart. Scope the executive-facing surface accordingly, and do not try to
out-build a mature BI tool at its own game.

## Installation

```bash
composer require sandermuller/census
```

Publish the config to change where models are discovered or who may see what:

```bash
php artisan vendor:publish --tag=census-config
```

## Design notes

Three of these cost a real bug; they are not preferences.

- **Aggregates read through the base query builder.** Hydrating a model from a partial `SELECT` hands
  its accessors a row without the columns they read. Where an accessor falls back to a relation,
  `preventLazyLoading` turns that into an exception.
- **Global scopes stay on**, so a count matches what the application itself reads. Only the soft-delete
  scope is lifted, because "how many rows are trashed" is one of the stats.
- **Boolean columns roll up into one aggregate.** A model with 55 of them would otherwise cost 55 scans
  of the same table for what a single row of `SUM(CASE …)` already carries.
- **Every read carries a statement timeout** and degrades to a visible "skipped" note. A
  `whereDoesntHave` against a table with tens of millions of rows is a table scan; it must not take the
  page down with it.

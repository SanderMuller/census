{{--
    A host application that wants the dashboard inside its own chrome publishes the views
    (`vendor:publish --tag=census-views`) and replaces this file. Everything else keeps working,
    because the pages only ever yield into `content`.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1" name="viewport">

    <title>{{ $title ?? 'Model stats' }}</title>

    <script defer
            src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root { --ms-ink: #10233f; --ms-muted: #5a6b85; --ms-line: #c9d3e0; --ms-surface: #f4f7fb; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 2rem;
            font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--ms-ink);
        }

        a { color: var(--ms-ink); }

        code { font-size: .9em; color: var(--ms-muted); }

        table { width: 100%; border-collapse: collapse; }

        th, td { padding: .5rem .25rem; text-align: left; border-bottom: 1px solid var(--ms-line); }

        th { font-weight: 600; }

        .ms-numeric { text-align: right; font-variant-numeric: tabular-nums; }

        .ms-muted { color: var(--ms-muted); }

        .ms-bar { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; }

        .ms-actions { display: flex; gap: 1rem; }

        .ms-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr));
            margin-bottom: 2rem;
        }

        .ms-card {
            padding: 1rem;
            border: 1px solid var(--ms-line);
            border-radius: .375rem;
            background: var(--ms-surface);
        }

        .ms-card-label { margin: 0; font-size: .85rem; color: var(--ms-muted); }

        .ms-card-value { margin: .25rem 0 0; font-size: 1.6rem; font-variant-numeric: tabular-nums; }

        .ms-card td { border: 0; padding: .15rem 0; font-size: .85rem; }

        .ms-search { width: 100%; padding: .5rem .75rem; border: 1px solid var(--ms-line); border-radius: .375rem; }

        :focus-visible { outline: 2px solid var(--ms-ink); outline-offset: 2px; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accelerator Diagnostics</title>
    <style>
        body { margin: 0; padding: 2rem; background: #f8fafc; color: #0f172a; font-family: ui-sans-serif, system-ui, sans-serif; }
        main { max-width: 72rem; margin: 0 auto; }
        header { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
        a { color: #2563eb; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
        section { overflow: hidden; border: 1px solid #e2e8f0; border-radius: .75rem; background: white; }
        h2 { margin: 0; padding: 1rem; border-bottom: 1px solid #e2e8f0; font-size: 1rem; }
        dl { margin: 0; }
        dl div { display: grid; grid-template-columns: minmax(9rem, 1fr) 1.5fr; gap: 1rem; padding: .7rem 1rem; border-bottom: 1px solid #f1f5f9; }
        dt { color: #64748b; } dd { margin: 0; overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <header>
        <div><h1>Accelerator Diagnostics</h1><p>Bounded, authenticated, read-only runtime facts.</p></div>
        <a href="{{ request()->fullUrlWithQuery(['json' => 1]) }}">JSON</a>
    </header>
    <div class="grid">
        @foreach ($sections as $title => $values)
            <section>
                <h2>{{ $title }}</h2>
                <dl>
                    @foreach ($values as $label => $value)
                        <div><dt>{{ $label }}</dt><dd>{{ is_bool($value) ? ($value ? 'YES' : 'NO') : $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>
</main>
</body>
</html>

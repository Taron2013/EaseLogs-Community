<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('easelogs.display_name'))</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            color: #1a1a1a;
            background: #f8f8f6;
            margin: 0;
            padding: 1.5rem;
        }
        a { color: #1a4d8c; }
        .container { max-width: 32rem; margin: 0 auto; }
        header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd; }
        header h1 { margin: 0; font-size: 1.5rem; }
        header .edition { display: block; margin-top: 0.15rem; font-size: 0.75rem; color: #666; }
        .flash { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .flash-success { background: #e8f5e9; border: 1px solid #a5d6a7; }
        .flash-error { background: #ffebee; border: 1px solid #ef9a9a; }
        .form-section { padding: 1.1rem 1.15rem; background: #fff; border: 1px solid #ececeb; border-radius: 10px; }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-weight: 500; margin-bottom: 0.25rem; font-size: 0.875rem; }
        .field input { width: 100%; padding: 0.4rem 0.5rem; border: 1px solid #ccc; border-radius: 4px; font: inherit; }
        .field-hint { font-size: 0.8rem; color: #666; margin-top: 0.2rem; }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border: 1px solid #1a4d8c;
            border-radius: 4px;
            background: #1a4d8c;
            color: #fff;
            cursor: pointer;
        }
        .page-heading { margin-top: 0; font-size: 1.35rem; }
        .page-intro { color: #555; margin-bottom: 1.25rem; }
        .errors ul { margin: 0 0 1rem; padding-left: 1.25rem; color: #b71c1c; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>
                {{ config('easelogs.short_name') }}
                <span class="edition">{{ config('easelogs.edition') }}</span>
            </h1>
        </header>

        @if (session('success'))
            <div class="flash flash-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="flash flash-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</body>
</html>

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
        a:hover { text-decoration: underline; }
        .container { max-width: 960px; margin: 0 auto; }
        header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd; }
        header h1 { margin: 0; font-size: 1.5rem; line-height: 1.2; }
        header .edition { display: block; margin-top: 0.15rem; font-size: 0.75rem; font-weight: 500; color: #666; letter-spacing: 0.02em; }
        header nav { margin-top: 0.5rem; }
        header nav a { margin-right: 1rem; }
        .flash { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 4px; }
        .flash-success { background: #e8f5e9; border: 1px solid #a5d6a7; }
        .flash-error { background: #ffebee; border: 1px solid #ef9a9a; }
        .flash-warning { background: #fff8e1; border: 1px solid #ffe082; color: #5d4e37; }
        .btn {
            display: inline-block;
            padding: 0.4rem 0.75rem;
            font-size: 0.875rem;
            text-decoration: none;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #fff;
            color: #1a1a1a;
            cursor: pointer;
        }
        .btn-primary { background: #1a4d8c; color: #fff; border-color: #1a4d8c; }
        .btn-danger { background: #b71c1c; color: #fff; border-color: #b71c1c; }
        .page-heading { margin-top: 0; margin-bottom: 0.35rem; font-size: 1.5rem; color: #1a1a1a; }
        .page-intro { margin-top: 0; margin-bottom: 1.5rem; color: #555; max-width: 40rem; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: 0.6rem 0.75rem; border-bottom: 1px solid #eee; }
        th { background: #f0f0ee; font-weight: 600; }
        th .sort-link { color: inherit; text-decoration: none; font-weight: 600; }
        th .sort-link:hover { text-decoration: underline; }
        th .sort-indicator { font-size: 0.75rem; margin-left: 0.15rem; }
        .form-section { margin-bottom: 1.5rem; padding: 1.1rem 1.15rem; background: #fff; border: 1px solid #ececeb; border-radius: 10px; }
        .form-section h2 { font-size: 1rem; margin: 0 0 0.75rem; color: #303030; }
        .form-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); }
        .field { margin-bottom: 1rem; }
        .field label { display: block; font-weight: 500; margin-bottom: 0.25rem; font-size: 0.875rem; }
        .field input[type="text"],
        .field input[type="number"],
        .field input[type="date"],
        .field select,
        .field textarea {
            width: 100%;
            max-width: 32rem;
            padding: 0.4rem 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font: inherit;
        }
        .field textarea { min-height: 4rem; }
        .field-inline { display: flex; align-items: center; gap: 0.5rem; }
        .field-inline input[type="checkbox"] { width: auto; max-width: none; }
        .field-hint { font-size: 0.8rem; color: #666; margin-top: 0.2rem; }
        .errors ul { margin: 0; padding-left: 1.25rem; }
        .actions { margin-top: 1.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .detail-grid { display: grid; grid-template-columns: 10rem 1fr; gap: 0.35rem 1rem; background: #fff; padding: 1rem; border: 1px solid #eee; border-radius: 4px; }
        .detail-grid dt { font-weight: 500; color: #555; }
        .detail-grid dd { margin: 0; }
        .pagination { margin-top: 1rem; }
        .artwork-thumb { width: 64px; height: 64px; object-fit: cover; border-radius: 4px; background: #eee; display: block; }
        .artwork-thumb-placeholder { width: 64px; height: 64px; border-radius: 4px; background: #ececeb; color: #888; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; text-align: center; padding: 0.25rem; }
        .artwork-photo { max-width: 100%; max-height: 32rem; border-radius: 8px; border: 1px solid #ececeb; background: #fff; }
        .artwork-photo-preview { max-width: 12rem; max-height: 12rem; border-radius: 6px; border: 1px solid #ececeb; object-fit: cover; }
        .artwork-photo-edit-reference {
            display: block;
            max-width: 100%;
            max-height: 24rem;
            width: auto;
            border-radius: 8px;
            border: 1px solid #ececeb;
            background: #fff;
            object-fit: contain;
        }
        .artwork-edit-photo-wrap { margin-bottom: 1.25rem; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>
                <a href="{{ route('artworks.index') }}" style="text-decoration:none;color:inherit;">
                    {{ config('easelogs.short_name') }}
                    <span class="edition">{{ config('easelogs.edition') }}</span>
                </a>
            </h1>
            <nav>
                <a href="{{ route('artworks.index') }}">Artworks</a>
                <a href="{{ route('artworks.create') }}">New artwork</a>
                <a href="{{ route('artworks.import-export') }}">Import / Export</a>
                <form method="POST" action="{{ route('logout') }}" style="display:inline;margin:0;">
                    @csrf
                    <button type="submit" class="btn" style="margin-left:0.5rem;">Sign out</button>
                </form>
            </nav>
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

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
        .app-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem 1rem;
        }
        .app-nav-primary,
        .app-nav-account {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.85rem;
        }
        .app-nav a { margin-right: 0; }
        .app-nav-logout { display: inline; margin: 0; }
        .profile-card { max-width: 32rem; }
        .profile-account-details {
            display: grid;
            grid-template-columns: 10rem 1fr;
            gap: 0.35rem 1rem;
            margin: 0;
            max-width: 100%;
        }
        .profile-account-details dt {
            font-weight: 500;
            color: #555;
            font-size: 0.875rem;
        }
        .profile-account-details dd {
            margin: 0;
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
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
        .artwork-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .artwork-mobile-list { display: none; }
        .artwork-mobile-list-toolbar {
            margin-bottom: 0.65rem;
            padding: 0.5rem 0.65rem;
            background: #fff;
            border: 1px solid #ececeb;
            border-radius: 8px;
        }
        .artwork-mobile-select-all {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
        }
        .artwork-mobile-select-all input[type="checkbox"] { width: auto; max-width: none; }
        .artwork-mobile-card {
            background: #fff;
            border: 1px solid #ececeb;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.75rem;
        }
        .artwork-mobile-card-header {
            display: grid;
            grid-template-columns: auto auto 1fr;
            gap: 0.65rem;
            align-items: start;
            margin-bottom: 0.75rem;
        }
        .artwork-mobile-card-title {
            margin: 0;
            font-size: 1rem;
            line-height: 1.35;
            align-self: center;
        }
        .artwork-mobile-card-title a { text-decoration: none; font-weight: 600; }
        .artwork-mobile-card-meta {
            display: grid;
            gap: 0.45rem 0.75rem;
            margin: 0 0 0.85rem;
            font-size: 0.875rem;
        }
        .artwork-mobile-card-meta > div {
            display: grid;
            grid-template-columns: 7.5rem 1fr;
            gap: 0.35rem 0.5rem;
        }
        .artwork-mobile-card-meta dt {
            margin: 0;
            font-weight: 500;
            color: #555;
        }
        .artwork-mobile-card-meta dd { margin: 0; }
        .artwork-mobile-card-actions .artwork-actions-stack {
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 0.85rem;
        }
        .filter-field {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 0.35rem 0.5rem;
        }
        .filter-field label { font-size: 0.8rem; font-weight: 500; color: #555; }
        .filter-apply-btn { align-self: flex-end; }
        .artwork-sort-bar {
            margin-bottom: 1.25rem;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1px solid #ececeb;
            border-radius: 8px;
        }
        .artwork-sort-quick { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.75rem; }
        .artwork-sort-fields { display: none; flex-wrap: wrap; align-items: flex-end; gap: 0.5rem 0.75rem; }
        .artwork-filters-clear-sep { color: #888; }
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
        .detail-grid {
            display: grid;
            grid-template-columns: 10rem 1fr;
            gap: 0.35rem 1rem;
            background: #fff;
            padding: 1rem;
            border: 1px solid #eee;
            border-radius: 4px;
            max-width: 100%;
            overflow: hidden;
        }
        .detail-grid dt { font-weight: 500; color: #555; }
        .detail-grid dd {
            margin: 0;
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .pagination { margin-top: 1rem; }
        .easelogs-pagination { margin-top: 1rem; }
        .easelogs-pagination-list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.35rem 0.5rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .easelogs-pagination-link,
        .easelogs-pagination-disabled,
        .easelogs-pagination-current,
        .easelogs-pagination-ellipsis {
            display: inline-block;
            font-size: 0.875rem;
            line-height: 1.4;
            padding: 0.3rem 0.55rem;
            border-radius: 4px;
        }
        .easelogs-pagination-link {
            text-decoration: none;
            border: 1px solid #d8d8d6;
            background: #fff;
            color: #1a4d8c;
        }
        .easelogs-pagination-link:hover { background: #f0f0ee; }
        .easelogs-pagination-current {
            font-weight: 600;
            background: #e8e8e6;
            border: 1px solid #d0d0ce;
            color: #1a1a1a;
        }
        .easelogs-pagination-disabled {
            color: #999;
            border: 1px solid #ececeb;
            background: #fafaf8;
        }
        .easelogs-pagination-ellipsis {
            color: #666;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
        }
        .artwork-filters {
            margin-bottom: 1.25rem;
            padding: 0.85rem 1rem;
            background: #fff;
            border: 1px solid #ececeb;
            border-radius: 8px;
        }
        .artwork-filters-label { margin: 0 0 0.5rem; font-size: 0.8rem; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: 0.04em; }
        .artwork-filters-quick { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.75rem; }
        .filter-pill {
            display: inline-block;
            padding: 0.25rem 0.55rem;
            font-size: 0.8rem;
            text-decoration: none;
            border: 1px solid #d8d8d6;
            border-radius: 999px;
            background: #fafaf8;
            color: #333;
        }
        .filter-pill:hover { background: #f0f0ee; }
        .filter-pill.is-active { background: #1a4d8c; border-color: #1a4d8c; color: #fff; }
        .artwork-filters-fields {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.5rem 0.75rem;
        }
        .artwork-filters-fields label { font-size: 0.8rem; font-weight: 500; color: #555; }
        .artwork-filters-fields select {
            padding: 0.35rem 0.45rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            font: inherit;
            min-width: 9rem;
        }
        .artwork-filters-clear { margin: 0.65rem 0 0; font-size: 0.85rem; }
        .bulk-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 0.75rem;
            margin-bottom: 0.75rem;
            padding: 0.65rem 0.85rem;
            background: #fff8f8;
            border: 1px solid #f0d4d4;
            border-radius: 8px;
        }
        .bulk-actions-count { margin: 0; font-size: 0.875rem; font-weight: 500; }
        .bulk-delete-form { display: none; }
        .artwork-select-col { width: 3.25rem; vertical-align: middle; text-align: center; }
        .artwork-select-label { display: block; font-size: 0.7rem; font-weight: 600; color: #555; margin-bottom: 0.25rem; }
        .artwork-select-col input[type="checkbox"] { width: auto; max-width: none; cursor: pointer; }
        .artwork-actions { vertical-align: top; white-space: nowrap; }
        .artwork-actions-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.2rem;
        }
        .artwork-action-link { font-size: 0.875rem; }
        .artwork-action-delete-form { margin: 0; }
        .artwork-action-delete {
            padding: 0;
            border: none;
            background: none;
            font: inherit;
            font-size: 0.875rem;
            color: #8b3a3a;
            cursor: pointer;
            text-decoration: underline;
        }
        .artwork-action-delete:hover { color: #b71c1c; }
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
        @media (max-width: 768px) {
            html,
            body {
                overflow-x: clip;
            }
            body { padding: 1rem; }
            .container {
                min-width: 0;
            }
            .profile-page .page-heading {
                font-size: 1.35rem;
            }
            .profile-page .page-intro {
                margin-bottom: 1.25rem;
                max-width: none;
            }
            .profile-card.form-section {
                padding: 1rem;
            }
            .app-nav {
                flex-direction: column;
                align-items: stretch;
            }
            .app-nav-primary,
            .app-nav-account {
                width: 100%;
            }
            .app-nav-account {
                padding-top: 0.5rem;
                border-top: 1px solid #e8e8e6;
            }
            .app-nav-logout .btn {
                width: 100%;
            }
            .artwork-index-table { display: none; }
            .artwork-mobile-list { display: block; }
            .artwork-table-wrap { overflow-x: visible; }
            .artwork-filters-fields {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-field {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
                width: 100%;
            }
            .filter-field select {
                width: 100%;
                min-width: 0;
                max-width: none;
            }
            .filter-apply-btn {
                align-self: stretch;
                width: 100%;
                margin-top: 0.25rem;
            }
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .bulk-actions .btn {
                width: 100%;
                text-align: center;
            }
            .artwork-sort-fields-mobile {
                display: flex;
                flex-direction: column;
                align-items: stretch;
            }
            .artwork-sort-fields-mobile .filter-field {
                flex-direction: column;
                align-items: stretch;
                width: 100%;
            }
            .artwork-sort-fields-mobile select {
                width: 100%;
                min-width: 0;
            }
            .artwork-sort-fields-mobile .filter-apply-btn {
                width: 100%;
            }
            .detail-grid {
                grid-template-columns: 1fr;
                gap: 0.2rem 0;
                padding: 0.85rem 1rem;
            }
            .detail-grid dt {
                margin-top: 0.75rem;
            }
            .detail-grid dt:first-child {
                margin-top: 0;
            }
            .detail-grid dd {
                margin-bottom: 0.35rem;
            }
            .profile-account-details {
                grid-template-columns: 1fr;
                gap: 0.25rem 0;
            }
            .profile-account-details dt {
                margin-top: 1rem;
            }
            .profile-account-details dt:first-child {
                margin-top: 0;
            }
            .profile-account-details dd {
                margin-bottom: 0;
                padding-bottom: 0.15rem;
            }
            .profile-account-details dt + dd {
                margin-bottom: 0.25rem;
            }
            .profile-card {
                max-width: none;
                width: 100%;
            }
            .profile-card .field input[type="text"],
            .profile-card .field input[type="email"],
            .profile-card .field input[type="password"] {
                max-width: none;
            }
            .profile-card .actions {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            .profile-card .actions .btn {
                width: 100%;
                text-align: center;
            }
        }
        @media (min-width: 769px) {
            .artwork-mobile-list { display: none; }
            .artwork-sort-fields-mobile { display: none; }
        }
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
            <nav class="app-nav" aria-label="Main">
                <div class="app-nav-primary">
                    <a href="{{ route('artworks.index') }}">Artworks</a>
                    <a href="{{ route('artworks.create') }}">New artwork</a>
                    <a href="{{ route('artworks.import-export') }}">Import / Export</a>
                </div>
                <div class="app-nav-account" aria-label="Account">
                    <a href="{{ route('profile.show') }}">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" class="app-nav-logout">
                        @csrf
                        <button type="submit" class="btn">Sign out</button>
                    </form>
                </div>
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

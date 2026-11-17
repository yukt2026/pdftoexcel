<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'AI Invoice to Excel') }} - @yield('title', 'SaaS Invoice Automation')</title>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            /* Theme Color Palette: Stripe, Linear & Modern Financial SaaS Theme */
            --primary-teal: #0F766E;
            --secondary-teal: #134E4A;
            --teal-accent: #14B8A6;
            --teal-hover: #0D9488;
            --teal-light: #F0FDFA;
            --teal-border: #CCFBF1;

            --primary-navy: #172554;
            --navy-sidebar: #172554;
            --navy-header: #111e47;
            --navy-footer: #0c1638;

            --main-bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --border-color: #E2E8F0;
            --border-light: #F1F5F9;

            --success: #16A34A;
            --warning: #D97706;
            --danger: #DC2626;

            /* Legacy compatibility mappings */
            --emerald-600: #0F766E;
            --emerald-700: #134E4A;
            --emerald-800: #134E4A;
            --emerald-50: #F0FDFA;
            --emerald-100: #CCFBF1;
            --emerald-200: #99F6E4;

            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-300: #CBD5E1;
            --slate-400: #94A3B8;
            --slate-500: #64748B;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1E293B;
            --slate-900: #0F172A;

            /* Dimensions & Layout */
            --sidebar-width: 270px;
            --topbar-height: 64px;
            --card-radius: 10px;
            --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.02);
            --card-hover-shadow: 0 4px 14px -2px rgba(15, 23, 42, 0.07);
        }

        /* 1. Global Viewport & Reset */
        html, body {
            height: 100%;
            height: 100dvh;
            margin: 0;
            padding: 0;
            overflow: hidden; /* Body does not scroll; sidebar and content have independent scrolling */
            background-color: var(--main-bg);
            color: var(--text-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        #app-container {
            display: flex;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            position: relative;
        }

        /* 2. SIDEBAR: Full viewport height, flex column, fixed width */
        #sidebar-wrapper {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            height: 100vh;
            height: 100dvh;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1040;
            background: var(--navy-sidebar);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Sidebar Header (Fixed flex section - does NOT scroll) */
        .sidebar-header {
            flex-shrink: 0;
            height: var(--topbar-height);
            padding: 0 1.25rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background: var(--navy-header);
        }

        .sidebar-brand-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            width: 100%;
        }

        .sidebar-brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-teal), var(--teal-accent));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(20, 184, 166, 0.35);
            flex-shrink: 0;
        }

        .sidebar-brand-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .brand-dot {
            color: var(--teal-accent);
        }

        .sidebar-brand-badge {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--teal-accent);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* Sidebar Navigation Menu (flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden) */
        .sidebar-menu {
            flex: 1 1 0%;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0.85rem 0.75rem 1.25rem;
            overscroll-behavior: contain;
        }

        /* Visually subtle scrollbar for sidebar */
        .sidebar-menu::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar-menu::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.16);
            border-radius: 9999px;
        }
        .sidebar-menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.32);
        }
        .sidebar-menu {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.16) transparent;
        }

        .menu-section-header {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94A3B8;
            padding: 0.85rem 0.65rem 0.35rem;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.52rem 0.75rem;
            color: #CBD5E1;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 2px;
            transition: all 0.15s ease-in-out;
        }

        .nav-link-custom i {
            font-size: 1rem;
            color: #94A3B8;
            transition: color 0.15s ease-in-out;
            width: 18px;
            text-align: center;
        }

        .nav-link-custom:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
        }

        .nav-link-custom:hover i {
            color: var(--teal-accent);
        }

        .nav-link-custom.active {
            background-color: rgba(20, 184, 166, 0.18);
            color: #2DD4BF;
            font-weight: 600;
            border-left: 3px solid var(--teal-accent);
        }

        .nav-link-custom.active i {
            color: #2DD4BF;
        }

        /* Sidebar Footer (Fixed flex section - does NOT scroll) */
        .sidebar-footer {
            flex-shrink: 0;
            padding: 0.85rem 1.15rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: var(--navy-footer);
        }

        .user-status-indicator {
            display: inline-block;
            width: 7px;
            height: 7px;
            background-color: #10B981;
            border-radius: 9999px;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
        }

        .sidebar-footer-role {
            font-size: 0.75rem;
            font-weight: 600;
            color: #E2E8F0;
        }

        .sidebar-role-badge {
            background: rgba(255, 255, 255, 0.1);
            color: #CBD5E1;
            border: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 600;
            padding: 0.2rem 0.45rem;
            border-radius: 4px;
        }

        /* 3. MAIN CONTENT: Independent scrolling container */
        #page-content-wrapper {
            flex: 1 1 0%;
            margin-left: var(--sidebar-width);
            height: 100vh;
            height: 100dvh;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            background-color: var(--main-bg);
            transition: margin-left 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            -webkit-overflow-scrolling: touch;
        }

        /* Main content subtle scrollbar */
        #page-content-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        #page-content-wrapper::-webkit-scrollbar-track {
            background: #F1F5F9;
        }
        #page-content-wrapper::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 9999px;
        }
        #page-content-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }

        /* Sticky Top Navigation Bar */
        .top-navbar {
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 1020;
            height: var(--topbar-height);
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border-color);
            padding: 0 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .main-content {
            flex: 1 0 auto;
            padding: 1.75rem 2rem 3rem;
        }

        /* 4. CARDS & SAAS COMPONENTS */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-color) !important;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .card:hover {
            box-shadow: var(--card-hover-shadow);
            border-color: #CBD5E1 !important;
        }

        .card-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.35rem;
            font-weight: 600;
            color: var(--primary-navy);
            border-radius: var(--card-radius) var(--card-radius) 0 0 !important;
        }

        .card-body {
            padding: 1.35rem;
        }

        .card-footer {
            background: #F8FAFC;
            border-top: 1px solid var(--border-color);
            padding: 0.9rem 1.35rem;
            border-radius: 0 0 var(--card-radius) var(--card-radius) !important;
        }

        /* 5. BUTTONS: Primary Teal & Secondary Palette */
        .btn-teal, .btn-primary {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: #ffffff;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.15s ease-in-out;
        }

        .btn-teal:hover, .btn-teal:focus, .btn-primary:hover, .btn-primary:focus {
            background-color: var(--secondary-teal);
            border-color: var(--secondary-teal);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(15, 118, 110, 0.25);
        }

        .btn-outline-teal {
            background-color: transparent;
            border-color: var(--primary-teal);
            color: var(--primary-teal);
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.15s ease-in-out;
        }

        .btn-outline-teal:hover {
            background-color: var(--teal-light);
            border-color: var(--primary-teal);
            color: var(--primary-teal);
        }

        /* Backward Compatibility for btn-emerald */
        .btn-emerald {
            background-color: var(--primary-teal) !important;
            border-color: var(--primary-teal) !important;
            color: #ffffff !important;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.15s ease-in-out;
        }

        /* Refined Table Action & Download Buttons */
        .btn-action-download {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: #ffffff !important;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(15, 118, 110, 0.15);
            transition: all 0.15s ease-in-out;
        }

        .btn-action-download:hover {
            background-color: var(--secondary-teal);
            border-color: var(--secondary-teal);
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(15, 118, 110, 0.3);
            transform: translateY(-1px);
        }

        .btn-action-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary) !important;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .btn-action-outline:hover {
            background-color: #F1F5F9;
            border-color: #CBD5E1;
            color: var(--primary-navy) !important;
        }

        /* Sleek horizontal scrollbar for responsive tables */
        .table-responsive {
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 transparent;
        }

        .table-responsive::-webkit-scrollbar {
            height: 4px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: transparent;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 9999px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background-color: #94A3B8;
        }

        .btn-emerald:hover, .btn-emerald:focus {
            background-color: var(--secondary-teal) !important;
            border-color: var(--secondary-teal) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(15, 118, 110, 0.25);
        }

        .btn-outline-emerald {
            background-color: transparent !important;
            border-color: var(--primary-teal) !important;
            color: var(--primary-teal) !important;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.15s ease-in-out;
        }

        .btn-outline-emerald:hover {
            background-color: var(--teal-light) !important;
            border-color: var(--primary-teal) !important;
            color: var(--primary-teal) !important;
        }

        .btn-navy {
            background-color: var(--primary-navy);
            border-color: var(--primary-navy);
            color: #ffffff;
            font-weight: 500;
            border-radius: 6px;
        }

        .btn-navy:hover {
            background-color: #1e3a8a;
            border-color: #1e3a8a;
            color: #ffffff;
        }

        /* 6. STATUS BADGES */
        .badge {
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .badge-subtle-success {
            background-color: #F0FDF4;
            color: var(--success);
            border: 1px solid #BBF7D0;
        }

        .badge-subtle-primary {
            background-color: #F0FDFA;
            color: var(--primary-teal);
            border: 1px solid #CCFBF1;
        }

        .badge-subtle-warning {
            background-color: #FFFBEB;
            color: var(--warning);
            border: 1px solid #FDE68A;
        }

        .badge-subtle-danger {
            background-color: #FEF2F2;
            color: var(--danger);
            border: 1px solid #FECACA;
        }

        .badge-subtle-secondary {
            background-color: #F8FAFC;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        /* 7. TABLES: Modern Crisp SaaS Table */
        .table {
            --bs-table-bg: transparent;
            --bs-table-striped-bg: #F8FAFC;
            --bs-table-hover-bg: #F1F5F9;
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .table thead th {
            background-color: #F8FAFC;
            color: var(--text-secondary);
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .table tbody td {
            padding: 0.85rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-light);
        }

        .table tbody tr:hover {
            background-color: rgba(241, 245, 249, 0.75);
        }

        /* 8. FORMS & INPUTS */
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.35rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 0.86rem;
            padding: 0.45rem 0.75rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
        }

        .form-control-sm, .form-select-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.8125rem;
        }

        .input-group-text {
            background-color: #F8FAFC;
            border-color: var(--border-color);
            color: var(--text-secondary);
            font-size: 0.86rem;
        }

        /* 9. DROPDOWNS & MODALS */
        .dropdown-menu {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05);
            padding: 0.4rem;
        }

        .dropdown-item {
            border-radius: 6px;
            padding: 0.45rem 0.75rem;
            font-size: 0.84rem;
            color: var(--text-primary);
            font-weight: 500;
            transition: background-color 0.1s ease;
        }

        .dropdown-item:hover {
            background-color: var(--border-light);
            color: var(--primary-navy);
        }

        .modal-content {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.15);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.15rem 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            background-color: #F8FAFC;
            border-radius: 0 0 12px 12px;
        }

        /* 10. PAGINATION */
        .pagination .page-item .page-link {
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 0.35rem 0.75rem;
            font-size: 0.8125rem;
            border-radius: 6px;
            margin: 0 2px;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: #ffffff;
            font-weight: 600;
        }

        .pagination .page-item .page-link:hover {
            background-color: var(--border-light);
            color: var(--primary-teal);
        }

        /* 11. MOBILE & TABLET RESPONSIVENESS */
        #sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(2px);
            z-index: 1030;
        }

        @media (max-width: 991.98px) {
            #sidebar-wrapper {
                transform: translateX(-100%);
                box-shadow: 0 0 35px rgba(0, 0, 0, 0.45);
            }

            #sidebar-wrapper.show {
                transform: translateX(0);
            }

            #page-content-wrapper {
                margin-left: 0 !important;
                width: 100vw;
            }

            #sidebar-backdrop.show {
                display: block;
            }

            .main-content {
                padding: 1.15rem 1rem 2.5rem;
            }

            .top-navbar {
                padding: 0 1rem;
            }
        }
    </style>
    @stack('styles')
</head>
<body>

    <div id="app-container">
        <!-- Mobile Sidebar Backdrop Overlay -->
        <div id="sidebar-backdrop" onclick="toggleSidebar()"></div>

        <!-- SIDEBAR: Full Viewport Height, Deep Navy Theme, Independent Scrolling -->
        <aside id="sidebar-wrapper">
            <!-- 1. Fixed Header (Does not scroll) -->
            <div class="sidebar-header">
                <a href="{{ route('dashboard') }}" class="sidebar-brand-link">
                    <div class="sidebar-brand-icon">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    </div>
                    <div>
                        <div class="sidebar-brand-title">AI Invoice <span class="brand-dot">&bull;</span></div>
                        <div class="sidebar-brand-badge">Enterprise SaaS</div>
                    </div>
                </a>
            </div>

            <!-- 2. Independent Scrollable Navigation Menu -->
            <nav class="sidebar-menu">
                <!-- Group 1: OVERVIEW -->
                <div class="menu-section-header">Overview</div>
                <a href="{{ route('dashboard') }}" class="nav-link-custom {{ request()->routeIs('dashboard') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Group 2: DOCUMENT INGESTION -->
                <div class="menu-section-header">Document Ingestion</div>
                <a href="{{ route('uploads.invoices') }}" class="nav-link-custom {{ request()->routeIs('uploads.invoices') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Upload Invoices</span>
                </a>
                <a href="{{ route('uploads.shipping') }}" class="nav-link-custom {{ request()->routeIs('uploads.shipping') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-truck"></i>
                    <span>Upload Shipping Labels</span>
                </a>
                <a href="{{ route('batches.index') }}" class="nav-link-custom {{ request()->routeIs('batches.*') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-folder2-open"></i>
                    <span>Upload Batches</span>
                </a>
                <a href="{{ route('processing.index') }}" class="nav-link-custom {{ request()->routeIs('processing.*') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-cpu"></i>
                    <span>Processing Queue</span>
                </a>

                <!-- Group 3: INVOICE OPERATIONS -->
                <div class="menu-section-header">Invoice Operations</div>
                <a href="{{ route('invoices.index') }}" class="nav-link-custom {{ request()->routeIs('invoices.index') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-receipt"></i>
                    <span>All Invoices</span>
                </a>
                <a href="{{ route('invoices.review') }}" class="nav-link-custom {{ request()->routeIs('invoices.review') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-clipboard-check"></i>
                    <span>Data Review</span>
                    @php
                        $pendingBadgeQuery = \App\Models\Invoice::whereIn('processing_status', ['extracted', 'review_needed']);
                        if (!auth()->user()->isAdmin()) {
                            $pendingBadgeQuery->where('user_id', auth()->id());
                        }
                        $pendingReviewBadge = $pendingBadgeQuery->count();
                    @endphp
                    @if($pendingReviewBadge > 0)
                        <span class="badge bg-warning text-dark ms-auto rounded-pill" style="font-size: 0.65rem;">{{ $pendingReviewBadge }}</span>
                    @endif
                </a>

                <!-- Group 4: RECONCILIATION -->
                <div class="menu-section-header">Reconciliation</div>
                <a href="{{ route('invoices.matched') }}" class="nav-link-custom {{ request()->routeIs('invoices.matched') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-patch-check"></i>
                    <span>Matched Invoices</span>
                </a>
                <a href="{{ route('invoices.unmatched') }}" class="nav-link-custom {{ request()->routeIs('invoices.unmatched') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Unmatched Invoices</span>
                </a>
                <a href="{{ route('matching.index') }}" class="nav-link-custom {{ request()->routeIs('matching.index') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>2-Way & 3-Way Match</span>
                </a>
                <a href="{{ route('shipping.index') }}" class="nav-link-custom {{ request()->routeIs('shipping.index') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-box-seam"></i>
                    <span>Shipping Bills</span>
                </a>

                <!-- Group 5: EXPORTS -->
                <div class="menu-section-header">Exports</div>
                <a href="{{ route('exports.index') }}" class="nav-link-custom {{ request()->routeIs('exports.*') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-file-earmark-excel"></i>
                    <span>Excel Exports</span>
                </a>
                <a href="{{ route('history.index', ['tab' => 'exports']) }}" class="nav-link-custom {{ request()->routeIs('history.*') && request('tab') === 'exports' ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-download"></i>
                    <span>Export History</span>
                </a>
                <a href="{{ route('history.index') }}" class="nav-link-custom {{ request()->routeIs('history.*') && request('tab') !== 'exports' ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-clock-history"></i>
                    <span>Processing History</span>
                </a>

                <!-- Group 6: MANAGEMENT -->
                <div class="menu-section-header">Management</div>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('reports.index') }}" class="nav-link-custom {{ request()->routeIs('reports.*') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                        <i class="bi bi-graph-up"></i>
                        <span>Reports</span>
                    </a>
                    <a href="{{ route('users.index') }}" class="nav-link-custom {{ request()->routeIs('users.*') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                        <i class="bi bi-people"></i>
                        <span>User Management</span>
                    </a>
                    <a href="{{ route('settings.index') }}" class="nav-link-custom {{ request()->routeIs('settings.*') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                @endif
                <a href="{{ route('profile.index') }}" class="nav-link-custom {{ request()->routeIs('profile.*') ? 'active' : '' }}" onclick="closeSidebarOnMobile()">
                    <i class="bi bi-person"></i>
                    <span>My Profile</span>
                </a>
            </nav>

            <!-- 3. Fixed Footer (Does not scroll) -->
            <div class="sidebar-footer">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="user-status-indicator"></span>
                        <span class="sidebar-footer-role">
                            {{ auth()->user()->isAdmin() ? 'Administrator' : 'Standard User' }}
                        </span>
                    </div>
                    <span class="badge sidebar-role-badge">
                        {{ auth()->user()->role }}
                    </span>
                </div>
            </div>
        </aside>

        <!-- PAGE CONTENT WRAPPER: Independent Vertical Scrolling -->
        <div id="page-content-wrapper">
            <!-- Sticky Top Navigation Bar -->
            <header class="top-navbar">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-sm btn-light border d-lg-none" onclick="toggleSidebar()" aria-label="Toggle Navigation">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div>
                        <h5 class="mb-0 fw-bold" style="color: var(--primary-navy); letter-spacing: -0.02em;">@yield('page_title', 'Dashboard')</h5>
                        @yield('breadcrumbs')
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <!-- Quick Action Button Bar -->
                    <div class="dropdown me-2">
                        <button class="btn btn-sm btn-teal dropdown-toggle rounded-pill px-3 shadow-sm d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Quick Action
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border mt-2" style="border-radius: 10px; border-color: var(--border-color);">
                            <li><a class="dropdown-item py-2 small" href="{{ route('uploads.invoices') }}"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Upload Invoice PDF</a></li>
                            <li><a class="dropdown-item py-2 small" href="{{ route('uploads.shipping') }}"><i class="bi bi-truck text-teal me-2" style="color: var(--primary-teal);"></i>Upload Shipping Label PDF</a></li>
                            <li>
                                <form action="{{ route('processing.batch') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 small text-dark"><i class="bi bi-cpu text-warning me-2"></i>Process Documents</button>
                                </form>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 small fw-semibold" style="color: var(--primary-teal);" href="{{ route('exports.invoices') }}"><i class="bi bi-file-earmark-excel me-2"></i>Export Excel</a></li>
                        </ul>
                    </div>

                    <!-- User Dropdown Menu -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-white bg-white border rounded-pill d-flex align-items-center gap-2 py-1 px-3 shadow-sm" type="button" data-bs-toggle="dropdown">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 0.8rem; background: var(--teal-light); color: var(--primary-teal); border: 1px solid var(--teal-border);">
                                {{ strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) }}
                            </div>
                            <span class="small fw-semibold d-none d-sm-inline" style="color: var(--primary-navy);">{{ auth()->user()?->name ?? 'User' }}</span>
                            <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.65rem;">{{ auth()->user()?->role }}</span>
                            <i class="bi bi-chevron-down text-muted" style="font-size: 0.7rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border mt-2" style="border-radius: 10px; border-color: var(--border-color);">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold small" style="color: var(--primary-navy);">{{ auth()->user()?->name }}</div>
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ auth()->user()?->email }}</div>
                            </li>
                            <li><a class="dropdown-item py-2 small" href="{{ route('profile.index') }}"><i class="bi bi-person me-2 text-secondary"></i>My Profile</a></li>
                            @if(auth()->user()->isAdmin())
                                <li><a class="dropdown-item py-2 small" href="{{ route('settings.index') }}"><i class="bi bi-gear me-2 text-secondary"></i>System Settings</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 small text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Log Out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content Canvas -->
            <main class="main-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 py-2 px-3 mb-4" role="alert" style="background-color: #F0FDF4; border-color: #BBF7D0; color: #166534;">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <div class="small fw-medium">{{ session('success') }}</div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-3 py-2 px-3 mb-4" role="alert" style="background-color: #FFFBEB; border-color: #FDE68A; color: #92400E;">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                        <div class="small fw-medium">{{ session('warning') }}</div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-3 py-2 px-3 mb-4" role="alert" style="background-color: #FEF2F2; border-color: #FECACA; color: #991B1B;">
                        <div class="fw-semibold small mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-x-circle-fill fs-5"></i> Please correct the following errors:
                        </div>
                        <ul class="mb-0 ps-4 small">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar-wrapper');
            const backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        }

        function closeSidebarOnMobile() {
            if (window.innerWidth < 992) {
                const sidebar = document.getElementById('sidebar-wrapper');
                const backdrop = document.getElementById('sidebar-backdrop');
                if (sidebar && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    backdrop.classList.remove('show');
                }
            }
        }
    </script>
    @stack('scripts')
</body>
</html>

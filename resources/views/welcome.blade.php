<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'AI Invoice to Excel') }} &bull; Enterprise Extraction & Reconciliation</title>

    <!-- Google Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-teal: #0F766E;
            --secondary-teal: #134E4A;
            --primary-navy: #172554;
            --navy-dark: #0F172A;
            --main-bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --border-color: #E2E8F0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--main-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            letter-spacing: -0.011em;
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .hero-section {
            background: radial-gradient(circle at 50% 10%, #1E3A8A 0%, var(--primary-navy) 45%, #0B1120 100%);
            color: #FFFFFF;
            padding: 5rem 0 6rem 0;
            position: relative;
        }

        .btn-teal {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
            color: #FFFFFF;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(15, 118, 110, 0.3);
            transition: all 0.2s ease;
        }

        .btn-teal:hover {
            background-color: var(--secondary-teal);
            border-color: var(--secondary-teal);
            color: #FFFFFF;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(15, 118, 110, 0.4);
        }

        .feature-card {
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .feature-card:hover {
            border-color: #CBD5E1;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            transform: translateY(-2px);
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <header class="navbar navbar-expand-md py-3 sticky-top" style="background-color: rgba(23, 37, 84, 0.95); backdrop-filter: blur(8px); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 text-white text-decoration-none" href="{{ url('/') }}">
                <div class="d-flex align-items-center justify-content-center rounded-3" style="width: 36px; height: 36px; background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%);">
                    <i class="bi bi-file-earmark-spreadsheet-fill text-white fs-5"></i>
                </div>
                <div class="d-flex flex-column">
                    <span class="fw-bold tracking-tight" style="font-size: 1.05rem; line-height: 1.2;">AI INVOICE<span class="text-teal-400" style="color: #2DD4BF;">&bull;EXCEL</span></span>
                    <span class="text-uppercase" style="font-size: 0.65rem; color: #94A3B8; letter-spacing: 0.08em; font-weight: 600;">Enterprise SaaS</span>
                </div>
            </a>

            <div class="d-flex align-items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-teal rounded-pill px-4 btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i> Open Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-light rounded-pill px-4 btn-sm">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(15, 118, 110, 0.25); border: 1px solid rgba(45, 212, 191, 0.4); color: #5EEAD4; font-size: 0.8rem; font-weight: 500;">
                        <i class="bi bi-stars"></i> Powered by Google Gemini AI & Multi-Carrier OCR
                    </div>
                    <h1 class="display-4 fw-extrabold tracking-tight mb-3" style="font-weight: 800; line-height: 1.15;">
                        Intelligent Indian GST Invoice & Shipping Reconciliation to Excel
                    </h1>
                    <p class="lead text-slate-300 mb-4 mx-auto" style="color: #CBD5E1; max-width: 680px; font-size: 1.15rem;">
                        Automate multi-file PDF ingestion for Flipkart, DREESTI, and vendor tax invoices. Reconcile Order & PO numbers with E-Kart & Delhivery AWBs, exporting directly to 2-sheet XLSX workbooks.
                    </p>

                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-teal btn-lg rounded-pill px-5">
                                Go to Application Dashboard <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-teal btn-lg rounded-pill px-5">
                                Access Web Portal <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="text-uppercase fw-bold small" style="color: var(--primary-teal); letter-spacing: 0.08em;">Core Capabilities</span>
                <h2 class="fw-bold mt-1 text-dark">Engineered for Enterprise Compliance</h2>
                <p class="text-muted small mx-auto" style="max-width: 520px;">
                    End-to-end processing pipeline from raw scanned PDFs to validated financial spreadsheets.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <div class="icon-box mb-3" style="background-color: #F0FDFA; color: var(--primary-teal);">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">GST Tax Invoice Extraction</h5>
                        <p class="text-secondary small mb-0">
                            Parses invoice numbers, buyer addresses, GSTINs, and multi-product line items with separated CGST, SGST, IGST, discounts, and handling fees.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <div class="icon-box mb-3" style="background-color: #EFF6FF; color: #2563EB;">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Waybill & Courier OCR</h5>
                        <p class="text-secondary small mb-0">
                            Detects courier tracking IDs (AWB) from Flipkart E-Kart, Delhivery, and standard waybills without confusing return codes or destination routing.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <div class="icon-box mb-3" style="background-color: #FEF3C7; color: #D97706;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">5-Tier Matching Engine</h5>
                        <p class="text-secondary small mb-0">
                            Reconciles invoices against shipping labels with exact Order ID matching, PO matching, normalized suffix removal (<span class="mono">_1</span>, <span class="mono">_2</span>), and address verification.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <div class="icon-box mb-3" style="background-color: #ECFDF5; color: #16A34A;">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">2-Sheet Excel Exports</h5>
                        <p class="text-secondary small mb-0">
                            Pre-formatted workbooks containing Sheet 1 for granular line-item records with matched AWB and Sheet 2 for invoice-level tax and payment summaries.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <div class="icon-box mb-3" style="background-color: #F1F5F9; color: #475569;">
                            <i class="bi bi-sliders"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Audit & Review Workspace</h5>
                        <p class="text-secondary small mb-0">
                            Live side-by-side verification keeping original AI-extracted values separate from manual human adjustments with comprehensive audit logging.
                        </p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="feature-card p-4 h-100">
                        <div class="icon-box mb-3" style="background-color: #FEF2F2; color: #DC2626;">
                            <i class="bi bi-lock-fill"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Enterprise Security</h5>
                        <p class="text-secondary small mb-0">
                            Encrypted database storage, user data isolation, role-based access control, CSRF protection, and zero credential leakage to browser frontends.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4 border-top bg-white mt-auto">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
            <div>
                &copy; {{ date('Y') }} <strong>{{ config('app.name', 'AI Invoice to Excel') }}</strong>. All rights reserved.
            </div>
            <div class="d-flex gap-3">
                <a href="{{ route('login') }}" class="text-decoration-none text-secondary">Sign In</a>
                <span>&bull;</span>
                <span class="text-muted">Enterprise Financial SaaS v2.0</span>
            </div>
        </div>
    </footer>
</body>
</html>

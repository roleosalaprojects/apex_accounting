<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apex Accounting — Books that balance. Always.</title>
    <meta name="description" content="Double-entry accounting built for Philippine businesses. BIR-compliant invoices, books of accounts, VAT &amp; EWT — every centavo accounted for.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=archivo:400,500,600,700,800,900&family=instrument-serif:400,400i&family=ibm-plex-mono:400,500,600" rel="stylesheet">
    <style>
        :root {
            --ink: #0a0a0a;
            --ink-2: #111110;
            --ink-3: #1a1916;
            --line: #262420;
            --paper: #f4f1ea;
            --paper-dim: #b8b2a4;
            --amber: #f5b80c;
            --amber-deep: #c98f00;
            --green: #4ade80;
            --red: #f87171;
            --display: "Archivo", system-ui, sans-serif;
            --serif: "Instrument Serif", Georgia, serif;
            --mono: "IBM Plex Mono", ui-monospace, monospace;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background: var(--ink);
            color: var(--paper);
            font-family: var(--display);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        ::selection { background: var(--amber); color: var(--ink); }

        /* grain + ledger-grid backdrop */
        body::before {
            content: "";
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(to bottom, transparent 31px, rgba(244,241,234,.035) 32px),
                linear-gradient(to right, rgba(244,241,234,.025) 1px, transparent 1px);
            background-size: 100% 32px, 96px 100%;
            mask-image: radial-gradient(ellipse 120% 80% at 50% 0%, black 30%, transparent 75%);
        }
        body::after {
            content: "";
            position: fixed; inset: -50%; z-index: 0; pointer-events: none; opacity: .35;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.05'/%3E%3C/svg%3E");
        }
        main, nav, footer { position: relative; z-index: 1; }

        .wrap { max-width: 1180px; margin: 0 auto; padding-inline: 24px; }

        /* ---------- nav ---------- */
        nav {
            position: sticky; top: 0; z-index: 50;
            backdrop-filter: blur(14px);
            background: color-mix(in srgb, var(--ink) 75%, transparent);
            border-bottom: 1px solid var(--line);
        }
        .nav-inner { display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .logo { display: flex; align-items: center; gap: 10px; font-weight: 800; letter-spacing: -.02em; font-size: 17px; color: var(--paper); text-decoration: none; }
        .logo-mark {
            width: 28px; height: 28px; background: var(--amber); color: var(--ink);
            display: grid; place-items: center; font-family: var(--mono); font-weight: 600; font-size: 15px;
            clip-path: polygon(50% 0, 100% 100%, 0 100%);
        }
        .nav-links { display: flex; gap: 28px; align-items: center; }
        .nav-links a { color: var(--paper-dim); text-decoration: none; font-size: 14px; font-weight: 500; transition: color .2s; }
        .nav-links a:hover { color: var(--paper); }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--amber); color: var(--ink) !important;
            font-weight: 700; font-size: 14px; text-decoration: none;
            padding: 10px 20px; border: 1px solid var(--amber);
            transition: background .2s, transform .15s;
        }
        .btn:hover { background: #ffd54a; transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: var(--paper) !important; border: 1px solid var(--line); }
        .btn-ghost:hover { background: var(--ink-3); border-color: var(--paper-dim); }

        /* ---------- hero ---------- */
        .hero { padding-block: 96px 72px; }
        .kicker {
            display: inline-flex; align-items: center; gap: 10px;
            font-family: var(--mono); font-size: 12px; letter-spacing: .18em; text-transform: uppercase;
            color: var(--amber); margin-bottom: 28px;
        }
        .kicker::before { content: ""; width: 36px; height: 1px; background: var(--amber); }
        h1 {
            font-size: clamp(52px, 9vw, 118px);
            line-height: .96; letter-spacing: -.04em; font-weight: 800;
            text-wrap: balance;
        }
        h1 em { font-family: var(--serif); font-weight: 400; font-style: italic; letter-spacing: -.01em; color: var(--amber); }
        .hero-sub {
            margin-top: 28px; max-width: 560px; font-size: 19px; line-height: 1.55; color: var(--paper-dim);
        }
        .hero-sub strong { color: var(--paper); font-weight: 600; }
        .hero-cta { margin-top: 40px; display: flex; gap: 14px; flex-wrap: wrap; }
        .hero-cta .btn { padding: 15px 30px; font-size: 15px; }

        /* hero ledger card */
        .hero-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 64px; align-items: center; }
        @media (max-width: 920px) { .hero-grid { grid-template-columns: 1fr; } }
        .ledger {
            background: var(--ink-2); border: 1px solid var(--line);
            box-shadow: 0 0 0 1px rgba(245,184,12,.06), 0 40px 80px -30px rgba(0,0,0,.8), 0 0 120px -40px rgba(245,184,12,.18);
            font-family: var(--mono); font-size: 13px;
            transform: rotate(1.2deg);
        }
        .ledger-head {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 18px; border-bottom: 1px solid var(--line);
            color: var(--paper-dim); font-size: 11px; letter-spacing: .12em; text-transform: uppercase;
        }
        .ledger-head .dot { color: var(--green); }
        .ledger table { width: 100%; border-collapse: collapse; }
        .ledger td { padding: 11px 18px; border-bottom: 1px solid color-mix(in srgb, var(--line) 60%, transparent); white-space: nowrap; }
        .ledger td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
        .ledger .acct { color: var(--paper-dim); }
        .ledger .dr { color: var(--paper); }
        .ledger .cr { color: var(--paper-dim); padding-left: 34px; }
        .ledger tfoot td { border-bottom: none; border-top: 1px solid var(--amber); padding-top: 13px; color: var(--amber); font-weight: 600; }
        .ledger .balanced {
            display: flex; justify-content: space-between; padding: 12px 18px;
            background: color-mix(in srgb, var(--amber) 8%, transparent);
            color: var(--amber); font-size: 11px; letter-spacing: .14em; text-transform: uppercase;
        }

        /* ---------- marquee ---------- */
        .marquee {
            border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);
            overflow: hidden; padding: 18px 0; background: var(--ink-2);
        }
        .marquee-track { display: flex; gap: 0; width: max-content; animation: scroll 36s linear infinite; }
        .marquee span {
            font-family: var(--mono); font-size: 13px; letter-spacing: .14em; text-transform: uppercase;
            color: var(--paper-dim); padding: 0 28px; display: flex; align-items: center; gap: 28px; white-space: nowrap;
        }
        .marquee span::after { content: "•"; color: var(--amber); }
        @keyframes scroll { to { transform: translateX(-50%); } }

        /* ---------- sections ---------- */
        section { padding-block: 110px; }
        .sec-label {
            font-family: var(--mono); font-size: 12px; letter-spacing: .18em; text-transform: uppercase;
            color: var(--amber); margin-bottom: 18px;
        }
        h2 { font-size: clamp(34px, 5vw, 56px); letter-spacing: -.03em; line-height: 1.04; font-weight: 800; text-wrap: balance; }
        h2 em { font-family: var(--serif); font-style: italic; font-weight: 400; color: var(--amber); }
        .sec-head { max-width: 720px; margin-bottom: 64px; }
        .sec-head p { margin-top: 18px; color: var(--paper-dim); font-size: 17px; line-height: 1.6; }

        /* bento */
        .bento { display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; }
        .cell {
            border: 1px solid var(--line); background: var(--ink-2);
            padding: 30px; position: relative; overflow: hidden;
            transition: border-color .25s, transform .25s;
        }
        .cell:hover { border-color: color-mix(in srgb, var(--amber) 45%, var(--line)); transform: translateY(-3px); }
        .cell h3 { font-size: 19px; font-weight: 700; letter-spacing: -.01em; margin-bottom: 10px; }
        .cell p { color: var(--paper-dim); font-size: 14.5px; line-height: 1.6; }
        .cell .num {
            font-family: var(--mono); font-size: 11px; color: var(--amber);
            letter-spacing: .14em; display: block; margin-bottom: 22px; text-transform: uppercase;
        }
        .c-3 { grid-column: span 3; } .c-2 { grid-column: span 2; } .c-4 { grid-column: span 4; }
        @media (max-width: 860px) { .c-3, .c-2, .c-4 { grid-column: span 6; } }
        .cell-hero {
            background: linear-gradient(135deg, color-mix(in srgb, var(--amber) 14%, var(--ink-2)), var(--ink-2) 55%);
        }
        .cell-figures { font-family: var(--mono); }
        .cell-figures .big {
            font-size: clamp(40px, 4.5vw, 56px); color: var(--paper); font-weight: 600;
            font-variant-numeric: tabular-nums; letter-spacing: -.02em; display: block; margin-bottom: 6px;
        }
        .cell-figures .sub { font-size: 12px; color: var(--paper-dim); letter-spacing: .1em; text-transform: uppercase; }

        /* compliance ticket strip */
        .forms { display: flex; gap: 14px; flex-wrap: wrap; margin-top: 48px; }
        .form-chip {
            font-family: var(--mono); font-size: 13px; padding: 12px 18px;
            border: 1px dashed color-mix(in srgb, var(--amber) 55%, var(--line));
            color: var(--paper); background: color-mix(in srgb, var(--amber) 5%, transparent);
        }
        .form-chip b { color: var(--amber); font-weight: 600; }

        /* numbers band */
        .band {
            border-top: 1px solid var(--line); border-bottom: 1px solid var(--line);
            background: var(--ink-2);
            display: grid; grid-template-columns: repeat(4, 1fr);
        }
        @media (max-width: 860px) { .band { grid-template-columns: repeat(2, 1fr); } }
        .stat { padding: 44px 30px; border-right: 1px solid var(--line); }
        .stat:last-child { border-right: none; }
        @media (max-width: 860px) { .stat:nth-child(2) { border-right: none; } .stat:nth-child(-n+2) { border-bottom: 1px solid var(--line); } }
        .stat .v { font-family: var(--mono); font-size: clamp(30px, 3.5vw, 44px); font-weight: 600; color: var(--amber); font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
        .stat .k { margin-top: 8px; font-size: 13px; color: var(--paper-dim); letter-spacing: .06em; }

        /* CTA */
        .cta { text-align: center; padding-block: 140px 150px; }
        .cta h2 { font-size: clamp(44px, 7.5vw, 96px); }
        .cta p { margin: 24px auto 0; max-width: 520px; color: var(--paper-dim); font-size: 18px; line-height: 1.6; }
        .cta .btn { margin-top: 44px; padding: 18px 40px; font-size: 16px; }

        footer { border-top: 1px solid var(--line); padding: 36px 0; }
        .foot { display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
        .foot, .foot a { color: var(--paper-dim); font-size: 13px; font-family: var(--mono); text-decoration: none; }
        .foot a:hover { color: var(--paper); }

        /* reveal on scroll */
        .reveal { opacity: 0; transform: translateY(26px); transition: opacity .7s ease, transform .7s ease; }
        .reveal.in { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
            .marquee-track { animation: none; }
        }
    </style>
</head>
<body>

<nav>
    <div class="wrap nav-inner">
        <a class="logo" href="/"><span class="logo-mark">A</span>Apex&nbsp;Accounting</a>
        <div class="nav-links">
            <a href="#ledger">Ledger</a>
            <a href="#compliance">BIR Compliance</a>
            <a href="#reports">Reports</a>
            <a class="btn btn-ghost" href="/admin">Sign in</a>
        </div>
    </div>
</nav>

<main>
    <header class="hero wrap">
        <div class="hero-grid">
            <div>
                <span class="kicker">Double-entry · Multi-company · Philippines</span>
                <h1>Books that <em>balance.</em><br>Always.</h1>
                <p class="hero-sub">
                    Apex is accounting software built around one unbreakable rule:
                    <strong>every debit has its credit.</strong> BIR-compliant invoices,
                    books of accounts, VAT &amp; EWT — every centavo accounted for, down to the audit trail.
                </p>
                <div class="hero-cta">
                    <a class="btn" href="/admin">Open the books →</a>
                    <a class="btn btn-ghost" href="#ledger">See how it works</a>
                </div>
            </div>
            <aside class="ledger" aria-label="Sample journal entry">
                <div class="ledger-head">
                    <span>JE-2026-000148 · POSTED</span>
                    <span class="dot">● BALANCED</span>
                </div>
                <table>
                    <tbody>
                        <tr><td class="acct">1200 · Accounts Receivable</td><td class="dr">₱112,000.00</td></tr>
                        <tr><td class="acct cr">4000 · Sales Revenue</td><td class="cr">₱100,000.00</td></tr>
                        <tr><td class="acct cr">2310 · Output VAT (12%)</td><td class="cr">₱12,000.00</td></tr>
                    </tbody>
                    <tfoot>
                        <tr><td>TOTALS</td><td>₱112,000.00 = ₱112,000.00</td></tr>
                    </tfoot>
                </table>
                <div class="balanced"><span>Dr = Cr</span><span>✓ tie-out verified</span></div>
            </aside>
        </div>
    </header>

    <div class="marquee" aria-hidden="true">
        <div class="marquee-track">
            <span>General Ledger</span><span>VAT Summary</span><span>BIR Form 2307</span><span>Trial Balance</span><span>Bank Reconciliation</span><span>Fixed Assets</span><span>AR / AP Aging</span><span>Cash Flow</span><span>Books of Accounts</span><span>Audit Trail</span>
            <span>General Ledger</span><span>VAT Summary</span><span>BIR Form 2307</span><span>Trial Balance</span><span>Bank Reconciliation</span><span>Fixed Assets</span><span>AR / AP Aging</span><span>Cash Flow</span><span>Books of Accounts</span><span>Audit Trail</span>
        </div>
    </div>

    <section id="ledger" class="wrap">
        <div class="sec-head reveal">
            <div class="sec-label">01 — The Ledger</div>
            <h2>One ledger. <em>Everything</em> posts through it.</h2>
            <p>Invoices, bills, payments, depreciation, bank charges — nothing touches your books except as a balanced journal entry. Posted documents are immutable; corrections are new entries, never edits.</p>
        </div>
        <div class="bento">
            <div class="cell c-3 cell-hero reveal">
                <span class="num">AR / AP</span>
                <h3>Invoices &amp; bills that post themselves</h3>
                <p>Line items with tax codes, income and expense accounts, and input-VAT buckets. Receive payments against invoices, pay bills with EWT defaulted from the vendor — applications tracked to the centavo.</p>
            </div>
            <div class="cell c-3 reveal">
                <span class="num">Banking</span>
                <h3>Reconciliation that actually reconciles</h3>
                <p>Deposits, transfers, and bank charges as first-class actions. Match statement lines against the ledger and close the gap to zero.</p>
            </div>
            <div class="cell c-2 cell-figures reveal">
                <span class="big" data-count="130">0</span>
                <span class="sub">automated tie-out tests guarding every posting rule</span>
            </div>
            <div class="cell c-2 reveal">
                <span class="num">Fixed Assets</span>
                <h3>Depreciation on rails</h3>
                <p>Register, place in service, run straight-line depreciation per period, dispose with VAT handled.</p>
            </div>
            <div class="cell c-2 reveal">
                <span class="num">Recurring</span>
                <h3>Set it, post it</h3>
                <p>Templates for journal entries, invoices, bills, and depreciation — run when due, with per-run reporting.</p>
            </div>
            <div class="cell c-4 reveal">
                <span class="num">Multi-company</span>
                <h3>All your entities, one login</h3>
                <p>Run separate companies with fully isolated books, roles scoped per company, and a tenant switcher to move between them. Period close is gated to approvers; locked periods are immutable.</p>
            </div>
            <div class="cell c-2 reveal">
                <span class="num">Audit</span>
                <h3>Every action, on record</h3>
                <p>A full audit log and login trail. Who posted, who closed, who signed in — answered in one screen.</p>
            </div>
        </div>
    </section>

    <div class="band reveal">
        <div class="stat"><div class="v">₱0.00</div><div class="k">rounding drift tolerated</div></div>
        <div class="stat"><div class="v" data-count="15">0</div><div class="k">reports, XLSX &amp; PDF export</div></div>
        <div class="stat"><div class="v">12%</div><div class="k">VAT computed, bucketed, summarized</div></div>
        <div class="stat"><div class="v">2×</div><div class="k">entry, always — Dr = Cr</div></div>
    </div>

    <section id="compliance" class="wrap">
        <div class="sec-head reveal">
            <div class="sec-label">02 — BIR Compliance</div>
            <h2>Built for the <em>Bureau,</em> not bolted on.</h2>
            <p>Philippine tax isn't a plugin here — it's the foundation. Output and input VAT bucketing, expanded withholding with vendor defaults, RR 7-2024 invoice layouts, and the books of accounts your examiner expects.</p>
        </div>
        <div class="forms reveal">
            <div class="form-chip"><b>RR 7-2024</b> — compliant sales invoices, printable PDF</div>
            <div class="form-chip"><b>Form 2307</b> — certificate of creditable tax withheld</div>
            <div class="form-chip"><b>VAT Summary</b> — output &amp; input, by bucket</div>
            <div class="form-chip"><b>EWT Summary</b> — by vendor, by ATC</div>
            <div class="form-chip"><b>Books of Accounts</b> — sales, purchases, receipts, disbursements</div>
        </div>
    </section>

    <section id="reports" class="wrap">
        <div class="sec-head reveal">
            <div class="sec-label">03 — Reports</div>
            <h2>From trial balance to <em>cash flow</em> in one click.</h2>
            <p>Trial Balance, P&amp;L, Balance Sheet, General Journal &amp; Ledger, AR/AP Aging, Statement of Account, Cash Flow — every report date-ranged, tied out against the ledger, and exportable to XLSX or PDF.</p>
        </div>
        <div class="bento reveal">
            <div class="cell c-2"><span class="num">Financial</span><h3>Trial Balance · P&amp;L · Balance Sheet</h3><p>The statements, straight from the ledger, with tie-out metadata on every run.</p></div>
            <div class="cell c-2"><span class="num">Operational</span><h3>Aging · Statement of Account</h3><p>Know who owes you and what you owe, bucketed by days outstanding.</p></div>
            <div class="cell c-2"><span class="num">Statutory</span><h3>Books · VAT · EWT</h3><p>The BIR set, generated from the same entries as everything else. One source of truth.</p></div>
        </div>
    </section>

    <section class="cta wrap">
        <div class="reveal">
            <h2>Close the month with <em>confidence.</em></h2>
            <p>Your ledger is already waiting. Sign in, pick a company, and post your first balanced entry in minutes.</p>
            <a class="btn" href="/admin">Open Apex Accounting →</a>
        </div>
    </section>
</main>

<footer>
    <div class="wrap foot">
        <span>© {{ date('Y') }} Apex Accounting</span>
        <span>Dr = Cr · always</span>
        <a href="/admin">Sign in →</a>
    </div>
</footer>

<script>
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));

    const cio = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (!e.isIntersecting) return;
            const el = e.target, target = +el.dataset.count, t0 = performance.now();
            const tick = (t) => {
                const p = Math.min((t - t0) / 1200, 1);
                el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
            cio.unobserve(el);
        });
    }, { threshold: 0.5 });
    document.querySelectorAll('[data-count]').forEach(el => cio.observe(el));
</script>
</body>
</html>

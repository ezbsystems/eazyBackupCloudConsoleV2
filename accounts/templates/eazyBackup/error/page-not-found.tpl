<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error</title>

    <style>
        :root {
            color-scheme: dark;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background-color: #020617; /* bg-slate-950 */
            color: #e5e7eb;            /* text-slate-200 */
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
                         sans-serif;
        }

        .page-wrap {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }

        .bg-nebula {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at top,
                    rgba(31, 41, 55, 0.5),
                    transparent 60%);
        }

        .content {
            position: relative;
            max-width: 640px;
            width: 100%;
            text-align: center;
        }

        .content h1 {
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: -0.03em;
            margin: 0 0 0.5rem;
            color: #f9fafb;
        }

        .content p.lead {
            margin: 0 0 1.5rem;
            font-size: 0.95rem;
            color: #cbd5f5;
        }

        .card {
            margin-top: 1.5rem;
            background: rgba(15, 23, 42, 0.9); /* bg-slate-900/90 */
            border-radius: 1.5rem;
            border: 1px solid rgba(148, 163, 184, 0.4); /* border-slate-400/40 */
            box-shadow: 0 18px 60px rgba(0, 0, 0, 0.75);
            padding: 1.75rem 1.75rem 1.5rem;
        }

        .card p {
            margin: 0 0 1rem;
            font-size: 0.9rem;
            color: #e5e7eb;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.55rem 1.5rem;
            border-radius: 9999px;
            border: 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #f9fafb;
            background: linear-gradient(90deg, #fe5000, #ff8a3a);
            box-shadow: 0 0 18px rgba(254, 80, 0, 0.55);
            text-decoration: none;
            cursor: pointer;
            transition:
                transform 150ms ease-out,
                box-shadow 150ms ease-out,
                filter 150ms ease-out;
        }

        .btn-primary:hover {
            transform: translateY(1px);
            box-shadow: 0 0 26px rgba(254, 80, 0, 0.75);
            filter: brightness(1.05);
        }

        .btn-primary span.icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="page-wrap">
        <div class="bg-nebula"></div>

        <div class="content">
            <!-- Page Heading -->
            <h1>Page not found</h1>
            <p class="lead">
                The page you were looking for doesn&rsquo;t exist or is no longer available.
            </p>

            <!-- Message Container -->
            <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
                <p class="mb-4">
                    If you typed the address manually, please check the URL.
                    If the problem persists, please contact support.
                </p>
                <a href="{$systemurl}" class="btn-primary">
                    <span class="icon">&laquo;</span>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>
    </div>
    </div>
</body>
</html>

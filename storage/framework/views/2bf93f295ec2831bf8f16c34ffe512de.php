<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $__env->yieldContent('title', 'Azura'); ?></title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f8fafc;
                --ink: #0f172a;
                --muted: #64748b;
                --accent: #1f2937;
                --brand: #0f172a;
                --glow-1: rgba(254, 215, 170, 0.6);
                --glow-2: rgba(186, 230, 253, 0.5);
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: "DM Serif Display", "Iowan Old Style", "Palatino Linotype", serif;
                background: var(--bg);
                color: var(--ink);
            }
            .shell {
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 48px 20px;
            }
            .card {
                position: relative;
                width: min(860px, 100%);
                overflow: hidden;
                border-radius: 28px;
                border: 1px solid rgba(148, 163, 184, 0.3);
                background: white;
                padding: clamp(28px, 4vw, 40px);
                box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
            }
            .glow {
                position: absolute;
                width: 320px;
                height: 320px;
                border-radius: 50%;
                filter: blur(60px);
                pointer-events: none;
            }
            .glow.one { top: -140px; right: -120px; background: var(--glow-1); }
            .glow.two { bottom: -180px; left: -120px; background: var(--glow-2); }
            .grid {
                position: relative;
                display: grid;
                gap: 24px;
                grid-template-columns: minmax(0, 1fr);
            }
            .eyebrow {
                font-family: "Space Grotesk", "Trebuchet MS", sans-serif;
                font-size: 0.7rem;
                letter-spacing: 0.4em;
                text-transform: uppercase;
                color: var(--muted);
            }
            .code {
                font-size: clamp(2.5rem, 4vw, 4rem);
                font-weight: 600;
                letter-spacing: -0.02em;
                margin: 8px 0 0;
            }
            .heading {
                font-size: clamp(1.5rem, 3vw, 2.2rem);
                margin: 10px 0 0;
            }
            .copy {
                font-family: "Space Grotesk", "Trebuchet MS", sans-serif;
                color: var(--muted);
                line-height: 1.6;
                font-size: 0.95rem;
                max-width: 520px;
            }
            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 18px;
            }
            .btn {
                font-family: "Space Grotesk", "Trebuchet MS", sans-serif;
                text-decoration: none;
                padding: 10px 18px;
                border-radius: 999px;
                border: 1px solid rgba(148, 163, 184, 0.4);
                color: var(--ink);
                font-weight: 600;
                font-size: 0.85rem;
            }
            .btn.primary {
                background: var(--brand);
                color: white;
                border-color: var(--brand);
            }
            .footer {
                margin-top: 24px;
                font-size: 0.75rem;
                font-family: "Space Grotesk", "Trebuchet MS", sans-serif;
                color: var(--muted);
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <div class="card">
                <div class="glow one"></div>
                <div class="glow two"></div>
                <div class="grid">
                    <div>
                        <div class="eyebrow"><?php echo $__env->yieldContent('eyebrow', 'Azura'); ?></div>
                        <div class="code"><?php echo $__env->yieldContent('code', 'Error'); ?></div>
                        <div class="heading"><?php echo $__env->yieldContent('heading', 'Something went off-course'); ?></div>
                        <p class="copy"><?php echo $__env->yieldContent('message', 'We could not complete that request.'); ?></p>
                        <div class="actions">
                            <a class="btn primary" href="/">Back to home</a>
                            <a class="btn" href="/support">Contact support</a>
                        </div>
                        <div class="footer">
                            Azura keeps your catalog ready and your orders traceable.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
<?php /**PATH E:\laragon\www\dropshipping\resources\views/errors/layout.blade.php ENDPATH**/ ?>
<?php

/**
 * Fallback error page — PURE native PHP, TANPA TemplateEngine.
 * Dipakai kalau TemplateEngine gagal compile/render, atau user belum
 * bikin view custom di resources/views/errors/.
 *
 * @var int         $status
 * @var string      $message
 * @var array|null  $debug   ['exception' => string, 'file' => string, 'line' => int, 'trace' => string]
 */

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$statusMap = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    409 => 'Conflict',
    419 => 'Page Expired',
    422 => 'Unprocessable Entity',
    429 => 'Too Many Requests',
    500 => 'Internal Server Error',
    503 => 'Service Unavailable',
];

$statusText = $statusMap[$status] ?? 'Unexpected Error';

if (in_array($status, [401, 403, 419, 429], true)) {
    $accent = '#F2B84B';
    $accentDim = 'rgba(242, 184, 75, 0.12)';
} elseif ($status === 404) {
    $accent = '#4FD1C5';
    $accentDim = 'rgba(79, 209, 197, 0.12)';
} elseif ($status >= 500) {
    $accent = '#F2545B';
    $accentDim = 'rgba(242, 84, 91, 0.12)';
} else {
    $accent = '#8B9CFF';
    $accentDim = 'rgba(139, 156, 255, 0.12)';
}

$totalLines = 26;
$highlightLine = ($status % 18) + 4;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($status) ?> — <?= e($statusText) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0A0C10;
            --surface: #10131A;
            --surface-2: #151922;
            --line: #1E222B;
            --text: #E4E7EC;
            --text-dim: #6E7681;
            --text-faint: #3A3F4A;
            --accent: <?= $accent ?>;
            --accent-dim: <?= $accentDim ?>;
            --mono: 'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
            --sans: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        ::-webkit-scrollbar {
            display: none;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            overflow-x: hidden;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .tabbar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
            flex-shrink: 0;
        }

        .dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 8px var(--accent);
            flex-shrink: 0;
        }

        .tab-filename {
            font-family: var(--mono);
            font-size: 12.5px;
            color: var(--text-dim);
            letter-spacing: 0.2px;
        }

        .tab-filename b {
            color: var(--text);
            font-weight: 500;
        }

        .tabbar-status {
            margin-left: auto;
            font-family: var(--mono);
            font-size: 11px;
            color: var(--accent);
            background: var(--accent-dim);
            padding: 3px 9px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        .editor {
            display: flex;
            flex: 1;
            position: relative;
        }

        .gutter {
            width: 56px;
            flex-shrink: 0;
            background: var(--surface);
            border-right: 1px solid var(--line);
            padding-top: 28px;
            display: none;
        }

        @media (min-width: 720px) {
            .gutter {
                display: block;
            }
        }

        .gutter-line {
            font-family: var(--mono);
            font-size: 12px;
            color: var(--text-faint);
            text-align: right;
            padding-right: 16px;
            line-height: 30px;
        }

        .gutter-line.active {
            color: var(--accent);
            font-weight: 700;
        }

        .content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            position: relative;
        }

        .content::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 0%, var(--accent-dim) 50%, transparent 100%);
            height: 140px;
            opacity: 0.5;
            animation: sweep 5s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes sweep {

            0%,
            100% {
                transform: translateY(-20vh);
                opacity: 0;
            }

            50% {
                transform: translateY(60vh);
                opacity: 0.5;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .content::before {
                animation: none;
                display: none;
            }

            .cursor {
                animation: none !important;
                opacity: 1;
            }
        }

        .panel {
            max-width: 620px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            font-family: var(--mono);
            font-size: 12.5px;
            color: var(--accent);
            letter-spacing: 1px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .eyebrow::before {
            content: '//';
            color: var(--text-faint);
        }

        .code {
            font-family: var(--mono);
            font-weight: 700;
            font-size: clamp(64px, 14vw, 108px);
            line-height: 1;
            color: var(--text);
            letter-spacing: -3px;
            margin-bottom: 4px;
        }

        .status-text {
            font-family: var(--mono);
            font-size: 15px;
            color: var(--accent);
            margin-bottom: 24px;
            font-weight: 500;
        }

        .message {
            font-size: 16px;
            line-height: 1.65;
            color: #B4BAC4;
            max-width: 480px;
            margin-bottom: 32px;
        }

        .message .cursor {
            display: inline-block;
            width: 8px;
            height: 17px;
            background: var(--accent);
            vertical-align: middle;
            margin-left: 4px;
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {
            50% {
                opacity: 0;
            }
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            font-family: var(--sans);
            font-size: 14px;
            font-weight: 500;
            padding: 11px 20px;
            border-radius: 7px;
            text-decoration: none;
            border: 1px solid var(--line);
            transition: border-color 0.15s, background 0.15s;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--bg);
            border-color: var(--accent);
        }

        .btn-primary:hover {
            filter: brightness(1.1);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-dim);
        }

        .btn-ghost:hover {
            border-color: var(--text-dim);
            color: var(--text);
        }

        .debug {
            margin-top: 36px;
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            background: var(--surface-2);
        }

        .debug summary {
            font-family: var(--mono);
            font-size: 12.5px;
            color: var(--text-dim);
            padding: 12px 16px;
            cursor: pointer;
            list-style: none;
            display: flex;
            align-items: center;
            gap: 8px;
            user-select: none;
        }

        .debug summary::-webkit-details-marker {
            display: none;
        }

        .debug summary::before {
            content: '▸';
            color: var(--accent);
            transition: transform 0.15s;
        }

        .debug[open] summary::before {
            transform: rotate(90deg);
        }

        .debug-body {
            padding: 4px 16px 16px;
            border-top: 1px solid var(--line);
        }

        .debug-row {
            font-family: var(--mono);
            font-size: 12px;
            color: var(--text-dim);
            padding: 8px 0;
            border-bottom: 1px dashed var(--line);
        }

        .debug-row:last-child {
            border-bottom: none;
        }

        .debug-row b {
            color: var(--text);
            font-weight: 500;
        }

        .trace {
            font-family: var(--mono);
            font-size: 11.5px;
            line-height: 1.7;
            color: var(--text-faint);
            white-space: pre-wrap;
            word-break: break-all;
            margin-top: 10px;
            max-height: 260px;
            overflow-y: auto;
        }

        footer {
            text-align: center;
            padding: 18px;
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text-faint);
            border-top: 1px solid var(--line);
        }
    </style>
</head>

<body>

    <div class="tabbar">
        <span class="dot"></span>
        <span class="tab-filename">core/exceptions/<b>error.php</b></span>
        <span class="tabbar-status"><?= e($status) ?> <?= e(strtoupper($statusText)) ?></span>
    </div>

    <div class="editor">
        <div class="gutter">
            <?php for ($i = 1; $i <= $totalLines; $i++): ?>
                <div class="gutter-line <?= $i === $highlightLine ? 'active' : '' ?>"><?= $i ?></div>
            <?php endfor; ?>
        </div>

        <div class="content">
            <div class="panel">
                <div class="eyebrow">runtime exception caught</div>
                <div class="code"><?= e($status) ?></div>
                <div class="status-text"><?= e($statusText) ?></div>

                <p class="message">
                    <?= e($message) ?><span class="cursor"></span>
                </p>

                <div class="actions">
                    <a href="/" class="btn btn-primary">Kembali ke beranda</a>
                    <button onclick="history.back()" class="btn btn-ghost">Halaman sebelumnya</button>
                </div>

                <?php if ($debug !== null): ?>
                    <details class="debug" open>
                        <summary>Detail exception (mode debug aktif)</summary>
                        <div class="debug-body">
                            <div class="debug-row"><b>Exception:</b> <?= e($debug['exception']) ?></div>
                            <div class="debug-row"><b>File:</b> <?= e($debug['file']) ?>:<?= e($debug['line']) ?></div>
                            <div class="trace"><?= e($debug['trace']) ?></div>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer><?= jtech_env('APP_NAME') ?? "Novalites " ?> — <?= e(date('Y-m-d H:i:s')) ?></footer>

</body>

</html>
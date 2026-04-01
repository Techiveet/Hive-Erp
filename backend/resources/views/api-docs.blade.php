<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} API Command Deck</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
    <style>
        :root {
            --bg-1: #04111f;
            --bg-2: #0b1f33;
            --panel: rgba(9, 20, 35, 0.86);
            --panel-soft: rgba(17, 31, 51, 0.76);
            --line: rgba(125, 211, 252, 0.18);
            --line-strong: rgba(134, 239, 172, 0.28);
            --text: #f4fbff;
            --muted: #9ec9d3;
            --cyan: #7dd3fc;
            --green: #86efac;
            --gold: #fde047;
            --shadow: 0 30px 80px rgba(2, 8, 23, 0.45);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Space Grotesk", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 10% 10%, rgba(125, 211, 252, 0.18), transparent 28%),
                radial-gradient(circle at 90% 10%, rgba(134, 239, 172, 0.12), transparent 22%),
                linear-gradient(155deg, var(--bg-1) 0%, #071522 40%, var(--bg-2) 100%);
        }

        .shell {
            width: min(1440px, calc(100% - 28px));
            margin: 0 auto;
            padding: 22px 0 42px;
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 28px;
            border-radius: 30px;
            border: 1px solid var(--line);
            background: linear-gradient(145deg, rgba(8, 19, 35, 0.96), rgba(11, 31, 51, 0.82));
            box-shadow: var(--shadow);
        }

        .hero::before,
        .hero::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .hero::before {
            inset: -60px auto auto -60px;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(125, 211, 252, 0.24), transparent 68%);
        }

        .hero::after {
            inset: auto -80px -100px auto;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(253, 224, 71, 0.18), transparent 68%);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid rgba(125, 211, 252, 0.24);
            background: rgba(125, 211, 252, 0.08);
            font-size: 12px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 12px;
            max-width: 820px;
            font-size: clamp(2.4rem, 5vw, 4.9rem);
            line-height: 0.96;
        }

        .hero p {
            max-width: 820px;
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.8;
        }

        .hero-grid,
        .control-grid {
            display: grid;
            gap: 16px;
        }

        .hero-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 26px;
        }

        .control-grid {
            grid-template-columns: 1.3fr 1fr;
            margin-top: 22px;
        }

        .panel {
            border-radius: 24px;
            border: 1px solid var(--line);
            background: var(--panel);
            padding: 20px;
        }

        .panel h2,
        .panel h3 {
            margin: 0 0 10px;
        }

        .panel p,
        .panel li,
        .hint {
            color: var(--muted);
            line-height: 1.7;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .stat,
        .callout {
            border-radius: 18px;
            border: 1px solid rgba(158, 201, 211, 0.12);
            background: var(--panel-soft);
            padding: 16px;
        }

        .stat strong,
        .callout strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.95rem;
        }

        .controls {
            display: grid;
            gap: 14px;
        }

        .mode-row,
        .button-row,
        .link-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .mode-btn,
        .action-btn,
        .link-btn {
            appearance: none;
            border: 1px solid rgba(125, 211, 252, 0.18);
            background: rgba(125, 211, 252, 0.08);
            color: var(--text);
            border-radius: 14px;
            padding: 11px 14px;
            font-family: inherit;
            cursor: pointer;
            transition: 180ms ease;
            text-decoration: none;
        }

        .mode-btn.active,
        .action-btn.primary {
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.92), rgba(22, 163, 74, 0.86));
            border-color: transparent;
        }

        .action-btn.secondary {
            background: rgba(134, 239, 172, 0.1);
            border-color: rgba(134, 239, 172, 0.22);
        }

        .mode-btn:hover,
        .action-btn:hover,
        .link-btn:hover {
            transform: translateY(-1px);
            border-color: var(--line-strong);
        }

        label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(158, 201, 211, 0.16);
            background: rgba(5, 15, 26, 0.7);
            color: var(--text);
            padding: 12px 14px;
            font: inherit;
        }

        input::placeholder {
            color: rgba(158, 201, 211, 0.55);
        }

        .swagger-wrap {
            margin-top: 22px;
            border-radius: 28px;
            border: 1px solid rgba(158, 201, 211, 0.16);
            background: rgba(246, 252, 255, 0.97);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .swagger-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(14, 165, 233, 0.1);
            background: linear-gradient(180deg, #f8fbff 0%, #eff8ff 100%);
            color: #0f172a;
        }

        .swagger-toolbar code {
            font-family: "IBM Plex Mono", monospace;
            font-size: 0.82rem;
        }

        .swagger-ui .topbar { display: none; }
        .swagger-ui { font-family: "Space Grotesk", sans-serif; }
        .swagger-ui .scheme-container {
            background: linear-gradient(180deg, #f8fbff 0%, #eef8ff 100%);
            border: 1px solid rgba(14, 165, 233, 0.12);
            border-radius: 18px;
            box-shadow: none;
            padding: 18px;
        }
        .swagger-ui .opblock {
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }
        .swagger-ui .opblock .opblock-summary-method {
            border-radius: 12px;
            font-family: "IBM Plex Mono", monospace;
            font-weight: 500;
        }
        .swagger-ui .btn.execute {
            background: linear-gradient(135deg, #0284c7, #16a34a);
            border-color: transparent;
        }

        @media (max-width: 960px) {
            .hero-grid,
            .control-grid,
            .stats { grid-template-columns: 1fr; }
            .shell { width: min(100% - 18px, 1440px); padding-top: 16px; }
            .hero, .panel, .swagger-wrap { border-radius: 22px; }
            .swagger-toolbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div class="eyebrow">Interactive Command Deck</div>
            <h1>{{ $appName }} API Mission Control</h1>
            <p>
                Explore every registered API route, switch between central and tenant testing modes, inject your bearer token,
                and send live requests from one place. Use this page directly at <code>/api/docs</code> or open the frontend dashboard experience for session-aware defaults.
            </p>

            <div class="hero-grid">
                <div class="callout">
                    <strong>Central mode</strong>
                    <span class="hint">Use for shared platform endpoints. Leave <code>X-Tenant</code> empty unless you intentionally want tenant-scoped behavior on shared routes.</span>
                </div>
                <div class="callout">
                    <strong>Tenant mode</strong>
                    <span class="hint">Use tenant aliases like <code>/v1/tenant/*</code> or set the <code>X-Tenant</code> header for shared tenant endpoints.</span>
                </div>
                <div class="callout">
                    <strong>Authorization</strong>
                    <span class="hint">Paste the token returned by login. This page automatically adds <code>Authorization: Bearer ...</code> to outgoing requests.</span>
                </div>
            </div>

            <div class="control-grid">
                <div class="panel">
                    <h2>Request Controls</h2>
                    <p>These controls apply to every Try it out request in Swagger UI below.</p>

                    <div class="controls">
                        <div>
                            <label>Request Mode</label>
                            <div class="mode-row">
                                <button class="mode-btn active" type="button" data-mode="central">Central</button>
                                <button class="mode-btn" type="button" data-mode="tenant">Tenant</button>
                            </div>
                        </div>

                        <div>
                            <label for="token-input">Bearer Token</label>
                            <input id="token-input" type="password" placeholder="Paste Sanctum token here" autocomplete="off">
                        </div>

                        <div>
                            <label for="tenant-input">Tenant Header</label>
                            <input id="tenant-input" type="text" placeholder="tenantapple">
                        </div>

                        <div class="button-row">
                            <button id="save-auth" class="action-btn primary" type="button">Apply To Requests</button>
                            <button id="clear-auth" class="action-btn secondary" type="button">Clear</button>
                        </div>

                        <div class="hint" id="status-copy">Central mode active. Requests will target {{ $apiRoot }}/v1...</div>
                    </div>
                </div>

                <div class="panel">
                    <h3>Quick Access</h3>
                    <div class="stats">
                        <div class="stat">
                            <strong>Docs UI</strong>
                            <span class="hint">Direct backend view at <code>/api/docs</code> or legacy <code>/api-docs</code>.</span>
                        </div>
                        <div class="stat">
                            <strong>OpenAPI JSON</strong>
                            <span class="hint">Machine-readable spec at <code>{{ $specUrl }}</code>.</span>
                        </div>
                        <div class="stat">
                            <strong>Frontend Docs</strong>
                            <span class="hint">Dashboard page with session-aware defaults and quick links.</span>
                        </div>
                    </div>

                    <div class="link-row" style="margin-top: 16px;">
                        <a class="link-btn" href="{{ $specUrl }}" target="_blank" rel="noreferrer">Open Raw JSON</a>
                        @if ($frontendDocsUrl)
                            <a class="link-btn" href="{{ $frontendDocsUrl }}" target="_blank" rel="noreferrer">Open Frontend Docs</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="swagger-wrap">
            <div class="swagger-toolbar">
                <div>
                    <strong>Live Base</strong>
                    <code id="base-url">{{ $apiRoot }}</code>
                </div>
                <div class="hint" style="color:#334155;">Swagger “Authorize” is optional here because this page injects your token automatically.</div>
            </div>
            <div id="swagger-ui"></div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        const STORAGE_KEYS = {
            mode: 'hive_api_docs_mode',
            token: 'hive_api_docs_token',
            tenant: 'hive_api_docs_tenant',
        };

        const state = {
            mode: localStorage.getItem(STORAGE_KEYS.mode) || 'central',
            token: localStorage.getItem(STORAGE_KEYS.token) || '',
            tenant: localStorage.getItem(STORAGE_KEYS.tenant) || '',
        };

        const tokenInput = document.getElementById('token-input');
        const tenantInput = document.getElementById('tenant-input');
        const saveAuthButton = document.getElementById('save-auth');
        const clearAuthButton = document.getElementById('clear-auth');
        const statusCopy = document.getElementById('status-copy');
        const baseUrl = document.getElementById('base-url');
        const modeButtons = Array.from(document.querySelectorAll('[data-mode]'));

        tokenInput.value = state.token;
        tenantInput.value = state.tenant;

        const syncModeUi = () => {
            modeButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.mode === state.mode);
            });

            const tenantHint = state.mode === 'tenant'
                ? `Tenant mode active. Shared routes will send X-Tenant=${state.tenant || '[empty]'}. You can also use /v1/tenant/* aliases.`
                : 'Central mode active. Requests will target {{ $apiRoot }}/v1...';

            statusCopy.textContent = tenantHint;
            baseUrl.textContent = '{{ $apiRoot }}';
        };

        const applyStorage = () => {
            localStorage.setItem(STORAGE_KEYS.mode, state.mode);
            localStorage.setItem(STORAGE_KEYS.token, state.token);
            localStorage.setItem(STORAGE_KEYS.tenant, state.tenant);
            syncModeUi();
        };

        modeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                state.mode = button.dataset.mode;
                applyStorage();
            });
        });

        saveAuthButton.addEventListener('click', () => {
            state.token = tokenInput.value.trim();
            state.tenant = tenantInput.value.trim();
            applyStorage();
        });

        clearAuthButton.addEventListener('click', () => {
            state.token = '';
            state.tenant = '';
            tokenInput.value = '';
            tenantInput.value = '';
            applyStorage();
        });

        syncModeUi();

        window.onload = async () => {
            const response = await fetch(@json($specUrl));
            const spec = await response.json();

            window.ui = SwaggerUIBundle({
                spec,
                dom_id: '#swagger-ui',
                deepLinking: true,
                docExpansion: 'list',
                displayRequestDuration: true,
                filter: true,
                persistAuthorization: true,
                tryItOutEnabled: true,
                requestInterceptor: (request) => {
                    request.headers = request.headers || {};

                    const token = localStorage.getItem(STORAGE_KEYS.token) || '';
                    const tenant = localStorage.getItem(STORAGE_KEYS.tenant) || '';
                    const mode = localStorage.getItem(STORAGE_KEYS.mode) || 'central';

                    if (token) {
                        request.headers.Authorization = `Bearer ${token}`;
                    }

                    if (mode === 'tenant' && tenant) {
                        request.headers['X-Tenant'] = tenant;
                    } else {
                        delete request.headers['X-Tenant'];
                    }

                    return request;
                },
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset,
                ],
                layout: 'BaseLayout',
            });
        };
    </script>
</body>
</html>

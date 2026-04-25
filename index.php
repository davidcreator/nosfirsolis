<?php

declare(strict_types=1);

define('DIR_ROOT', __DIR__);

$rootConfig = require DIR_ROOT . '/config.php';
$installed = is_array($rootConfig) && !empty($rootConfig['app']['installed']);
$appName = is_array($rootConfig) && isset($rootConfig['app']['name'])
    ? (string) $rootConfig['app']['name']
    : 'Solis';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$base = rtrim($scheme . '://' . $host . $scriptDir, '/');
$basePath = $scriptDir === '/' ? '' : $scriptDir;

if (!$installed) {
    header('Location: ' . $base . '/install');
    exit;
}

require_once DIR_ROOT . '/system/Engine/Startup.php';

$sessionName = is_array($rootConfig) && isset($rootConfig['app']['session_name'])
    ? (string) $rootConfig['app']['session_name']
    : 'nsplanner_session';
$sessionPath = DIR_SYSTEM . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . 'sessions';
new \System\Engine\Session($sessionName, $sessionPath);

$loginAction = $basePath . '/client/auth/authenticate';
$clientAreaUrl = $basePath . '/client';
$messageSuccess = flash('success');
$messageError = flash('error');

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> | Plataforma de planejamento de conteúdo</title>
    <style>
        :root {
            --bg-cream: #f5efe4;
            --bg-ink: #101820;
            --bg-deep: #1a2d3a;
            --bg-card: #fdfaf3;
            --line: #dbcdb3;
            --accent: #d87c34;
            --accent-strong: #b85f1f;
            --text-primary: #14212c;
            --text-muted: #51616f;
            --ok-bg: #e3f6e9;
            --ok-text: #1f6a35;
            --err-bg: #fde8e5;
            --err-text: #8f2f22;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI Variable", "Trebuchet MS", "Candara", sans-serif;
            background:
                radial-gradient(circle at 12% 12%, rgba(216, 124, 52, 0.25), transparent 38%),
                radial-gradient(circle at 88% 18%, rgba(16, 24, 32, 0.2), transparent 34%),
                linear-gradient(140deg, var(--bg-cream), #efe2cb 58%, #ead8bc 100%);
            color: var(--text-primary);
        }

        .shell {
            width: min(1120px, 92vw);
            margin: 34px auto 40px;
            display: grid;
            grid-template-columns: 1.25fr 0.95fr;
            gap: 26px;
        }

        .panel {
            background: rgba(253, 250, 243, 0.92);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(16, 24, 32, 0.12);
            padding: 30px;
            backdrop-filter: blur(2px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid #cab899;
            color: #50361d;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 12px;
        }

        h1 {
            margin: 18px 0 12px;
            font-size: clamp(28px, 4.3vw, 46px);
            line-height: 1.08;
            letter-spacing: -0.02em;
        }

        .lead {
            margin: 0 0 22px;
            color: var(--text-muted);
            font-size: clamp(15px, 2.2vw, 19px);
            line-height: 1.6;
            max-width: 62ch;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .feature {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 15px;
            background: #fffdf8;
        }

        .feature strong {
            display: block;
            margin-bottom: 6px;
            font-size: 15px;
        }

        .feature p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.45;
        }

        .login-card {
            background: linear-gradient(165deg, var(--bg-ink), var(--bg-deep));
            border-radius: 24px;
            padding: 28px;
            color: #f8f1e6;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .login-card h2 {
            margin: 0 0 8px;
            font-size: 27px;
            line-height: 1.1;
            letter-spacing: -0.01em;
        }

        .login-card p {
            margin: 0 0 20px;
            color: #cad4df;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
            font-size: 14px;
            line-height: 1.4;
            border: 1px solid transparent;
        }

        .alert-ok {
            background: var(--ok-bg);
            color: var(--ok-text);
            border-color: #c8ead3;
        }

        .alert-error {
            background: var(--err-bg);
            color: var(--err-text);
            border-color: #f4c7c0;
        }

        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 700;
            color: #f7e8d5;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .field input {
            width: 100%;
            border: 1px solid #385165;
            background: #112535;
            color: #f8f4ed;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .field input:focus {
            border-color: #e6a56d;
            box-shadow: 0 0 0 3px rgba(216, 124, 52, 0.18);
        }

        .cta {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 13px 16px;
            margin-top: 8px;
            background: linear-gradient(90deg, var(--accent), #e39a58);
            color: #22150a;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.12s ease, background 0.2s ease;
        }

        .cta:hover {
            transform: translateY(-1px);
            background: linear-gradient(90deg, var(--accent-strong), var(--accent));
        }

        .note {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed rgba(233, 208, 178, 0.45);
            color: #d6e0ea;
            font-size: 13px;
            line-height: 1.5;
        }

        .alt-link {
            display: inline-flex;
            margin-top: 12px;
            color: #ffe1be;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 225, 190, 0.4);
        }

        .alt-link:hover {
            border-bottom-color: #ffe1be;
        }

        @media (max-width: 980px) {
            .shell {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .panel {
                padding: 22px;
                border-radius: 18px;
            }

            .login-card {
                border-radius: 18px;
                padding: 22px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="panel">
            <span class="brand"><?= e($appName) ?> Platform</span>
            <h1>Planejamento estratégico, execução diária e visão clara da sua operação de conteúdo.</h1>
            <p class="lead">
                O Solis organiza campanhas, calendários e distribuição em um único fluxo.
                Sua equipe acompanha o que precisa ser feito, quando publicar e como medir resultado.
            </p>

            <div class="grid">
                <article class="feature">
                    <strong>Calendário inteligente</strong>
                    <p>Planeje ano, mês e período com contexto de campanhas e datas importantes.</p>
                </article>
                <article class="feature">
                    <strong>Operação em equipe</strong>
                    <p>Centralize status, revisões e prioridades para reduzir retrabalho e atrasos.</p>
                </article>
                <article class="feature">
                    <strong>Publicação social</strong>
                    <p>Prepare drafts e formatos por plataforma com consistência de marca.</p>
                </article>
                <article class="feature">
                    <strong>Tracking de campanha</strong>
                    <p>Use links rastreáveis para entender cliques e ajustar sua estratégia.</p>
                </article>
            </div>
        </section>

        <aside class="login-card">
            <h2>Acesso do cliente</h2>
            <p>Entre com seu usuário para abrir o painel de trabalho.</p>

            <?php if (is_string($messageSuccess) && $messageSuccess !== ''): ?>
                <div class="alert alert-ok"><?= e($messageSuccess) ?></div>
            <?php endif; ?>

            <?php if (is_string($messageError) && $messageError !== ''): ?>
                <div class="alert alert-error"><?= e($messageError) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e($loginAction) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="landing">
                <div class="field">
                    <label for="email">E-mail</label>
                    <input id="email" type="email" name="email" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="password">Senha</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="cta">Entrar no Solis</button>
            </form>

            <a class="alt-link" href="<?= e($clientAreaUrl) ?>">Abrir área do cliente</a>
            <p class="note">
                O acesso administrativo não fica exposto nesta página pública.
                A administração deve ser acessada apenas pela URL dedicada.
            </p>
        </aside>
    </main>
</body>
</html>


<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/csrf.php';

if (
    !isset($_SESSION['admin_logado'])
    || $_SESSION['admin_logado'] !== true
    || !in_array($_SESSION['admin_role'] ?? '', ['superadmin', 'desenvolvedor'], true)
) {
    header('Location: /admin');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$defaultBackgroundColor = '#FAF8F4';
$saveError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_from_post()) {
        $saveError = 'A sessão expirou. Atualiza a página e tenta novamente.';
    } elseif (($_POST['action'] ?? '') === 'reset') {
        setLojaConfig('site_background_color', $defaultBackgroundColor);
        setLojaConfig('site_accent_color', 'AUTO');
        header('Location: /admin/paleta_cores.php?guardado=1');
        exit;
    } else {
        $submittedColor = strtoupper(trim((string) ($_POST['site_background_color'] ?? '')));
        $accentAuto = isset($_POST['accent_auto']);
        $submittedAccent = $accentAuto ? 'AUTO' : strtoupper(trim((string) ($_POST['site_accent_color'] ?? '')));

        if (!preg_match('/^#[0-9A-F]{6}$/', $submittedColor)) {
            $saveError = 'Introduz uma cor de fundo hexadecimal válida, por exemplo #FAF8F4.';
        } elseif ($submittedAccent !== 'AUTO' && !preg_match('/^#[0-9A-F]{6}$/', $submittedAccent)) {
            $saveError = 'Introduz uma cor de destaque hexadecimal válida.';
        } else {
            setLojaConfig('site_background_color', $submittedColor);
            setLojaConfig('site_accent_color', $submittedAccent);
            header('Location: /admin/paleta_cores.php?guardado=1');
            exit;
        }
    }
}

$currentBackgroundColor = strtoupper((string) getLojaConfig('site_background_color', $defaultBackgroundColor));
if (!preg_match('/^#[0-9A-F]{6}$/', $currentBackgroundColor)) {
    $currentBackgroundColor = $defaultBackgroundColor;
}
$currentAccentSetting = strtoupper((string) getLojaConfig('site_accent_color', 'AUTO'));
$accentIsCustom = (bool) preg_match('/^#[0-9A-F]{6}$/', $currentAccentSetting);

$titulo_pagina = 'Paleta de Cores';
include __DIR__ . '/../templates/header.php';

// Accent auto-derivado a partir do fundo guardado (mostrado no seletor em modo automático)
$autoAccentPreview = '#B08968';
if (function_exists('site_derive_accent')) {
    [$autoAccentPreview] = site_derive_accent($currentBackgroundColor, $siteBackgroundIsDark ?? false);
}
$accentFieldValue = $accentIsCustom ? $currentAccentSetting : $autoAccentPreview;
?>

<main class="dashboard-container palette-page animate-entry">
    <div class="admin-page-header">
        <div class="header-title-container">
            <?php renderBackButton('/admin', 'Voltar ao Painel'); ?>
            <h2>Paleta de Cores</h2>
        </div>
    </div>

    <?php if (isset($_GET['guardado'])): ?>
        <div class="palette-alert palette-alert-success" role="status">Cor de fundo guardada com sucesso.</div>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
        <div class="palette-alert palette-alert-error" role="alert"><?php echo htmlspecialchars($saveError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="palette-layout">
        <form method="post" class="palette-card" id="palette-form">
            <?php echo csrf_input(); ?>

            <div class="palette-card-heading">
                <div>
                    <span class="palette-eyebrow">Cor global</span>
                    <h3>Fundo das páginas</h3>
                </div>
            </div>

            <p class="palette-description">
                Define o fundo do corpo de todas as páginas, incluindo a página inicial e a onda superior do footer.
            </p>

            <div class="palette-fields">
                <label class="palette-picker-label" for="site-background-picker">
                    <span>Selecionar cor</span>
                    <input
                        type="color"
                        id="site-background-picker"
                        value="<?php echo htmlspecialchars($currentBackgroundColor, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="Selecionar cor de fundo"
                    >
                </label>

                <label class="palette-hex-label" for="site-background-color">
                    <span>Valor hexadecimal</span>
                    <input
                        type="text"
                        id="site-background-color"
                        name="site_background_color"
                        value="<?php echo htmlspecialchars($currentBackgroundColor, ENT_QUOTES, 'UTF-8'); ?>"
                        maxlength="7"
                        pattern="^#[0-9A-Fa-f]{6}$"
                        placeholder="#FAF8F4"
                        spellcheck="false"
                        required
                    >
                </label>
            </div>

            <div class="palette-card-heading palette-accent-heading">
                <div>
                    <span class="palette-eyebrow">Cor de destaque</span>
                    <h3>Accent — kickers, ícones, botões</h3>
                </div>
            </div>

            <p class="palette-description">
                Por defeito é derivada automaticamente do fundo, para combinar sempre. Desliga o automático para escolher uma cor de marca fixa.
            </p>

            <label class="palette-toggle">
                <input type="checkbox" id="accent-auto" name="accent_auto" <?php echo $accentIsCustom ? '' : 'checked'; ?>>
                <span>Derivar automaticamente do fundo</span>
            </label>

            <div class="palette-fields" id="accent-fields">
                <label class="palette-picker-label" for="site-accent-picker">
                    <span>Selecionar cor</span>
                    <input
                        type="color"
                        id="site-accent-picker"
                        value="<?php echo htmlspecialchars($accentFieldValue, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="Selecionar cor de destaque"
                    >
                </label>

                <label class="palette-hex-label" for="site-accent-color">
                    <span>Valor hexadecimal</span>
                    <input
                        type="text"
                        id="site-accent-color"
                        name="site_accent_color"
                        value="<?php echo htmlspecialchars($accentFieldValue, ENT_QUOTES, 'UTF-8'); ?>"
                        maxlength="7"
                        pattern="^#[0-9A-Fa-f]{6}$"
                        placeholder="#B08968"
                        spellcheck="false"
                    >
                </label>
            </div>

            <div class="palette-actions">
                <button type="submit" class="button btn-admin-primary">Guardar cor</button>
                <button type="submit" name="action" value="reset" class="button">Repor bege original</button>
            </div>
        </form>

        <section class="palette-preview-card" aria-labelledby="palette-preview-title">
            <div class="palette-preview-heading">
                <span class="palette-eyebrow">Pré-visualização</span>
                <h3 id="palette-preview-title">Aplicação no site</h3>
            </div>

            <div class="palette-preview" id="palette-preview">
                <div class="palette-preview-header"></div>
                <div class="palette-preview-content">
                    <div class="palette-preview-title"></div>
                    <div class="palette-preview-line palette-preview-line-long"></div>
                    <div class="palette-preview-line"></div>
                    <div class="palette-preview-cards">
                        <span></span><span></span><span></span>
                    </div>
                </div>
                <div class="palette-preview-footer"></div>
            </div>

            <div class="palette-value-row">
                <span>Cor selecionada</span>
                <strong id="palette-preview-value"><?php echo htmlspecialchars($currentBackgroundColor, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </section>
    </div>
</main>

<script>
(function () {
    const picker = document.getElementById('site-background-picker');
    const hexInput = document.getElementById('site-background-color');
    const preview = document.getElementById('palette-preview');
    const swatch = document.getElementById('palette-current-swatch');
    const valueLabel = document.getElementById('palette-preview-value');
    const originalColor = getComputedStyle(document.documentElement)
        .getPropertyValue('--cor-fundo-pagina')
        .trim() || '#FAF8F4';

    function normalizeHex(value) {
        const normalized = value.trim().toUpperCase();
        return /^#[0-9A-F]{6}$/.test(normalized) ? normalized : null;
    }

    function previewColor(color) {
        const rgb = [
            parseInt(color.slice(1, 3), 16) / 255,
            parseInt(color.slice(3, 5), 16) / 255,
            parseInt(color.slice(5, 7), 16) / 255
        ];
        const linear = rgb.map(function (channel) {
            return channel <= 0.03928
                ? channel / 12.92
                : Math.pow((channel + 0.055) / 1.055, 2.4);
        });
        const luminance = (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
        const isDark = luminance < 0.38;

        preview.style.setProperty('--palette-preview-bg', color);
        if (swatch) swatch.style.backgroundColor = color;
        valueLabel.textContent = color;
        document.documentElement.style.setProperty('--cor-fundo-pagina', color);
        document.documentElement.style.setProperty('--cor-texto-pagina', isDark ? '#F8FAFC' : '#1C1C1C');
        document.documentElement.style.setProperty('--cor-texto-pagina-suave', isDark ? '#CBD5E1' : '#64748B');
        document.documentElement.style.setProperty('--cor-divisor-pagina', isDark ? 'rgba(255,255,255,.24)' : 'rgba(28,28,28,.15)');
        document.documentElement.style.setProperty('--cor-divisor-pagina-forte', isDark ? 'rgba(255,255,255,.38)' : 'rgba(28,28,28,.24)');
        document.body.classList.toggle('site-bg-dark', isDark);
        document.body.classList.toggle('site-bg-light', !isDark);
    }

    picker.addEventListener('input', function () {
        const color = this.value.toUpperCase();
        hexInput.value = color;
        previewColor(color);
    });

    hexInput.addEventListener('input', function () {
        const color = normalizeHex(this.value);
        if (!color) return;
        picker.value = color;
        previewColor(color);
    });

    document.getElementById('palette-form').addEventListener('reset', function () {
        previewColor(originalColor);
    });

    window.addEventListener('beforeunload', function () {
        document.documentElement.style.setProperty('--cor-fundo-pagina', originalColor);
    });

    previewColor(hexInput.value);
})();
</script>

<script>
// --- Cor de destaque (accent): auto-derivado do fundo, ou custom ---
(function () {
    const bgHex = document.getElementById('site-background-color');
    const bgPicker = document.getElementById('site-background-picker');
    const autoChk = document.getElementById('accent-auto');
    const accPicker = document.getElementById('site-accent-picker');
    const accHex = document.getElementById('site-accent-color');
    const accFields = document.getElementById('accent-fields');
    if (!bgHex || !autoChk || !accPicker || !accHex) return;

    const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
    const normHex = (v) => {
        const n = (v || '').trim().toUpperCase();
        return /^#[0-9A-F]{6}$/.test(n) ? n : null;
    };

    function hexToHsl(hex) {
        const r = parseInt(hex.slice(1, 3), 16) / 255;
        const g = parseInt(hex.slice(3, 5), 16) / 255;
        const b = parseInt(hex.slice(5, 7), 16) / 255;
        const max = Math.max(r, g, b), min = Math.min(r, g, b);
        const l = (max + min) / 2, d = max - min;
        let h = 0, s = 0;
        if (d !== 0) {
            s = d / (1 - Math.abs(2 * l - 1));
            if (max === r) h = ((g - b) / d) % 6;
            else if (max === g) h = (b - r) / d + 2;
            else h = (r - g) / d + 4;
            h *= 60; if (h < 0) h += 360;
        }
        return [h, s, l];
    }
    function hslToHex(h, s, l) {
        const c = (1 - Math.abs(2 * l - 1)) * s;
        const x = c * (1 - Math.abs((h / 60) % 2 - 1));
        const m = l - c / 2;
        let r, g, b;
        if (h < 60) { r = c; g = x; b = 0; }
        else if (h < 120) { r = x; g = c; b = 0; }
        else if (h < 180) { r = 0; g = c; b = x; }
        else if (h < 240) { r = 0; g = x; b = c; }
        else if (h < 300) { r = x; g = 0; b = c; }
        else { r = c; g = 0; b = x; }
        const to = (v) => Math.round((v + m) * 255).toString(16).padStart(2, '0');
        return ('#' + to(r) + to(g) + to(b)).toUpperCase();
    }
    function isDarkBg(hex) {
        const lin = [hex.slice(1, 3), hex.slice(3, 5), hex.slice(5, 7)].map((c) => {
            const ch = parseInt(c, 16) / 255;
            return ch <= 0.03928 ? ch / 12.92 : Math.pow((ch + 0.055) / 1.055, 2.4);
        });
        return (0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2]) < 0.38;
    }
    function deriveAccent(bg) {
        const [h, s] = hexToHsl(bg);
        const s2 = clamp(s + 0.06, 0.34, 0.62);
        const l = isDarkBg(bg) ? 0.62 : 0.50;
        return hslToHex(h, s2, l);
    }
    function softOf(hex) {
        const [h, s, l] = hexToHsl(hex);
        return hslToHex(h, s * 0.9, Math.min(0.74, l + 0.15));
    }
    const currentBg = () => normHex(bgHex.value) || '#FAF8F4';

    function applyAccent() {
        let acc;
        if (autoChk.checked) {
            acc = deriveAccent(currentBg());
            accPicker.value = acc;
            accHex.value = acc;
        } else {
            acc = normHex(accHex.value) || accPicker.value.toUpperCase();
        }
        document.documentElement.style.setProperty('--cor-accent', acc);
        document.documentElement.style.setProperty('--cor-accent-soft', softOf(acc));
        accFields.classList.toggle('is-auto', autoChk.checked);
        accPicker.disabled = autoChk.checked;
        accHex.disabled = autoChk.checked;
    }

    autoChk.addEventListener('change', applyAccent);
    accPicker.addEventListener('input', function () { accHex.value = this.value.toUpperCase(); applyAccent(); });
    accHex.addEventListener('input', function () { const c = normHex(this.value); if (c) accPicker.value = c; applyAccent(); });
    if (bgHex) bgHex.addEventListener('input', function () { if (autoChk.checked) applyAccent(); });
    if (bgPicker) bgPicker.addEventListener('input', function () { if (autoChk.checked) applyAccent(); });

    applyAccent();
})();
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>

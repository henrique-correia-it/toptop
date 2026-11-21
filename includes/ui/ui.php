<?php
/**
 * includes/ui/ui.php — Biblioteca Completa de Componentes TopTop (DESIGN.md)
 *
 * 16 primitivos componíveis (31 funções ui_*()) que cobrem 100% da loja, área de cliente e backoffice:
 *  1. ui_button()                               — Botões e links CTA (.ui-btn)
 *  2. ui_icon_button() / ui_close_button()      — Botões compactos de ícone e fechar
 *  3. ui_card()                                 — Cartões/superfícies elevadas (.ui-card)
 *  4. ui_badge()                                — Etiquetas de estado (.ui-badge)
 *  5. ui_field() / ui_switch() / ui_checkbox() / ui_search_input() — Controlos de formulário
 *  6. ui_alert() / ui_empty_state() / ui_spinner() — Mensagens, estados vazios e loading
 *  7. ui_page_header() / ui_back_button() / ui_toolbar() / ui_pagination() — Navegação e paginação
 *  8. ui_modal() / ui_quick_edit_modal() / ui_context_menu() — Modais e menus flutuantes
 *  9. ui_table() / ui_selection_bar() / ui_bulk_bar() / ui_customer_orders_table() — Tabelas e seleção
 * 10. ui_product_card()                         — Cartão canónico de produto (.produto)
 * 11. ui_stat_card() / ui_nav_card()            — Cartões KPI e navegação de dashboards
 * 12. ui_tabs()                                 — Separadores (.ui-tabs)
 * 13. ui_accordion()                            — Secções colapsáveis (.ui-accordion)
 * 14. ui_progress_bar()                         — Barras de progresso (.ui-progress)
 * 15. ui_qty_stepper()                          — Seletor de quantidade (.ui-qty)
 * 16. ui_kv_list()                              — Lista chave-valor com ícones (.ui-kv-list)
 */

if (!function_exists('ui_e')) {
    function ui_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ui_attrs')) {
    function ui_attrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $k => $v) {
            if ($v === null || $v === false) {
                continue;
            }
            $key = preg_replace('/[^a-zA-Z0-9_:-]/', '', (string)$k);
            if ($key === '') {
                continue;
            }
            if ($v === true) {
                $parts[] = $key;
            } else {
                $parts[] = $key . '="' . ui_e($v) . '"';
            }
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}

/** 1. Button / Link CTA */
if (!function_exists('ui_button')) {
    function ui_button(string $label, array $opts = []): string
    {
        $variant   = $opts['variant'] ?? 'default';
        $size      = $opts['size'] ?? 'md';
        $userClass = trim((string)($opts['class'] ?? ''));

        // Se o chamador passar uma classe de botão específica existente da aplicação, mantém-na sem sobrepor .ui-btn
        $hasLegacyBase = ($userClass !== '' && preg_match('/\b(button|button-primary|button-secondary|button-hero|btn-admin-|btn-boutique|sc-btn-|action-btn-pill|add-btn|voltar-btn|delete-btn|btn-add-full|btn-primary-plus|btn-add-row|btn-ver-mais|btn-voltar-compra|btn-track|btn-ctrl|btn-reset|btn-edit-framing|gc-btn-guardar|gal-btn-|qe-btn|focal-btn-|btn-sel-mode)\b/', $userClass));

        $classes = [];
        if (!$hasLegacyBase && empty($opts['raw_class'])) {
            $classes[] = 'ui-btn';
            if ($variant !== 'default') {
                $classes[] = 'ui-btn--' . preg_replace('/[^a-z0-9_-]/i', '', $variant);
            }
            if ($size === 'sm') {
                $classes[] = 'ui-btn--sm';
            }
            if (!empty($opts['full'])) {
                $classes[] = 'ui-btn--full';
            }
            if (!empty($opts['plus'])) {
                $classes[] = 'ui-btn--plus';
            }
        }
        if ($userClass !== '') {
            $classes[] = $userClass;
        }

        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        if (!empty($opts['id'])) {
            $attrs['id'] = $opts['id'];
        }
        if (!empty($opts['title'])) {
            $attrs['title'] = $opts['title'];
        }
        if (!empty($opts['style'])) {
            $attrs['style'] = $opts['style'];
        }
        if (!empty($opts['onclick'])) {
            $attrs['onclick'] = $opts['onclick'];
        }
        if (!empty($opts['form'])) {
            $attrs['form'] = $opts['form'];
        }
        if (!empty($ClassesStr = implode(' ', array_unique(array_filter($classes))))) {
            $attrs['class'] = $ClassesStr;
        }

        $iconHtml = !empty($opts['icon']) ? (string)$opts['icon'] : '';
        $inner    = isset($opts['html']) ? (string)$opts['html'] : ($iconHtml . ui_e($label));

        if (!empty($opts['href'])) {
            $attrs['href'] = $opts['href'];
            if (!empty($opts['target'])) {
                $attrs['target'] = $opts['target'];
                if ($opts['target'] === '_blank' && empty($attrs['rel'])) {
                    $attrs['rel'] = 'noopener noreferrer';
                }
            }
            return '<a' . ui_attrs($attrs) . '>' . $inner . '</a>';
        }

        $attrs['type'] = $opts['type'] ?? 'button';
        if (!empty($opts['name'])) {
            $attrs['name'] = $opts['name'];
        }
        if (isset($opts['value'])) {
            $attrs['value'] = $opts['value'];
        }
        if (!empty($opts['disabled'])) {
            $attrs['disabled'] = true;
        }
        return '<button' . ui_attrs($attrs) . '>' . $inner . '</button>';
    }
}

/** 1b. Selection Mode Toolbar Button (#btn-sel-mode) */
if (!function_exists('ui_select_mode_button')) {
    function ui_select_mode_button(array $opts = []): string
    {
        $label = (string)($opts['label'] ?? 'Selecionar');
        $icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
        return ui_button($label, [
            'id'    => $opts['id'] ?? 'btn-sel-mode',
            'class' => trim('button btn-sel-mode ' . ($opts['class'] ?? '')),
            'type'  => 'button',
            'html'  => $icon . "\n                    " . ui_e($label),
            'attrs' => is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [],
        ]);
    }
}

/** 2. Icon-only Action Button (.btn-*-single) */
if (!function_exists('ui_icon_button')) {
    function ui_icon_button(string $action, array $opts = []): string
    {
        $map = [
            'view'      => 'btn-view-single',
            'edit'      => 'btn-edit-single',
            'delete'    => 'btn-del-single',
            'duplicate' => 'btn-duplicate-single',
            'cancel'    => 'btn-cancel-single',
            'archive'   => 'btn-archive-single',
            'reply'     => 'btn-reply-single',
            'check'     => 'btn-check-single',
        ];
        $baseClass = $map[$action] ?? 'btn-view-single';
        $classes   = [$baseClass];
        if (!empty($opts['class'])) {
            $classes[] = (string)$opts['class'];
        }

        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['class'] = implode(' ', $classes);
        if (!empty($opts['id'])) {
            $attrs['id'] = $opts['id'];
        }
        if (!empty($opts['title'])) {
            $attrs['title'] = $opts['title'];
            $attrs['aria-label'] = $attrs['aria-label'] ?? $opts['title'];
        }
        if (!empty($opts['onclick'])) {
            $attrs['onclick'] = $opts['onclick'];
        }
        if (!empty($opts['style'])) {
            $attrs['style'] = $opts['style'];
        }

        if (!empty($opts['href'])) {
            $attrs['href'] = $opts['href'];
            return '<a' . ui_attrs($attrs) . '></a>';
        }
        $attrs['type'] = $opts['type'] ?? 'button';
        if (!empty($opts['name'])) {
            $attrs['name'] = $opts['name'];
        }
        if (isset($opts['value'])) {
            $attrs['value'] = $opts['value'];
        }
        return '<button' . ui_attrs($attrs) . '></button>';
    }
}

/** 2b. Standalone Close Button (.btn-close-unified) */
if (!function_exists('ui_close_button')) {
    function ui_close_button(array $opts = []): string
    {
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['type']       = $opts['type'] ?? 'button';
        $attrs['class']      = trim(($opts['raw_class'] ?? false) ? ($opts['class'] ?? 'btn-close-unified') : ('btn-close-unified ' . ($opts['class'] ?? '')));
        $attrs['title']      = $opts['title'] ?? 'Fechar';
        $attrs['aria-label'] = $attrs['aria-label'] ?? $attrs['title'];
        if (!empty($opts['id'])) {
            $attrs['id'] = $opts['id'];
        }
        if (!empty($opts['onclick'])) {
            $attrs['onclick'] = $opts['onclick'];
        }
        return '<button' . ui_attrs($attrs) . '>&times;</button>';
    }
}

/** 3. Card (.ui-card & .form-card) */
if (!function_exists('ui_card')) {
    function ui_card(string $bodyHtml, array $opts = []): string
    {
        if (($opts['variant'] ?? '') === 'form') {
            $cardClass = trim('form-card ' . ($opts['class'] ?? ''));
            $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
            $attrs['class'] = $cardClass;
            if (!empty($opts['id'])) {
                $attrs['id'] = $opts['id'];
            }
            if (!empty($opts['style'])) {
                $attrs['style'] = $opts['style'];
            }
            $html = '<div' . ui_attrs($attrs) . '>';
            if (!empty($opts['title']) || !empty($opts['title_html']) || !empty($opts['header_html'])) {
                $hdrAttrs = is_array($opts['header_attrs'] ?? null) ? $opts['header_attrs'] : [];
                $hdrAttrs['class'] = trim('form-card-header ' . ($opts['header_class'] ?? ''));
                if (!empty($opts['header_style'])) {
                    $hdrAttrs['style'] = $opts['header_style'];
                }
                $html .= '<div' . ui_attrs($hdrAttrs) . '>';
                if (!empty($opts['header_html'])) {
                    $html .= (string)$opts['header_html'];
                } elseif (!empty($opts['title_html'])) {
                    $html .= '<h3>' . (string)$opts['title_html'] . '</h3>';
                } else {
                    $icon = !empty($opts['icon']) ? (string)$opts['icon'] . ' ' : '';
                    $html .= '<h3>' . $icon . ui_e((string)$opts['title']) . '</h3>';
                }
                if (!empty($opts['header_actions'])) {
                    $html .= (string)$opts['header_actions'];
                }
                $html .= '</div>';
            }
            $bodyAttrs = is_array($opts['body_attrs'] ?? null) ? $opts['body_attrs'] : [];
            $bodyAttrs['class'] = trim('form-card-body ' . ($opts['body_class'] ?? ''));
            if (!empty($opts['body_style'])) {
                $bodyAttrs['style'] = $opts['body_style'];
            }
            $html .= '<div' . ui_attrs($bodyAttrs) . '>' . $bodyHtml . '</div></div>';
            return $html;
        }

        $classes = ['ui-card'];
        $hasHeader = !empty($opts['title']) || !empty($opts['header_actions']) || !empty($opts['eyebrow']);
        $hasFooter = !empty($opts['footer']);
        if (!$hasHeader && !$hasFooter) {
            $classes[] = !empty($opts['compact']) ? 'ui-card--compact' : 'ui-card--padded';
        }
        if (!empty($opts['interactive']) || !empty($opts['href'])) {
            $classes[] = 'ui-card--interactive';
        }
        if (!empty($opts['danger'])) {
            $classes[] = 'ui-card--danger';
        }
        if (!empty($opts['class'])) {
            $classes[] = (string)$opts['class'];
        }

        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['class'] = implode(' ', $classes);
        $tag = !empty($opts['href']) ? 'a' : 'div';
        if ($tag === 'a') {
            $attrs['href'] = $opts['href'];
        }

        $html = '<' . $tag . ui_attrs($attrs) . '>';
        if ($hasHeader) {
            $html .= '<div class="ui-card__header"><div class="ui-card__heading">';
            if (!empty($opts['icon'])) {
                $html .= '<span class="ui-card__icon" aria-hidden="true">' . (string)$opts['icon'] . '</span>';
            }
            $html .= '<div>';
            if (!empty($opts['eyebrow'])) {
                $html .= '<span class="ui-page-header__kicker">' . ui_e($opts['eyebrow']) . '</span>';
            }
            if (!empty($opts['title'])) {
                $html .= '<h3 class="ui-card__title">' . ui_e($opts['title']) . '</h3>';
            }
            if (!empty($opts['subtitle'])) {
                $html .= '<p class="ui-card__subtitle">' . ui_e($opts['subtitle']) . '</p>';
            }
            $html .= '</div></div>';
            if (!empty($opts['header_actions'])) {
                $html .= '<div class="ui-page-header__actions">' . $opts['header_actions'] . '</div>';
            }
            $html .= '</div>';
            $bodyClass = !empty($opts['flush']) ? 'ui-card__body ui-card__body--flush' : 'ui-card__body';
            $html .= '<div class="' . $bodyClass . '">' . $bodyHtml . '</div>';
        } else {
            $html .= $bodyHtml;
        }
        if ($hasFooter) {
            $html .= '<div class="ui-card__footer">' . $opts['footer'] . '</div>';
        }
        $html .= '</' . $tag . '>';
        return $html;
    }
}

/** 4. Badge (supports optional dot & leading icon) */
if (!function_exists('ui_badge')) {
    function ui_badge(string $label, string $tone = 'neutral', array $opts = []): string
    {
        $allowed   = ['neutral', 'success', 'warning', 'danger', 'info', 'accent'];
        $tone      = in_array($tone, $allowed, true) ? $tone : 'neutral';
        $userClass = trim((string)($opts['class'] ?? ''));
        $hasLegacy = ($userClass !== '' && preg_match('/\b(badge\b|badge-status|ip-pill|badge-principal|order-status)\b/', $userClass));

        $classes = [];
        if (!$hasLegacy && empty($opts['raw_class'])) {
            $classes[] = 'ui-badge';
            $classes[] = 'ui-badge--' . $tone;
            if (!empty($opts['interactive'])) {
                $classes[] = 'ui-badge--interactive';
            }
        }
        if ($userClass !== '') {
            $classes[] = $userClass;
        }
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['class'] = implode(' ', array_unique(array_filter($classes)));
        $inner = '';
        if (!empty($opts['dot'])) {
            $inner .= '<span class="ui-badge__dot" aria-hidden="true"></span>';
        }
        if (!empty($opts['icon'])) {
            $inner .= (string)$opts['icon'];
        }
        $inner .= ui_e($label);
        return '<span' . ui_attrs($attrs) . '>' . $inner . '</span>';
    }
}

/** 5a-0. Standalone Input Control (ui_input) & Textarea Control (ui_textarea) */
if (!function_exists('ui_input')) {
    function ui_input(string $name = '', array $opts = []): string
    {
        $type  = (string)($opts['type'] ?? 'text');
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['type'] = $type;

        if ($name !== '') {
            $attrs['name'] = $name;
        }
        if (array_key_exists('id', $opts)) {
            if ($opts['id'] !== false && $opts['id'] !== null && $opts['id'] !== '') {
                $attrs['id'] = (string)$opts['id'];
            }
        } elseif ($name !== '' && empty($attrs['id']) && !empty($opts['auto_id'])) {
            $attrs['id'] = $name;
        }

        $classes = [];
        if (!empty($opts['class'])) {
            $classes[] = trim((string)$opts['class']);
        }
        if (!empty($attrs['class'])) {
            $classes[] = trim((string)$attrs['class']);
        }
        if (!empty($classes)) {
            $attrs['class'] = implode(' ', array_unique(array_filter($classes)));
        }

        if (array_key_exists('value', $opts) && $opts['value'] !== null && $opts['value'] !== false) {
            $attrs['value'] = (string)$opts['value'];
        }
        foreach (['placeholder', 'autocomplete', 'inputmode', 'min', 'max', 'step', 'maxlength', 'pattern', 'title', 'style', 'form'] as $prop) {
            if (array_key_exists($prop, $opts) && $opts[$prop] !== null && $opts[$prop] !== false && $opts[$prop] !== '') {
                $attrs[$prop] = (string)$opts[$prop];
            }
        }
        if (!empty($opts['aria_label'])) {
            $attrs['aria-label'] = (string)$opts['aria_label'];
        }
        foreach (['required', 'disabled', 'readonly'] as $boolProp) {
            if (!empty($opts[$boolProp])) {
                $attrs[$boolProp] = true;
            }
        }

        if ($type === 'password' && !empty($opts['password_toggle'])) {
            return '<div class="password-wrapper"><input' . ui_attrs($attrs) . '>'
                . '<button type="button" class="toggle-senha" aria-label="Mostrar ou ocultar palavra-passe" onclick="const i=this.previousElementSibling;i.type=i.type===\'password\'?\'text\':\'password\';">'
                . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
                . '</button></div>';
        }
        if (!empty($opts['prefix'])) {
            return '<div class="ui-field__prefix-wrap"><span class="ui-field__prefix">' . ui_e($opts['prefix']) . '</span><input' . ui_attrs($attrs) . '></div>';
        }
        return '<input' . ui_attrs($attrs) . '>';
    }
}

if (!function_exists('ui_textarea')) {
    function ui_textarea(string $name = '', string $value = '', array $opts = []): string
    {
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        if ($name !== '') {
            $attrs['name'] = $name;
        }
        if (array_key_exists('id', $opts) && $opts['id'] !== false && $opts['id'] !== null && $opts['id'] !== '') {
            $attrs['id'] = (string)$opts['id'];
        }
        $attrs['rows'] = $opts['rows'] ?? 4;

        $classes = [];
        if (!empty($opts['class'])) {
            $classes[] = trim((string)$opts['class']);
        }
        if (!empty($attrs['class'])) {
            $classes[] = trim((string)$attrs['class']);
        }
        if (!empty($classes)) {
            $attrs['class'] = implode(' ', array_unique(array_filter($classes)));
        }

        foreach (['placeholder', 'maxlength', 'style', 'title', 'form'] as $prop) {
            if (array_key_exists($prop, $opts) && $opts[$prop] !== null && $opts[$prop] !== false && $opts[$prop] !== '') {
                $attrs[$prop] = (string)$opts[$prop];
            }
        }
        foreach (['required', 'disabled', 'readonly'] as $boolProp) {
            if (!empty($opts[$boolProp])) {
                $attrs[$boolProp] = true;
            }
        }
        return '<textarea' . ui_attrs($attrs) . '>' . ui_e($value) . '</textarea>';
    }
}

/** 5a. Form Field */
if (!function_exists('ui_field')) {
    function ui_field(string $name, string $label, array $opts = []): string
    {
        $type  = $opts['type'] ?? 'text';
        $id    = $opts['id'] ?? ('field-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $name));
        $value = (string)($opts['value'] ?? '');
        $error = (string)($opts['error'] ?? '');
        $hint  = (string)($opts['hint'] ?? '');

        $wrapClass = ($opts['wrap_class'] ?? 'ui-field') . ($error !== '' ? ' ui-field--error' : '');
        if (!empty($opts['class'])) {
            $wrapClass .= ' ' . $opts['class'];
        }

        $rawAttrs  = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $userCtrlClass = trim(($opts['control_class'] ?? '') . ' ' . ($rawAttrs['class'] ?? ''));

        $html = '<div class="' . ui_e(trim($wrapClass)) . '">';
        if ($label !== '') {
            $lblAttr = !empty($opts['label_id']) ? ' id="' . ui_e($opts['label_id']) . '"' : '';
            $lblClass = $opts['label_class'] ?? 'ui-field__label';
            $html .= '<label' . ($lblClass !== '' ? ' class="' . ui_e($lblClass) . '"' : '') . ' for="' . ui_e($id) . '"' . $lblAttr . '>' . ui_e($label) . '</label>';
        }

        if ($type === 'textarea') {
            $html .= ui_textarea($name, $value, array_merge($opts, [
                'id'    => $id,
                'class' => trim('ui-field__control ' . $userCtrlClass),
            ]));
        } elseif ($type === 'select') {
            $options = is_array($opts['options'] ?? null) ? $opts['options'] : [];
            $html .= ui_select($name, $options, $value, [
                'id'           => $id,
                'class'        => $userCtrlClass,
                'wrap_class'   => $opts['select_wrap_class'] ?? '',
                'variant'      => $opts['variant'] ?? 'default',
                'placeholder'  => $opts['placeholder'] ?? null,
                'required'     => !empty($opts['required']),
                'disabled'     => !empty($opts['disabled']),
                'autocomplete' => $opts['autocomplete'] ?? null,
                'onchange'     => $opts['onchange'] ?? null,
                'title'        => $opts['title'] ?? null,
                'attrs'        => $rawAttrs,
            ]);
        } else {
            $html .= ui_input($name, array_merge($opts, [
                'id'    => $id,
                'value' => $value,
                'class' => trim('ui-field__control ' . $userCtrlClass),
            ]));
        }

        if ($hint !== '' && $error === '') {
            $html .= '<span class="ui-field__hint">' . ui_e($hint) . '</span>';
        }
        if ($error !== '') {
            $html .= '<span class="ui-field__error" role="alert">' . ui_e($error) . '</span>';
        }
        $html .= '</div>';
        return $html;
    }
}

/** 5a-bis. Select / Drop-List Unificada */
if (!function_exists('ui_select')) {
    /**
     * Renderiza uma drop-list (<div class="select-wrapper"><select class="select-estilizado">...)
     * automaticamente melhorada por public/js/utils.js.
     *
     * @param string $name     Atributo name do <select> (pode ser '' se usar apenas id via $opts['id'])
     * @param array  $options  Mapa [valor => label] ou lista [['value' => ..., 'label' => ..., 'disabled' => ...]]
     * @param mixed  $selected Valor atualmente selecionado
     * @param array  $opts     Opções: id, class, wrap_class, variant ('default'|'toolbar'), placeholder,
     *                         required, disabled, autocomplete, onchange, title, attrs
     */
    function ui_select(string $name, array $options = [], $selected = '', array $opts = []): string
    {
        $variant = (string)($opts['variant'] ?? 'default');
        $isToolbar = ($variant === 'toolbar' || $variant === 'sort');

        $wrapClass = 'select-wrapper';
        if ($isToolbar) {
            $wrapClass .= ' sort-select-wrapper';
        }
        if (!empty($opts['wrap_class'])) {
            $wrapClass .= ' ' . trim((string)$opts['wrap_class']);
        }

        $selectAttrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $id = $opts['id'] ?? ($selectAttrs['id'] ?? ($name !== '' ? $name : null));
        if ($id !== null && $id !== '') {
            $selectAttrs['id'] = (string)$id;
        }
        if ($name !== '') {
            $selectAttrs['name'] = $name;
        }

        $classes = ['select-estilizado'];
        if ($isToolbar) {
            $classes[] = 'sort-select';
        }
        if (!empty($opts['disabled'])) {
            $classes[] = 'disabled';
            $selectAttrs['disabled'] = true;
        }
        if (!empty($opts['class'])) {
            $classes[] = trim((string)$opts['class']);
        }
        if (!empty($selectAttrs['class'])) {
            $classes[] = trim((string)$selectAttrs['class']);
        }
        $selectAttrs['class'] = implode(' ', array_unique(array_filter($classes)));

        if (!empty($opts['required'])) {
            $selectAttrs['required'] = true;
        }
        if (!empty($opts['autocomplete'])) {
            $selectAttrs['autocomplete'] = (string)$opts['autocomplete'];
        }
        if (!empty($opts['onchange'])) {
            $selectAttrs['onchange'] = (string)$opts['onchange'];
        }
        if (!empty($opts['title'])) {
            $selectAttrs['title'] = (string)$opts['title'];
        }

        $selectedStr = (string)($selected ?? '');
        $html = '<div class="' . ui_e(trim($wrapClass)) . '"><select' . ui_attrs($selectAttrs) . '>';

        if (array_key_exists('placeholder', $opts) && $opts['placeholder'] !== null && $opts['placeholder'] !== false) {
            $phSelected = ($selectedStr === '') ? ' selected' : '';
            $phDisabled = !empty($opts['placeholder_disabled']) ? ' disabled' : '';
            $html .= '<option value=""' . $phSelected . $phDisabled . '>' . ui_e((string)$opts['placeholder']) . '</option>';
        }

        foreach ($options as $optKey => $optItem) {
            if (is_array($optItem)) {
                $optVal = (string)($optItem['value'] ?? $optKey);
                $optLabel = (string)($optItem['label'] ?? $optVal);
                $optDisabled = !empty($optItem['disabled']);
                $optExtraAttrs = is_array($optItem['attrs'] ?? null) ? $optItem['attrs'] : [];
            } else {
                $optVal = (string)$optKey;
                $optLabel = (string)$optItem;
                $optDisabled = false;
                $optExtraAttrs = [];
            }

            $optAttrs = $optExtraAttrs;
            $optAttrs['value'] = $optVal;
            if ($optVal === $selectedStr) {
                $optAttrs['selected'] = true;
            }
            if ($optDisabled) {
                $optAttrs['disabled'] = true;
            }
            $html .= '<option' . ui_attrs($optAttrs) . '>' . ui_e($optLabel) . '</option>';
        }

        $html .= '</select></div>';
        return $html;
    }
}

/** 5b. Switch (.switch-premium & .toggle-visiveis) */
if (!function_exists('ui_switch')) {
    function ui_switch(string $name, string $label, bool $checked = false, array $opts = []): string
    {
        $isCompact = (($opts['variant'] ?? '') === 'compact');
        $id = array_key_exists('id', $opts)
            ? $opts['id']
            : ($isCompact ? false : ($name !== '' ? ('switch-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)) : null));

        $inputAttrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $inputAttrs['type'] = 'checkbox';
        if ($id !== null && $id !== false && $id !== '') {
            $inputAttrs['id'] = (string)$id;
        }
        if ($name !== '') {
            $inputAttrs['name'] = $name;
        }
        if (!empty($opts['input_class'])) {
            $inputAttrs['class'] = trim((string)$opts['input_class']);
        }
        if (array_key_exists('value', $opts) ? ($opts['value'] !== false && $opts['value'] !== null) : !$isCompact) {
            $inputAttrs['value'] = (string)($opts['value'] ?? '1');
        }
        if (!empty($opts['onchange'])) {
            $inputAttrs['onchange'] = (string)$opts['onchange'];
        }
        if ($checked) {
            $inputAttrs['checked'] = true;
        }

        if ($isCompact) {
            $wrapAttrs = is_array($opts['wrap_attrs'] ?? null) ? $opts['wrap_attrs'] : [];
            $wrapAttrs['class'] = trim('toggle-visiveis ' . ($opts['class'] ?? ''));
            if (!empty($opts['title'])) {
                $wrapAttrs['title'] = (string)$opts['title'];
            }
            if (!empty($opts['style'])) {
                $wrapAttrs['style'] = (string)$opts['style'];
            }
            $lblHtml = ($label !== '') ? '<span>' . ui_e($label) . '</span>' : '';
            return '<label' . ui_attrs($wrapAttrs) . '>'
                . '<input' . ui_attrs($inputAttrs) . '>'
                . '<span class="toggle-track"><span class="toggle-thumb"></span></span>'
                . $lblHtml
                . '</label>';
        }

        $tag = ($opts['tag'] ?? 'label') === 'span' ? 'span' : 'label';
        $wrapAttrs = is_array($opts['wrap_attrs'] ?? null) ? $opts['wrap_attrs'] : [];
        $wrapAttrs['class'] = trim('switch-premium ' . ($opts['class'] ?? ''));
        if ($tag === 'label' && $id !== null && $id !== false && $id !== '') {
            $wrapAttrs['for'] = (string)$id;
        }
        if (!empty($opts['title'])) {
            $wrapAttrs['title'] = (string)$opts['title'];
        }

        $lblCls = (string)($opts['label_class'] ?? 'label-text');
        $lblHtml = ($label !== '') ? ('<span' . ($lblCls !== '' ? ' class="' . ui_e($lblCls) . '"' : '') . '>' . ui_e($label) . '</span>') : '';

        return '<' . $tag . ui_attrs($wrapAttrs) . '>'
            . '<input' . ui_attrs($inputAttrs) . '>'
            . '<span class="slider" aria-hidden="true"></span>'
            . $lblHtml
            . '</' . $tag . '>';
    }
}

/** 6a. Alert */
if (!function_exists('ui_alert')) {
    function ui_alert(string $message, string $tone = 'info', array $opts = []): string
    {
        $allowed = ['info', 'success', 'warning', 'danger'];
        $tone = in_array($tone, $allowed, true) ? $tone : 'info';
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['class'] = trim('ui-alert ui-alert--' . $tone . ' ' . ($opts['class'] ?? ''));
        $attrs['role'] = $tone === 'danger' ? 'alert' : 'status';
        return '<div' . ui_attrs($attrs) . '>' . ui_e($message) . '</div>';
    }
}

/** 6b. Empty State */
if (!function_exists('ui_empty_state')) {
    function ui_empty_state(string $message, array $opts = []): string
    {
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['class'] = trim('ui-empty ' . ($opts['class'] ?? ''));
        if (!empty($opts['style'])) {
            $attrs['style'] = (string)$opts['style'];
        }
        $html = '<div' . ui_attrs($attrs) . '>';
        $html .= '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        $html .= '<p>' . ui_e($message) . '</p>';
        if (!empty($opts['cta_label']) && !empty($opts['cta_href'])) {
            $html .= ui_button((string)$opts['cta_label'], ['href' => (string)$opts['cta_href'], 'variant' => 'default']);
        }
        $html .= '</div>';
        return $html;
    }
}

/** 7a. Back Button */
if (!function_exists('ui_back_button')) {
    function ui_back_button(string $url = '/admin', string $title = 'Voltar'): string
    {
        return '<a href="' . ui_e($url) . '" class="btn-back-arrow" title="' . ui_e($title) . '" aria-label="' . ui_e($title) . '">'
            . '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>'
            . '</svg></a>';
    }
}

/** 7b. Page Header (suporta layout padrão 'ui' e layout 'admin' 100% compatível com .admin-page-header) */
if (!function_exists('ui_page_header')) {
    function ui_page_header(string $title, array $opts = []): string
    {
        if (($opts['layout'] ?? '') === 'admin') {
            $titleCls = trim('header-title-container ' . ($opts['title_class'] ?? ''));
            $html = '<div class="' . ui_e(trim('admin-page-header ' . ($opts['class'] ?? ''))) . '">'
                . '<div class="' . ui_e($titleCls) . '">';
            if (!empty($opts['back_url'])) {
                $html .= ui_back_button((string)$opts['back_url'], (string)($opts['back_title'] ?? 'Voltar ao Painel'));
            }
            if (!empty($opts['subtitle'])) {
                $html .= '<div class="order-title-copy"><h2>' . ui_e($title) . '</h2><p>' . ui_e($opts['subtitle']) . '</p></div>';
            } else {
                $html .= '<h2>' . ui_e($title) . '</h2>';
            }
            $html .= '</div>';
            if (!empty($opts['actions'])) {
                $actionsCls = trim(($opts['actions_class'] ?? 'header-actions'));
                $html .= '<div class="' . ui_e($actionsCls) . '">' . (string)$opts['actions'] . '</div>';
            }
            $html .= '</div>';
            return $html;
        }

        $center = !empty($opts['center']);
        $cls = 'ui-page-header' . ($center ? ' ui-page-header--center' : '');
        if (!empty($opts['class'])) {
            $cls .= ' ' . $opts['class'];
        }
        $html = '<div class="' . ui_e($cls) . '"><div class="ui-page-header__left">';
        if (!empty($opts['back_url'])) {
            $html .= ui_back_button((string)$opts['back_url'], (string)($opts['back_title'] ?? 'Voltar'));
        }
        $html .= '<div>';
        if (!empty($opts['kicker'])) {
            $html .= '<span class="ui-page-header__kicker">' . ui_e($opts['kicker']) . '</span>';
        }
        $tag = $opts['heading_tag'] ?? 'h2';
        if (!in_array($tag, ['h1', 'h2', 'h3'], true)) {
            $tag = 'h2';
        }
        $html .= '<' . $tag . ' class="ui-page-header__title">' . ui_e($title) . '</' . $tag . '>';
        if (!empty($opts['subtitle'])) {
            $html .= '<p class="ui-page-header__subtitle">' . ui_e($opts['subtitle']) . '</p>';
        }
        $html .= '</div></div>';
        if (!empty($opts['actions'])) {
            $html .= '<div class="ui-page-header__actions">' . $opts['actions'] . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

/** 8. Modal Shell */
if (!function_exists('ui_modal')) {
    function ui_modal(string $id, string $title, string $bodyHtml, array $opts = []): string
    {
        $cls       = trim(($opts['raw_class'] ?? false) ? ($opts['class'] ?? 'qe-modal') : ('qe-modal ' . ($opts['class'] ?? '')));
        $cardCls   = trim($opts['card_class'] ?? 'qe-card');
        $headerCls = trim($opts['header_class'] ?? 'qe-hd');
        $bodyCls   = trim($opts['body_class'] ?? 'qe-body');
        $closeCls  = trim($opts['close_class'] ?? 'qe-close');
        $closeId   = (string)($opts['close_id'] ?? '');

        $html = '<div class="' . ui_e($cls) . '" id="' . ui_e($id) . '" role="dialog" aria-modal="true" aria-labelledby="' . ui_e($id . '-title') . '">';
        $html .= '<div class="' . ui_e($cardCls) . '">';
        $html .= '<div class="' . ui_e($headerCls) . '"><h3 id="' . ui_e($id . '-title') . '">' . ui_e($title) . '</h3>';
        $html .= ui_close_button(['class' => $closeCls, 'id' => $closeId !== '' ? $closeId : null]) . '</div>';
        $html .= '<div class="' . ui_e($bodyCls) . '">' . $bodyHtml . '</div>';
        if (!empty($opts['footer'])) {
            $html .= '<div class="qe-btns">' . $opts['footer'] . '</div>';
        }
        $html .= '</div></div>';
        return $html;
    }
}

/** 8b. Quick Edit Modal Shell (wrapper compatível para admin/components/quick_edit_modal.php) */
if (!function_exists('ui_quick_edit_modal')) {
    function ui_quick_edit_modal(string $id = 'qe-modal', string $title = 'Edição Rápida'): string
    {
        return '<div class="qe-modal" id="' . ui_e($id) . '" role="dialog" aria-modal="true">'
            . '<div class="qe-card">'
            . '<div class="qe-hd"><h3>' . ui_e($title) . '</h3>'
            . '<button type="button" class="btn-close-unified qe-close" title="Fechar" aria-label="Fechar">&times;</button></div>'
            . '<form class="qe-form"><div class="qe-body"></div>'
            . '<div class="qe-btns">'
            . '<button type="button" class="qe-btn qe-btn-cancel">Cancelar</button>'
            . '<button type="submit" class="qe-btn qe-btn-save">Guardar Alterações</button>'
            . '</div></form></div></div>';
    }
}

/** 8c. Context Menu (wrapper compatível para admin/components/context_menu.php) */
if (!function_exists('ui_context_menu')) {
    function ui_context_menu(array $items = [], string $id = 'ctx-menu'): string
    {
        $html = '<div class="ctx-menu" id="' . ui_e($id) . '">';
        foreach ($items as $item) {
            if ($item === 'separator') {
                $html .= '<div class="ctx-sep"></div>';
            } elseif (is_array($item)) {
                $href  = ui_e($item['href'] ?? '#');
                $elId  = ui_e($item['id'] ?? '');
                $cls   = ui_e(trim('ctx-item ' . ($item['class'] ?? '')));
                $icon  = (string)($item['icon'] ?? '');
                $label = ui_e($item['label'] ?? '');
                $html .= '<a href="' . $href . '"' . ($elId !== '' ? ' id="' . $elId . '"' : '') . ' class="' . $cls . '">' . $icon . $label . '</a>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}

/** 9. Canonical Storefront Product Card (index.php & produtos.php — 100% compatível com modalProduto.js) */
if (!function_exists('ui_product_card')) {
    function ui_product_card(array $produto, array $opts = []): string
    {
        $id           = (int)($produto['id'] ?? 0);
        $nome         = (string)($produto['nome'] ?? '');
        $slug         = (string)($opts['slug'] ?? ($produto['slug'] ?? ''));
        $href         = (string)($opts['href'] ?? ($slug !== '' ? '/produto/' . $slug : '/produto.php?id=' . $id));
        $preco        = (float)($produto['preco'] ?? 0);
        $promo        = !empty($produto['preco_promocional']) ? (float)$produto['preco_promocional'] : 0.0;
        $emPromocao   = $promo > 0;
        $stock        = (int)($produto['stock_total'] ?? 0);
        $esgotado     = ($stock <= 0);
        $foto         = (string)($opts['foto_principal'] ?? ($produto['foto_exibicao'] ?? ($produto['foto_principal'] ?? 'default.jpg')));
        $fotoSec      = (string)($opts['foto_secundaria'] ?? '');
        $withModal    = !empty($opts['modal_data']);
        $showQuickAdd = !empty($opts['quick_add']);
        $imgDims      = !empty($opts['img_dimensions']);

        $tag = $esgotado ? 'div' : 'a';
        $attrs = [
            'class' => trim('produto ' . ($esgotado ? 'esgotado' : '') . ' ' . ($opts['class'] ?? '')),
        ];
        if (!$esgotado) {
            $attrs['href'] = $href;
        }
        if ($withModal) {
            $attrs['data-id']                = $id;
            $attrs['data-slug']              = $slug;
            $attrs['data-categoria']         = (string)($produto['categoria'] ?? '');
            $attrs['data-referencia']        = (string)($produto['referencia'] ?? '');
            $attrs['data-nome']              = $nome;
            $attrs['data-preco']             = $produto['preco'] ?? 0;
            $attrs['data-descricao']         = (string)($produto['descricao'] ?? '');
            $attrs['data-fotos']             = json_encode($opts['fotos'] ?? []);
            $attrs['data-preco-promocional'] = $produto['preco_promocional'] ?? '';
            $attrs['data-atributos']         = (string)($produto['atributos'] ?? '{}');
            $attrs['data-quantidade']        = $stock;
            $attrs['data-base-variacao-id']  = $produto['base_variacao_id'] ?? '';
            $attrs['data-guia-tamanho-id']   = $produto['guia_tamanho_id'] ?? '';
            $attrs['data-peso']              = $produto['peso_gramas'] ?? 0;
            $attrs['data-filtros-ativos']    = !empty($opts['filtros_ativos']) ? 'true' : 'false';
        }

        $fmtMoney = function_exists('format_money')
            ? 'format_money'
            : static fn($v) => '€' . number_format((float)$v, 2, ',', '.');

        $html = '<' . $tag . ui_attrs($attrs) . '>';
        if ($emPromocao) {
            $html .= '<span class="badge promocao">Promoção</span>';
        }
        $html .= '<div class="produto-imagem-container">';
        $html .= '<img src="/public/images/' . ui_e($foto) . '" alt="' . ui_e($nome) . '" class="imagem-principal" loading="lazy"' . ($imgDims ? ' width="600" height="800"' : '') . '>';
        if ($fotoSec !== '' && $fotoSec !== $foto) {
            $html .= '<img src="/public/images/' . ui_e($fotoSec) . '" alt="' . ui_e($nome) . '" class="imagem-secundaria" loading="lazy">';
        }
        if ($esgotado) {
            $html .= '<div class="badge-esgotado-overlay">Esgotado</div>';
        } elseif ($showQuickAdd) {
            $temVar = (!empty($produto['atributos']) && $produto['atributos'] !== '{}');
            $html .= '<span role="button" class="btn-adicionar-rapido" data-tem-variacoes="' . ($temVar ? '1' : '0') . '">'
                . ($temVar ? 'Ver Opções' : 'Adicionar Rápido') . '</span>';
        }
        $html .= '</div>';

        $html .= '<div class="produto-info">';
        $html .= '<h4>' . ui_e($nome) . '</h4>';
        if ($emPromocao) {
            $html .= '<p class="preco-promocao"><del>' . ui_e($fmtMoney($preco)) . '</del> <strong>' . ui_e($fmtMoney($promo)) . '</strong></p>';
        } else {
            $html .= '<p>' . ui_e($fmtMoney($preco)) . '</p>';
        }
        $html .= '</div></' . $tag . '>';

        return $html;
    }
}

/** 9b. Canonical Table Component (usado por TODAS as tabelas da loja, cliente e admin/dev) */
if (!function_exists('ui_table')) {
    function ui_table(array $columns, array $rows, array $opts = []): string
    {
        $selectable   = !empty($opts['selectable']);
        $selStyle     = !empty($opts['sel_hidden_inline']);
        $wrapClass    = (string)($opts['wrap_class'] ?? 'table-wrapper');
        $wrapId       = (string)($opts['wrap_id'] ?? '');
        $wrapStyle    = (string)($opts['wrap_style'] ?? '');
        $tableClass   = (string)($opts['table_class'] ?? 'admin-table');
        $emptyMessage = (string)($opts['empty_message'] ?? 'Nenhum registo encontrado.');
        $emptyClass   = (string)($opts['empty_class'] ?? 'reservation-empty');

        $wrapAttrs = ['class' => $wrapClass];
        if ($wrapId !== '') {
            $wrapAttrs['id'] = $wrapId;
        }
        if ($wrapStyle !== '') {
            $wrapAttrs['style'] = $wrapStyle;
        }

        $totalCols = count($columns) + ($selectable ? 1 : 0);

        $html = '<div' . ui_attrs($wrapAttrs) . '>';
        $html .= '<table class="' . ui_e($tableClass) . '"><thead><tr>';
        if ($selectable) {
            $html .= '<th class="col-sel"' . ($selStyle ? ' style="width:40px; display:none;"' : '') . '></th>';
        }
        foreach ($columns as $col) {
            if (is_array($col)) {
                $colAttrs = is_array($col['attrs'] ?? null) ? $col['attrs'] : [];
                if (!empty($col['class'])) {
                    $colAttrs['class'] = trim(($colAttrs['class'] ?? '') . ' ' . $col['class']);
                }
                $html .= '<th' . ui_attrs($colAttrs) . '>' . ui_e($col['label'] ?? '') . '</th>';
            } else {
                $html .= '<th>' . ui_e($col) . '</th>';
            }
        }
        $html .= '</tr></thead><tbody>';

        if (empty($rows)) {
            $html .= '<tr><td colspan="' . $totalCols . '" class="' . ui_e($emptyClass) . '">' . ui_e($emptyMessage) . '</td></tr>';
        } else {
            $checkSvg = '<div class="log-check-circle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>';
            foreach ($rows as $row) {
                $rowAttrs = is_array($row['attrs'] ?? null) ? $row['attrs'] : [];
                $cells    = is_array($row['cells'] ?? null) ? $row['cells'] : (is_array($row) ? $row : []);
                $html .= '<tr' . ui_attrs($rowAttrs) . '>';
                if ($selectable) {
                    $html .= '<td class="col-sel"' . ($selStyle ? ' style="display:none;"' : '') . '>' . $checkSvg . '</td>';
                }
                foreach ($cells as $cell) {
                    if (is_array($cell)) {
                        $cellAttrs = is_array($cell['attrs'] ?? null) ? $cell['attrs'] : [];
                        if (!empty($cell['class'])) {
                            $cellAttrs['class'] = trim(($cellAttrs['class'] ?? '') . ' ' . $cell['class']);
                        }
                        if (!empty($cell['style'])) {
                            $cellAttrs['style'] = $cell['style'];
                        }
                        $html .= '<td' . ui_attrs($cellAttrs) . '>' . (string)($cell['html'] ?? '') . '</td>';
                    } else {
                        $html .= '<td>' . (string)$cell . '</td>';
                    }
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';
        return $html;
    }
}

/** 9c. Selection Mode Bar (unificada para todas as páginas com seleção em massa) */
if (!function_exists('ui_selection_bar')) {
    function ui_selection_bar(array $opts = []): string
    {
        $unit     = (string)($opts['unit'] ?? 'selecionado(s)');
        $allLabel = (string)($opts['all_label'] ?? 'Selecionar todos');
        $spacer   = !empty($opts['spacer']);
        return '<div class="sel-mode-bar" id="sel-mode-bar">'
            . '<span><span class="sel-count" id="sel-count">0</span> ' . ui_e($unit) . '</span>'
            . '<button class="btn-sel-all" id="btn-sel-all" type="button">' . ui_e($allLabel) . '</button>'
            . ($spacer ? '<div class="sel-spacer"></div>' : '')
            . '</div>';
    }
}

/** 9d. Floating Bulk Action Bar + Hidden POST Form (unificada) */
if (!function_exists('ui_bulk_bar')) {
    function ui_bulk_bar(string $formId, string $action, array $opts = []): string
    {
        $unit        = (string)($opts['unit'] ?? 'selecionado(s)');
        $actionId    = (string)($opts['action_id'] ?? 'bulk-del');
        $actionLabel = (string)($opts['action_label'] ?? 'Apagar Selecionados');
        $csrf        = (string)($opts['csrf_token'] ?? ($_SESSION['csrf_token'] ?? ''));
        $extraHidden = is_array($opts['hidden'] ?? null) ? $opts['hidden'] : [];
        $idsDivId    = (string)($opts['ids_container_id'] ?? 'form-ids');

        $html = '<div class="bulk-bar" id="bulk-bar">'
            . '<span class="bulk-bar-count"><span id="bulk-count">0</span> ' . ui_e($unit) . '</span>'
            . '<div class="bulk-bar-spacer"></div>'
            . '<button class="bulk-bar-cancel" id="bulk-cancel" type="button">Cancelar</button>'
            . '<button class="btn-admin-danger" id="' . ui_e($actionId) . '" type="button">' . ui_e($actionLabel) . '</button>'
            . '</div>';

        if ($formId !== '' && $action !== '') {
            $html .= '<form id="' . ui_e($formId) . '" action="' . ui_e($action) . '" method="POST" style="display:none;">'
                . '<input type="hidden" name="csrf_token" value="' . ui_e($csrf) . '">';
            foreach ($extraHidden as $k => $v) {
                $html .= '<input type="hidden" name="' . ui_e($k) . '" value="' . ui_e($v) . '">';
            }
            $html .= '<div id="' . ui_e($idsDivId) . '"></div></form>';
        }
        return $html;
    }
}

/** 9e. Canonical Customer Orders Table (compõe ui_table() — DRY) */
if (!function_exists('ui_customer_orders_table')) {
    function ui_customer_orders_table(array $orders, array $opts = []): string
    {
        if (empty($orders)) {
            return ui_empty_state(
                (string)($opts['empty_message'] ?? 'Ainda não tens encomendas associadas a esta conta.'),
                [
                    'cta_label' => $opts['cta_label'] ?? null,
                    'cta_href'  => $opts['cta_href'] ?? null,
                ]
            );
        }

        $fmtMoney = function_exists('format_money')
            ? 'format_money'
            : static fn($v) => '€' . number_format((float)$v, 2, ',', '.');

        $columns = [
            'Encomenda',
            'Data',
            'Entrega',
            'Estado',
            ['label' => 'Total', 'class' => 'is-money'],
            ['label' => 'Ações', 'class' => 'is-action'],
        ];

        $rows = [];
        foreach ($orders as $order) {
            $id          = (int)($order['id'] ?? 0);
            $estado      = (string)($order['estado'] ?? '');
            $estadoClass = strtolower(str_replace(' ', '-', $estado));
            $entrega     = (string)(($order['metodo_entrega'] ?? '') ?: 'Envio');
            $total       = (float)($order['total'] ?? 0) + (float)($order['portes_envio'] ?? 0);
            $token       = (string)($order['token'] ?? '');
            $dataFmt     = !empty($order['data_encomenda']) ? date('d/m/Y', strtotime((string)$order['data_encomenda'])) : '—';

            $rows[] = [
                'cells' => [
                    ['attrs' => ['data-label' => 'Encomenda'], 'html' => '<a href="/minha-conta/encomenda?id=' . $id . '" class="order-id">#' . $id . '</a>'],
                    ['attrs' => ['data-label' => 'Data'], 'html' => '<span class="order-date">' . ui_e($dataFmt) . '</span>'],
                    ['attrs' => ['data-label' => 'Entrega'], 'html' => '<span class="order-delivery">' . ui_e($entrega) . '</span>'],
                    ['attrs' => ['data-label' => 'Estado'], 'html' => '<span class="order-status status-' . ui_e($estadoClass) . '">' . ui_e($estado) . '</span>'],
                    ['attrs' => ['data-label' => 'Total'], 'class' => 'is-money', 'html' => '<strong class="order-total">' . ui_e($fmtMoney($total)) . '</strong>'],
                    ['class' => 'is-action', 'html' => '<div class="order-actions-cell">'
                        . '<a href="/estado_encomenda.php?id=' . $id . '&token=' . urlencode($token) . '" class="btn-track-mini" title="Rastrear Encomenda">'
                        . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
                        . '<span>Tracking</span></a>'
                        . '<a href="/minha-conta/encomenda?id=' . $id . '" class="order-icon-btn" aria-label="Ver detalhes da encomenda #' . $id . '">'
                        . '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>'
                        . '</a></div>'],
                ],
            ];
        }

        return ui_table($columns, $rows, [
            'wrap_class'  => 'cliente-orders-table-wrap',
            'table_class' => 'cliente-orders-table',
        ]);
    }
}

/** 10. Checkbox (.ui-checkbox & .filtro-checkbox) */
if (!function_exists('ui_checkbox')) {
    function ui_checkbox(string $name, string $label, bool $checked = false, array $opts = []): string
    {
        $isFilter = (($opts['variant'] ?? '') === 'filter');
        $id = array_key_exists('id', $opts)
            ? $opts['id']
            : ($isFilter ? false : ('chk-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $name . '_' . ($opts['value'] ?? '1'))));

        $inputAttrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $inputAttrs['type'] = 'checkbox';
        if ($id !== false && $id !== null && $id !== '') {
            $inputAttrs['id'] = (string)$id;
        }
        if ($name !== '') {
            $inputAttrs['name'] = $name;
        }
        $inputAttrs['value'] = (string)($opts['value'] ?? '1');
        if ($checked) {
            $inputAttrs['checked'] = true;
        }

        if ($isFilter) {
            return '<label class="' . ui_e(trim('filtro-checkbox ' . ($opts['class'] ?? ''))) . '">'
                . '<input' . ui_attrs($inputAttrs) . '>'
                . '<span class="custom-checkbox"></span>'
                . '<span class="filtro-label-text">' . ui_e($label) . '</span>'
                . '</label>';
        }
        if (($opts['variant'] ?? '') === 'client') {
            return '<label class="' . ui_e(trim('cliente-check ' . ($opts['class'] ?? ''))) . '">'
                . '<input' . ui_attrs($inputAttrs) . '>'
                . '<span class="cliente-custom-check"></span>'
                . '<span class="cliente-check-text">' . ui_e($label) . '</span>'
                . '</label>';
        }

        $forAttr = ($id !== false && $id !== null && $id !== '') ? ' for="' . ui_e((string)$id) . '"' : '';
        return '<label class="' . ui_e(trim('ui-checkbox ' . ($opts['class'] ?? ''))) . '"' . $forAttr . '>'
            . '<input' . ui_attrs($inputAttrs) . '>'
            . '<span class="ui-checkbox__box" aria-hidden="true"></span>'
            . '<span class="ui-checkbox__text">' . ui_e($label) . '</span>'
            . '</label>';
    }
}

/** 11. Search Input with Leading Icon (.ui-search) */
if (!function_exists('ui_search_input')) {
    function ui_search_input(string $name = 'q', string $placeholder = 'Pesquisar...', array $opts = []): string
    {
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['type']         = $opts['type'] ?? 'search';
        if ($name !== '') {
            $attrs['name']     = $name;
        }
        $attrs['placeholder']  = $placeholder;
        if (array_key_exists('value', $opts) && $opts['value'] !== null && $opts['value'] !== false) {
            $attrs['value']    = (string)$opts['value'];
        }
        $attrs['autocomplete'] = $opts['autocomplete'] ?? 'off';
        if (empty($opts['raw_input_class'])) {
            $attrs['class']    = trim('search-input-toolbar ' . ($opts['input_class'] ?? ''));
        } elseif (!empty($opts['input_class'])) {
            $attrs['class']    = trim((string)$opts['input_class']);
        }
        if (!empty($opts['id'])) {
            $attrs['id'] = $opts['id'];
        }
        if (!empty($opts['aria_label'])) {
            $attrs['aria-label'] = $opts['aria_label'];
        }
        $wrapCls = !empty($opts['raw_wrap_class'])
            ? trim((string)($opts['class'] ?? 'search-input-wrap'))
            : trim('search-input-wrap ' . ($opts['class'] ?? ''));
        return '<div class="' . ui_e($wrapCls) . '">'
            . '<svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
            . '<input' . ui_attrs($attrs) . '></div>';
    }
}

/** 12. Toolbar (.ui-toolbar / .prod-toolbar) */
if (!function_exists('ui_toolbar')) {
    function ui_toolbar(string $leftHtml = '', string $rightHtml = '', array $opts = []): string
    {
        $cls = trim('prod-toolbar ' . ($opts['class'] ?? ''));
        return '<div class="' . ui_e($cls) . '">'
            . '<div class="prod-toolbar-left">' . $leftHtml . '</div>'
            . '<div class="prod-toolbar-right">' . $rightHtml . '</div>'
            . '</div>';
    }
}

/** 13. Pagination (.pagination-container — unifica as 3 paginações do site) */
if (!function_exists('ui_pagination')) {
    function ui_pagination(int $currentPage, int $totalPages, callable $urlBuilder, array $opts = []): string
    {
        if ($totalPages <= 1) {
            return '';
        }
        $maxVisible = max(3, (int)($opts['max_visible'] ?? 5));
        $start = max(1, $currentPage - (int)floor($maxVisible / 2));
        $end   = min($totalPages, $start + $maxVisible - 1);
        if ($end - $start + 1 < $maxVisible) {
            $start = max(1, $end - $maxVisible + 1);
        }

        $html = '<nav class="pagination-container" aria-label="Paginação"><ul class="pagination-list">';
        if ($currentPage > 1) {
            $html .= '<li class="pagination-item prev"><a class="page-link" href="' . ui_e((string)$urlBuilder($currentPage - 1)) . '" aria-label="Anterior">&lsaquo;</a></li>';
        } else {
            $html .= '<li class="pagination-item prev disabled"><span class="page-link" aria-disabled="true">&lsaquo;</span></li>';
        }
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $currentPage);
            $html .= '<li class="pagination-item' . ($active ? ' active' : '') . '">'
                . '<a class="page-link" href="' . ui_e((string)$urlBuilder($i)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
        }
        if ($currentPage < $totalPages) {
            $html .= '<li class="pagination-item next"><a class="page-link" href="' . ui_e((string)$urlBuilder($currentPage + 1)) . '" aria-label="Seguinte">&rsaquo;</a></li>';
        } else {
            $html .= '<li class="pagination-item next disabled"><span class="page-link" aria-disabled="true">&rsaquo;</span></li>';
        }
        $html .= '</ul></nav>';
        return $html;
    }
}

/** 14. KPI / Stat Card (.stat-card) */
if (!function_exists('ui_stat_card')) {
    function ui_stat_card(string $value, string $label, array $opts = []): string
    {
        $href  = (string)($opts['href'] ?? '');
        $tag   = $href !== '' ? 'a' : 'div';
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['class'] = trim('stat-card ' . ($opts['class'] ?? ''));
        if ($href !== '') {
            $attrs['href'] = $href;
        }
        $iconHtml = !empty($opts['icon']) ? '<div class="stat-card-icon" aria-hidden="true">' . (string)$opts['icon'] . '</div>' : '';
        $valId    = !empty($opts['value_id']) ? ' id="' . ui_e($opts['value_id']) . '"' : '';
        $subHtml  = !empty($opts['subtitle']) ? '<small class="ui-stat-sub">' . ui_e($opts['subtitle']) . '</small>' : '';
        $extra    = (string)($opts['extra_html'] ?? '');
        return '<' . $tag . ui_attrs($attrs) . '>'
            . $iconHtml
            . '<div class="stat-card-info"><span class="stat-number"' . $valId . '>' . ui_e($value) . '</span>'
            . '<span class="stat-label">' . ui_e($label) . '</span>' . $subHtml . $extra . '</div>'
            . '</' . $tag . '>';
    }
}

/** 15. Navigation Card (.nav-card) */
if (!function_exists('ui_nav_card')) {
    function ui_nav_card(string $title, string $description, string $href, array $opts = []): string
    {
        $attrs = is_array($opts['attrs'] ?? null) ? $opts['attrs'] : [];
        $attrs['href']  = $href;
        $attrs['class'] = trim('nav-card ' . ($opts['class'] ?? ''));
        $iconHtml = !empty($opts['icon']) ? '<div class="nav-card-icon" aria-hidden="true">' . (string)$opts['icon'] . '</div>' : '';
        return '<a' . ui_attrs($attrs) . '>'
            . $iconHtml
            . '<div class="nav-card-body"><h3>' . ui_e($title) . '</h3><p>' . ui_e($description) . '</p></div>'
            . '</a>';
    }
}

/** 16. Tabs (.ui-tabs), Accordion (.ui-accordion), Progress Bar (.ui-progress), Spinner (.ui-spinner), Qty Stepper (.ui-qty), KV List (.ui-kv-list) */
if (!function_exists('ui_tabs')) {
    function ui_tabs(array $tabs, string $activeKey = '', array $opts = []): string
    {
        $cls = trim('ui-tabs ' . ($opts['class'] ?? ''));
        $html = '<div class="' . ui_e($cls) . '" role="tablist">';
        foreach ($tabs as $tab) {
            $key    = (string)($tab['key'] ?? '');
            $label  = (string)($tab['label'] ?? '');
            $active = ($key !== '' && $key === $activeKey) || !empty($tab['active']);
            $badge  = !empty($tab['badge']) ? ' ' . ui_badge((string)$tab['badge'], (string)($tab['badge_tone'] ?? 'neutral')) : '';
            $tAttrs = is_array($tab['attrs'] ?? null) ? $tab['attrs'] : [];
            $tAttrs['type']          = 'button';
            $tAttrs['role']          = 'tab';
            $tAttrs['aria-selected'] = $active ? 'true' : 'false';
            $tAttrs['data-tab']      = $key;
            $tAttrs['class']         = trim('ui-tab ' . ($active ? 'is-active active ' : '') . ($tab['class'] ?? ''));
            $html .= '<button' . ui_attrs($tAttrs) . '>' . ui_e($label) . $badge . '</button>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('ui_accordion')) {
    function ui_accordion(string $title, string $bodyHtml, array $opts = []): string
    {
        $open = !empty($opts['open']);
        $cls  = trim('ui-accordion ' . ($opts['class'] ?? ''));
        return '<details class="' . ui_e($cls) . '"' . ($open ? ' open' : '') . '>'
            . '<summary class="ui-accordion__summary"><span>' . ui_e($title) . '</span>'
            . '<svg class="ui-accordion__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>'
            . '</summary>'
            . '<div class="ui-accordion__body">' . $bodyHtml . '</div>'
            . '</details>';
    }
}

if (!function_exists('ui_progress_bar')) {
    function ui_progress_bar(float $percent, string $tone = 'success', array $opts = []): string
    {
        $pct = max(0, min(100, $percent));
        $allowed = ['success', 'warning', 'danger', 'info', 'accent'];
        $tone = in_array($tone, $allowed, true) ? $tone : 'success';
        $cls = trim('ui-progress ' . ($opts['class'] ?? ''));
        return '<div class="' . ui_e($cls) . '" role="progressbar" aria-valuenow="' . round($pct, 1) . '" aria-valuemin="0" aria-valuemax="100">'
            . '<div class="ui-progress__fill ui-progress__fill--' . $tone . '" style="width:' . round($pct, 2) . '%;"></div>'
            . '</div>';
    }
}

if (!function_exists('ui_spinner')) {
    function ui_spinner(string $label = 'A carregar...', array $opts = []): string
    {
        $cls = trim('ui-spinner ' . ($opts['class'] ?? ''));
        return '<span class="' . ui_e($cls) . '" role="status" aria-label="' . ui_e($label) . '"></span>';
    }
}

if (!function_exists('ui_qty_stepper')) {
    function ui_qty_stepper(int $value = 1, array $opts = []): string
    {
        $min  = (int)($opts['min'] ?? 1);
        $max  = isset($opts['max']) ? (int)$opts['max'] : null;
        $id   = (string)($opts['id'] ?? '');
        $name = (string)($opts['name'] ?? '');

        if (($opts['variant'] ?? '') === 'product') {
            return '<div class="' . ui_e(trim('input-wrapper ' . ($opts['class'] ?? ''))) . '">'
                . '<button type="button" class="btn-qty" data-action="minus">-</button>'
                . '<input type="number"' . ($id !== '' ? ' id="' . ui_e($id) . '"' : '') . ($name !== '' ? ' name="' . ui_e($name) . '"' : '')
                . ' value="' . $value . '" min="' . $min . '"' . ($max !== null ? ' max="' . $max . '"' : '') . ' readonly>'
                . '<button type="button" class="btn-qty" data-action="plus">+</button>'
                . '</div>';
        }

        $nameAttr = $name !== '' ? $name : 'quantidade';
        return '<div class="' . ui_e(trim('cart-qty-editor ui-qty ' . ($opts['class'] ?? ''))) . '">'
            . '<button type="button" class="qty-btn" data-action="dec" aria-label="Diminuir quantidade">&minus;</button>'
            . '<input type="number" class="ui-qty__input" name="' . ui_e($nameAttr) . '"'
            . ($id !== '' ? ' id="' . ui_e($id) . '"' : '')
            . ' value="' . $value . '" min="' . $min . '"' . ($max !== null ? ' max="' . $max . '"' : '') . '>'
            . '<button type="button" class="qty-btn" data-action="inc" aria-label="Aumentar quantidade">+</button>'
            . '</div>';
    }
}

if (!function_exists('ui_kv_list')) {
    function ui_kv_list(array $items, array $opts = []): string
    {
        $cls = trim('ui-kv-list ' . ($opts['class'] ?? ''));
        $html = '<dl class="' . ui_e($cls) . '">';
        foreach ($items as $item) {
            $icon  = !empty($item['icon']) ? '<span class="ui-kv-list__icon" aria-hidden="true">' . (string)$item['icon'] . '</span>' : '';
            $label = ui_e($item['label'] ?? '');
            $val   = !empty($item['html']) ? (string)$item['html'] : ui_e($item['value'] ?? '');
            $html .= '<div class="ui-kv-list__row">' . $icon . '<div><dt>' . $label . '</dt><dd>' . $val . '</dd></div></div>';
        }
        $html .= '</dl>';
        return $html;
    }
}

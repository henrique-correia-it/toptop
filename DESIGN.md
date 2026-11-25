---
name: TopTop Design System
version: 1.0.0
brand: TopTop Clothing Store (toptop.pt)
aesthetic: Warm Minimalist Editorial Boutique
stack: PHP 8 + Vanilla JS + Modular CSS (no framework)
tokens:
  colors:
    canvas: "#FAF8F4"
    surface: "#FFFFFF"
    ink: "#1C1C1C"
    ink_muted: "#64748B"
    accent: "#B08968"
    accent_soft: "#C9A989"
    border: "#E2E8F0"
    success: "#28A745"
    danger: "#EF4444"
    info: "#2563EB"
  typography:
    sans: "'Inter', sans-serif"
    serif: "'Fraunces', Georgia, serif"
    display: "'Playfair Display', Georgia, serif"
  radii:
    sm: "4px"
    md: "10px"
    lg: "16px"
    pill: "999px"
  spacing_base: "4px"
components_library:
  php: "includes/ui/ui.php"
  css: "public/css/ui/_primitives.css"
  tokens_css: "public/css/base/_variables.css"
---

# DESIGN.md — TopTop Design System

## Overview

TopTop (`toptop.pt`) is a Portuguese fashion e-commerce boutique built on **Warm Minimalism meets Tactile Clarity**.
The storefront pairs a warm, editorial canvas (`#FAF8F4` by default, configurable by the store admin via `--cor-fundo-pagina`) with crisp elevated white surfaces (`#FFFFFF`), high-contrast ink typography (`#1C1C1C`), and refined pill-shaped interactive controls.
The backoffice (`/admin`, `/dev`) shares the exact same design tokens and component primitives, keeping cognitive load low and making the entire codebase predictable for both humans and AI coding agents.

---

## Colors

All colors MUST come from CSS custom properties defined in `public/css/base/_variables.css`. Never hardcode hex values in component or page stylesheets.

### Dynamic Theme Tokens (Injected by `templates/header.php`)
| Token | Default | Role |
|---|---|---|
| `--cor-fundo-pagina` | `#FAF8F4` | Global page canvas (admin-configurable) |
| `--cor-texto-pagina` | `#1C1C1C` | Auto-contrasted primary text over page canvas |
| `--cor-texto-pagina-suave` | `#64748B` | Auto-contrasted secondary text over page canvas |
| `--cor-accent` | `#B08968` | Warm sand/camel brand accent (auto-derived or custom) |
| `--cor-accent-soft` | `#C9A989` | Soft accent for subtle highlights and hover tints |
| `--cor-superficie-cartao` | `#FFFFFF` | Elevated card surface (always crisp and legible) |
| `--cor-borda-cartao` | `rgba(28,28,28,0.16)` | Subtle hairline border on elevated surfaces |

### Neutral & Semantic Scale (`base/_variables.css`)
| Role | Token | Value | Usage |
|---|---|---|---|
| Pure White | `--cor-branco` | `#FFFFFF` | Cards, inputs, buttons, modals |
| Ink / Primary Text | `--cor-texto` | `#1C1C1C` | Headings, body text, strong emphasis |
| Soft Black | `--cor-preto-suave` | `#111111` | Primary dark CTA buttons, active states |
| Surface Tint | `--cor-slate-50` | `#F8FAFC` | Button hover, table headers, soft panels |
| Subtle Fill | `--cor-slate-100` | `#F1F5F9` | Icon button backgrounds, neutral badges |
| Border Default | `--cor-slate-200` | `#E2E8F0` | Dividers, card/button borders |
| Border Strong | `--cor-slate-300` | `#CBD5E1` | Input borders, hover borders |
| Muted Text | `--cor-slate-500` | `#64748B` | Descriptions, metadata, icons |
| Secondary Ink | `--cor-slate-600` | `#475569` | Default button text, labels |
| Strong Ink | `--cor-slate-800` | `#1E293B` | Hover ink, dark labels |
| Success | `--cor-sucesso` / `--cor-sucesso-bg` | `#28A745` / `#F0FDF4` | Paid orders, active status, confirmations |
| Danger | `--cor-perigo` / `--cor-perigo-bg` | `#EF4444` / `#FEF2F2` | Delete actions, out-of-stock, validation errors |
| Info | `--cor-info` | `#2563EB` | Processing/shipped orders, informational badges |

---

## Typography

| Role | Font Family | Token | Weight | Size | Notes |
|---|---|---|---|---|---|
| Editorial Display | `Playfair Display` / `Fraunces` | `--font-display` / `--font-serif` | 500–700 | `1.5rem–3rem` | Homepage hero, editorial headings |
| Section Heading (`h2`) | `Inter` | `--font-sans` | 600 | `--text-xl` (`1.5rem`) | Uppercase, `letter-spacing: 0.1em` on storefront |
| Card / Subheading (`h3`) | `Inter` | `--font-sans` | 600 | `--text-lg` (`1.25rem`) | Left-aligned, tight line-height `1.3` |
| Body Copy | `Inter` | `--font-sans` | 400 | `--text-base` (`0.95rem`) | `line-height: 1.6`, antialiased |
| UI Controls / Buttons | `Inter` | `--font-sans` | 600 | `--text-sm` (`0.85rem`) | Sentence case, `letter-spacing: 0` |
| Badges / Captions | `Inter` | `--font-sans` | 600 | `--text-xs` (`0.75rem`) | Compact labels, table headers, timestamps |

---

## Layout

- **Spacing Scale (4px/8px grid):** `--space-1` (`4px`), `--space-2` (`8px`), `--space-3` (`12px`), `--space-4` (`16px`), `--space-5` (`20px`), `--space-6` (`24px`), `--space-8` (`32px`), `--space-10` (`40px`), `--space-12` (`48px`).
- **Max Container Widths:** Storefront content `1280px`; Admin dashboards `1200px`; Focused forms/auth `460px–720px`.
- **Responsive Breakpoints:**
  - `<= 480px`: Compact mobile (1-column grids, full-width CTAs).
  - `<= 768px`: Mobile navigation drawer, 2-column product grid, stacked checkout/account layouts.
  - `<= 992px`: Tablet adjustments, hide desktop-only inline edit controls.
  - `> 1200px`: Full desktop grid.
- **Sticky Footer:** `body` is `display: flex; flex-direction: column; min-height: 100vh;` with `main` expanding naturally.

---

## Elevation & Depth

Depth is expressed through **tonal surface contrast + soft hairline borders** rather than heavy drop shadows:

| Level | Surface | Shadow Token | Z-Index Token | Usage |
|---|---|---|---|---|
| 0 — Canvas | `var(--cor-fundo-pagina)` | none | `--z-base` (`1`) | Page background |
| 1 — Surface | `var(--cor-branco)` + `1px solid var(--cor-borda-cartao)` | `--sombra-subtil` | `--z-base` (`1`) | Cards, product tiles, tables, forms |
| 2 — Floating | `var(--cor-branco)` | `--sombra-media` | `--z-dropdown` (`100`) | Account dropdown, context menu, select options |
| 3 — Sticky Chrome | `var(--cor-branco)` / translucent | `--sombra-subtil` | `--z-sticky` (`500`) | Site header, bulk action bar |
| 4 — Overlay / Modal | `var(--cor-branco)` over `rgba(15,23,42,0.48)` | `--sombra-forte` | `--z-modal` (`2000`) | Modals, quick-edit, side cart |
| 5 — Feedback Toast | `var(--cor-branco)` | `--sombra-forte` | `--z-toast` (`3000`) | `#popupMensagem` notifications |

---

## Shapes

| Token | Value | Usage |
|---|---|---|
| `--radius-sm` | `4px` | Micro indicators, small tags |
| `--radius-md` | `10px` | Inputs, selects, textareas, icon action buttons |
| `--radius-lg` | `16px` | Cards, panels, modals, product image containers |
| `--radius-pill` | `999px` | All text buttons (`.ui-btn`), badges (`.ui-badge`), toggles (`.switch-premium`) |

---

## Components

Every UI element on the site MUST be composed from these **16 minimal composable primitives (31 component functions)** defined in `includes/ui/ui.php` and styled in `public/css/ui/_primitives.css`.

### 1. Button (`ui_button` / `.ui-btn`, `ui_select_mode_button`)
- **Variants:** `default` (white pill, subtle border), `primary` (solid dark ink `#111111`), `danger` (soft red), `ghost` (transparent), `active` (indigo active filter/mode).
- **Options:** `size` (`sm`, `md`), `full`, `plus` (rotating `+`), `icon`, `html`, `href`, `target`, `type`, `name`, `value`, `disabled`, `onclick`, `style`, `attrs`.
- `ui_select_mode_button($opts)`: Canonical `#btn-sel-mode` multi-select toggle button used across admin/dev table toolbars.

### 2. Icon & Close Buttons (`ui_icon_button`, `ui_close_button`)
- `ui_icon_button($action, $opts)`: Compact `34×34px` rounded-square action buttons (`.btn-*-single`) for `view`, `edit`, `delete`, `duplicate`, `cancel`, `archive`, `reply`, `check`.
- `ui_close_button($opts)`: Standalone `.btn-close-unified` dismiss button for modals, drawers, and overlays.

### 3. Card (`ui_card` / `.ui-card` & `.form-card`)
- Unified elevated surface (`#FFFFFF`, `1px` border, `16px` radius, `overflow: visible` so inner drop-lists never clip).
- **Options:** `variant` (`'default'` -> `.ui-card`, `'form'` -> `.form-card`), `eyebrow`, `title`, `title_html`, `header_html`, `subtitle`, `icon`, `header_actions`, `footer`, `compact`, `flush`, `interactive`, `danger`, `href`, `body_style`.

### 4. Badge (`ui_badge` / `.ui-badge`)
- Compact pill status indicator (`neutral`, `success`, `warning`, `danger`, `info`, `accent`).
- **Options:** `dot` (live status dot), `icon`, `interactive` (clickable toggle), `class`.

### 5. Form Controls (`ui_field`, `ui_input`, `ui_textarea`, `ui_select`, `ui_switch`, `ui_checkbox`, `ui_search_input`)
- `ui_field($name, $label, $opts)`: Composes `<label>` + `ui_input()`, `ui_textarea()`, or `ui_select()` with `hint` and `error` message support.
- `ui_input($name, $opts)`: Standalone `<input>` primitive (`text`, `email`, `password` with `password_toggle`, `number`, `tel`, `search`, `color`, `prefix`).
- `ui_textarea($name, $value, $opts)`: Standalone `<textarea>` primitive.
- `ui_select($name, $options, $selected, $opts)`: Unified pill drop-list (`.select-wrapper > select.select-estilizado`) automatically enhanced by `public/js/utils.js` (`window.uiCreateSelect` / `window.uiRefreshSelect`), supporting `'variant' => 'default'|'toolbar'`.
- `ui_switch($name, $label, $checked, $opts)`: Unified toggle switch (`.switch-premium` default, or `'variant' => 'compact'` for `.toggle-visiveis`) with keyboard `:focus-visible`.
- `ui_checkbox($name, $label, $checked, $opts)`: Accessible custom checkbox (`.ui-checkbox`, `'variant' => 'filter'` for `.filtro-checkbox`, `'variant' => 'client'` for `.cliente-check`).
- `ui_search_input($name, $placeholder, $opts)`: Pill search input with leading magnifying-glass icon.

### 6. Feedback & Loading (`ui_alert`, `ui_empty_state`, `ui_spinner`)
- `ui_alert($message, $tone)`: Inline status banner (`info`, `success`, `warning`, `danger`).
- `ui_empty_state($message, $opts)`: Centered empty-state block with optional CTA (`cta_label`, `cta_href`).
- `ui_spinner($label)`: Accessible CSS loading spinner.
- Global transient notifications use `mostrarPopup(texto, 'sucesso'|'erro')`.

### 7. Page Chrome & Navigation (`ui_page_header`, `ui_back_button`, `ui_toolbar`, `ui_pagination`)
- `ui_page_header($title, $opts)`: Supports storefront/default layout (`kicker`, `heading_tag`, `subtitle`, `center`, `actions`) and `'layout' => 'admin'` (`.admin-page-header` + `ui_back_button()`).
- `ui_back_button($url, $title, $opts)` (`.ui-back-btn`): Minimalist frameless `36×36px` black left-chevron button (`#111111`), transparent at rest with a subtle circular grey hover tint.
- `ui_toolbar($leftHtml, $rightHtml)`: Unified left/right flex toolbar.
- `ui_pagination($currentPage, $totalPages, $urlBuilder)`: Single canonical pagination bar.

### 8. Modals & Floating Menus (`ui_modal`, `ui_quick_edit_modal`, `ui_context_menu`)
- Unified modal shells (`qe-modal`) and right-click context menu (`ctx-menu`); `admin/components/*` are thin wrappers delegating to these functions.

### 9. Tables & Bulk Selection (`ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_customer_orders_table`)
- **Single canonical table (`ui_table`):** 100% of UI tables across storefront, customer area, admin, and dev render through `ui_table()`.
- `ui_selection_bar()` + `ui_bulk_bar()`: Unified multi-select bar and floating bulk-action bar + hidden CSRF form.

### 10. Product Card (`ui_product_card` / `.produto`)
- Single canonical storefront product card used on `index.php` and `produtos.php` (promo badge, out-of-stock overlay, secondary hover image, quick-add button, `modalProduto.js` `data-*` hooks).

### 11. Dashboard Cards (`ui_stat_card`, `ui_nav_card`)
- `ui_stat_card($value, $label, $opts)`: KPI / interactive toggle stat cards (`admin.php`, `dev.php`).
- `ui_nav_card($title, $description, $href, $opts)`: Dashboard navigation cards (`admin.php`, `dev.php`).

### 12–16. Composable Widgets (`ui_tabs`, `ui_accordion`, `ui_progress_bar`, `ui_qty_stepper`, `ui_kv_list`)
- `ui_tabs($tabs, $activeKey)`: Pill tab bar with optional badge counts.
- `ui_accordion($title, $bodyHtml)`: Native `<details>` collapsible panel.
- `ui_progress_bar($percent, $tone)`: Accessible `role="progressbar"` (`uso_armazenamento.php`).
- `ui_qty_stepper($value, $opts)`: `- / input / +` quantity selector.
- `ui_kv_list($items)`: Icon + label + value metadata list.

---

## Do's and Don'ts

### Do
- **DO** import `includes/ui/ui.php` (auto-loaded by `templates/header.php`) and compose pages from the 16 primitives (31 functions) above.
- **DO** use `var(--cor-*)`, `var(--space-*)`, `var(--radius-*)`, and `var(--sombra-*)` tokens exclusively.
- **DO** keep all text outputs escaped via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (handled automatically by `includes/ui/ui.php`).
- **DO** bump `config/versao_site.php` whenever any CSS or JS file is modified.

### Don't
- **DON'T** invent new ad-hoc button classes (`.btn-foo`, `.my-custom-btn`) — use `ui_button()` / `.ui-btn`.
- **DON'T** hardcode hex colors (`#fff`, `#333`, `#e2e8f0`) or arbitrary `border-radius` values in page CSS.
- **DON'T** add `!important` unless strictly overriding an inline `style=""` attribute controlled by third-party/JS code.
- **DON'T** override `body > main` background color with a fixed hex — always respect `var(--cor-fundo-pagina)`.
- **DON'T** put CSS `var(--...)` inside `url("data:image/svg+xml,...")` (browsers cannot resolve CSS variables inside data URIs).
- **DON'T** make `DESIGN.md` publicly accessible (it is blocked by `.htaccess` `*.md` and excluded from SFTP).

---

## Agentic Development Guide

When an AI agent builds or modifies any page in TopTop:
1. **Read `DESIGN.md` first** to align with tokens, shapes, and the 9 primitives.
2. **Never duplicate markup** — call `ui_button()`, `ui_card()`, `ui_badge()`, `ui_field()`, `ui_alert()`, `ui_empty_state()`, `ui_page_header()`, `ui_modal()`, or `ui_product_card()` from `includes/ui/ui.php`.
3. **Need a new variant?** Add a minimal modifier to `public/css/ui/_primitives.css` and document it in `DESIGN.md` instead of creating a one-off page style.
4. **Keep storefront fast:** Storefront visitors load `public/css/style.php?scope=public`; admin-only styles stay in `$adminOnlyPartials`.

---

## Complete Application Component Inventory (100% Page-by-Page Matrix)

Every single UI-rendering file in the repository (50 PHP views + 15 JS modules + 19 CSS component partials) maps to the minimal component library in `includes/ui/ui.php` (31 component functions grouped into 16 composable primitives):

### A. Storefront (12 Pages)
| Page | UI Components Used | Library Primitive(s) |
|---|---|---|
| `index.php` | Hero banner, editorial marquee, trust strip, section headers, category bento cards, product grid, admin home/category edit modals | `ui_product_card`, `ui_page_header`, `ui_button`, `ui_modal`, `ui_field` (+ editorial homepage sections) |
| `produtos.php` | Catalog header, promo switch, sort select, filter sidebar/drawer, filter accordions & checkboxes, product grid, empty state, pagination, product quick-view modal, qty stepper | `ui_product_card`, `ui_empty_state`, `ui_switch`, `ui_field`, `ui_checkbox`, `ui_accordion`, `ui_pagination`, `ui_modal`, `ui_close_button`, `ui_qty_stepper`, `ui_spinner`, `ui_badge`, `ui_button` |
| `produto.php` | Gallery + thumbnails + spinner, product meta pills, description accordion, variation selects, qty stepper, add-to-cart CTA, related products, 404 empty state | `ui_empty_state`, `ui_badge`, `ui_accordion`, `ui_field`, `ui_qty_stepper`, `ui_button`, `ui_spinner`, `ui_product_card` |
| `carrinho.php` | Page header, cart item rows (JS), qty stepper, order summary card, checkout CTA, loading spinner, empty state | `ui_page_header`, `ui_card`, `ui_button`, `ui_qty_stepper`, `ui_close_button`, `ui_spinner`, `ui_empty_state` |
| `checkout.php` | Contact/delivery/payment cards, account notices/warnings, form fields, delivery choice cards, review KV list, summary sidebar + qty stepper, loading overlay | `ui_card`, `ui_alert`, `ui_field`, `ui_button`, `ui_kv_list`, `ui_qty_stepper`, `ui_close_button`, `ui_spinner` |
| `sucesso.php` | Transaction status card (success/pending/failed), order badge, primary/secondary CTAs | `ui_card`, `ui_badge`, `ui_button` |
| `estado_encomenda.php` | Tracking header, order progress timeline, status alert banner, GLS tracking box, order items & totals, delivery KV list, message history, empty state | `ui_page_header`, `ui_card`, `ui_alert`, `ui_kv_list`, `ui_badge`, `ui_button`, `ui_empty_state` |
| `consultar-encomenda.php` | Page header, narrow form card, error alert, order/email fields, submit CTA | `ui_page_header`, `ui_card`, `ui_alert`, `ui_field`, `ui_button` |
| `contacto.php` | Hero header, contact info card (KV list + social links), message form card, Google map embed | `ui_page_header`, `ui_card`, `ui_kv_list`, `ui_field`, `ui_button` |
| `envios.php` | Page header, free-shipping banner, country search input, country accordions with ISO badge & shipping rate tables, extra info cards, empty state | `ui_table`, `ui_page_header`, `ui_alert`, `ui_card`, `ui_search_input`, `ui_accordion`, `ui_badge`, `ui_empty_state` |
| `trocas.php` | Page header, policy info cards grid, admin inline edit buttons/icon picker | `ui_page_header`, `ui_card`, `ui_button`, `ui_icon_button`, `ui_close_button` |
| `404.php` | 404 hero block, CTAs, interactive memory mini-game, victory modal | `ui_button`, `ui_modal` (+ 404 mini-game widget) |

### B. Global Templates, Auth & Customer Account (13 Files)
| File | UI Components Used | Library Primitive(s) |
|---|---|---|
| `templates/header.php` | Global site header bar, desktop/mobile navs, search overlay, account dropdown (with POST logout), cart badge | Global layout chrome singleton (auto-loads `includes/ui/ui.php`) |
| `templates/footer.php` | Global 4-column footer, toast popup (`#popupMensagem`), confirmation modal, size-guide modal, side-cart drawer, back-to-top FAB, admin header/footer editor modals | Global chrome singletons + `ui_modal`, `ui_alert`, `ui_field`, `ui_button`, `ui_close_button` |
| `templates/cliente-sidebar.php` | Customer avatar header + 4 account nav links | Account layout sidebar partial |
| `entrar.php` | Auth card, profile choice buttons, login fields (password toggle), Caps Lock warning, submit button | `ui_card`, `ui_field` (`password_toggle`), `ui_alert`, `ui_button` |
| `registar.php` | Auth card, registration fields (password toggle), pending verification state, submit button | `ui_card`, `ui_field` (`password_toggle`), `ui_alert`, `ui_button` |
| `recuperar-conta.php` | Auth card, email field, generic success/error alerts, submit button | `ui_alert`, `ui_button`, `ui_card`, `ui_field` |
| `redefinir-conta.php` | Auth card, password fields (password toggle), status alerts, submit button | `ui_card`, `ui_field` (`password_toggle`), `ui_alert`, `ui_button` |
| `verificar-email.php` | Auth card, CSRF POST confirmation button, error alerts | `ui_alert`, `ui_button`, `ui_card` |
| `minha-conta.php` | Account header, 2 KPI stat cards, recent orders panel & table / empty state | `ui_customer_orders_table` (`ui_table`), `ui_empty_state`, `ui_stat_card`, `ui_card`, `ui_page_header` |
| `minha-conta-encomendas.php` | Account header, full orders history table / empty state with CTA | `ui_customer_orders_table` (`ui_table`), `ui_empty_state`, `ui_button`, `ui_card`, `ui_page_header` |
| `minha-conta-encomenda.php` | Back link, order header + status badge, items & totals panel, tracking CTA, delivery & tracking box, 404 empty state | `ui_back_button`, `ui_page_header`, `ui_badge`, `ui_card`, `ui_button`, `ui_empty_state` |
| `minha-conta-moradas.php` | Saved address cards, principal badge, edit/delete icon buttons, set-default button, empty state, add/edit address form & checkbox | `ui_icon_button`, `ui_button`, `ui_empty_state`, `ui_badge`, `ui_card`, `ui_field`, `ui_checkbox` |
| `minha-conta-dados.php` | Profile form card, change password card (password toggles), danger zone card, delete account confirmation modal | `ui_card` (`danger`), `ui_field` (`password_toggle`), `ui_button`, `ui_modal` |

### C. Admin & Developer Backoffice (25 Files)
| File | UI Components Used | Library Primitive(s) |
|---|---|---|
| `admin/admin.php` | Dashboard header + edit-mode toggle, 3 KPI stat cards, 12 nav cards, activity feed + empty state, context menus, quick-edit modal | `ui_page_header`, `ui_stat_card`, `ui_nav_card`, `ui_empty_state`, `ui_context_menu`, `ui_quick_edit_modal`, `ui_button` |
| `admin/admin_produtos.php` | Page header, toolbar (add CTA, visible switch, sort select, select toggle), selection bar, products table, status badges, icon buttons, pagination, bulk bar, context menu, quick-edit modal | `ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_icon_button`, `ui_context_menu`, `ui_quick_edit_modal`, `ui_page_header`, `ui_toolbar`, `ui_switch`, `ui_pagination`, `ui_badge`, `ui_button` |
| `admin/_form_produto.php` (`adicionar.php`, `editar.php`) | Page header, 6 form section cards, inputs/selects/textareas, image uploader + cropper modal, attributes & variations builders, submit CTA | `ui_page_header`, `ui_card`, `ui_field`, `ui_button`, `ui_icon_button`, `ui_modal`, `ui_table` |
| `admin/encomendas.php` | Page header, toolbar (status filter, search, select toggle), selection bar, orders table, status/payment badges, icon buttons, pagination, bulk bar, context menu | `ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_icon_button`, `ui_context_menu`, `ui_page_header`, `ui_toolbar`, `ui_pagination`, `ui_badge`, `ui_button` |
| `admin/detalhes_encomenda.php` | Page header + status badge, customer info card (KV list + WhatsApp CTA), order items table + totals, message history, tabbed actions sidebar, warning/danger alerts, email modal | `ui_table`, `ui_page_header`, `ui_card`, `ui_kv_list`, `ui_tabs`, `ui_alert`, `ui_field`, `ui_button`, `ui_badge`, `ui_modal`, `ui_empty_state` |
| `admin/reservas_stock.php` | Page header, toolbar, selection bar, reservations table + live countdown + icon buttons, bulk bar | `ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_icon_button`, `ui_page_header`, `ui_toolbar`, `ui_badge` |
| `admin/clientes.php` | Page header + count badge, inline alerts, toolbar (search + select), selection bar, customers table + interactive active toggle + delete icon, pagination, bulk bar, context menu | `ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_alert`, `ui_pagination`, `ui_icon_button`, `ui_context_menu`, `ui_page_header`, `ui_toolbar`, `ui_badge` |
| `admin/listar_admins.php` | Page header, inline alerts, toolbar (add + search + select), selection bar, admins table + role/protected badges + icon buttons, bulk bar, context menu | `ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_alert`, `ui_icon_button`, `ui_context_menu`, `ui_page_header`, `ui_toolbar`, `ui_badge`, `ui_button` |
| `admin/adicionar_admin.php` & `admin/editar_admin.php` | Page header, inline alerts, 2-column form cards, inputs/selects/password toggles, footer actions | `ui_page_header`, `ui_alert`, `ui_card`, `ui_field` (`password_toggle`), `ui_button` |
| `admin/gerir_atributos.php` | Page header, add-group card + switch, collapsible attribute group cards, sortable value rows, icon buttons, empty state, inline edit | `ui_page_header`, `ui_card`, `ui_accordion`, `ui_switch`, `ui_badge`, `ui_field`, `ui_button`, `ui_icon_button`, `ui_empty_state` |
| `admin/gerir_categorias.php` | Page header + add CTA, category cards (photo thumb, inline name, visibility switch, tag, save/delete), empty state, new-category modal | `ui_page_header`, `ui_card`, `ui_switch`, `ui_badge`, `ui_button`, `ui_icon_button`, `ui_empty_state`, `ui_modal`, `ui_field` |
| `admin/gerir_guias_tamanho.php` | Page header, search + new CTA toolbar, guide cards grid + icon buttons, empty state, visual table editor modal, preview modal | `ui_page_header`, `ui_toolbar`, `ui_search_input`, `ui_card`, `ui_icon_button`, `ui_button`, `ui_empty_state`, `ui_modal`, `ui_field` |
| `admin/gestor_emails.php` | Page header, click-to-copy placeholders sidebar card, grouped template accordions + forms, context menu, quick-edit modal | `ui_page_header`, `ui_card`, `ui_accordion`, `ui_field`, `ui_button`, `ui_context_menu`, `ui_quick_edit_modal` |
| `admin/mensagens.php` | Page header, 2-column Kanban board (received/archived) with counters, draggable message cards + icon buttons, empty state, reply modal | `ui_page_header`, `ui_card`, `ui_badge`, `ui_icon_button`, `ui_empty_state`, `ui_quick_edit_modal`, `ui_field` |
| `admin/paleta_cores.php` | Page header, inline alerts, color settings card (color pickers + auto toggle + actions), live wireframe preview card | `ui_page_header`, `ui_alert`, `ui_card`, `ui_switch`, `ui_field`, `ui_button` |
| `admin/portes.php` | Page header + autosave dot badge, free-shipping card (switch + `€` prefix input), toolbar, country shipping cards + weight rows, add-country modal with search | `ui_page_header`, `ui_badge` (`dot`), `ui_card`, `ui_switch`, `ui_field` (`prefix`), `ui_toolbar`, `ui_button`, `ui_icon_button`, `ui_modal`, `ui_search_input` |
| `dev/dev.php` | Dashboard header + backup dialog + simulate dropdown, 4 KPI/toggle stat cards, 7 nav cards (incl. Design System), recent logs feed | `ui_page_header`, `ui_stat_card`, `ui_nav_card`, `ui_modal`, `ui_button`, `ui_alert` |
| `dev/design_system.php` | Live showcase of all color tokens + all 16 UI primitives & variants (dev-only) | All `includes/ui/ui.php` primitives |
| `dev/galeria_imagens.php` | Page header, compact stat strip, filter tabs + scan/delete toolbar, status banner, image gallery grid + ghost badges, lightbox | `ui_page_header`, `ui_stat_card`, `ui_toolbar`, `ui_tabs`, `ui_alert`, `ui_badge`, `ui_button`, `ui_empty_state`, `ui_close_button`, `ui_spinner` |
| `dev/login_logs.php` | Page header, toolbar (filter + search + select), selection bar, logs table, pagination, bulk bar | `ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_pagination`, `ui_page_header`, `ui_toolbar`, `ui_badge` |
| `dev/previews.php` | Page header + subtitle, 4 themed preview link cards | `ui_page_header`, `ui_card`, `ui_button` |
| `dev/uso_armazenamento.php` | Page header, total storage card + progress bar, breakdown items + progress bars | `ui_page_header`, `ui_card`, `ui_progress_bar` |
| `dev/ver_logs.php` | Page header, log viewer card, file tabs with size badges, monospace `<pre>` viewer, control bar (refresh/clear/auto-refresh) | `ui_page_header`, `ui_card`, `ui_tabs`, `ui_badge`, `ui_button`, `ui_switch` |
| `admin/components/*.php` | `back_button.php`, `context_menu.php`, `quick_edit_modal.php` | Thin 4-line wrappers over `ui_back_button()`, `ui_context_menu()`, `ui_quick_edit_modal()` |

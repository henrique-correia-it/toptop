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
  showcase: "/dev/design_system.php"
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
| `--radius-pill` | `999px` | All text buttons (`.ui-btn`), badges (`.ui-badge`), toggles (`.ui-switch`) |

---

## Components

Every UI element on the site MUST be composed from these **9 minimal primitives** defined in `includes/ui/ui.php` and styled in `public/css/ui/_primitives.css`.

### 1. Button (`ui_button` / `.ui-btn`)
- **Variants:** `default` (white pill, subtle border — matches the signature TopTop button), `primary` (solid dark ink `#111111` for key conversions), `danger` (soft red border/text), `ghost` (transparent subtle action), `active` (indigo active filter/mode).
- **Modifiers:** `size` (`sm`, `md`), `full` (`true`), `plus` (`true` for rotating `+` icon), `href` (renders `<a>`), `disabled`.
- **PHP:** `ui_button('Guardar', ['variant' => 'primary', 'type' => 'submit'])`

### 2. Icon Button (`ui_icon_button` / `.btn-*-single`)
- Compact `34x34px` rounded-square (`10px`) icon-only action button for tables and cards.
- **Actions:** `view`, `edit`, `delete`, `duplicate`, `cancel`, `archive`, `reply`, `check`, `close`.
- **PHP:** `ui_icon_button('edit', ['href' => 'editar.php?id=1', 'title' => 'Editar'])`

### 3. Card (`ui_card` / `.ui-card`)
- Unified elevated container (`#FFFFFF`, `1px` border, `16px` radius, `20px–24px` padding).
- **Variants:** `default`, `interactive` (hover lift when `href` is set), `stat` (KPI number + label + icon), `compact`.
- **PHP:** `ui_card($bodyHtml, ['title' => 'Resumo', 'header_actions' => $btn])`

### 4. Badge (`ui_badge` / `.ui-badge`)
- Compact pill status indicator (`font-size: 0.75rem`, `font-weight: 600`).
- **Tones:** `neutral`, `success`, `warning`, `danger`, `info`, `accent`.
- **PHP:** `ui_badge('Pago', 'success')`

### 5. Form Controls (`ui_field`, `ui_switch` / `.ui-field`, `.ui-switch`)
- Standardized label + input/select/textarea + helper/error text with `:focus-visible` accessibility ring.
- **PHP:** `ui_field('email', 'Email', ['type' => 'email', 'required' => true, 'value' => $email])`
- **PHP:** `ui_switch('ativo', 'Visível na loja', true)`

### 6. Feedback (`ui_alert`, `ui_empty_state` / `.ui-alert`, `.ui-empty`)
- Inline banner messages (`success`, `danger`, `warning`, `info`) and centered empty-state blocks with optional CTA.
- Global transient notifications use `mostrarPopup(texto, 'sucesso'|'erro')`.
- **PHP:** `ui_alert('Guardado com sucesso!', 'success')`
- **PHP:** `ui_empty_state('Ainda não existem encomendas.', ['cta_label' => 'Explorar loja', 'cta_href' => '/produtos'])`

### 7. Page Header (`ui_page_header` / `.ui-page-header`)
- Standardized page top bar with optional kicker, back arrow (`ui_back_button`), title, subtitle, and right-aligned action slot.
- **PHP:** `ui_page_header('Gestão de Produtos', ['back_url' => '/admin', 'actions' => ui_button('Novo Produto', ['href' => 'adicionar.php', 'plus' => true])])`

### 8. Modal & Context Menu (`ui_modal`, `ui_quick_edit_modal`, `ui_context_menu`)
- Unified modal shell with header, close button, scrollable body, and footer actions. Compatible with `admin-ui.js` (`qe-modal` and `ctx-menu` hooks; `admin/components/*` are thin wrappers over `includes/ui/ui.php`).
- **PHP:** `ui_modal('modal-exemplo', 'Editar Item', $formHtml, ['footer' => $actionsHtml])`

### 9. Table, Bulk Selection & Product Card (`ui_table`, `ui_selection_bar`, `ui_bulk_bar`, `ui_product_card`)
- **Single canonical table (`ui_table`):** Every table across the entire app (storefront customer orders in `minha-conta.php` / `minha-conta-encomendas.php` via `ui_customer_orders_table()`, and all 6 admin/dev tables in `admin_produtos.php`, `encomendas.php`, `clientes.php`, `listar_admins.php`, `reservas_stock.php`, `dev/login_logs.php`) renders through `ui_table()`.
- **Bulk selection (`ui_selection_bar`, `ui_bulk_bar`):** Unified selection-mode bar and floating bulk action bar + hidden CSRF form.
- **Product card (`ui_product_card`):** Single canonical storefront product card used on `index.php` and `produtos.php`.

---

## Do's and Don'ts

### Do
- **DO** import `require_once __DIR__ . '/includes/ui/ui.php';` (or `../includes/ui/ui.php`) and compose pages from the 9 primitives above.
- **DO** use `var(--cor-*)`, `var(--space-*)`, `var(--radius-*)`, and `var(--sombra-*)` tokens exclusively.
- **DO** keep all text outputs escaped via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (handled automatically by `includes/ui/ui.php`).
- **DO** bump `config/versao_site.php` whenever any CSS or JS file is modified.
- **DO** verify new/edited UI in `/dev/design_system.php` (dev-only live showcase).

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
3. **Need a new variant?** Add a minimal modifier to `public/css/ui/_primitives.css` and document it in `DESIGN.md` and `/dev/design_system.php` instead of creating a one-off page style.
4. **Keep storefront fast:** Storefront visitors load `public/css/style.php?scope=public`; admin-only styles stay in `$adminOnlyPartials`.

---

## Complete Application Component Inventory (100% Page-by-Page Matrix)

Every single UI-rendering file in the repository (50 PHP views + 15 JS modules + 19 CSS component partials) maps to the minimal component library in `includes/ui/ui.php` (24 functions grouped into 16 composable primitives):

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

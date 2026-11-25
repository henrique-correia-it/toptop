# Auditoria Profunda e Exaustiva do Projeto (`c:\Projetos\public_html`)

> **Âmbito:** Análise linha a linha de **100% da base de código** (226 ficheiros de código, configuração, templates, serviços, endpoints AJAX, painel admin, ferramentas dev, JavaScript, CSS, logs e documentação + inventário completo de 111 assets/imagens e verificação da biblioteca `phpmailer/`) e de todas as ligações entre ficheiros.
>
> **Objetivo:** Corrigir bugs, fechar vulnerabilidades de segurança, reparar ligações quebradas, otimizar performance, melhorar acessibilidade e limpar código morto/duplicado — **sem adicionar funcionalidades novas**.

---

## 1. Resumo Executivo e Estatísticas

| Métrica | Valor |
|---|---:|
| **Total de problemas / melhorias consolidados** (deduplicados cross-file) | **146** |
| **Não requer opinião humana** (correções objetivas prontas a executar) | **105** (71.9%) |
| **Requer opinião humana** (depende de decisão de negócio, UX ou infra) | **41** (28.1%) |
| **P0 — Crítico / Quick Win Imediato** (Score >= 40) | **21** |
| **P1 — Muito Alta Prioridade** (Score 20 – 39.99) | **46** |
| **P2 — Alta Prioridade** (Score 10 – 19.99) | **58** |
| **P3 — Média Prioridade** (Score 5 – 9.99) | **18** |
| **P4 — Baixa Prioridade / Refatoração** (Score < 5) | **3** |

### Metodologia de Pontuação e Ordenação

- **Benefício (1 a 10):** Impacto real na segurança, integridade financeira/pagamentos, integridade de dados/stock, estabilidade em produção, performance, acessibilidade e manutenibilidade (10 = crítico em produção/pagamentos/segurança severa; 1 = cosmético).
- **Custo (1 a 10):** Esforço técnico, número de ficheiros afetados, risco de regressão e necessidade de testes (1 = trivial <10 min; 10 = refatoração estrutural massiva).
- **Score de Prioridade:** `Score = Benefício² / Custo` (ordenado de forma decrescente; desempate por maior Benefício e depois menor Custo). Elevar o Benefício ao quadrado evita que tarefas cosméticas baratas (2/1 = 2) empatem com problemas críticos de esforço médio (10/5 = 2), mantendo os *quick wins* críticos no topo.
- **Classificação de Decisão:**
  - **Não requer opinião humana:** Bugs objetivos, vulnerabilidades inequívocas, crashes PHP 8/JS, ficheiros órfãos, colisões CSS/z-index, acessibilidade WCAG óbvia e ligações quebradas.
  - **Requer opinião humana:** Credenciais/infraestrutura de alojamento, escolhas de negócio/RGPD/RBAC, comportamento intencional de UX/design ou remoção de funcionalidades/atalhos potencialmente usados.

---

## 2. Tabela Geral Ordenada por Score (Todos os 146 Pontos)

| Rank | Score | Benef. | Custo | Classificação | Categoria | Problema / Melhoria |
|---|---:|---:|---:|---|---|---|
| **#001** | **100,00** | 10 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Pagamentos | CSP bloqueia 3D Secure do Stripe (hooks.stripe.com) e omite diretivas de hardening |
| **#002** | **81,00** | 9 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug UI / Responsividade | Modal de produto fica partido em 2 colunas (>=680px) em mobile/tablet com overflow horizontal |
| **#003** | **81,00** | 9 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Diretórios internos expostos via HTTP (phpmailer/, templates/, admin/components/, scratch/) e falta Options -Indexes |
| **#004** | **64,00** | 8 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Performance / SEO | Regex .htaccess de remoção de .php quebra query strings e força 301 em style.php, sitemap.php e AJAX GET |
| **#005** | **64,00** | 8 | 1 | 🟠 Requer opinião humana | Configuração / Bug | ✅ RESOLVIDO |
| **#006** | **64,00** | 8 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Fiabilidade | Preloader da homepage bloqueia o site inteiro quando localStorage está desativado |
| **#007** | **50,00** | 10 | 2 | 🟠 Requer opinião humana | Segurança / Operações | Deploy por FTP sem encriptação (porta 21), password em texto limpo e autoUpload ativo para produção |
| **#008** | **50,00** | 10 | 2 | 🟠 Requer opinião humana | Segurança | Reutilização de password entre SMTP (MAIL_PASS) e FTP + segredos de produção em .env no webroot |
| **#009** | **50,00** | 10 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug Crítico / Pagamentos | limpar_abandonadas.php pode apagar permanentemente encomendas PAGAS |
| **#010** | **50,00** | 10 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug Crítico / Pagamentos | Pagamentos com redirect (MB WAY, Klarna, 3DS) nunca limpam o carrinho + client_secret exposto no URL |
| **#011** | **50,00** | 10 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug Crítico / Pagamentos | Ecrã MB WAY pendente (estado 'aguardar') é código morto — clientes MB WAY veem 'Pagamento Recusado' |
| **#012** | **50,00** | 10 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug Crítico / Pagamentos | ajax_libertar_reserva_checkout.php apaga encomenda e repõe stock em caso de erro de rede do Stripe |
| **#013** | **49,00** | 7 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Menu de contexto dos stat cards em admin.php nunca abre e 4 nav cards não-editáveis editam o card errado |
| **#014** | **49,00** | 7 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Ligação entre Ficheiros / Performance | config/versao_site.php não usa return — cache-busting de CSS/JS preso permanentemente em ?v=1 |
| **#015** | **49,00** | 7 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Performance | Leak de memória Tippy.js em footer.php (titulo._tippy vs produto._tippy) e crash se CDN falhar |
| **#016** | **49,00** | 7 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Bug emPromocao: !!'0.00' em produtos.php marca produtos sem promoção como promocionais no carrinho |
| **#017** | **40,50** | 9 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Integridade de Dados | apagar_encomendas_massa.php apaga reservas 'incompleta' sem repor stock |
| **#018** | **40,50** | 9 | 2 | 🟠 Requer opinião humana | Segurança / Operações | ✅ RESOLVIDO |
| **#019** | **40,50** | 9 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Pastas de upload em public/assets/* sem .htaccess de bloqueio + blocklist incompleta em public/images/.htaccess |
| **#020** | **40,50** | 9 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Stored XSS completo em contacto.php (getFooterText sem escape) e esquemas javascript: no footer |
| **#021** | **40,50** | 9 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Side-cart não desativa em /checkout (ID errado formCheckout vs form-dados) e carrinho vazio não redireciona |
| **#022** | **36,00** | 6 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Contador 'Encomendas por Enviar' no Dashboard diverge do filtro encomendas.php?estado=por_enviar |
| **#023** | **36,00** | 6 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | admin/ajax_edit_interface_label.php permite criar chaves arbitrárias ilimitadas em admin_interface_strings |
| **#024** | **32,00** | 8 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | redefinir-conta.php: race no token admin (DELETE por email), falta no-store, sem limite max password nem log |
| **#025** | **32,00** | 8 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Acessibilidade | Checkboxes escondidos com display: none inutilizam filtros e formulários para teclado e screen readers |
| **#026** | **32,00** | 8 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | DOM XSS em modalProduto.js (produtos relacionados, miniaturas e selects via innerHTML sem escape) |
| **#027** | **32,00** | 8 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Integridade de Dados | CartService confia em selecoes enviadas pelo cliente em vez de pv.atributos da BD |
| **#028** | **32,00** | 8 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Falta validação de esquema URL (javascript:/data:) em ui.php e bypass // em getHeaderLogo() |
| **#029** | **32,00** | 8 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Bug | Fatal PHP 8 TypeError via query params array (?q[]=x e ?atributos[Cor]=Preto) + JSON_CONTAINS sem validação |
| **#030** | **27,00** | 9 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Segurança | Deadlock permanente de contas não verificadas em registar.php + janela de conta ativa + sem rate-limit |
| **#031** | **27,00** | 9 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug Crítico / Pagamentos | Race condition em stripe_webhook.php (payment_intent.canceled) deixa encomendas canceladas e envia email falso |
| **#032** | **27,00** | 9 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Pagamentos | ajax_reembolsar_stripe.php: race TOCTOU com falso positivo, sem check POST, sem log do Refund ID e fuga de erro |
| **#033** | **27,00** | 9 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Sanitizador HTML por regex contornável em sanitize_email_history_html() (Stored XSS em admin e tracking) |
| **#034** | **27,00** | 9 | 3 | 🟠 Requer opinião humana | Segurança / Performance | ✅ RESOLVIDO |
| **#035** | **27,00** | 9 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Stored XSS nos Guias de Tamanho (HTML gravado e renderizado raw via innerHTML no admin e loja) |
| **#036** | **27,00** | 9 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Cadeia de Stored XSS e CSS Injection na Homepage (home_bento_layouts, home_hero_bg, foto_posicao) |
| **#037** | **25,00** | 10 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug Crítico / Pagamentos | Cancelar/apagar reservas ou encomendas incompletas no Admin nunca cancela o Stripe PaymentIntent |
| **#038** | **25,00** | 5 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Eliminação em massa de encomendas perde filtros/paginação porque return_to só aceita encomendas.php |
| **#039** | **25,00** | 5 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | SEO / Segurança | robots.txt aponta sitemap com 301/host errado, expõe /scratch/ e omite rotas privadas |
| **#040** | **25,00** | 5 | 1 | 🟠 Requer opinião humana | UX / Bug | ✅ RESOLVIDO |
| **#041** | **25,00** | 5 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | admin/editar.php faz duplo escape HTML no título do produto (&amp; e &#039; literais) |
| **#042** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Integridade de Dados | Apagar guia de tamanhos deixa produtos.guia_tamanho_id órfão (404 na loja) + bugs no editor de guias |
| **#043** | **24,50** | 7 | 2 | 🟠 Requer opinião humana | Bug / Integridade de Dados | ✅ RESOLVIDO |
| **#044** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Concorrência | Envio SMTP bloqueante (timeout 60s) dentro de transação MySQL FOR UPDATE em ajax_gerir_encomenda.php |
| **#045** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Fiabilidade | mensagens.php: enviarResposta() engole todos os erros silenciosamente, botão errado e email enviado antes de UPDATE BD |
| **#046** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Segurança / Performance | ajax_get_variacoes.php devolve HTTP 200 em erro (parece esgotado) e atributos=null crashea produtos.php; ajax_search sem try/catch nem rate-limit |
| **#047** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Estabilidade | TypeError fatal (HTTP 500 DoS) por inputs array em csrf_from_post/array, customer_save_address e format_address |
| **#048** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug UI / CSS | 4 bugs visuais diretos no CSS Core (hover categorias, .btn-check-single invisível, borda thumbnail modal, :disabled hover) |
| **#049** | **24,50** | 7 | 2 | 🟠 Requer opinião humana | SEO / Configuração | ✅ RESOLVIDO |
| **#050** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Fiabilidade | search.js: fetch não abortado ao limpar/fechar pesquisa, storage sem try/catch aborta navegação e formato € errado |
| **#051** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | admin-ui.js: tecla Escape falha em modais .ativo, menus de contexto não fecham ao clicar e href='#' salta para topo |
| **#052** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Bug | Duplo escape HTML nos assuntos de email (D&#039;Angelo / &amp;) e falta sanitização CR/LF em templates |
| **#053** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Performance | Dupla ligação MySQL e duplo parsing de .env abertos a cada pedido em 6 páginas |
| **#054** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Ligação entre Ficheiros / Bug | admin-ui.js não carrega em /admin (sem barra) nem em /dev/ (prende modais em design_system.php) + CSRF dev no público |
| **#055** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Segurança | envios.php:  sem escape, locales NumberFormatter inválidos, max=0 exibido como '10kg - 0kg' e HTML inválido |
| **#056** | **24,50** | 7 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | contacto.php: strlen() em bytes rejeita mensagens PT válidas <1000 chars + editor do footer crashea ao gravar morada |
| **#057** | **21,33** | 8 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Fiabilidade | email_handler.php: catch(Exception) só captura PHPMailerException + KeepAlive sujo + race em mensagens_cliente + logo CLI |
| **#058** | **21,33** | 8 | 3 | 🟠 Requer opinião humana | Bug / Integridade de Dados | ✅ RESOLVIDO |
| **#059** | **21,33** | 8 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Bug | Fuga de mensagens raw SQL/Stripe em 6 endpoints admin + 6 sem try/catch + sobrescrita de HTTP 405/403/404 para 400 |
| **#060** | **21,33** | 8 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança | Múltiplos XSS / injeção de atributos e JS em páginas admin (encomendas, detalhes, gestor_emails, portes, mensagens) |
| **#061** | **21,33** | 8 | 3 | 🟠 Requer opinião humana | Segurança / Integridade de Dados | ajax_apagar_conta_cliente.php: omite cliente_email_verifications (risco FK), não destrói sessão, HTTP 200 e PII de rascunhos |
| **#062** | **21,33** | 8 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Performance / Segurança | style.php: immutable 1 ano sem ?v=, sem ETag/304, default scope=full (450KB) e envia CSS admin ao público |
| **#063** | **21,33** | 8 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Race síncrona entre gestao_atributos.js, gestao_variacoes.js e multiCropper.js + mismatch tipo de IDs de imagens |
| **#064** | **21,33** | 8 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Checkout: cálculo de portes cliente diverge do servidor e Passo 2 ignora total_cents autoritativo |
| **#065** | **21,33** | 8 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Ligação entre Ficheiros | Checkout & Moradas: código 'UK' rejeitado pelo Stripe (usar 'GB'), bloqueio em Recolha e telefone readonly +351 |
| **#066** | **20,25** | 9 | 4 | 🟠 Requer opinião humana | Bug / Integridade de Dados | Corrupção de stock (stock fantasma e overselling) e ausência de máquina de estados em encomendas |
| **#067** | **20,25** | 9 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Pagamentos | stripe_webhook.php e 3 fallbacks: falta validação de moeda (eur), pagamentos órfãos/pós-cancel sem alerta e email loja bloqueado |
| **#068** | **18,00** | 6 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug | gerir_categorias.php: click bubbling no input file, blob quebrado em erro de upload e ordenação id ASC vs ordem ASC |
| **#069** | **18,00** | 6 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Segurança | gerir_atributos.php: edição inline colapsa card a cada clique, auth após <header> e crashes/toasts corrompidos |
| **#070** | **18,00** | 6 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Performance / Bug | config/loja_config.php: queries N+1 por chave, poluição de cache por  e falta try/catch |
| **#071** | **18,00** | 6 | 2 | 🟠 Requer opinião humana | Bug UI / Responsividade | _cliente.css: .badge-principal duplicado/contraditório e contradição mobile (oculta .logout-link e span morto) |
| **#072** | **18,00** | 6 | 2 | 🟠 Requer opinião humana | Bug UI / Acessibilidade | Ocultação global de scrollbars quebra scrollbar de .custom-select-options e remove affordance em tabelas/modais |
| **#073** | **18,00** | 6 | 2 | 🟠 Requer opinião humana | Segurança / Bug | apagar_logs_massa.php tem ramo oculto TRUNCATE TABLE sem log + bugs de paginação/LIKE/UTF-8 em login_logs.php |
| **#074** | **18,00** | 6 | 2 | 🟠 Requer opinião humana | Bug / Segurança | .htaccess: redirect HTTPS trata PUT/PATCH/DELETE com 301, falha em localhost com porta/[::1] e HSTS falha atrás de proxy |
| **#075** | **18,00** | 6 | 2 | 🟠 Requer opinião humana | Segurança / Bug | confirmacao.js: innerHTML em título/mensagem, const abrirModal global, leak de listener e precedência form vs link |
| **#076** | **18,00** | 6 | 2 | 🟠 Requer opinião humana | Segurança / Política de Negócio | Revisão de política RBAC: admin regular pode apagar encomendas pagas/pendentes, suspender clientes e alterar portes/preços |
| **#077** | **18,00** | 6 | 2 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Bug | minha-conta-encomenda.php: classe status-* sem escape, link GLS sem ?match=, soma portes em recolha e expõe 'incompleta' |
| **#078** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Performance | image_upload.php: sem re-encoding GD, sem limite de dimensões (decompression bomb) e base64_decode antes do limite |
| **#079** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Integridade de Dados | portes.php & ajax_salvar_portes.php: max=0 contorna overlap, países duplicados por case, sem rollback e cor de erro presa |
| **#080** | **16,33** | 7 | 3 | 🟠 Requer opinião humana | Segurança / Bug | editar_admin.php / apagar_admin.php / listar_admins.php: race no último superadmin, estado corrompido, banner morto e sem session_regenerate_id |
| **#081** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug | Bugs de UI/JS em _form_produto.php, admin_produtos.php e detalhes_encomenda.php (double-submit, .loading preso, $ em replace) |
| **#082** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Pagamentos | Aritmética monetária em float IEEE-754 causa drift de arredondamento e falha portes grátis no limite exato |
| **#083** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Concorrência | Deadlock em restore_order_stock(), leak de statements, rollback sem try/catch e htmlspecialchars em Exception |
| **#084** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug UI / Responsividade | Bugs responsivos em Page CSS: resumo mobile do checkout preso em scrollbox, _ver-logs min-height, grids <360px e .home-editable mobile |
| **#085** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / UX | multiCropper.js: modal de recorte preso (sem cancelar/Escape), leak de Cropper, null canvas crash e double-click |
| **#086** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Segurança | carrinho.js: bypass // em safeImageUrl, stock===0 deixa botão + ativo, storage sem sanitização/try-catch e link relativo |
| **#087** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Segurança | minha-conta-moradas.php: apagar/desmarcar principal deixa sem principal, mass assignment  e província suja |
| **#088** | **16,33** | 7 | 3 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Acessibilidade | minha-conta-dados.php: alteração de password sem rate-limit, sem PRG, sem rotação CSRF e modal/HTML5 inválidos |
| **#089** | **16,20** | 9 | 5 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Concorrência / Segurança / Pagamentos | ajax_create_payment_intent.php: Stripe HTTPS dentro de transação FOR UPDATE, rollback pós-commit e DoS de stock |
| **#090** | **16,20** | 9 | 5 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Arquitetura | Bypass CSRF (hash_equals('', '')) e TypeError PHP 8 + HTTP 200 em erros em 25+ endpoints admin/dev/mass-action |
| **#091** | **16,00** | 8 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Integridade de Dados | Divergências graves de validação de produtos (Quick Edit salta duplicados, vírgulas apagadas, stock/peso negativos, 0 variações) |
| **#092** | **16,00** | 8 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Bug | entrar.php: enumeração de admins/contas, sem rate-limit IP, race em tentativas e lockout de perfil duplo |
| **#093** | **16,00** | 8 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug UI / Arquitetura CSS | Seletor bare button/button:hover em _buttons.css corrompe .ui-btn/.ui-tab e causou 417 !important |
| **#094** | **16,00** | 8 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Segurança / Integridade de Dados | ajax_backup_db.php: brute-force password sem rate-limit, sem audit log e OOM/corrupção no dump SQL |
| **#095** | **16,00** | 8 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Perda de Dados | Galeria de imagens dev: colisão entre pastas apaga ficheiro errado, default.jpg desprotegido e hero/ ignorado |
| **#096** | **16,00** | 8 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Fiabilidade | modalProduto.js: race assíncrona, JSON.parse fora de try trava modal, preço '0.00' truthy dá €0,00, Escape/popstate/touch |
| **#097** | **16,00** | 8 | 4 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / Concorrência / UX | estado_encomenda.php: GET muta BD/stock sem FOR UPDATE, HTTP 200 em 404, timeline negativa e meses em inglês |
| **#098** | **16,00** | 4 | 1 | ✅ RESOLVIDO (🟢 Não requer opinião humana) | Bug / SEO | Rota amigável /produto/{slug}/ com barra final devolve 404 |
| **#099** | **16,00** | 4 | 1 | 🟠 Requer opinião humana | UX / Bug | minha-conta.php mostra KPI de encomendas limitado a 5 (LIMIT 5) |
| **#100** | **12,80** | 8 | 5 | 🟠 Requer opinião humana | Performance / Fiabilidade | limpar_abandonadas.php executa chamadas Stripe síncronas dentro de header.php (bloqueia TTFB e crashea se MySQL cair) |
| **#101** | **12,80** | 8 | 5 | 🟢 Não requer opinião humana | Bug UI / Arquitetura CSS | ✅ RESOLVIDO (Lote 5) |
| **#102** | **12,50** | 5 | 2 | 🟢 Não requer opinião humana | Bug | ✅ RESOLVIDO (Lote 5) |
| **#103** | **12,50** | 5 | 2 | 🟢 Não requer opinião humana | Bug / UX | ✅ RESOLVIDO (Lote 5) |
| **#104** | **12,50** | 5 | 2 | 🟢 Não requer opinião humana | Bug / Arquitetura | ✅ RESOLVIDO (Lote 5) |
| **#105** | **12,50** | 5 | 2 | 🟠 Requer opinião humana | Bug / Operações | ajax_criar_encomenda_fake.php cria encomendas em produção sem log e com campos incoerentes com o estado |
| **#106** | **12,50** | 5 | 2 | 🟠 Requer opinião humana | SEO / Fiabilidade | sitemap.php gera <lastmod> falso (data atual) e devolve HTTP 200 com XML truncado se query SQL falhar |
| **#107** | **12,50** | 5 | 2 | 🟢 Não requer opinião humana | Bug / SEO | ✅ RESOLVIDO (Lote 5) |
| **#108** | **12,50** | 5 | 2 | 🟠 Requer opinião humana | Regra de Negócio | CartService: precedência entre preco_variacao e promo_base (promoção base ignorada se variação tiver preço) |
| **#109** | **12,25** | 7 | 4 | 🟢 Não requer opinião humana | Bug / Integridade de Dados | ✅ RESOLVIDO (Lote 5) |
| **#110** | **12,25** | 7 | 4 | 🟠 Requer opinião humana | Bug / Integridade de Dados | gerir_atributos.php: modal de eliminação mente sobre limpeza em produtos e renomear atributo quebra filtros |
| **#111** | **12,25** | 7 | 4 | 🟠 Requer opinião humana | Segurança | Lacunas de sessão/auth em cliente_auth.php, auth.php e session.php (revogação não revalidada, logout sem regenerar ID) |
| **#112** | **12,25** | 7 | 4 | 🟢 Não requer opinião humana | Bug / Segurança / UI | ✅ RESOLVIDO (Lote 5) |
| **#113** | **12,25** | 7 | 4 | 🟢 Não requer opinião humana | Acessibilidade | ✅ RESOLVIDO (Lote 5) |
| **#114** | **12,25** | 7 | 4 | 🟢 Não requer opinião humana | Bug UI / Arquitetura CSS | ✅ RESOLVIDO (Lote 5) |
| **#115** | **12,25** | 7 | 4 | 🟢 Não requer opinião humana | Acessibilidade / Performance / Bug | ✅ RESOLVIDO (Lote 5) |
| **#116** | **12,00** | 6 | 3 | 🟠 Requer opinião humana | Arquitetura / Segurança | detalhes_encomenda.php: pedido GET executa chamadas síncronas ao Stripe (30s), muta BD e envia email |
| **#117** | **12,00** | 6 | 3 | 🟢 Não requer opinião humana | Performance / Bug / Segurança | ✅ RESOLVIDO (Lote 5) |
| **#118** | **12,00** | 6 | 3 | 🟠 Requer opinião humana | Bug / Ligação entre Ficheiros | dev/ajax_save_header, home e trocas: upload órfão em erro BD, chaves desalinhadas home_hero_cta vs home_hero_btn e falta is_array |
| **#119** | **12,00** | 6 | 3 | 🟢 Não requer opinião humana | Bug / Arquitetura | ✅ RESOLVIDO (Lote 5) |
| **#120** | **12,00** | 6 | 3 | 🟢 Não requer opinião humana | Segurança / Fiabilidade | ✅ RESOLVIDO (Lote 5) |
| **#121** | **12,00** | 6 | 3 | 🟠 Requer opinião humana | Performance / SEO / Segurança | header.php: query de categorias duplicada/desnecessária em admin, canonical com ?utm_*, CDNs sem SRI e logout mobile duplicado |
| **#122** | **12,00** | 6 | 3 | 🟢 Não requer opinião humana | SEO / Performance / Acessibilidade | ✅ RESOLVIDO (Lote 5) |
| **#123** | **12,00** | 6 | 3 | 🟢 Não requer opinião humana | Bug / Segurança | ✅ RESOLVIDO (Lote 5) |
| **#124** | **12,00** | 6 | 3 | 🟠 Requer opinião humana | Bug / Performance | ShippingService.php: gap entre escalões cobra taxa máxima,  ignorado, sem cache e código postal sem hífen rejeitado |
| **#125** | **11,57** | 9 | 7 | 🟠 Requer opinião humana | Bug Crítico / Integridade de Dados | editar.php apaga e recria TODOS os IDs de produto_variacoes e produto_imagens em cada edição |
| **#126** | **9,00** | 6 | 4 | 🟠 Requer opinião humana | Segurança | Vetores de enumeração de contas em ajax_check_email_checkout.php, registar.php e timing/flood em recuperar-conta.php |
| **#127** | **9,00** | 6 | 4 | 🟢 Não requer opinião humana | Performance / CSS | ✅ RESOLVIDO (Lote 5) |
| **#128** | **9,00** | 6 | 4 | 🟢 Não requer opinião humana | Performance / SEO / Ligação entre Ficheiros | ✅ RESOLVIDO (Lote 5) |
| **#129** | **9,00** | 6 | 4 | 🟠 Requer opinião humana | Performance | Queries N+1 severas e payloads excessivos no painel Admin (eliminações em massa, duplicação, listagens e detalhes) |
| **#130** | **9,00** | 3 | 1 | 🟠 Requer opinião humana | Arquitetura & Limpeza | Código morto, ficheiros órfãos (back_button.php, admin/logout.php, mbway.png) e higiene menor em Admin/Dev/Config |
| **#131** | **9,00** | 3 | 1 | 🟢 Não requer opinião humana | Performance / Configuração | ✅ RESOLVIDO (Lote 5) |
| **#132** | **9,00** | 3 | 1 | 🟢 Não requer opinião humana | Acessibilidade / UX / Limpeza | ✅ RESOLVIDO (Lote 5) |
| **#133** | **8,33** | 5 | 3 | 🟠 Requer opinião humana | Ligação entre Ficheiros / Arquitetura | Esquemas JSON inconsistentes, validação frouxa de IDs negativos/existência e falta de GET check em endpoints admin |
| **#134** | **8,33** | 5 | 3 | 🟢 Não requer opinião humana | Bug / Limpeza | ✅ RESOLVIDO (Lote 5) |
| **#135** | **8,33** | 5 | 3 | 🟠 Requer opinião humana | Acessibilidade | Falhas de contraste WCAG AA (<4.5:1): dourado #D4AF37 em _404.css (1.96:1) e --cor-slate-400/#999/#aaa sobre branco |
| **#136** | **8,33** | 5 | 3 | 🟠 Requer opinião humana | Performance / Bug / Limpeza | dev.php, uso_armazenamento.php, previews.php e ajax_toggle_header_scroll.php: código morto, scan síncrono divergente e CSS ?v=time() |
| **#137** | **8,33** | 5 | 3 | 🟢 Não requer opinião humana | Bug / Performance | ✅ RESOLVIDO (Lote 6) |
| **#138** | **8,00** | 4 | 2 | 🟢 Não requer opinião humana | Segurança / Acessibilidade | ✅ RESOLVIDO (Lote 6) |
| **#139** | **8,00** | 4 | 2 | 🟠 Requer opinião humana | UX / Bug / Acessibilidade | trocas.php: debounce 700ms perde edições ao sair rápido, paste HTML corrompe editor, falta <h1> e edição mobile inconsistente |
| **#140** | **7,20** | 6 | 5 | 🟢 Não requer opinião humana | Acessibilidade | ✅ RESOLVIDO (Lote 6) |
| **#141** | **6,00** | 6 | 6 | 🟢 Não requer opinião humana | Arquitetura & Limpeza | ✅ RESOLVIDO (Lote 6) |
| **#142** | **5,33** | 4 | 3 | 🟠 Requer opinião humana | Dependências / Legal | PHPMailer 6.10.0 desatualizado (atual 7.2.0) e texto completo da licença LGPL-2.1 em falta |
| **#143** | **5,00** | 5 | 5 | 🟢 Não requer opinião humana | Arquitetura & Limpeza | ✅ RESOLVIDO (Lote 6) |
| **#144** | **4,50** | 6 | 8 | 🟠 Requer opinião humana | Arquitetura & Limpeza / UI | Guerra dos 5 sistemas de botões (_button-system.css anula contraste primário/secundário) e 417 !important no CSS |
| **#145** | **4,00** | 2 | 1 | 🟠 Requer opinião humana | Documentação / Higiene | Contradições na documentação (DESIGN.md e README.md) e .agents/ ausente de .gitignore |
| **#146** | **2,67** | 4 | 6 | 🟠 Requer opinião humana | Arquitetura & Limpeza | Bypass de Design Tokens (200+ cores hex hardcoded), duplicação de padrões CSS entre páginas e _gerir_categorias.css com underscore |

---

## 3. Lista Rápida — Itens que Requerem Opinião Humana (41 Pontos)

Estes são os únicos pontos que dependem de uma decisão sua antes de serem alterados (ordenados por Score):

| Rank | Score | Problema | Decisão Humana Necessária |
|---|---:|---|---|
| **#005** | **64,00** | MAIL_STORE_ADDRESS ausente no .env desvia notificações da loja para noreply@toptop.pt | ✅ RESOLVIDO |
| **#007** | **50,00** | Deploy por FTP sem encriptação (porta 21), password em texto limpo e autoUpload ativo para produção | Confirmar disponibilidade de SFTP/SSH no alojamento cPanel, configurar chave SSH e definir workflow de deploy manual. |
| **#008** | **50,00** | Reutilização de password entre SMTP (MAIL_PASS) e FTP + segredos de produção em .env no webroot | Requer acesso ao alojamento/cPanel para rodar passwords, separar credenciais locais vs produção e mover .env para fora de public_html. |
| **#018** | **40,50** | Alternância Stripe LIVE <-> TEST com 1 clique sem confirmação, sem log e sem validar chaves .env | ✅ RESOLVIDO |
| **#034** | **27,00** | Confiança cega em HTTP_CF_CONNECTING_IP permite bypass total de rate-limit + exaustão de inodes em /tmp | ✅ RESOLVIDO |
| **#040** | **25,00** | history.scrollRestoration = 'manual' global em header.php quebra navegação Voltar do browser | ✅ RESOLVIDO |
| **#043** | **24,50** | Reservas presas >30 min desaparecem de reservas_stock.php + inconsistência cancelar vs apagar + leak de countdown | ✅ RESOLVIDO |
| **#049** | **24,50** | Inconsistência de domínio canónico (toptop.pt vs www.toptop.pt) sem redirect no .htaccess | ✅ RESOLVIDO |
| **#058** | **21,33** | ajax_categoria.php: renomear categoria deixa TODOS os produtos órfãos + limite 8 contornável + 6/7 casos sem try/catch | ✅ RESOLVIDO |
| **#061** | **21,33** | ajax_apagar_conta_cliente.php: omite cliente_email_verifications (risco FK), não destrói sessão, HTTP 200 e PII de rascunhos | A correção técnica (apagar verificações, destruir sessão, libertar rascunhos incompletos, HTTP codes) não precisa de opinião; apenas confirmar política RGPD sobre manter vs anonimizar PII em encomendas pagas após prazo fiscal. |
| **#066** | **20,25** | Corrupção de stock (stock fantasma e overselling) e ausência de máquina de estados em encomendas | Confirmar a matriz oficial de transições permitidas (ex.: impedir cancelar 'pago' sem reembolso Stripe; decidir se cancelar 'enviada'/'concluida' deve ou não repor stock). |
| **#071** | **18,00** | _cliente.css: .badge-principal duplicado/contraditório e contradição mobile (oculta .logout-link e span morto) | Confirmar o estilo visual pretendido para .badge-principal (pill escuro vs badge verde) e onde deve aparecer o botão de logout em mobile (<=900px). |
| **#072** | **18,00** | Ocultação global de scrollbars quebra scrollbar de .custom-select-options e remove affordance em tabelas/modais | Confirmar se esconder a scrollbar da página inteira é requisito estético intencional ou se deve restringir-se a uma classe utilitária .no-scrollbar (corrigir .custom-select-options e tabelas é objetivo). |
| **#073** | **18,00** | apagar_logs_massa.php tem ramo oculto TRUNCATE TABLE sem log + bugs de paginação/LIKE/UTF-8 em login_logs.php | Decidir se o ramo oculto tudo=1 (TRUNCATE TABLE admin_login_logs) deve ser removido por segurança ou exposto com re-autenticação. |
| **#074** | **18,00** | .htaccess: redirect HTTPS trata PUT/PATCH/DELETE com 301, falha em localhost com porta/[::1] e HSTS falha atrás de proxy | Confirmar se includeSubDomains; preload no HSTS é seguro para todos os subdomínios do alojamento (ex.: ftp.toptop.pt). |
| **#075** | **18,00** | confirmacao.js: innerHTML em título/mensagem, const abrirModal global, leak de listener e precedência form vs link | Decidir se as mensagens de confirmação devem preservar formatação <strong>/<br> (via sanitizador DOM allowlist) ou ser texto puro (textContent). |
| **#076** | **18,00** | Revisão de política RBAC: admin regular pode apagar encomendas pagas/pendentes, suspender clientes e alterar portes/preços | Decidir se o papel 'admin' regular deve poder eliminar encomendas pagas/pendentes (sem repor stock), suspender contas de clientes, editar portes ou alterar preços via Quick Edit. |
| **#080** | **16,33** | editar_admin.php / apagar_admin.php / listar_admins.php: race no último superadmin, estado corrompido, banner morto e sem session_regenerate_id | Correções de transação FOR UPDATE, session_regenerate_id, flash messages e guards são objetivas; decidir apenas se alterar própria password deve exigir password atual e se despromover outro admin deve invalidar a sessão dele. |
| **#099** | **16,00** | minha-conta.php mostra KPI de encomendas limitado a 5 (LIMIT 5) | Confirmar se o KPI deve mostrar o número total de encomendas do cliente (recomendado) ou literalmente a contagem das últimas 5. |
| **#100** | **12,80** | limpar_abandonadas.php executa chamadas Stripe síncronas dentro de header.php (bloqueia TTFB e crashea se MySQL cair) | Hardening (throttle global + try/catch no RELEASE_LOCK) é objetivo; mover a limpeza para um cron job no servidor requer confirmação da capacidade de cron do alojamento. |
| **#105** | **12,50** | ajax_criar_encomenda_fake.php cria encomendas em produção sem log e com campos incoerentes com o estado | Corrigir campos incoerentes e adicionar log é objetivo; decidir se deve pedir confirmação ou bloquear criação de encomendas fake quando em modo Stripe LIVE. |
| **#106** | **12,50** | sitemap.php gera <lastmod> falso (data atual) e devolve HTTP 200 com XML truncado se query SQL falhar | Confirmar se produtos ativos mas esgotados (stock=0) devem continuar incluídos no sitemap.php. |
| **#108** | **12,50** | CartService: precedência entre preco_variacao e promo_base (promoção base ignorada se variação tiver preço) | Confirmar regra de negócio: quando uma variação tem preço próprio (preco_variacao > 0), a promoção do produto base (promo_base) deve ou não aplicar-se? |
| **#110** | **12,25** | gerir_atributos.php: modal de eliminação mente sobre limpeza em produtos e renomear atributo quebra filtros | Decidir se renomear/apagar um atributo em uso deve atualizar em cascata o JSON produtos.atributos/variacoes, bloquear a ação quando em uso, ou apenas corrigir o texto do aviso. |
| **#111** | **12,25** | Lacunas de sessão/auth em cliente_auth.php, auth.php e session.php (revogação não revalidada, logout sem regenerar ID) | Regenerar session ID no logout e corrigir session.php são objetivos; decidir se o impacto de revalidar ativo=1/admin_role na BD a cada pedido (ou usar session_version/timeout) é desejado. |
| **#116** | **12,00** | detalhes_encomenda.php: pedido GET executa chamadas síncronas ao Stripe (30s), muta BD e envia email | Decidir se o auto-sync ao abrir a encomenda deve manter-se (endurecido com rawurlencode/helper partilhado) ou passar para um botão POST 'Verificar pagamento no Stripe'. |
| **#118** | **12,00** | dev/ajax_save_header, home e trocas: upload órfão em erro BD, chaves desalinhadas home_hero_cta vs home_hero_btn e falta is_array | Confirmar se home_hero_btn em index.php deve ser editável (alinhando ajax_save_home.php para home_hero_btn e removendo home_trust_1_* mortas). |
| **#121** | **12,00** | header.php: query de categorias duplicada/desnecessária em admin, canonical com ?utm_*, CDNs sem SRI e logout mobile duplicado | Todas as correções técnicas são objetivas; apenas confirmar qual das 2 meta tags google-site-verification (L231-L232) deve permanecer. |
| **#124** | **12,00** | ShippingService.php: gap entre escalões cobra taxa máxima,  ignorado, sem cache e código postal sem hífen rejeitado | Confirmar se is_portugal_continental() deve aceitar códigos postais sem hífen (ex.: 1000001 ou 1000 001) ou manter estritamente DDDD-DDD. |
| **#125** | **11,57** | editar.php apaga e recria TODOS os IDs de produto_variacoes e produto_imagens em cada edição | A correção para upsert in-place é objetiva; confirmar apenas se a coluna produto_variacoes.preco ainda é usada (para preservar em editar.php) ou foi descontinuada (remover de duplicar_produto.php). |
| **#126** | **9,00** | Vetores de enumeração de contas em ajax_check_email_checkout.php, registar.php e timing/flood em recuperar-conta.php | Bloquear checkout guest para emails registados e dizer 'Já existe conta' em registar.php são trade-offs deliberados de UX vs privacidade. (Adicionar rate-limit por email em recuperar-conta.php é objetivo.) |
| **#129** | **9,00** | Queries N+1 severas e payloads excessivos no painel Admin (eliminações em massa, duplicação, listagens e detalhes) | Otimizações SQL/payload são objetivas; decidir apenas o limite/paginação desejada para mensagens arquivadas em admin/mensagens.php. |
| **#130** | **9,00** | Código morto, ficheiros órfãos (back_button.php, admin/logout.php, mbway.png) e higiene menor em Admin/Dev/Config | Confirmar se admin/logout.php (sem referências internas) e public/assets/mbway.png podem ser eliminados sem quebrar bookmarks externos. |
| **#133** | **8,33** | Esquemas JSON inconsistentes, validação frouxa de IDs negativos/existência e falta de GET check em endpoints admin | Rejeitar IDs<=0, devolver 404 em affected_rows=0 e exigir GET são objetivos; confirmar apenas se vale alterar os shapes JSON acoplados a JS (ajax_get_template, ajax_get_valores, ajax_validar_produto). |
| **#135** | **8,33** | Falhas de contraste WCAG AA (<4.5:1): dourado #D4AF37 em _404.css (1.96:1) e --cor-slate-400/#999/#aaa sobre branco | Requer aprovação visual das tonalidades mais escuras para texto secundário (--cor-slate-500/600) e dourado acessível em _404.css. |
| **#136** | **8,33** | dev.php, uso_armazenamento.php, previews.php e ajax_toggle_header_scroll.php: código morto, scan síncrono divergente e CSS ?v=time() | Decidir se o código morto login_as_customer (L15-L42 de dev.php) deve ser removido ou se o botão de UI deve ser restaurado; decidir também se _dev.css/_previews.css entram no bundle style.php ou usam filemtime(). |
| **#139** | **8,00** | trocas.php: debounce 700ms perde edições ao sair rápido, paste HTML corrompe editor, falta <h1> e edição mobile inconsistente | Confirmar se a edição inline em trocas.php deve continuar ativa em mobile ou restringir-se a desktop (>992px) como em index.php e footer.php. |
| **#142** | **5,33** | PHPMailer 6.10.0 desatualizado (atual 7.2.0) e texto completo da licença LGPL-2.1 em falta | Testar compatibilidade da versão PHP de produção antes de atualizar major 6.10 -> 7.2 (repor texto completo da licença LGPL-2.1 é obrigatório e objetivo). |
| **#144** | **4,50** | Guerra dos 5 sistemas de botões (_button-system.css anula contraste primário/secundário) e 417 !important no CSS | Confirmar se botões primários admin (.btn-admin-primary, .qe-btn-save, login submit) devem permanecer pílulas brancas ou recuperar preenchimento escuro (como .ui-btn--primary em DESIGN.md) e se podemos adotar CSS @layer. |
| **#145** | **4,00** | Contradições na documentação (DESIGN.md e README.md) e .agents/ ausente de .gitignore | Confirmar se .agents/ e AUDITORIA_COMPLETA.md devem ficar em .gitignore e se DESIGN.md deve continuar público no repositório Git (L207 diz 'DON'T make DESIGN.md publicly accessible'). |
| **#146** | **2,67** | Bypass de Design Tokens (200+ cores hex hardcoded), duplicação de padrões CSS entre páginas e _gerir_categorias.css com underscore | Confirmar a profundidade desejada para migrar padrões repetidos (cards admin, pill select, editorial kicker, checkbox SVG) para ui/_primitives.css e se _portes.css deve manter índigo #4f46e5 ou adotar a paleta da loja. |

---

## 4. Detalhe Completo de Todos os Problemas (Ordenados por Score)

### P0 — Crítico / Quick Win Imediato

#### #001 — [RESOLVIDO] CSP bloqueia 3D Secure do Stripe (hooks.stripe.com) e omite diretivas de hardening [RESOLVIDO]
- **Score:** `100,00` (Benefício: **10/10** | Custo: **1/10**) | **Categoria:** Segurança / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `.htaccess:L138`
- **Evidência Técnica:** frame-src permite apenas 'self', google.com e js.stripe.com, omitindo https://hooks.stripe.com (obrigatório para desafios 3DS2/SCA na UE sob PSD2) e https://m.stripe.network. Faltam também object-src 'none', base-uri 'self', frame-ancestors 'self' e form-action 'self'.
- **Correção Recomendada:** Adicionar https://hooks.stripe.com (e https://m.stripe.network) à CSP em .htaccess:L138 e incluir object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'.

#### #002 — [RESOLVIDO] Modal de produto fica partido em 2 colunas (>=680px) em mobile/tablet com overflow horizontal [RESOLVIDO]
- **Score:** `81,00` (Benefício: **9/10** | Custo: **1/10**) | **Categoria:** Bug UI / Responsividade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/components/_modal.css:L163-L165, L751-L754, L843-L846, L971-L985`
- **Evidência Técnica:** As media queries L164 (768px) e L752 (820px) colapsam .modal-grid-conteudo para 1fr, mas a 3.ª reescrita em L843-L846 (fora de media query) volta a impor grid-template-columns: minmax(300px, 0.92fr) minmax(380px, 1fr), e a media query final em L971-L985 esqueceu grid-template-columns: 1fr.
- **Correção Recomendada:** Repor grid-template-columns: 1fr dentro de @media (max-width: 820px) na L978 (e consolidar as 3 camadas duplicadas de _modal.css).

#### #003 — [RESOLVIDO] Diretórios internos expostos via HTTP (phpmailer/, templates/, admin/components/, scratch/) e falta Options -Indexes [RESOLVIDO]
- **Score:** `81,00` (Benefício: **9/10** | Custo: **1/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `.htaccess:L35-L42`
- **Evidência Técnica:** O .htaccess bloqueia config/, includes/, admin/includes/ e logs/, mas deixa phpmailer/ (expõe versão exata 6.10.0 em /phpmailer/LICENSE e scripts PHP), templates/, admin/components/ e scratch/ acessíveis por HTTP. Falta Options -Indexes global (expõe listagem de diretórios sem index.php se ativo no Apache). Regra ^admin/config/ é morta.
- **Correção Recomendada:** Adicionar Options -Indexes no topo do .htaccess, acrescentar RewriteRule de bloqueio [F,L] para phpmailer/, templates/, admin/components/ e scratch/, e remover a regra morta ^admin/config/.

#### #004 — [RESOLVIDO] Regex .htaccess de remoção de .php quebra query strings e força 301 em style.php, sitemap.php e AJAX GET [RESOLVIDO]
- **Score:** `64,00` (Benefício: **8/10** | Custo: **1/10**) | **Categoria:** Bug / Performance / SEO | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `.htaccess:L88-L90`
- **Evidência Técnica:** RewriteCond %{THE_REQUEST} /([^.]+)\.php [NC] não exclui ? nem espaço, quebrando URLs como /entrar?next=/minha-conta.php num 301 malformado, e obriga pedidos GET de /public/css/style.php, /sitemap.php e /ajax_*.php a sofrerem um redirect 301 extra.
- **Correção Recomendada:** Restringir o regex a ^[A-Z]{3,}\s/+([^.\s?]+)\.php[\s?] e excluir /public/css/style.php, /sitemap.php e /(admin/|dev/)?ajax_.

#### #005 — [RESOLVIDO] MAIL_STORE_ADDRESS ausente no .env desvia notificações da loja para noreply@toptop.pt
- **Score:** `64,00` (Benefício: **8/10** | Custo: **1/10**) | **Categoria:** Configuração / Bug | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `.env:L17-L24, .env.example:L13-L21, admin/includes/email_handler.php:L67, L239`
- **Evidência Técnica:** email_handler.php:L239 faz fallback para MAIL_REPLY_TO_ADDRESS quando MAIL_STORE_ADDRESS não existe. Como .env define MAIL_REPLY_TO_ADDRESS='noreply@toptop.pt' e omite MAIL_STORE_ADDRESS (e MAIL_ENCRYPTION), as notificações internas de novas encomendas são enviadas para noreply@toptop.pt.
- **Correção Recomendada:** Documentar MAIL_STORE_ADDRESS e MAIL_ENCRYPTION em .env.example e configurar MAIL_STORE_ADDRESS em .env com a caixa de correio real da loja.
- **Decisão Humana Necessária:** Confirmar o endereço de email real da loja a definir em MAIL_STORE_ADDRESS.

#### #006 — [RESOLVIDO] Preloader da homepage bloqueia o site inteiro quando localStorage está desativado [RESOLVIDO]
- **Score:** `64,00` (Benefício: **8/10** | Custo: **1/10**) | **Categoria:** Bug / Fiabilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `index.php:L154-L164`
- **Evidência Técnica:** localStorage.getItem('toptop_last_curtain') e localStorage.setItem() executam fora de try/catch. Em browsers/webviews com storage bloqueado (SecurityError), o script aborta antes de abrir/remover #premium-preloader, deixando a cortina permanentemente sobre a página.
- **Correção Recomendada:** Envolver o acesso a localStorage em try/catch, ocultando/removendo imediatamente #premium-preloader em caso de exceção.

#### #007 — Deploy por FTP sem encriptação (porta 21), password em texto limpo e autoUpload ativo para produção
- **Score:** `50,00` (Benefício: **10/10** | Custo: **2/10**) | **Categoria:** Segurança / Operações | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `.vscode/sftp.json:L4-L7, L13-L44`
- **Evidência Técnica:** protocol: 'ftp' na porta 21 transmite credenciais e código PHP em cleartext, guarda a password de produção em texto limpo no disco e ativa watcher.autoUpload: true (qualquer git checkout ou edição envia logo para /public_html/). A lista ignore omite .agents/**, backups/**, *.sql, *.bak, *.key.
- **Correção Recomendada:** Migrar para SFTP com chave privada, desativar watcher.autoUpload, rodar a password do alojamento e sincronizar ignore com .gitignore.
- **Decisão Humana Necessária:** Confirmar disponibilidade de SFTP/SSH no alojamento cPanel, configurar chave SSH e definir workflow de deploy manual.

#### #008 — Reutilização de password entre SMTP (MAIL_PASS) e FTP + segredos de produção em .env no webroot
- **Score:** `50,00` (Benefício: **10/10** | Custo: **2/10**) | **Categoria:** Segurança | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `.env:L2-L10, L20, .vscode/sftp.json:L7`
- **Evidência Técnica:** MAIL_PASS em .env e password em .vscode/sftp.json são idênticos. O ficheiro .env local contém credenciais live de BD, SMTP e Stripe diretamente dentro da raiz web (public_html/.env).
- **Correção Recomendada:** Rodar ambas as passwords para valores distintos, manter apenas credenciais locais/Stripe test no .env local e, em produção, mover .env para fora de public_html com fallback no loader PHP.
- **Decisão Humana Necessária:** Requer acesso ao alojamento/cPanel para rodar passwords, separar credenciais locais vs produção e mover .env para fora de public_html.

#### #009 — [RESOLVIDO] limpar_abandonadas.php pode apagar permanentemente encomendas PAGAS [RESOLVIDO]
- **Score:** `50,00` (Benefício: **10/10** | Custo: **2/10**) | **Categoria:** Bug Crítico / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/limpar_abandonadas.php:L34-L45, L107-L120`
- **Evidência Técnica:** Ignora o retorno de stripeRequest('POST', 'payment_intents/{id}/cancel') — se o cliente pagar na janela entre GET e POST /cancel (ou houver timeout),  mantém-se true e a encomenda paga é apagada e o stock reposto. Além disso, se  estiver vazio, salta o bloco Stripe e apaga encomendas com PaymentIntent ativo.
- **Correção Recomendada:** Nunca apagar encomendas com stripe_payment_intent_id se  estiver vazio; só manter  = true quando o PI já era 'canceled' ou o POST /cancel devolveu HTTP 200 com status === 'canceled'.

#### #010 — [RESOLVIDO] Pagamentos com redirect (MB WAY, Klarna, 3DS) nunca limpam o carrinho + client_secret exposto no URL [RESOLVIDO]
- **Score:** `50,00` (Benefício: **10/10** | Custo: **2/10**) | **Categoria:** Bug Crítico / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `checkout.php:L747-L768, sucesso.php:L150-L241`
- **Evidência Técnica:** localStorage.removeItem('carrinho') só corre no ramo sem redirect de checkout.php:L764 (e mesmo quando status != succeeded/processing); sucesso.php nunca limpa o carrinho após retorno de MB WAY/Klarna/3DS. confirmPayment() não tem try/catch (prende #btn-pagar). L766 expõe payment_intent_client_secret no URL sem necessidade.
- **Correção Recomendada:** Limpar o carrinho em sucesso.php quando  === 'sucesso' (e em checkout.php apenas se succeeded/processing), envolver confirmPayment() em try/catch e remover payment_intent_client_secret do URL.

#### #011 — [RESOLVIDO] Ecrã MB WAY pendente (estado 'aguardar') é código morto — clientes MB WAY veem 'Pagamento Recusado' [RESOLVIDO]
- **Score:** `50,00` (Benefício: **10/10** | Custo: **2/10**) | **Categoria:** Bug Crítico / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `sucesso.php:L41-L139, L183-L210`
- **Evidência Técnica:** sucesso.php implementa UI para  === 'aguardar' (L183-L210), mas L92-L139 nunca atribuem 'aguardar': se o cliente MB WAY não aprovar nos 3s de polling (requires_action/requires_confirmation), cai no ecrã vermelho 'Pagamento Recusado'. Além disso, se o polling atingir succeeded, não atualiza a BD nem envia emails.
- **Correção Recomendada:** Atribuir  = 'aguardar' quando o PI permanecer em requires_action/requires_confirmation após o polling (sem redirect_status failed/canceled), e invocar o helper partilhado de finalização de pagamento quando o polling detetar succeeded.

#### #012 — [RESOLVIDO] ajax_libertar_reserva_checkout.php apaga encomenda e repõe stock em caso de erro de rede do Stripe [RESOLVIDO]
- **Score:** `50,00` (Benefício: **10/10** | Custo: **2/10**) | **Categoria:** Bug Crítico / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `ajax_libertar_reserva_checkout.php:L48-L75`
- **Evidência Técnica:** Se o GET ao Stripe falhar (_http_code != 200),  fica '' e a execução cai diretamente em release_incomplete_order_reservation(), apagando encomendas potencialmente pagas ou em curso. Ignora status 'processing', aceita 'requires_payment_method' como cancelado e não valida regex /^pi_/.
- **Correção Recomendada:** Abortar com HTTP 502 se _http_code !== 200 ou  === ''; rejeitar com HTTP 409 se status for 'succeeded' ou 'processing'; exigir cancelStatus === 'canceled'; validar formato do PaymentIntent ID.

#### #013 — [RESOLVIDO] Menu de contexto dos stat cards em admin.php nunca abre e 4 nav cards não-editáveis editam o card errado [RESOLVIDO]
- **Score:** `49,00` (Benefício: **7/10** | Custo: **1/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/admin.php:L84-L150, L288-L305, public/js/admin-ui.js:L20`
- **Evidência Técnica:** admin-ui.js:L20 ouve .nav-card mas não .editable-card/.stat-card. Assim, os 3 KPI stat cards nunca abrem menu, enquanto os 4 nav cards não editáveis abrem #ctx-menu reutilizando o handler onclick do último card editável clicado!
- **Correção Recomendada:** Trocar .nav-card por .editable-card no seletor de admin-ui.js:L20.

#### #014 — [RESOLVIDO] config/versao_site.php não usa return — cache-busting de CSS/JS preso permanentemente em ?v=1 [RESOLVIDO]
- **Score:** `49,00` (Benefício: **7/10** | Custo: **1/10**) | **Categoria:** Ligação entre Ficheiros / Performance | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/versao_site.php:L1, templates/header.php:L34, templates/footer.php:L5, dev/ajax_limpar_cache_global.php:L13-L35`
- **Evidência Técnica:** config/versao_site.php define apenas  = '1795211000' sem return. header.php e footer.php fazem  = require(...), que devolve 1 até que ajax_limpar_cache_global.php sobrescreva o ficheiro. Além disso, ajax_limpar_cache_global.php devolve 403 em vez de 405 em não-POST.
- **Correção Recomendada:** Alterar config/versao_site.php para <?php return '1795211000'; (sem ?>) e devolver 405 para método inválido em ajax_limpar_cache_global.php.

#### #015 — [RESOLVIDO] Leak de memória Tippy.js em footer.php (titulo._tippy vs produto._tippy) e crash se CDN falhar [RESOLVIDO]
- **Score:** `49,00` (Benefício: **7/10** | Custo: **1/10**) | **Categoria:** Bug / Performance | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `templates/footer.php:L943-L982`
- **Evidência Técnica:** L948 e L961 tentam destruir titulo._tippy, mas L967 instancia tippy(produto, ...). Logo, tooltips nunca são destruídos em resize/AJAX e acumulam-se infinitamente; falta também typeof tippy === 'function'.
- **Correção Recomendada:** Trocar titulo._tippy por produto._tippy em L948-L949 e L961-L962 e guardar tippy() com typeof tippy === 'function'.

#### #016 — [RESOLVIDO] Bug emPromocao: !!'0.00' em produtos.php marca produtos sem promoção como promocionais no carrinho [RESOLVIDO]
- **Score:** `49,00` (Benefício: **7/10** | Custo: **1/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `produtos.php:L1012, L1052, includes/ui/ui.php:L536, public/js/carrinho.js:L424`
- **Evidência Técnica:** ui_product_card() emite data-preco-promocional='0.00'. Em produtos.php, !!produtoCard.dataset.precoPromocional avalia '0.00' como true, exibindo preço riscado duplicado no carrinho para artigos a preço normal.
- **Correção Recomendada:** Substituir por parseFloat(produtoCard.dataset.precoPromocional) > 0 em produtos.php:L1012, L1052 e emitir '' em ui_product_card() quando  <= 0.

#### #017 — [RESOLVIDO] apagar_encomendas_massa.php apaga reservas 'incompleta' sem repor stock [RESOLVIDO]
- **Score:** `40,50` (Benefício: **9/10** | Custo: **2/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/apagar_encomendas_massa.php:L35, L47-L65, L108-L111, admin/apagar_encomenda.php:L78-L84`
- **Evidência Técnica:** Ao contrário de apagar_encomenda.php (que protege estado != 'incompleta'), apagar_encomendas_massa.php elimina encomenda_itens e encomendas de qualquer estado sem repor stock, destruindo inventário reservado; mostra ainda mensagem verde de sucesso com 0 apagadas.
- **Correção Recomendada:** Filtrar SELECT id FROM encomendas WHERE id IN (...) AND estado != 'incompleta' FOR UPDATE antes de apagar e unificar a lógica com apagar_encomenda.php num helper em OrderService.php.

#### #018 — [RESOLVIDO] Alternância Stripe LIVE <-> TEST com 1 clique sem confirmação, sem log e sem validar chaves .env
- **Score:** `40,50` (Benefício: **9/10** | Custo: **2/10**) | **Categoria:** Segurança / Operações | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `dev/dev.php:L296-L302, L409-L446, dev/ajax_toggle_stripe.php:L1-L34`
- **Evidência Técnica:** Um clique acidental no card Stripe Mode em /dev alterna imediatamente a loja live para modo TEST (ou vice-versa) sem confirmação, sem lock de duplo clique, sem verificar se as chaves existem em .env, ignorando o retorno de setLojaConfig() e sem escrever em log_seguranca().
- **Correção Recomendada:** Exigir mostrarModalConfirmacao() em dev.php, verificar chaves target em .env, validar retorno de setLojaConfig() (HTTP 500 se falhar), usar in_array estrito e registar em log_seguranca() e log_stripe().
- **Decisão Humana Necessária:** Confirmar se além do modal de confirmação e log de auditoria deve exigir re-introdução da password (como no backup BD).

#### #019 — [RESOLVIDO] Pastas de upload em public/assets/* sem .htaccess de bloqueio + blocklist incompleta em public/images/.htaccess [RESOLVIDO]
- **Score:** `40,50` (Benefício: **9/10** | Custo: **2/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/images/.htaccess:L1-L12, dev/ajax_save_header.php:L81, dev/ajax_save_home.php:L77, admin/ajax_categoria.php:L218`
- **Evidência Técnica:** public/assets/header/, hero/ e img_categorias/ recebem move_uploaded_file() sem nenhum .htaccess que impeça execução de PHP/CGI/HTML/SVG. Em public/images/.htaccess, a blocklist omite .pht, .phps, .inc, .js, .xml, .user.ini e não desativa php_flag engine.
- **Correção Recomendada:** Converter public/images/.htaccess para allowlist estrita de extensões de imagem + php_flag engine off + X-Content-Type-Options: nosniff, e replicar nas subpastas de upload de public/assets/.

#### #020 — [RESOLVIDO] Stored XSS completo em contacto.php (getFooterText sem escape) e esquemas javascript: no footer [RESOLVIDO]
- **Score:** `40,50` (Benefício: **9/10** | Custo: **2/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `contacto.php:L79-L81, L93, L105, L131-L140, dev/ajax_save_footer.php:L27-L60, templates/footer.php:L73-L78, L714-L717`
- **Evidência Técnica:** ajax_save_footer.php guarda URLs/textos sem validar esquema https?:// nem comprimentos; contacto.php imprime todos os getFooterText() diretamente em HTML/atributos sem htmlspecialchars(); footer.php permite javascript: em link_whatsapp/instagram/facebook tanto no SSR como no editor JS live.
- **Correção Recomendada:** Validar esquema ^https?://, email, telefone e tamanhos máximos em dev/ajax_save_footer.php (e remover código morto L66-L67); escapar todos os getFooterText() em contacto.php; filtrar URLs no render de footer.php/contacto.php e no JS.

#### #021 — [RESOLVIDO] Side-cart não desativa em /checkout (ID errado formCheckout vs form-dados) e carrinho vazio não redireciona [RESOLVIDO]
- **Score:** `40,50` (Benefício: **9/10** | Custo: **2/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `checkout.php:L31, L176, L269, public/js/carrinho.js:L41, L54, templates/header.php:L353`
- **Evidência Técnica:** carrinho.js:L41 procura #formCheckout, mas checkout.php usa #form-dados. O side-cart continua acessível no Passo 2 (com stock reservado e PaymentIntent criado!), dessincronizando UI/localStorage/Stripe. Além disso, abrir /checkout diretamente com carrinho vazio mostra formulário com €0,00 em vez de redirecionar.
- **Correção Recomendada:** Detetar #form-dados / .checkout-page em carrinho.js:L41, ocultar o ícone de carrinho no header em /checkout e redirecionar imediatamente para /carrinho no load de checkout.php se carrinho estiver vazio.

### P1 — Muito Alta Prioridade

#### #022 — [RESOLVIDO] Contador 'Encomendas por Enviar' no Dashboard diverge do filtro encomendas.php?estado=por_enviar [RESOLVIDO]
- **Score:** `36,00` (Benefício: **6/10** | Custo: **1/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/admin.php:L23, L93, admin/encomendas.php:L30-L31, L153-L160`
- **Evidência Técnica:** admin.php conta estado IN ('pago', 'em processamento'), mas encomendas.php filtra apenas estado = 'pago' e omite <option value='por_enviar'>, exibindo 'Todos os estados' no dropdown.
- **Correção Recomendada:** Alinhar encomendas.php:L30-L31 para estado IN ('pago', 'em processamento') e adicionar a opção 'Por Enviar' no <select>.

#### #023 — [RESOLVIDO] admin/ajax_edit_interface_label.php permite criar chaves arbitrárias ilimitadas em admin_interface_strings [RESOLVIDO]
- **Score:** `36,00` (Benefício: **6/10** | Custo: **1/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_edit_interface_label.php:L29-L38, config/interface_labels.php:L15, L45-L60`
- **Evidência Técnica:**  não tem allowlist, regex nem limites de comprimento; saveInterfaceString() usa INSERT ... ON DUPLICATE KEY UPDATE numa tabela carregada integralmente em memória em todas as páginas admin.
- **Correção Recomendada:** Restringir  à allowlist exata das 11 chaves de admin.php, exigir is_string()/trim() e limites de comprimento (título <= 120, descrição <= 255) com try/catch.

#### #024 — [RESOLVIDO] redefinir-conta.php: race no token admin (DELETE por email), falta no-store, sem limite max password nem log [RESOLVIDO]
- **Score:** `32,00` (Benefício: **8/10** | Custo: **2/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `redefinir-conta.php:L68, L92, L108, verificar-email.php, templates/header.php:L18-L28`
- **Evidência Técnica:** L92 executa DELETE FROM password_resets WHERE email = ? (sem AND token = ?), permitindo consumo de token substituído. Páginas com tokens no URL recebem Cache-Control: no-cache em vez de no-store para anónimos. Falta limite máximo de password (bcrypt trunca a 72 bytes / CPU DoS) e log_seguranca().
- **Correção Recomendada:** Filtrar DELETE por token = ? AND email = ? AND expira > NOW(), chamar send_no_cache_headers() em redefinir-conta.php e verificar-email.php, impor max 72-128 bytes na password e registar em log_seguranca().

#### #025 — [RESOLVIDO] Checkboxes escondidos com display: none inutilizam filtros e formulários para teclado e screen readers [RESOLVIDO]
- **Score:** `32,00` (Benefício: **8/10** | Custo: **2/10**) | **Categoria:** Acessibilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/pages/_products.css:L186-L188, public/css/pages/_form-produto.css:L256, L385, L637, public/css/pages/_checkout.css:L163-L195, public/css/pages/_cliente.css:L112-L144`
- **Evidência Técnica:** Filtros da loja e checkboxes do formulário de produto usam display: none (impossível focar via Tab). Em _checkout.css e _cliente.css são focáveis mas sem qualquer anel :focus-visible.
- **Correção Recomendada:** Trocar display: none por padrão visually-hidden (position:absolute; opacity:0; width:1px; height:1px) e adicionar anel :focus-visible nos checkboxes customizados.

#### #026 — [RESOLVIDO] DOM XSS em modalProduto.js (produtos relacionados, miniaturas e selects via innerHTML sem escape) [RESOLVIDO]
- **Score:** `32,00` (Benefício: **8/10** | Custo: **2/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/modalProduto.js:L440, L524, L658-L660`
- **Evidência Técnica:** p.nome, p.foto_principal, foto, nomeAtributo e valor são interpolados diretamente em innerHTML (e innerHTML += em loop O(n²) nas opções de select).
- **Correção Recomendada:** Construir os elementos com document.createElement() + .textContent/.src/.alt/.value ou usar window.escapeHtml() centralizado em utils.js.

#### #027 — [RESOLVIDO] CartService confia em selecoes enviadas pelo cliente em vez de pv.atributos da BD [RESOLVIDO]
- **Score:** `32,00` (Benefício: **8/10** | Custo: **2/10**) | **Categoria:** Segurança / Integridade de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `includes/CartService.php:L22-L36, L96`
- **Evidência Técnica:** validate_cart_items() valida preço e stock na BD mas grava json_encode(['selecoes'] ?? []) diretamente do payload JSON do browser em encomenda_itens.selecoes_atributos, sem validar contra pv.atributos nem limitar tamanho.
- **Correção Recomendada:** Selecionar pv.atributos nas queries de CartService.php e persistir os atributos autoritativos da BD (com JSON_UNESCAPED_UNICODE).

#### #028 — [RESOLVIDO] Falta validação de esquema URL (javascript:/data:) em ui.php e bypass // em getHeaderLogo() [RESOLVIDO]
- **Score:** `32,00` (Benefício: **8/10** | Custo: **2/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `includes/ui/ui.php:L89-L878, config/header_functions.php:L34`
- **Evidência Técnica:** ui.php passa href e action apenas por htmlspecialchars() (não bloqueia javascript:/data:). header_functions.php:L34 usa #^(/|https?://)#i que aceita URLs protocol-relative //externo.tld/img.jpg.
- **Correção Recomendada:** Criar ui_safe_url() em ui.php aceitando apenas caminhos relativos seguros e http/https/mailto/tel; apertar regex em getHeaderLogo() para #^(/[^/\\\\]|https?://)#i.

#### #029 — [RESOLVIDO] Fatal PHP 8 TypeError via query params array (?q[]=x e ?atributos[Cor]=Preto) + JSON_CONTAINS sem validação [RESOLVIDO]
- **Score:** `32,00` (Benefício: **8/10** | Custo: **2/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `produtos.php:L58, L71-L72, L88-L109, L292-L295, templates/header.php:L297, L387`
- **Evidência Técnica:** header.php passa ['q'] direto a htmlspecialchars() e produtos.php:L292 itera  sem is_array(), causando HTTP 500 fatal com arrays na URL. JSON_CONTAINS aceita control chars (erro SQL 500) e número ilimitado de filtros; LIKE não escapa % e _.
- **Correção Recomendada:** Validar is_string() em ['q'], sanitizar  para string => string[] com in_array estrito, validar grupos de atributos contra atributos_grupos (com limite máximo) e escapar %/_ no LIKE.

#### #030 — [RESOLVIDO] Deadlock permanente de contas não verificadas em registar.php + janela de conta ativa + sem rate-limit [RESOLVIDO]
- **Score:** `27,00` (Benefício: **9/10** | Custo: **3/10**) | **Categoria:** Bug / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `registar.php:L37-L59, verificar-email.php:L31-L32, recuperar-conta.php:L37`
- **Evidência Técnica:** Se o link de 24h expirar, verificar-email.php manda criar nova conta, mas registar.php bloqueia o email existente (ativo=0) e recuperar-conta.php ignora ativo=0 — email bloqueado para sempre. customer_create() insere ativo=1 e só depois atualiza para 0 fora de transação. Falha SMTP é ignorada e não há rate-limit.
- **Correção Recomendada:** Inserir ativo=0 numa transação com o token, permitir re-registo/reenvio para contas ativo=0 expiradas, verificar retorno booleano de enviarEmailTemplate() e adicionar rate_limit_allow('registar', 5, 600).

#### #031 — [RESOLVIDO] Race condition em stripe_webhook.php (payment_intent.canceled) deixa encomendas canceladas e envia email falso [RESOLVIDO]
- **Score:** `27,00` (Benefício: **9/10** | Custo: **3/10**) | **Categoria:** Bug Crítico / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `stripe_webhook.php:L154-L200, ajax_libertar_reserva_checkout.php:L56, L75, config/limpar_abandonadas.php:L109, L116`
- **Evidência Técnica:** Quando o cliente clica 'Editar dados' no checkout (ou a limpeza automática corre), o PI é cancelado no Stripe antes de apagar o registo local. Se o webhook payment_intent.canceled chegar primeiro, muda 'incompleta' para 'cancelada' e envia email 'cancelada_por_atraso' ao cliente que ainda está no checkout!
- **Correção Recomendada:** Em stripe_webhook.php (payment_intent.canceled): se a encomenda estava 'incompleta', libertar/apagar sem enviar email 'cancelada_por_atraso' (reservar o email apenas para transição a partir de 'pendente').

#### #032 — [RESOLVIDO] ajax_reembolsar_stripe.php: race TOCTOU com falso positivo, sem check POST, sem log do Refund ID e fuga de erro [RESOLVIDO]
- **Score:** `27,00` (Benefício: **9/10** | Custo: **3/10**) | **Categoria:** Segurança / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_reembolsar_stripe.php:L12-L142`
- **Evidência Técnica:** Não valida REQUEST_METHOD === 'POST'. Lê encomenda fora de transação, executa reembolso irreversível no Stripe e só depois tenta cancelar na BD: se outro admin mudou o estado entretanto,  é false mas a resposta diz 'Reembolso processado e encomenda cancelada com sucesso!'. Nunca guarda ['id'] nem loga via log_stripe() e devolve erro Stripe raw com HTTP 200.
- **Correção Recomendada:** Exigir POST (405), usar lock/guarda de estado antes do reembolso e devolver alerta crítico (HTTP 409 + log_stripe) se  falhar após reembolso, gravar ['id'] em log_stripe() e notas_internas, e devolver mensagem genérica (HTTP 502) em erro Stripe.

#### #033 — [RESOLVIDO] Sanitizador HTML por regex contornável em sanitize_email_history_html() (Stored XSS em admin e tracking) [RESOLVIDO]
- **Score:** `27,00` (Benefício: **9/10** | Custo: **3/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/formatters.php:L60-L69, admin/detalhes_encomenda.php:L380, estado_encomenda.php:L425`
- **Evidência Técnica:** Os regex de sanitize_email_history_html() são contornáveis por atributos separados por / (<a/onclick=...>), entidades HTML em href (&#106;avascript:) ou espaços dentro de href, e removem <table> usado pelo próprio email_handler.php.
- **Correção Recomendada:** Substituir por sanitizador DOMDocument baseado em allowlist estrita de tags (p, br, strong, b, em, i, u, ul, ol, li, a, span, div, table, tr, td) e apenas href validado por parse_url() (http/https/mailto) com rel='noopener noreferrer'.

#### #034 — [RESOLVIDO] Confiança cega em HTTP_CF_CONNECTING_IP permite bypass total de rate-limit + exaustão de inodes em /tmp
- **Score:** `27,00` (Benefício: **9/10** | Custo: **3/10**) | **Categoria:** Segurança / Performance | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `config/rate_limit.php:L4-L12, L22-L67`
- **Evidência Técnica:** rate_limit_client_ip() aceita qualquer header HTTP_CF_CONNECTING_IP forjado sem validar REMOTE_ADDR, anulando todos os rate-limits (checkout, recuperação de conta, etc.). Ficheiros .json em /tmp/toptop_rl nunca têm garbage collection (exaustão de inodes) e são reescritos mesmo quando bloqueados.
- **Correção Recomendada:** Só aceitar HTTP_CF_CONNECTING_IP quando habilitado por .env e validado contra proxies confiáveis; mover pasta para diretório privado da app com GC probabilístico; evitar ftruncate/fwrite quando bloqueado sem pruning.
- **Decisão Humana Necessária:** Confirmar se a produção está estritamente atrás da Cloudflare (e se REMOTE_ADDR deve validar CIDRs Cloudflare ou usar mod_remoteip).

#### #035 — [RESOLVIDO] Stored XSS nos Guias de Tamanho (HTML gravado e renderizado raw via innerHTML no admin e loja) [RESOLVIDO]
- **Score:** `27,00` (Benefício: **9/10** | Custo: **3/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_operations_guias.php:L22-L44, ajax_get_guia_tamanho.php:L13-L19, public/js/modalProduto.js:L299, admin/gerir_guias_tamanho.php:L267, L296`
- **Evidência Técnica:** guias_tamanho.conteudo aceita HTML arbitrário sem sanitização e é injetado diretamente via innerHTML no modal público de produto e na pré-visualização admin.
- **Correção Recomendada:** Sanitizar com DOMDocument no servidor (allowlist estrita table/thead/tbody/tfoot/tr/th/td/br sem atributos perigosos) em ajax_operations_guias.php e defensivamente em ajax_get_guia_tamanho.php / modalProduto.js.

#### #036 — [RESOLVIDO] Cadeia de Stored XSS e CSS Injection na Homepage (home_bento_layouts, home_hero_bg, foto_posicao) [RESOLVIDO]
- **Score:** `27,00` (Benefício: **9/10** | Custo: **3/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_salvar_layouts.php:L21-L28, index.php:L55, L183, L268-L285, L581`
- **Evidência Técnica:** ajax_salvar_layouts.php grava JSON arbitrário sem esquema e ignora retorno de setLojaConfig(); index.php:L581 imprime em <script> sem JSON_HEX_TAG. L183 imprime home_hero_bg sem htmlspecialchars(); L271-L285 imprimem foto_posicao/foto_zoom sem escape nem regex dentro de <style> inválido dentro de <a>.
- **Correção Recomendada:** Validar esquema estrito (chaves 4..8 -> inteiros 1..4) e verificar setLojaConfig() em ajax_salvar_layouts.php; usar JSON_HEX_* em index.php; validar regex de posição/float de zoom e trocar <style> dentro de <a> por CSS custom properties.

#### #037 — [RESOLVIDO] Cancelar/apagar reservas ou encomendas incompletas no Admin nunca cancela o Stripe PaymentIntent [RESOLVIDO]
- **Score:** `25,00` (Benefício: **10/10** | Custo: **4/10**) | **Categoria:** Bug Crítico / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_gerir_encomenda.php:L54-L98, admin/cancelar_reserva.php:L29-L68, admin/cancelar_reservas_massa.php:L19-L63, stripe_webhook.php:L57-L68`
- **Evidência Técnica:** Nenhum dos 3 endpoints consulta ou cancela stripe_payment_intent_id no Stripe antes de cancelar/apagar e repor stock. Se o cliente concluir pagamento 3DS/MB WAY/Klarna, o Stripe cobra o cliente e o webhook ignora (encomenda apagada ou já cancelada) — cobrança sem encomenda!
- **Correção Recomendada:** Para qualquer encomenda incompleta com stripe_payment_intent_id, verificar estado no Stripe (se succeeded -> marcar pago; se processing/erro -> abortar) e cancelar o PI antes de repor stock/apagar, reutilizando a lógica de limpar_abandonadas.php.

#### #038 — [RESOLVIDO] Eliminação em massa de encomendas perde filtros/paginação porque return_to só aceita encomendas.php [RESOLVIDO]
- **Score:** `25,00` (Benefício: **5/10** | Custo: **1/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/encomendas.php:L185-L196, L236, admin/apagar_encomendas_massa.php:L118-L124, .htaccess:L88-L90`
- **Evidência Técnica:** Como .htaccess redireciona para /admin/encomendas (sem .php), REQUEST_URI não casa com o regex de apagar_encomendas_massa.php (que exige .php), perdendo sempre os filtros. Eliminação individual omite return_to.
- **Correção Recomendada:** Aceitar (?:/admin/)?encomendas(?:\.php)?(\?[^\r\n]*)? em apagar_encomendas_massa.php e apagar_encomenda.php, e incluir return_to no form individual.

#### #039 — [RESOLVIDO] robots.txt aponta sitemap com 301/host errado, expõe /scratch/ e omite rotas privadas [RESOLVIDO]
- **Score:** `25,00` (Benefício: **5/10** | Custo: **1/10**) | **Categoria:** SEO / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `robots.txt:L1-L17`
- **Evidência Técnica:** Sitemap usa www.toptop.pt/sitemap.php (dispara 301 e contradiz .env), Disallow: /scratch/ divulga diretório interno desprotegido e faltam Disallow para /carrinho, /sair e /estado_encomenda (tokens).
- **Correção Recomendada:** Alinhar URL do Sitemap com host canónico sem 301, remover /scratch/ (após bloquear no .htaccess) e adicionar Disallow para /carrinho, /sair e /estado_encomenda.

#### #040 — [RESOLVIDO] history.scrollRestoration = 'manual' global em header.php quebra navegação Voltar do browser
- **Score:** `25,00` (Benefício: **5/10** | Custo: **1/10**) | **Categoria:** UX / Bug | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `templates/header.php:L203-L207`
- **Evidência Técnica:** Desativa a restauração de scroll do browser em todo o site e executa window.scrollTo(0, 0) dentro de <head> (além de estar copiado 16 vezes noutros ficheiros PHP).
- **Correção Recomendada:** Remover o bloco global em header.php (e todas as 16 cópias redundantes), ou restringir apenas à página onde seja estritamente necessário.
- **Decisão Humana Necessária:** Confirmar se forçar o scroll para o topo em todas as páginas (incluindo ao voltar atrás) foi intencional ou pode ser removido.

#### #041 — [RESOLVIDO] admin/editar.php faz duplo escape HTML no título do produto (&amp; e &#039; literais) [RESOLVIDO]
- **Score:** `25,00` (Benefício: **5/10** | Custo: **1/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/editar.php:L302-L303, templates/header.php:L172, admin/_form_produto.php:L52`
- **Evidência Técnica:** editar.php pré-escapa  com htmlspecialchars(), que é escapado novamente por header.php e ui_page_header(). L303 usa htmlspecialchars() em vez de urlencode().
- **Correção Recomendada:** Remover htmlspecialchars() de  na L302 e usar urlencode() na L303.

#### #042 — [RESOLVIDO] Apagar guia de tamanhos deixa produtos.guia_tamanho_id órfão (404 na loja) + bugs no editor de guias [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_operations_guias.php:L52-L63, admin/gerir_guias_tamanho.php:L215-L221, L339, L358-L361`
- **Evidência Técnica:** DELETE FROM guias_tamanho não limpa produtos.guia_tamanho_id = NULL nem verifica affected_rows (causa 404 na loja ou vaza erro FK). Em gerir_guias_tamanho.php, manipularTabela() crashea sem <tbody>, submit pode enviar HTML antigo sem chamar atualizarHtmlDoTextarea() e apagar o último guia não repõe .empty-state.
- **Correção Recomendada:** Numa transação em ajax_operations_guias.php, fazer UPDATE produtos SET guia_tamanho_id = NULL WHERE guia_tamanho_id = ? antes do DELETE e validar affected_rows; corrigir tBodies/submit/empty-state no JS.

#### #043 — [RESOLVIDO] Reservas presas >30 min desaparecem de reservas_stock.php + inconsistência cancelar vs apagar + leak de countdown
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/reservas_stock.php:L17-L25, L218-L227, admin/cancelar_reserva.php:L41-L65, admin/cancelar_reservas_massa.php:L41-L60`
- **Evidência Técnica:** reservas_stock.php filtra data_encomenda >= NOW() - 30 min, tornando invisíveis reservas presas (ex.: falha Stripe ou limite 5/execução). cancelar_reservas_massa.php repõe stock antes de atualizar encomendas e não verifica affected_rows. O setInterval guarda NodeList estática.
- **Correção Recomendada:** Remover filtro de 30 min (exibir badge 'Expirada' quando 00:00), atualizar estado antes de repor stock via restore_order_stock() ordenado, e consultar elementos vivos no setInterval.
- **Decisão Humana Necessária:** Decidir se o cancelamento manual de reservas em reservas_stock.php deve apagar a encomenda incompleta (igual a limpar_abandonadas.php) ou mantê-la como 'cancelada' para auditoria.

#### #044 — [RESOLVIDO] Envio SMTP bloqueante (timeout 60s) dentro de transação MySQL FOR UPDATE em ajax_gerir_encomenda.php [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Concorrência | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_gerir_encomenda.php:L38-L45, L165-L186, admin/includes/email_handler.php:L65, L200-L217`
- **Evidência Técnica:** No case 'enviar_email', enviarEmailEncomenda() é executado dentro do bloco begin_transaction() + SELECT ... FOR UPDATE (bloqueando a linha de encomenda até 60s durante rede SMTP, causando lock wait timeouts em webhooks e possível rollback após email enviado).
- **Correção Recomendada:** Adiar enviarEmailEncomenda() para depois de ->commit() (usando  como no case 'mudar_estado', ou não abrir transação FOR UPDATE para envio de email).

#### #045 — [RESOLVIDO] mensagens.php: enviarResposta() engole todos os erros silenciosamente, botão errado e email enviado antes de UPDATE BD [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Fiabilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/mensagens.php:L165, L260-L292, L328-L354, admin/ajax_responder_contacto.php:L61-L67`
- **Evidência Técnica:** enviarResposta() tem try/finally sem else nem catch (falhas SMTP/rede passam despercebidas). O modal abre com botão 'Guardar Alterações'. Drag-and-drop não tem lock por card. No backend, o email é enviado antes de UPDATE contactos SET respondida = 1 — falha na BD devolve erro e incentiva reenvio duplicado.
- **Correção Recomendada:** Adicionar else + catch com mostrarPopup('erro'), ajustar texto do botão ao abrir modal, bloquear card durante drag fetch e tratar falha pós-envio na BD separadamente em ajax_responder_contacto.php.

#### #046 — [RESOLVIDO] ajax_get_variacoes.php devolve HTTP 200 em erro (parece esgotado) e atributos=null crashea produtos.php; ajax_search sem try/catch nem rate-limit [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Segurança / Performance | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `ajax_get_variacoes.php:L14, L32, L71-L83, ajax_search.php:L17-L36, ajax_get_relacionados.php:L12-L34`
- **Evidência Técnica:** ajax_get_variacoes.php captura Exception e responde HTTP 200 (frontend interpreta como esgotado), aceita ID negativo e retorna atributos=null (crashea Object.keys() em produtos.php:L1032). ajax_search.php não tem try/catch, não tem rate-limit em LIKE '%...%' e seleciona p.atributos não usado.
- **Correção Recomendada:** Validar id > 0, normalizar atributos para objeto vazio, devolver HTTP 500 com log_app() em Throwable; adicionar try/catch, rate-limit e remover p.atributos de ajax_search.php; logar erros em ajax_get_relacionados.php.

#### #047 — [RESOLVIDO] TypeError fatal (HTTP 500 DoS) por inputs array em csrf_from_post/array, customer_save_address e format_address [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Segurança / Estabilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/csrf.php:L9-L42, includes/CustomerService.php:L81-L89, includes/OrderService.php:L12-L17`
- **Evidência Técnica:** Passar csrf_token[]=x ou campos de morada como arrays passa array a parâmetros ?string / trim(), lançando TypeError PHP 8 não tratado em toda a aplicação.
- **Correção Recomendada:** Validar is_string() em csrf_from_post()/csrf_from_array() e no valor em , e aplicar guarda is_scalar() nos extratores de morada.

#### #048 — [RESOLVIDO] 4 bugs visuais diretos no CSS Core (hover categorias, .btn-check-single invisível, borda thumbnail modal, :disabled hover) [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug UI / CSS | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/layout/_header.css:L68-L75, L191-L196, public/css/components/_admin-buttons.css:L13, L74-L114, public/css/components/_modal.css:L891-L901, public/css/components/_button-system.css:L77-L131, L211`
- **Evidência Técnica:** 1) background:transparent !important suprime hover dos links de categoria desktop. 2) .btn-check-single herda cor branca sobre #f1f5f9 (quase invisível). 3) .modal-thumbnail define border:0 anulando border-color em :hover/.ativa. 4) 47 seletores :hover com !important animam botões :disabled.
- **Correção Recomendada:** Limitar L68-L69 a links diretos de topo, adicionar cor/hover a .btn-check-single, usar border:1.5px solid transparent em .modal-thumbnail, e acrescentar :not(:disabled) aos :hover de _button-system.css.

#### #049 — [RESOLVIDO] Inconsistência de domínio canónico (toptop.pt vs www.toptop.pt) sem redirect no .htaccess
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** SEO / Configuração | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `.env:L25, .htaccess:L49-L63, robots.txt:L17, sitemap.php:L9, templates/header.php:L233, index.php:L14-L31, produtos.php:L419-L423`
- **Evidência Técnica:** .env e sitemap.php usam https://toptop.pt, mas robots.txt, header.php, index.php e produtos.php hardcodam https://www.toptop.pt, e .htaccess não redireciona entre ambos — Google Search Console sinaliza sitemap cross-host.
- **Correção Recomendada:** Adicionar redirect 301 canónico no .htaccess (excluindo localhost) e alinhar .env APP_URL, robots.txt e templates para usar o mesmo host via .
- **Decisão Humana Necessária:** Escolher o host canónico oficial: https://toptop.pt (sem www) ou https://www.toptop.pt (com www).

#### #050 — [RESOLVIDO] search.js: fetch não abortado ao limpar/fechar pesquisa, storage sem try/catch aborta navegação e formato € errado [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Fiabilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/search.js:L16, L30, L39, L41, L106-L120, L152, L158-L159, L222, L250-L273, L296`
- **Evidência Técnica:** activeSearchController.abort() não é chamado em hideResults()/closeSearch()/termo<2, reabrindo resultados fantasmas. saveHistoryTerm() sem try/catch aborta goToSearch() antes de navegar. Histórico não-string crashea toLocaleLowerCase(). Preço usa .toFixed(2) com ponto em vez de vírgula PT.
- **Correção Recomendada:** Abortar controller em closeSearch()/hideResults()/termo<2, envolver storage em try/catch, filtrar strings válidas, validar input/resultsContainer e formatar preço com vírgula.

#### #051 — [RESOLVIDO] admin-ui.js: tecla Escape falha em modais .ativo, menus de contexto não fecham ao clicar e href='#' salta para topo [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/admin-ui.js:L13-L15, L31-L49, L97-L124, L143, L147, admin/gerir_categorias.php:L120, admin/gerir_guias_tamanho.php:L77, admin/listar_admins.php:L291, admin/clientes.php:L294, admin/gestor_emails.php:L220`
- **Evidência Técnica:** admin-ui.js fecha modais só com style.display='none' sem remover .ativo (que tem display:flex !important em _quick-edit.css:L68), logo Escape não fecha modais em gerir_categorias e gerir_guias_tamanho. Clicar num item .ctx-menu não fecha o menu; abrir 2.º menu não fecha o 1.º; href='#' sem preventDefault() salta a página; classList.add(tipo) crashea com espaços; console.log deixado em L147.
- **Correção Recomendada:** Remover classe .ativo no close/Escape em admin-ui.js, fechar .ctx-menu ao clicar em .ctx-item ou abrir outro menu, usar preventDefault() nos links #, sanitizar tipo no fallback mostrarPopup e remover console.log.

#### #052 — [RESOLVIDO] Duplo escape HTML nos assuntos de email (D&#039;Angelo / &amp;) e falta sanitização CR/LF em templates [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/includes/email_handler.php:L93, L102, L118, L154-L170, L265-L281, registar.php:L51-L59, recuperar-conta.php:L42, L69`
- **Evidência Técnica:** enviarEmailEncomenda() e enviarEmailNotificacaoLoja() aplicam placeholders já escapados com htmlspecialchars() ao ->Subject; registar.php e recuperar-conta.php pré-escapam  antes de enviarEmailTemplate(). CR/LF só é limpo no ramo 'personalizado' e emails destinatários não passam por FILTER_VALIDATE_EMAIL.
- **Correção Recomendada:** Separar mapa plain-text (sem entidades HTML e sem \r\n) para Subject do mapa HTML para Body em todas as funções; enviar  raw em registar.php/recuperar-conta.php; validar emails com FILTER_VALIDATE_EMAIL.

#### #053 — [RESOLVIDO] Dupla ligação MySQL e duplo parsing de .env abertos a cada pedido em 6 páginas [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Performance | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/admin.php:L10-L11, admin/admin_produtos.php:L9-L10, admin/listar_admins.php:L10-L11, admin/clientes.php:L9-L10, admin/gestor_emails.php:L9, L15, estado_encomenda.php:L51, L62`
- **Evidência Técnica:** templates/header.php:L58 já faz require_once config/database.php. Estas 6 páginas fazem include '../config/database.php' (sem _once) depois do header, abrindo uma segunda conexão MySQL e descartando a primeira. Adicionalmente, 13 endpoints AJAX abrem ligação MySQL antes de validar auth/CSRF.
- **Correção Recomendada:** Substituir include por require_once __DIR__ . '/../config/database.php' (removendo duplicados) e mover o require_once de database.php para DEPOIS das validações de método/auth/CSRF nos 13 endpoints AJAX.

#### #054 — [RESOLVIDO] admin-ui.js não carrega em /admin (sem barra) nem em /dev/ (prende modais em design_system.php) + CSRF dev no público [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Ligação entre Ficheiros / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `templates/footer.php:L937, L1002-L1028, dev/design_system.php:L115-L191`
- **Evidência Técnica:** footer.php:L937 testa strpos(uri, '/admin/') === 0, falhando em /admin (rota limpa do .htaccess) e em /dev/* (em design_system.php, abrir modais prende o utilizador sem forma de fechar!). L1002-L1028 emitem script dev e ['csrf_token'] em todas as páginas públicas.
- **Correção Recomendada:** Carregar admin-ui.js quando o path for '/admin', '/admin/*', '/dev' ou '/dev/*'; envolver L1002-L1028 em check admin_role === 'desenvolvedor'.

#### #055 — [RESOLVIDO] envios.php:  sem escape, locales NumberFormatter inválidos, max=0 exibido como '10kg - 0kg' e HTML inválido [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `envios.php:L60-L67, L73, L104-L120`
- **Evidência Técnica:**  e getCountryName() impressos sem escape;  = strtolower().'_'.strtoupper() gera locales inválidos/errados (be_BE Bielorrusso, gb_GB inválido, lu_LU); escalão ilimitado (max=0) mostra '10kg - 0kg'; <div> dentro de <button> é HTML5 inválido e falta <h1>.
- **Correção Recomendada:** Escapar com ui_e(), usar format_money() consistente, exibir 'Mais de X kg' quando max <= 0, trocar <div class='badge-pais'> por <span> e <h2> por <h1>.

#### #056 — [RESOLVIDO] contacto.php: strlen() em bytes rejeita mensagens PT válidas <1000 chars + editor do footer crashea ao gravar morada [RESOLVIDO]
- **Score:** `24,50` (Benefício: **7/10** | Custo: **2/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `contacto.php:L8-L9, L15-L51, L78, templates/footer.php:L682-L721`
- **Evidência Técnica:** strlen() > 1000 conta bytes UTF-8 (acentos = 2 bytes), rejeitando mensagens válidas do textarea maxlength=1000; sem PRG nem persistência em erro;  ignora global_edit_mode. Ao gravar morada em contacto.php, footer.php:L693 faz querySelector('span').innerText num <p> sem <span> (TypeError) e ignora erros HTTP.
- **Correção Recomendada:** Usar mb_strlen(, 'UTF-8') > 1000, adicionar limites em nome/email + PRG, reutilizar , e corrigir footer.php:L693 com fallback e validação de resposta JSON em Promise.all.

#### #057 — [RESOLVIDO] email_handler.php: catch(Exception) só captura PHPMailerException + KeepAlive sujo + race em mensagens_cliente + logo CLI [RESOLVIDO]
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Bug / Fiabilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/includes/email_handler.php:L5, L34-L49, L114, L143, L172, L198-L228, L304-L381`
- **Evidência Técnica:** use PHPMailer\PHPMailer\Exception faz com que mysqli_sql_exception e TypeError escapem não capturados (quebrando webhooks/checkout!). Template em falta loga ErrorInfo vazio. KeepAlive não limpa Subject/Body/AltBody nem define AltBody plain-text. mensagens_cliente atualizada sem FOR UPDATE. DOCUMENT_ROOT vazio em CLI/URL absoluta quebra cid:logo_toptop.
- **Correção Recomendada:** Usar alias PHPMailerException, capturar \Throwable em todas as funções, logar ->getMessage() + ErrorInfo, limpar estado KeepAlive + preencher AltBody, envolver mensagens_cliente em FOR UPDATE, cachear .env e corrigir caminho do logo.

#### #058 — [RESOLVIDO] ajax_categoria.php: renomear categoria deixa TODOS os produtos órfãos + limite 8 contornável + 6/7 casos sem try/catch
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_categoria.php:L45-L273, admin/gerir_categorias.php:L225, L294-L301`
- **Evidência Técnica:** produtos.categoria guarda o nome string; editar categoria atualiza categorias.nome mas nunca atualiza produtos.categoria (produtos desaparecem do filtro/menu!). apagar devolve sucesso mesmo se ID não existir e não verifica produtos. Limite de 8 visíveis só existe no JS (e criar insere sempre visivel=1). 6 casos sem try/catch, leak de ficheiro no upload_capa, slugs vazios/colididos e set_posicao aceita 999.99%.
- **Correção Recomendada:** Envolver editar em transação com cascade UPDATE produtos SET categoria=?, verificar contagem no apagar (409/aviso) e retornar 404 se inexistente, impor limite de 8 no servidor, adicionar try/catch com @unlink em falha, validar slug único e clamp 0-100% em set_posicao.
- **Decisão Humana Necessária:** Cascade no rename e validação server-side são objetivos; decidir apenas se apagar categoria com produtos deve bloquear a eliminação (409) ou limpar/reatribuir produtos.categoria.

#### #059 — [RESOLVIDO] Fuga de mensagens raw SQL/Stripe em 6 endpoints admin + 6 sem try/catch + sobrescrita de HTTP 405/403/404 para 400 [RESOLVIDO]
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_mensagem_estado.php:L54-L57, admin/ajax_operations_guias.php:L65-L68, admin/ajax_quick_edit_product.php:L77-L81, admin/ajax_responder_contacto.php:L14-L75, admin/ajax_gerir_encomenda.php:L19-L221, admin/ajax_reembolsar_stripe.php:L37-L85, admin/ajax_edit_interface_label.php:L38, admin/ajax_get_template.php, admin/ajax_get_valores.php, admin/ajax_salvar_layouts.php, admin/ajax_toggle_cliente_ativo.php`
- **Evidência Técnica:** 6 endpoints enviam ->getMessage() SQL ou erro raw do Stripe ao cliente; 3 sobrescrevem http_response_code(405/403/404) com 400 no catch; outros 6 não têm try/catch (resposta vazia quebra JSON no cliente).
- **Correção Recomendada:** Separar exceções de validação de erros internos (\Throwable), logar detalhes via log_app()/log_stripe(), devolver mensagens genéricas com status HTTP correto via config/http.php e envolver todas as queries em try/catch.

#### #060 — [RESOLVIDO] Múltiplos XSS / injeção de atributos e JS em páginas admin (encomendas, detalhes, gestor_emails, portes, mensagens) [RESOLVIDO]
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/encomendas.php:L156-L157, admin/detalhes_encomenda.php:L433, L512, L604, admin/gestor_emails.php:L106-L133, L272, admin/portes.php:L133-L319, admin/mensagens.php:L99, admin/admin_produtos.php:L361, admin/_form_produto.php:L54, L238`
- **Evidência Técnica:** Valores da BD (, , , ) são impressos sem escape em HTML/onclick/JS; CSRF tokens em strings JS sem json_encode(..., JSON_HEX_*); CSS.escape em falta em gestor_emails.php:L272; mailto: sem validação/encode em mensagens.php; innerHTML em admin_produtos.php:L361.
- **Correção Recomendada:** Escapar todas as saídas HTML com ui_e(), usar json_encode(..., JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) para PHP->JS, substituir onclick inline em portes.php por delegação com data-iso e usar DOM APIs seguras.

#### #061 — ajax_apagar_conta_cliente.php: omite cliente_email_verifications (risco FK), não destrói sessão, HTTP 200 e PII de rascunhos
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Segurança / Integridade de Dados | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `ajax_apagar_conta_cliente.php:L10-L83, minha-conta-dados.php:L151`
- **Evidência Técnica:** Não apaga cliente_email_verifications (ao contrário dos scripts admin), não chama session_destroy() nem roda CSRF, crashea TypeError com password array, devolve HTTP 200 em todos os erros e deixa rascunhos 'incompleta' com PII intactos.
- **Correção Recomendada:** Apagar cliente_email_verifications e libertar encomendas 'incompleta' na transação, validar is_string(), adotar config/http.php com códigos 400/401/403/405/500 e destruir sessão/cookie (ou regenerar ID se admin).
- **Decisão Humana Necessária:** A correção técnica (apagar verificações, destruir sessão, libertar rascunhos incompletos, HTTP codes) não precisa de opinião; apenas confirmar política RGPD sobre manter vs anonimizar PII em encomendas pagas após prazo fiscal.

#### #062 — [RESOLVIDO] style.php: immutable 1 ano sem ?v=, sem ETag/304, default scope=full (450KB) e envia CSS admin ao público [RESOLVIDO]
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Performance / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/style.php:L2-L140`
- **Evidência Técnica:** Emite Cache-Control: immutable de 1 ano mesmo sem ?v= (e ignora ); sem ETag/Last-Modified/304 nem Vary: Accept-Encoding. Default scope='full' serve 450KB com banners de nomes de ficheiros admin/dev a anónimos. Mesmo em scope='public', _guia-tamanhos.css (319 linhas admin), _admin-buttons.css, _quick-edit.css e seletores admin L124-L140 vão para todos os visitantes.
- **Correção Recomendada:** Usar default scope='public', emitir immutable só quando ?v= está presente, adicionar ETag/304 + Vary, mover _guia-tamanhos.css (e após split, _quick-edit.css/_admin-buttons.css) para , e remover banners de ficheiro em produção.

#### #063 — [RESOLVIDO] Race síncrona entre gestao_atributos.js, gestao_variacoes.js e multiCropper.js + mismatch tipo de IDs de imagens [RESOLVIDO]
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/gestao_atributos.js:L31, L59-L70, L118-L146, public/js/gestao_variacoes.js:L125-L135, L198-L248, admin/_form_produto.php:L257-L269`
- **Evidência Técnica:** Em novos produtos sem atributos, gestao_atributos.js dispara 'atributosProntos' no DOMContentLoaded ANTES de gestao_variacoes.js registar o listener. window.getImagensGaleria() pode ser chamado antes de existir; 123 === '123' desmarca fotos associadas ao editar; imagens removidas ficam órfãs em imagens_associadas; gestao_atributos.js não valida r.ok/Array e tem double-click race; stock negativo é aceite.
- **Correção Recomendada:** Sinalizar estado via flag window.__atributosProntos, guardar typeof window.getImagensGaleria === 'function', comparar String(id), podar imagens removidas em galeriaAtualizada, validar r.ok/Array.isArray e stock inteiro >= 0.

#### #064 — [RESOLVIDO] Checkout: cálculo de portes cliente diverge do servidor e Passo 2 ignora total_cents autoritativo [RESOLVIDO]
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `checkout.php:L466-L496, L651-L684, public/js/carrinho.js:L116-L127, includes/ShippingService.php:L102-L115, ajax_create_payment_intent.php:L205`
- **Evidência Técnica:** Quando peso < 1.º escalão, ShippingService.php cobra o 1.º escalão mas checkout.php e carrinho.js mostram €0,00. País 'OUTRO' cai em taxas 'PT'. No Passo 2, checkout.php ignora total_cents recalculado pelo servidor, exibindo preço antigo enquanto Stripe cobra o valor atualizado.
- **Correção Recomendada:** Espelhar a lógica exata de ShippingService.php em checkout.php e carrinho.js, tratar 'OUTRO' explicitamente e sincronizar #sum-total/#btn-pagar com os valores autoritativos devolvidos pelo servidor.

#### #065 — [RESOLVIDO] Checkout & Moradas: código 'UK' rejeitado pelo Stripe (usar 'GB'), bloqueio em Recolha e telefone readonly +351 [RESOLVIDO]
- **Score:** `21,33` (Benefício: **8/10** | Custo: **3/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `checkout.php:L58, L338, L382, L530-L548, L580, L595, L625, L776, ajax_create_payment_intent.php:L129, L144, minha-conta-moradas.php:L180, L326, includes/OrderService.php:L9`
- **Evidência Técnica:** 1) Stripe exige ISO 'GB', mas vários ficheiros usam 'UK' (e ajax_create_payment_intent envia sem normalizar). 2) Escolher 'OUTRO' desativa #btn-continuar e mudar para 'Recolha' nunca reativa; voltar a 'Envio' não repõe required na província. 3) Telefone readonly com '+351'/espaços falha regex PT e impede finalizar. 4) Recolha grava 'Portugal' em cliente_morada. 5) Usa alert() bloqueante e URLs AJAX relativos.
- **Correção Recomendada:** Mapear/migrar 'UK'->'GB' (aceitando ambos na leitura), reativar botão em Recolha e reexecutar fetchAddressConfig em Envio, normalizar telefone PT antes do regex, gravar 'Recolha na Loja' em cliente_morada, usar mostrarPopup() e caminhos raiz /ajax_*.php.

#### #066 — Corrupção de stock (stock fantasma e overselling) e ausência de máquina de estados em encomendas
- **Score:** `20,25` (Benefício: **9/10** | Custo: **4/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_gerir_encomenda.php:L100-L143, admin/detalhes_encomenda.php:L158-L171, L412-L456, L635-L639`
- **Evidência Técnica:** Reativar encomenda 'cancelada' usa GREATEST(0, quantidade - ?): se stock=0, reativa sem stock (overselling) e cancelar de novo adiciona +quantidade criando stock fantasma! Backend aceita qualquer transição (inclusive sair de 'concluida'/'cancelada') e UI permite cancelar encomenda 'pago' no dropdown sem reembolso Stripe. Ramo JS tipoCancelamento==='paga' é morto.
- **Correção Recomendada:** Impor máquina de estados no backend e UI, bloquear transição de estados terminais, exigir reembolso Stripe para cancelar 'pago', e bloquear variações com SELECT ... ORDER BY id ASC FOR UPDATE verificando quantidade >= necessária antes de reativar.
- **Decisão Humana Necessária:** Confirmar a matriz oficial de transições permitidas (ex.: impedir cancelar 'pago' sem reembolso Stripe; decidir se cancelar 'enviada'/'concluida' deve ou não repor stock).

#### #067 — [RESOLVIDO] stripe_webhook.php e 3 fallbacks: falta validação de moeda (eur), pagamentos órfãos/pós-cancel sem alerta e email loja bloqueado [RESOLVIDO]
- **Score:** `20,25` (Benefício: **9/10** | Custo: **4/10**) | **Categoria:** Segurança / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `stripe_webhook.php:L63-L142, sucesso.php:L41-L63, estado_encomenda.php:L99-L127, config/limpar_abandonadas.php:L47-L75`
- **Evidência Técnica:** Webhook não valida currency === 'eur'; se encomenda foi apagada ou já estava 'cancelada' ao chegar payment_intent.succeeded, retorna 200 sem alerta crítico (dinheiro cobrado sem encomenda!); sucesso.php, estado_encomenda.php e limpar_abandonadas.php duplicam a finalização SEM validar valor nem moeda; erro no email ao cliente impede enviarEmailNotificacaoLoja().
- **Correção Recomendada:** Extrair helper único finalize_paid_stripe_order() em OrderService.php validando moeda EUR + montante exato; gerar alerta CRITICAL/email loja em pagamentos órfãos ou pós-cancelamento; separar try/catch dos emails de cliente e loja; validar regex ^pm_.

### P2 — Alta Prioridade

#### #068 — [RESOLVIDO] gerir_categorias.php: click bubbling no input file, blob quebrado em erro de upload e ordenação id ASC vs ordem ASC [RESOLVIDO]
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/gerir_categorias.php:L15, L49-L71, L358-L418`
- **Evidência Técnica:** <input type='file'> dentro de div com onclick duplica disparo ao clicar; pré-visualização troca imediatamente para ObjectURL e se o upload falhar revoga o blob deixando <img> quebrada (ou faz leak em exceção); query ordena por id ASC em vez de ordem ASC, id ASC.
- **Correção Recomendada:** Travar propagação no input file, guardar src anterior e restaurar em erro (revogando ObjectURL no finally) e ordenar por ORDER BY ordem ASC, id ASC.

#### #069 — [RESOLVIDO] gerir_atributos.php: edição inline colapsa card a cada clique, auth após <header> e crashes/toasts corrompidos [RESOLVIDO]
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Bug / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/gerir_atributos.php:L14-L85, L119-L131, L265-L284, L366-L443`
- **Evidência Técnica:** Input de edição inline fica dentro de .attribute-info, logo clicar no input/guardar/cancelar alterna .collapsed! Check de role ocorre após renderizar header.php (devolve 200 com layout completo). L85 faz null offset se grupo não existir; toast JSON tem duplo htmlspecialchars(); Ordenar A-Z em grupo vazio dá erro; Sortable sem guarda CDN.
- **Correção Recomendada:** Mover check de auth para o topo (redirect/403 JSON), excluir .editable-container-wrapper no handler de clique, guardar null em L85, remover htmlspecialchars() do JSON toast, sair cedo se items<2 e guardar typeof Sortable.

#### #070 — [RESOLVIDO] config/loja_config.php: queries N+1 por chave, poluição de cache por  e falta try/catch [RESOLVIDO]
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Performance / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/loja_config.php:L6-L49`
- **Evidência Técnica:** Executa 1 SELECT preparado por cada chave pedida; se a chave não existir, guarda  do primeiro caller na cache estática (corrompendo chamadas seguintes com outro default); falha fatalmente sem try/catch sob MYSQLI_REPORT_STRICT.
- **Correção Recomendada:** Pré-carregar toda a tabela loja_configuracoes numa única query (dentro de try/catch), nunca guardar  na cache e atualizar a cache apenas se inicializada em setLojaConfig().

#### #071 — _cliente.css: .badge-principal duplicado/contraditório e contradição mobile (oculta .logout-link e span morto)
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Bug UI / Responsividade | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `public/css/pages/_cliente.css:L1053-L1062, L1276-L1287, L1314-L1332, L1465-L1481`
- **Evidência Técnica:** .badge-principal é definido como pill escuro em L1053 e sobrescrito por badge verde em L1276 (herdando text-transform do 1.º). A 900px o avatar encolhe para 32px e .logout-link + .cliente-details span ficam display:none; a 560px o avatar cresce para 40px e estiliza o span já oculto.
- **Correção Recomendada:** Eliminar a definição duplicada de .badge-principal, reconciliar tamanhos 900px/560px e garantir acesso visível a logout em mobile.
- **Decisão Humana Necessária:** Confirmar o estilo visual pretendido para .badge-principal (pill escuro vs badge verde) e onde deve aparecer o botão de logout em mobile (<=900px).

#### #072 — Ocultação global de scrollbars quebra scrollbar de .custom-select-options e remove affordance em tabelas/modais
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Bug UI / Acessibilidade | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `public/css/base/_utilities.css:L5-L17, public/css/components/_form-components.css:L166-L180`
- **Evidência Técnica:** ::-webkit-scrollbar { display: none; } e * { scrollbar-width: none; } anulam a scrollbar customizada de 8px definida em _form-components.css e escondem o indicador de scroll horizontal nas tabelas admin.
- **Correção Recomendada:** Restringir a ocultação a .no-scrollbar ou, no mínimo, restaurar display:block e scrollbar-width:thin em .custom-select-options e .table-wrapper/.ui-table-wrap.
- **Decisão Humana Necessária:** Confirmar se esconder a scrollbar da página inteira é requisito estético intencional ou se deve restringir-se a uma classe utilitária .no-scrollbar (corrigir .custom-select-options e tabelas é objetivo).

#### #073 — apagar_logs_massa.php tem ramo oculto TRUNCATE TABLE sem log + bugs de paginação/LIKE/UTF-8 em login_logs.php
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `dev/apagar_logs_massa.php:L1-L39, dev/login_logs.php:L14-L54, L119, L134`
- **Evidência Técnica:** tudo=1 faz TRUNCATE TABLE admin_login_logs sem existir botão na UI e sem registo em log_seguranca(); reporta count() em vez de affected_rows. login_logs.php não faz clamp de , não escapa %/_ em LIKE, usa substr() cortando caracteres UTF-8 e #btn-sel-mode falta type='button'.
- **Correção Recomendada:** Remover tudo=1 (ou exigir re-auth), logar eliminações em log_seguranca(), usar affected_rows, clamp de , escapar LIKE, usar mb_substr() e adicionar type='button'.
- **Decisão Humana Necessária:** Decidir se o ramo oculto tudo=1 (TRUNCATE TABLE admin_login_logs) deve ser removido por segurança ou exposto com re-autenticação.

#### #074 — .htaccess: redirect HTTPS trata PUT/PATCH/DELETE com 301, falha em localhost com porta/[::1] e HSTS falha atrás de proxy
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Bug / Segurança | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `.htaccess:L48-L63, L131`
- **Evidência Técnica:** L49 usa !POST (manda 301 em vez de 308 para PUT/PATCH/DELETE); exclusão localhost não casa localhost:8080 nem [::1] (presente em logs/sql.log!); HSTS verifica apenas %{HTTPS}=='on' ignorando X-Forwarded-Proto.
- **Correção Recomendada:** Usar ^(GET|HEAD)$ vs !^(GET|HEAD)$, excluir (localhost|127\.0\.0\.1|\[::1\])(:\d+)? e incluir %{HTTP:X-Forwarded-Proto} == 'https' na condição HSTS.
- **Decisão Humana Necessária:** Confirmar se includeSubDomains; preload no HSTS é seguro para todos os subdomínios do alojamento (ex.: ftp.toptop.pt).

#### #075 — confirmacao.js: innerHTML em título/mensagem, const abrirModal global, leak de listener e precedência form vs link
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `public/js/confirmacao.js:L3-L83, public/js/search.js:L204, public/js/admin-ui.js:L64-L66`
- **Evidência Técnica:** tituloEl/textoEl usam innerHTML; const abrirModal vaza no escopo global; reabrir modal acumula listeners clickFora; target.closest('form') vence sobre <a href>; faltam validações de URL same-origin em confirmacao.js, search.js e admin-ui.js.
- **Correção Recomendada:** Envolver confirmacao.js em IIFE, usar textContent no título e allowlist/textContent na mensagem, limpar listeners anteriores, priorizar links <a> quando aplicável e validar origem/esquema de URLs.
- **Decisão Humana Necessária:** Decidir se as mensagens de confirmação devem preservar formatação <strong>/<br> (via sanitizador DOM allowlist) ou ser texto puro (textContent).

#### #076 — Revisão de política RBAC: admin regular pode apagar encomendas pagas/pendentes, suspender clientes e alterar portes/preços
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Segurança / Política de Negócio | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/apagar_encomenda.php, admin/apagar_encomendas_massa.php, admin/ajax_toggle_cliente_ativo.php:L8, admin/ajax_gerir_encomenda.php:L27, admin/ajax_quick_edit_product.php:L7`
- **Evidência Técnica:** Qualquer admin regular pode apagar permanentemente registos de encomendas pagas (com impacto fiscal e sem repor stock), editar portes_envio, suspender clientes ou alterar preços.
- **Correção Recomendada:** Conforme decisão de negócio, bloquear eliminação de encomendas pagas/pendentes (ou restringir ações sensíveis a admin_has_role(['superadmin', 'desenvolvedor'])).
- **Decisão Humana Necessária:** Decidir se o papel 'admin' regular deve poder eliminar encomendas pagas/pendentes (sem repor stock), suspender contas de clientes, editar portes ou alterar preços via Quick Edit.

#### #077 — [RESOLVIDO] minha-conta-encomenda.php: classe status-* sem escape, link GLS sem ?match=, soma portes em recolha e expõe 'incompleta' [RESOLVIDO]
- **Score:** `18,00` (Benefício: **6/10** | Custo: **2/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `minha-conta-encomenda.php:L20-L127`
- **Evidência Técnica:** status-{} sem escape nem lowercase; link GLS omite ?match=tracking e noreferrer; soma portes mesmo em recolha; não exclui estado != 'incompleta' (expõe rascunhos); morada de recolha hardcoded; array_values() crashea se JSON for escalar; 404 omite sidebar.
- **Correção Recomendada:** Escapar classe CSS, acrescentar ?match= + noreferrer, zerar portes em recolha, filtrar estado != 'incompleta', ler morada via getFooterText(), validar is_array() e incluir sidebar/botão voltar no 404.

#### #078 — [RESOLVIDO] image_upload.php: sem re-encoding GD, sem limite de dimensões (decompression bomb) e base64_decode antes do limite
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Segurança / Performance | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/includes/image_upload.php:L15-L55`
- **Evidência Técnica:** getimagesizefromstring() só lê cabeçalhos (não remove EXIF/GPS nem polyglots), não limita largura×altura, descodifica base64 gigante em RAM antes de testar maxBytes, usa uniqid() previsível e placeholders duplicados deixam ficheiros órfãos.
- **Correção Recomendada:** Validar tamanho da string base64 antes do decode, limitar dimensões (ex.: <=6000px), re-codificar com GD para limpar metadados/polyglots, usar bin2hex(random_bytes(16)) e validar unicidade de placeholders.

#### #079 — [RESOLVIDO] portes.php & ajax_salvar_portes.php: max=0 contorna overlap, países duplicados por case, sem rollback e cor de erro presa
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/portes.php:L193-L364, admin/ajax_salvar_portes.php:L24-L63`
- **Evidência Técnica:** max=0 (ilimitado) faz r.min < r2.max && r.max > r2.min dar false, permitindo sobreposições (e servidor não valida overlaps). Chaves 'pt'+'PT' criam regras duplicadas. Apagar último país altera DOM sem rollback quando servidor rejeita {}. Cor vermelha em autosave-status nunca é limpa e falta AbortController.
- **Correção Recomendada:** Tratar max===0 como Infinity no cliente e servidor, rejeitar códigos duplicados após strtoupper e validar is_finite(), guardar snapshot para rollback em erro, limpar style.color e sequenciar saves com AbortController.

#### #080 — editar_admin.php / apagar_admin.php / listar_admins.php: race no último superadmin, estado corrompido, banner morto e sem session_regenerate_id
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/editar_admin.php:L71-L209, admin/adicionar_admin.php:L32-L92, admin/apagar_admin.php:L49-L92, admin/listar_admins.php:L26-L44, L281-L288`
- **Evidência Técnica:** Contagem de último superadmin/dev em editar_admin.php corre fora de transação; erro de validação sobrescreve ['role'] desbloqueando o dropdown; ?sucesso=2 no perfil próprio é ignorado (L166 morto); alterar própria password não chama session_regenerate_id(true); apagar_admin.php reporta sucesso com affected_rows=0; menu de contexto mostra Editar em devs protegidos.
- **Correção Recomendada:** Usar transação FOR UPDATE + try/catch, preservar  original, usar ['flash_message'], chamar session_regenerate_id(true), validar affected_rows > 0 e ocultar ctxEdit em linhas .protected-badge.
- **Decisão Humana Necessária:** Correções de transação FOR UPDATE, session_regenerate_id, flash messages e guards são objetivas; decidir apenas se alterar própria password deve exigir password atual e se despromover outro admin deve invalidar a sessão dele.

#### #081 — [RESOLVIDO] Bugs de UI/JS em _form_produto.php, admin_produtos.php e detalhes_encomenda.php (double-submit, .loading preso, $ em replace)
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/_form_produto.php:L91-L379, admin/admin_produtos.php:L160-L453, admin/detalhes_encomenda.php:L176-L647`
- **Evidência Técnica:** _form_produto.php permite double-submit, chama reportValidity() num input display:none, #stock_simples oculto bloqueia submit e Object.keys(null) crashea. admin_produtos.php deixa .loading preso em erro AJAX, aninha .qe-body duplo, vaza  em 0 linhas e tem ordenar_key morto. detalhes_encomenda.php usa .replace(re, val) vulnerável a $&/ e crashea em mensagens_cliente não-array.
- **Correção Recomendada:** Adicionar lock isSubmitting, desativar #stock_simples quando oculto, guardar null em atributos, adicionar else/catch/finally em admin_produtos.php, remover .qe-body extra, fechar  sempre e usar função () => val no .replace().

#### #082 — [RESOLVIDO] Aritmética monetária em float IEEE-754 causa drift de arredondamento e falha portes grátis no limite exato
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `includes/CartService.php:L42, L85-L86, L107, includes/ShippingService.php:L158-L160, public/js/carrinho.js:L98, L106, L162, L421`
- **Evidência Técnica:** Totais são acumulados em float ( +=  * ) e comparados diretamente ( >= ). Drift binário (ex.: 49.99999999999999 >= 50.0) pode negar portes grátis no valor exato ou divergir por 1 cêntimo.
- **Correção Recomendada:** Acumular subtotais em cêntimos inteiros (round( * 100) * ) tanto em PHP como em carrinho.js e comparar em cêntimos inteiros.

#### #083 — [RESOLVIDO] Deadlock em restore_order_stock(), leak de statements, rollback sem try/catch e htmlspecialchars em Exception
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug / Concorrência | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `includes/OrderService.php:L26-L162, includes/CartService.php:L22-L51`
- **Evidência Técnica:** restore_order_stock() não ordena por variacao_id ASC (risco de deadlock com checkouts concorrentes); CartService ordena itens sem variacao_id como 0 sem ORDER BY pv.id ASC; OrderService:L60 mete htmlspecialchars() na Exception (exibe &#039; em JSON); prepared statements vazam em erros e rollback() não tem try/catch.
- **Correção Recomendada:** Agrupar/ordenar por variacao_id ASC em restore_order_stock(), adicionar ORDER BY pv.id ASC LIMIT 1 em CartService, remover htmlspecialchars() da Exception, fechar statements em finally e proteger rollback() com try/catch.

#### #084 — [RESOLVIDO] Bugs responsivos em Page CSS: resumo mobile do checkout preso em scrollbox, _ver-logs min-height, grids <360px e .home-editable mobile
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug UI / Responsividade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/pages/_checkout.css:L16-L58, public/css/pages/_ver-logs.css:L88-L178, public/css/pages/_gerir_categorias.css:L131, L297-L316, public/css/pages/_previews.css:L5, public/css/pages/_uso-armazenamento.css:L67, public/css/pages/_guia-tamanhos.css:L34, public/css/pages/_home.css:L272, L430, public/css/components/_editable-content.css:L439-L460`
- **Evidência Técnica:** Em <=960px, .ck-right mantém max-height:calc(100vh-100px) e overflow-y:auto (caixa com scroll interno no topo do checkout); #log-conteudo mantém min-height:400px em ecrãs curtos; .gc-nome-input mantém margin-left:-12px em mobile; grids com minmax(320px/300px) e min-width:400px dão overflow horizontal; .home-editable ficou de fora do media query mobile que desativa edição.
- **Correção Recomendada:** Resetar max-height:none/overflow:visible em .ck-right mobile, reduzir min-height em _ver-logs.css, resetar margin-left:0 em _gerir_categorias.css, usar min(300px, 100%) nos grids/larguras e incluir .home-editable em _editable-content.css:L439.

#### #085 — [RESOLVIDO] multiCropper.js: modal de recorte preso (sem cancelar/Escape), leak de Cropper, null canvas crash e double-click
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug / UX | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/multiCropper.js:L39-L192, admin/_form_produto.php:L243-L249`
- **Evidência Técnica:** #cropperModal não tem botão cancelar nem Escape (obriga a recortar todos os ficheiros); cropper.destroy() nunca é chamado; getCroppedCanvas() null crashea toDataURL(); duplo clique em guardar duplica imagem; falta reader.onerror e guarda para CDN Cropper/Sortable.
- **Correção Recomendada:** Guardar typeof Cropper/Sortable, desativar botão guardar até evento ready e durante save, destruir instância ao fechar, adicionar Escape/cancelar + reader.onerror e verificar canvas != null.

#### #086 — [RESOLVIDO] carrinho.js: bypass // em safeImageUrl, stock===0 deixa botão + ativo, storage sem sanitização/try-catch e link relativo
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/carrinho.js:L12-L25, L134, L150, L205-L219, L275-L351, L399, L435`
- **Evidência Técnica:** safeImageUrl aceita //externo; imgToClone.src ignora safeImageUrl; item.stock && qty >= item.stock falha quando stock===0 deixando + ativo; localStorage.setItem sem try/catch e itens corrompidos geram NaN/'02'; comparação por JSON.stringify(selecoes) falha com chaves reordenadas; link 'produtos.php' relativo quebra em subrotas.
- **Correção Recomendada:** Bloquear // em safeImageUrl e aplicá-lo ao clone, corrigir check para Number(item.stock) >= 0, sanitizar storage + try/catch, comparar por variacao_id/chaves ordenadas, guardar null em subtotal/mostrarPopup e usar /produtos.

#### #087 — [RESOLVIDO] minha-conta-moradas.php: apagar/desmarcar principal deixa sem principal, mass assignment  e província suja
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Bug / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `minha-conta-moradas.php:L24-L123, L344-L375`
- **Evidência Técnica:** Apagar ou desmarcar morada principal não promove outra; criar passa  raw a customer_save_address() sem validar pais_regiao; editar salta verificação de duplicados; trocar país para PT não limpa inputProv oculto; país fora de PORTES_JSON reverte para PT ao editar.
- **Correção Recomendada:** Promover morada restante em transação ao apagar principal, validar/construir array explícito em vez de , verificar duplicados no editar, limpar inputProv quando !hasState e preservar opção de país legado no select.

#### #088 — [RESOLVIDO] minha-conta-dados.php: alteração de password sem rate-limit, sem PRG, sem rotação CSRF e modal/HTML5 inválidos
- **Score:** `16,33` (Benefício: **7/10** | Custo: **3/10**) | **Categoria:** Segurança / Acessibilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `minha-conta-dados.php:L24-L55, L122-L232`
- **Evidência Técnica:** Verificação de password_atual não tem rate-limit; falta PRG (F5 reenvia formulário); regenera session ID sem rodar csrf_token; guarda telefone/nif como '' sem max length; aninha <div> dentro de <label> (HTML5 inválido); #modal-apagar-conta sem ARIA/Escape/Enter e r.json() sem check r.ok.
- **Correção Recomendada:** Adicionar rate_limit_allow('alterar_password_cliente', 5, 600, id), rodar csrf_token, aplicar PRG com flash session, corrigir estrutura <label>, adicionar ARIA/Escape/form ao modal e validar r.ok.

#### #089 — [RESOLVIDO] ajax_create_payment_intent.php: Stripe HTTPS dentro de transação FOR UPDATE, rollback pós-commit e DoS de stock
- **Score:** `16,20` (Benefício: **9/10** | Custo: **5/10**) | **Categoria:** Concorrência / Segurança / Pagamentos | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `ajax_create_payment_intent.php:L35, L40-L93, L106-L217`
- **Evidência Técnica:** Mantém locks InnoDB FOR UPDATE durante chamada HTTPS ao Stripe (até 15s); sem flag , erro após commit() tenta rollback e cancela PI de encomenda já gravada; atacante pode reter todo o stock por 30 min sem limite de reservas ativas por sessão; falta Idempotency-Key, mínimo 50 cêntimos, limite de itens, send_no_cache_headers() e o catch genérico esconde mensagens reais de rutura de stock.
- **Correção Recomendada:** Mover criação do PaymentIntent (com Idempotency-Key) para fora da transação longa, rastrear , libertar reserva anterior da sessão antes de criar nova, validar >=50 cêntimos e max itens, enviar no-store e distinguir erros de stock (DomainException) de erros internos.

#### #090 — [RESOLVIDO] Bypass CSRF (hash_equals('', '')) e TypeError PHP 8 + HTTP 200 em erros em 25+ endpoints admin/dev/mass-action
- **Score:** `16,20` (Benefício: **9/10** | Custo: **5/10**) | **Categoria:** Segurança / Arquitetura | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/apagar_encomendas_massa.php:L13, admin/apagar_cliente_admin.php:L13, admin/apagar_clientes_massa.php:L13, admin/duplicar_produto.php:L10, admin/apagar_massa.php:L8, admin/apagar_encomenda.php:L9, admin/apagar_admin.php:L13, admin/mensagens.php:L16, 13x admin/ajax_*.php, 7x dev/ajax_*.php`
- **Evidência Técnica:** apagar_encomendas_massa.php, apagar_cliente_admin.php e apagar_clientes_massa.php usam hash_equals(['csrf_token'] ?? '', ['csrf_token'] ?? '') — se o token não foi inicializado na sessão, enviar '' passa CSRF! Outros 15+ endpoints fazem hash_equals() manual sem is_string() (TypeError 500), verificam CSRF antes de auth, omitem POST check (dev/ajax_galeria_imagens.php) e devolvem HTTP 200 em erros de auth/CSRF/validação.
- **Correção Recomendada:** Padronizar TODOS os endpoints admin, dev e mass-actions para usar config/auth.php, config/csrf.php (csrf_from_post/csrf_from_array) e config/http.php com ordem estrita: método (405) -> auth (401/403) -> CSRF (403) -> BD.

#### #091 — [RESOLVIDO] Divergências graves de validação de produtos (Quick Edit salta duplicados, vírgulas apagadas, stock/peso negativos, 0 variações)
- **Score:** `16,00` (Benefício: **8/10** | Custo: **4/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/adicionar.php:L42-L151, admin/editar.php:L46-L232, admin/includes/validacao_produto.php:L13-L39, admin/ajax_quick_edit_product.php:L19-L64`
- **Evidência Técnica:** Quick Edit não chama validarProduto(), silencia preco_promocional inválido/com vírgula para NULL e devolve HTTP 500 enganador para 0 variações/inexistente. editar.php permite peso negativo e referencia ''; ambos permitem stock negativo, 0 variações, ativo/categoria/JSON não validados e vazam ->getMessage() SQL. validacao_produto.php aprova strings vazias.
- **Correção Recomendada:** Centralizar validação completa (preços com vírgula, peso>=0, stock>=0, >=1 variação, ativo 0|1, JSON válido, unicidade) em validacao_produto.php, invocá-lo no Quick Edit e nunca expor erros SQL.

#### #092 — [RESOLVIDO] entrar.php: enumeração de admins/contas, sem rate-limit IP, race em tentativas e lockout de perfil duplo
- **Score:** `16,00` (Benefício: **8/10** | Custo: **4/10**) | **Categoria:** Segurança / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `entrar.php:L145-L224`
- **Evidência Técnica:** Admins bloqueados exibem contagem decrescente exata e override na 5.ª tentativa (clientes não), e contas inexistentes não correm password_verify() (side-channel timing). Não existe rate-limit por IP (password spraying livre). failed_login_attempts usa read-modify-write vulnerável a concorrência. Conta não verificada incrementa falhas mesmo com password certa. Perfil duplo bloqueado cai em mensagem errada.
- **Correção Recomendada:** Adicionar rate_limit_allow('login_ip', 20, 300), unificar mensagens de erro/lockout, executar password_verify() dummy para contas inexistentes, usar UPDATE SQL atómico em failed_login_attempts, não penalizar  e corrigir lógica de perfil duplo.

#### #093 — [RESOLVIDO] Seletor bare button/button:hover em _buttons.css corrompe .ui-btn/.ui-tab e causou 417 !important
- **Score:** `16,00` (Benefício: **8/10** | Custo: **4/10**) | **Categoria:** Bug UI / Arquitetura CSS | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/components/_buttons.css:L2-L21, public/css/ui/_primitives.css:L8-L30, L596-L616, public/css/style.php:L47, L56`
- **Evidência Técnica:** _buttons.css carrega DEPOIS de ui/_primitives.css e estiliza o elemento bare button/button:hover: torna <button class='ui-btn'> UPPERCASE (diferente de <a>), faz .ui-tab:hover ficar cinzento escuro com texto branco e forçou 250 !important no Core + 167 nas Pages.
- **Correção Recomendada:** Remover bare button e button:hover de _buttons.css (manter só classes explícitas), definir text-transform:none/letter-spacing:0 e .ui-tab:hover em _primitives.css e carregar _primitives.css depois dos legados.

#### #094 — [RESOLVIDO] ajax_backup_db.php: brute-force password sem rate-limit, sem audit log e OOM/corrupção no dump SQL
- **Score:** `16,00` (Benefício: **8/10** | Custo: **4/10**) | **Categoria:** Segurança / Integridade de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `dev/ajax_backup_db.php:L24-L110`
- **Evidência Técnica:** Sem rate-limit na verificação de current_password; exports bem-sucedidos não são auditados em log_seguranca(); usa MYSQLI_STORE_RESULT (OOM silencioso entrega .sql truncado após HTTP 200); falta SET NAMES utf8mb4 (corrompe acentos PT) e snapshot transacional REPEATABLE READ; colunas binárias corrompidas; session lock aberto durante download.
- **Correção Recomendada:** Adicionar rate_limit_allow, log_seguranca() em exports, session_write_close(), SET NAMES utf8mb4 + START TRANSACTION WITH CONSISTENT SNAPSHOT, streaming com MYSQLI_USE_RESULT, hex-encoding para binários e validação de todas as queries.

#### #095 — [RESOLVIDO] Galeria de imagens dev: colisão entre pastas apaga ficheiro errado, default.jpg desprotegido e hero/ ignorado
- **Score:** `16,00` (Benefício: **8/10** | Custo: **4/10**) | **Categoria:** Bug / Perda de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `dev/ajax_galeria_imagens.php:L22-L160, dev/galeria_imagens.php:L14-L394, dev/dev.php:L59-L90`
- **Evidência Técnica:** analisar e apagar não passam tipo/pasta — se houver colisão de nome, apaga da primeira pasta encontrada! analisar usa basename exato mas apagar usa LIKE '%nome%'. default.jpg não está protegido (pode ser eliminado!). public/assets/hero/ é ignorado e dev.php diverge da contagem. Falta realpath()/!is_link(), encodeURIComponent no lightbox, e apagar-tudo faz N×5 scans deixando o botão bloqueado se falhar.
- **Correção Recomendada:** Identificar ficheiros por par (tipo, nome), proteger default.jpg + realpath()/!is_link(), incluir hero/, alinhar correspondência exata e contagem com dev.php, corrigir lightbox/estado de erro e criar ação batch.

#### #096 — [RESOLVIDO] modalProduto.js: race assíncrona, JSON.parse fora de try trava modal, preço '0.00' truthy dá €0,00, Escape/popstate/touch
- **Score:** `16,00` (Benefício: **8/10** | Custo: **4/10**) | **Categoria:** Bug / Fiabilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/modalProduto.js:L52-L1136`
- **Evidência Técnica:** Sem AbortController/requestId em abrirModalProduto (respostas tardias sobrescrevem produto aberto); JSON.parse fora do try trava overlay; v.preco='0.00' é truthy (adiciona item a €0,00 no carrinho!); quantidade sem clamp; leak de listeners load/error; endsWith casa '21.jpg' com '1.jpg'; Escape fecha modal de produto com guia aberto por cima; Back (popstate) não fecha modal; scroll touch ativa zoom.
- **Correção Recomendada:** Usar AbortController + requestId, mover JSON.parse para try, validar parseFloat(v.preco) > 0, clamp quantidade, limpar ambos listeners load/error, comparar basename exato, priorizar #modalGuiaTamanhos no Escape, ouvir popstate e usar matchMedia('(hover: hover)').

#### #097 — [RESOLVIDO] estado_encomenda.php: GET muta BD/stock sem FOR UPDATE, HTTP 200 em 404, timeline negativa e meses em inglês
- **Score:** `16,00` (Benefício: **8/10** | Custo: **4/10**) | **Categoria:** Bug / Concorrência / UX | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `estado_encomenda.php:L9, L38, L51-L195, L212-L367`
- **Evidência Técnica:** Inclui header.php antes da pesquisa (encomenda inexistente devolve HTTP 200); duplica finalização Stripe e reposição de stock sem FOR UPDATE; aplica htmlspecialchars() no token antes do SELECT; estado fora da timeline gera width:-33.33%; date('F') imprime meses em inglês; 'recolha' mostra 'Enviada' com camião; 3 estados sem ícone; foto vazia gera URL partido; falta <h1> e noreferrer.
- **Correção Recomendada:** Validar encomenda antes de header.php (404 real), usar trim() no token, delegar sync/reposição para OrderService com FOR UPDATE, clamp progresso 0-100%, formatar meses em PT, adaptar timeline para recolha e adicionar ícones em falta.

#### #098 — [RESOLVIDO] Rota amigável /produto/{slug}/ com barra final devolve 404
- **Score:** `16,00` (Benefício: **4/10** | Custo: **1/10**) | **Categoria:** Bug / SEO | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `.htaccess:L85`
- **Evidência Técnica:** Única rota amigável no .htaccess que termina em $ em vez de /?$.
- **Correção Recomendada:** Alterar para RewriteRule ^produto/([a-zA-Z0-9-]+)/?$ produto.php?slug= [L,QSA].

#### #099 — minha-conta.php mostra KPI de encomendas limitado a 5 (LIMIT 5)
- **Score:** `16,00` (Benefício: **4/10** | Custo: **1/10**) | **Categoria:** UX / Bug | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `minha-conta.php:L14, L31, L37`
- **Evidência Técnica:** A query usa LIMIT 5 e o card imprime count(), ficando preso em '5' para qualquer cliente com >5 encomendas (enquanto 'Moradas guardadas' mostra o total real). L31 omite trim() e ENT_QUOTES.
- **Correção Recomendada:** Contar total real via SELECT COUNT(*) WHERE cliente_id = ? AND estado != 'incompleta' e usar trim() + ENT_QUOTES.
- **Decisão Humana Necessária:** Confirmar se o KPI deve mostrar o número total de encomendas do cliente (recomendado) ou literalmente a contagem das últimas 5.

#### #100 — limpar_abandonadas.php executa chamadas Stripe síncronas dentro de header.php (bloqueia TTFB e crashea se MySQL cair)
- **Score:** `12,80` (Benefício: **8/10** | Custo: **5/10**) | **Categoria:** Performance / Fiabilidade | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `config/limpar_abandonadas.php:L8-L128, templates/header.php:L4`
- **Evidência Técnica:** Throttle por  faz cada novo visitante/bot disputar GET_LOCK; quem ganha o lock bloqueia a renderização HTML + session lock durante até 5-10 chamadas Stripe (15s timeout cada). RELEASE_LOCK no finally sem try/catch crashea a página se o MySQL desconectar.
- **Correção Recomendada:** Usar timestamp global antes de GET_LOCK, envolver GET_LOCK/RELEASE_LOCK em try/catch, selecionar apenas colunas necessárias e migrar execução para cron CLI (ou fastcgi_finish_request).
- **Decisão Humana Necessária:** Hardening (throttle global + try/catch no RELEASE_LOCK) é objetivo; mover a limpeza para um cron job no servidor requer confirmação da capacidade de cron do alojamento.

#### #101 — [RESOLVIDO] Colisões ativas de CSS no bundle style.php entre páginas e 4 @keyframes colididos
- **Score:** `12,80` (Benefício: **8/10** | Custo: **5/10**) | **Categoria:** Bug UI / Arquitetura CSS | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/pages/_estado-encomenda.css:L3-L6, _form-produto.css, _editar-admin.css, _uso-armazenamento.css:L140, _dashboard.css:L205, L527, _portes.css:L318, _gerir-atributos.css:L37, _gestor-emails.css:L4, _gerir_categorias.css:L8, _cart.css:L42, L222, _product-card.css:L87, _admin-ui.css:L4`
- **Evidência Técnica:** Seletores sem escopo sobrescrevem outras páginas no bundle concatenado: _estado-encomenda.css sobrescreve padding de todas .pagina-info; _form-produto e _editar-admin corrompem-se em .form-card*; _uso-armazenamento sobrescreve .dashboard-section-title; _portes vaza .input-group; _gestor-emails força fundo branco em todo o admin; _cart.css sobrescreve .spinner do modal e .item-carrinho do side-cart/checkout; .badge colide entre público e admin; 4 @keyframes colidem (fadeIn, fadeInPage, fadeInUp, spin).
- **Correção Recomendada:** Escopar seletores de página sob classes raiz (.pagina-*, .portes-page, .storage-container, etc.), extrair .form-card único e centralizar keyframes partilhados em base/_utilities.css.

#### #102 — [RESOLVIDO] paleta_cores.php: ignora retorno de setLojaConfig(), Reset bloqueado por HTML5, flash no beforeunload e fórmula accent divergente
- **Score:** `12,50` (Benefício: **5/10** | Custo: **2/10**) | **Categoria:** Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/paleta_cores.php:L23-L67, L160, L196-L320, templates/header.php:L126, L134`
- **Evidência Técnica:** Falha na BD ainda redireciona para ?guardado=1; botão Reset sem formnovalidate fica bloqueado se o input hex for inválido; #palette-current-swatch e evento reset são mortos; beforeunload pisca a cor antiga ao gravar; cálculo de accent soft (0.74) diverge de header.php (0.70/0.78).
- **Correção Recomendada:** Verificar retorno de setLojaConfig(), adicionar formnovalidate ao Reset, remover código morto/beforeunload e alinhar fórmula de cor com header.php.

#### #103 — [RESOLVIDO] clientes.php (paginação fora de limites e toggle sem lock) + gestor_emails.php (auto-resize textarea cortado por maxHeight)
- **Score:** `12,50` (Benefício: **5/10** | Custo: **2/10**) | **Categoria:** Bug / UX | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/clientes.php:L21-L64, L240-L265, admin/gestor_emails.php:L17, L133, L196-L287`
- **Evidência Técnica:** clientes.php calcula  antes de limitar  a  e permite cliques concorrentes no toggle ativo sem lock nem check r.ok (e case '3' morto). gestor_emails.php não redimensiona textarea ao escrever e maxHeight fixo corta o texto; botão guardar não desativa durante fetch.
- **Correção Recomendada:** Fazer clamp de  antes de , adicionar lock/check r.ok nos toggles, ouvir evento input no textarea (ou usar maxHeight='none' após abrir) e desativar botão durante fetch.

#### #104 — [RESOLVIDO] stripeRequest() descarta  em GET, não suporta DELETE/PUT, envia Content-Type em GET e ignora falha JSON
- **Score:** `12,50` (Benefício: **5/10** | Custo: **2/10**) | **Categoria:** Bug / Arquitetura | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/stripe.php:L21-L57`
- **Evidência Técnica:** Não faz ltrim('/') no endpoint; parâmetros em GET são ignorados e DELETE corre como GET; envia Content-Type form-urlencoded em GET; resposta HTML 502 do Stripe devolve array vazio sem indicador de erro JSON; não verifica  === false.
- **Correção Recomendada:** Anexar http_build_query() em GET, suportar CUSTOMREQUEST para DELETE/PUT, enviar Content-Type só em escritas, verificar  === false e logar respostas não-JSON.

#### #105 — ajax_criar_encomenda_fake.php cria encomendas em produção sem log e com campos incoerentes com o estado
- **Score:** `12,50` (Benefício: **5/10** | Custo: **2/10**) | **Categoria:** Bug / Operações | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `dev/ajax_criar_encomenda_fake.php:L2-L97, dev/dev.php:L268-L287`
- **Evidência Técnica:** Insere encomendas reais na BD sem log_app(); estado 'enviada' não preenche codigo_tracking; 'pronta para levantamento' mete metodo_entrega='envio' com portes 4.50; 'pagamento na entrega' mete 'MB WAY (Dev)'; usa rand() em vez de random_int().
- **Correção Recomendada:** Alinhar tracking/metodo_entrega/portes/pagamento com o estado escolhido, usar random_int(), registar via log_app() e aplicar guarda/confirmação se em LIVE.
- **Decisão Humana Necessária:** Corrigir campos incoerentes e adicionar log é objetivo; decidir se deve pedir confirmação ou bloquear criação de encomendas fake quando em modo Stripe LIVE.

#### #106 — sitemap.php gera <lastmod> falso (data atual) e devolve HTTP 200 com XML truncado se query SQL falhar
- **Score:** `12,50` (Benefício: **5/10** | Custo: **2/10**) | **Categoria:** SEO / Fiabilidade | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `sitemap.php:L10, L19-L86`
- **Evidência Técnica:** Define <lastmod> = date('Y-m-d') em todos os URLs (ignorado pelo Google), não verifica falha de ->query() (emite XML quebrado com HTTP 200) e omite ORDER BY na query de produtos.
- **Correção Recomendada:** Omitir <lastmod> falso (ou usar timestamps reais), devolver HTTP 503 em erro SQL e adicionar ORDER BY id DESC.
- **Decisão Humana Necessária:** Confirmar se produtos ativos mas esgotados (stock=0) devem continuar incluídos no sitemap.php.

#### #107 — [RESOLVIDO] produto.php:  não fechado, null deprecations PHP 8.1+, fallback imagem vazia e slug não canónico devolve 200
- **Score:** `12,50` (Benefício: **5/10** | Custo: **2/10**) | **Categoria:** Bug / SEO | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `produto.php:L7-L25, L52-L145, L211`
- **Evidência Técnica:** Falta ->close(); recalcula  e  2x; htmlspecialchars() em colunas nullable sem (string); foto_principal='' gera '/public/images/'; URL com slug errado devolve 200 em vez de 301; preço SSR omite <del> promocional; usa filemtime() em vez de .
- **Correção Recomendada:** Fechar statement, remover duplicações, cast (string), usar ?: 'default.jpg', redirecionar 301 para slug canónico, renderizar <del> em SSR e usar .

#### #108 — CartService: precedência entre preco_variacao e promo_base (promoção base ignorada se variação tiver preço)
- **Score:** `12,50` (Benefício: **5/10** | Custo: **2/10**) | **Categoria:** Regra de Negócio | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `includes/CartService.php:L5-L16`
- **Evidência Técnica:** resolve_cart_unit_price() devolve preco_variacao sempre que > 0, ignorando promo_base, e não valida promo_base < preco_base.
- **Correção Recomendada:** Validar promo_base < preco_base e alinhar a precedência exata entre PHP (CartService) e JS (modalProduto.js) segundo a regra de negócio.
- **Decisão Humana Necessária:** Confirmar regra de negócio: quando uma variação tem preço próprio (preco_variacao > 0), a promoção do produto base (promo_base) deve ou não aplicar-se?

#### #109 — [RESOLVIDO] Imagens órfãs e colisões em adicionar.php, editar.php, duplicar_produto.php e apagar_massa.php
- **Score:** `12,25` (Benefício: **7/10** | Custo: **4/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `admin/adicionar.php:L60-L108, admin/editar.php:L78-L215, admin/duplicar_produto.php:L61-L137, admin/apagar_massa.php:L25-L44`
- **Evidência Técnica:** adicionar.php:L102 acede a placeholder sem isset() e deixa uploads fora da ordem órfãos. editar.php não apaga fotos antigas omitidas da ordem nem protege default.jpg/realpath. duplicar_produto.php usa time() na referência (colide em <1s), uniqid() em loop (sobrescreve imagens!) e insere fotos inexistentes na BD. apagar_massa.php esquece produtos.foto_principal.
- **Correção Recomendada:** Calcular array_diff() para apagar imagens removidas/não usadas, proteger default.jpg + realpath(), gerar nomes com random_bytes(16) e referências únicas verificadas na BD, ignorar ficheiros ausentes na cópia e incluir foto_principal em apagar_massa.php.

#### #110 — gerir_atributos.php: modal de eliminação mente sobre limpeza em produtos e renomear atributo quebra filtros
- **Score:** `12,25` (Benefício: **7/10** | Custo: **4/10**) | **Categoria:** Bug / Integridade de Dados | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/gerir_atributos.php:L58-L71, L457-L466, admin/ajax_editar_atributo.php:L28-L34`
- **Evidência Técnica:** O aviso diz que eliminar remove a informação de todos os produtos, mas o PHP só apaga da tabela de atributos (e sem apagar atributos_valores explicitamente). Renomear grupo/valor não atualiza produtos.atributos (guarda strings), partindo filtros da loja.
- **Correção Recomendada:** Implementar cascata transacional em produtos.atributos (e apagar atributos_valores filhos) ou bloquear/corrigir o aviso conforme decisão.
- **Decisão Humana Necessária:** Decidir se renomear/apagar um atributo em uso deve atualizar em cascata o JSON produtos.atributos/variacoes, bloquear a ação quando em uso, ou apenas corrigir o texto do aviso.

#### #111 — Lacunas de sessão/auth em cliente_auth.php, auth.php e session.php (revogação não revalidada, logout sem regenerar ID)
- **Score:** `12,25` (Benefício: **7/10** | Custo: **4/10**) | **Categoria:** Segurança | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `config/cliente_auth.php:L36-L96, config/auth.php:L7-L24, config/session.php:L5-L27`
- **Evidência Técnica:** require_cliente() não verifica se o cliente continua ativo=1 na BD; auth.php não deteta despromoção/eliminação de admin em sessões ativas; logout_cliente() não chama session_regenerate_id(true); session.php não guarda headers_sent(), passa ints a ini_set() e usa strpos frágil em CF-Visitor.
- **Correção Recomendada:** Revalidar estado ativo/role conforme política escolhida, chamar session_regenerate_id(true) em logout_cliente(), validar is_string() em admin_role(), usar json_decode em CF-Visitor e guardar headers_sent() em session.php.
- **Decisão Humana Necessária:** Regenerar session ID no logout e corrigir session.php são objetivos; decidir se o impacto de revalidar ativo=1/admin_role na BD a cada pedido (ou usar session_version/timeout) é desejado.

#### #112 — [RESOLVIDO] Múltiplos bugs funcionais em includes/ui/ui.php (CSRF vazio em bulk_bar, portes em recolha, paginação sem clamp, password value, ui_table raw)
- **Score:** `12,25` (Benefício: **7/10** | Custo: **4/10**) | **Categoria:** Bug / Segurança / UI | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `includes/ui/ui.php:L83-L972`
- **Evidência Técnica:** ui_bulk_bar() lê ['csrf_token'] sem chamar csrf_token(); ui_customer_orders_table() soma portes em recolha, usa &token= sem &amp; e strtotime() sem check; ui_pagination() sem clamp; ui_product_card() não exige  < ; ui_field() emite value em password e onclick inline; ui_card() sem padding só com footer; attrs['class'] é sobrescrito; ui_progress_bar() quebra se LC_NUMERIC usar vírgula; ui_table() renderiza strings sem escape.
- **Correção Recomendada:** Chamar csrf_token(), corrigir portes recolha/&amp;/strtotime, fazer clamp na paginação, validar  < , remover value de password, mesclar attrs['class'], usar number_format(..., '.', '') e adicionar opção segura 'text' em ui_table().

#### #113 — [RESOLVIDO] Acessibilidade CSS: outline:none sem :focus-visible em 25+ locais, dropdown conta só :hover, controlos inacessíveis em touch e sem reduced-motion
- **Score:** `12,25` (Benefício: **7/10** | Custo: **4/10**) | **Categoria:** Acessibilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/layout/_header.css:L164-L1507, _close-button.css:L21, _back-to-top.css:L23, _form-produto.css:L177, L466, _gerir-atributos.css:L182, L240, _home.css:L453, L1195, _info-page.css:L409, e 29 ficheiros sem prefers-reduced-motion`
- **Evidência Técnica:** Botões do header, fechar, zoom-slider e vários inputs removem outline sem substituto :focus-visible; menu de conta só abre por :hover (sem :focus-within); botões de apagar/editar só visíveis em :hover falham em touch/teclado; 30+ ficheiros ignoram prefers-reduced-motion; .accordion-content max-height:1000px corta listas longas.
- **Correção Recomendada:** Definir :focus-visible global em _reset.css e usar outline:2px solid transparent em inputs com box-shadow; adicionar :focus-within e @media (hover: none) aos controlos ocultos; adicionar bloco central prefers-reduced-motion em _reset.css; converter acordeão para grid-template-rows.

#### #114 — [RESOLVIDO] Anarquia de z-index (22+ níveis de 1 a 999999, 0% uso dos tokens --z-*): bulk-bar/back-to-top tapam header e modais tapam toasts
- **Score:** `12,25` (Benefício: **7/10** | Custo: **4/10**) | **Categoria:** Bug UI / Arquitetura CSS | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/base/_variables.css:L95-L100, _header.css, _admin-ui.css:L104, _back-to-top.css:L16, _modal.css:L8, _editable-content.css:L142, _products.css:L444, _home.css:L764, e outros 8 ficheiros`
- **Evidência Técnica:** .site-header está em 100 mas .bulk-bar (500) e .voltar-ao-topo-btn (999) flutuam por cima dele; .modal e .footer-editor-modal colidem em 10000; .search-overlay (10002) passa por cima de modais; drawers/modais em 9999-10003 escondem toasts (--z-toast: 3000).
- **Correção Recomendada:** Adotar uma escala única baseada nos tokens --z-* de _variables.css (header > FAB/bulk-bar, overlays < modais < confirmações < toasts).

#### #115 — [RESOLVIDO] utils.js: Custom Select inacessível por teclado, layout thrashing em loop, carregamento duplo em checkout e dessincronização com modalProduto
- **Score:** `12,25` (Benefício: **7/10** | Custo: **4/10**) | **Categoria:** Acessibilidade / Performance / Bug | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/utils.js:L20, L33-L214, public/js/modalProduto.js:L394-L404, checkout.php:L158, templates/footer.php:L933`
- **Evidência Técnica:** Custom select fecha no Tab e não suporta setas/Enter/Space; ajustarLarguraCustomSelect() injeta <span> no body e lê offsetWidth por cada opção (layout thrashing + MutationObserver churn); checkout.php carrega utils.js 2x (listeners duplicados); modalProduto.js altera select.value sem disparar change (label fica dessincronizada); number_format(1.005, 2) dá '1.00'.
- **Correção Recomendada:** Adicionar navegação ARIA listbox por teclado, medir largura via canvas ctx.measureText(), debounce no MutationObserver, guardar dupla inicialização em utils.js, remover script duplicado em checkout.php, disparar change ao pré-selecionar e corrigir arredondamento.

#### #116 — detalhes_encomenda.php: pedido GET executa chamadas síncronas ao Stripe (30s), muta BD e envia email
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Arquitetura / Segurança | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/detalhes_encomenda.php:L47-L112`
- **Evidência Técnica:** Um simples GET consulta até 2 endpoints Stripe (sem rawurlencode nos IDs), atualiza estado para 'pago' e envia email ao cliente (mas não à loja).
- **Correção Recomendada:** Mover para ação POST+CSRF explícita (recomendado) ou endurecer com rawurlencode + helper partilhado finalize_paid_stripe_order().
- **Decisão Humana Necessária:** Decidir se o auto-sync ao abrir a encomenda deve manter-se (endurecido com rawurlencode/helper partilhado) ou passar para um botão POST 'Verificar pagamento no Stripe'.

#### #117 — [RESOLVIDO] ajax_ver_logs.php & ver_logs.php: leitura O(N²) de ficheiros inteiros, JSON partido em UTF-8 inválido, sem no-store e race entre tabs
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Performance / Bug / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `dev/ajax_ver_logs.php:L1-L104, dev/ver_logs.php:L1-L167`
- **Evidência Técnica:** Lê do byte 0 com array_shift() em cada linha (O(N×linhas)); json_encode sem JSON_INVALID_UTF8_SUBSTITUTE devolve corpo vazio em bytes inválidos; GET sem Cache-Control: no-store; troca rápida de separadores tem race condition; logAtivo injetado raw no modal de confirmação.
- **Correção Recomendada:** Ler de trás para a frente a partir de SEEK_END em blocos de 64KB, adotar config/http.php (no-store + JSON_INVALID_UTF8_SUBSTITUTE), usar AbortController em carregarLog() e escapar logAtivo.

#### #118 — dev/ajax_save_header, home e trocas: upload órfão em erro BD, chaves desalinhadas home_hero_cta vs home_hero_btn e falta is_array
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Bug / Ligação entre Ficheiros | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `dev/ajax_save_header.php:L63-L137, dev/ajax_save_home.php:L48-L142, dev/ajax_save_trocas.php:L36-L75, index.php:L187, L208-L214`
- **Evidência Técnica:** ajax_save_header.php não apaga upload em falha BD, usa DOCUMENT_ROOT frágil e unlink sem validar prefixo/realpath; ajax_save_home.php permite home_hero_cta mas index.php lê home_hero_btn (e trust_1 é hardcoded); ajax_save_trocas.php falta is_array()/is_scalar() e limites.
- **Correção Recomendada:** Corrigir unlink/caminhos em ajax_save_header.php, alinhar whitelist de ajax_save_home.php com index.php, validar tipos/limites em ajax_save_trocas.php e devolver HTTP codes corretos.
- **Decisão Humana Necessária:** Confirmar se home_hero_btn em index.php deve ser editável (alinhando ajax_save_home.php para home_hero_btn e removendo home_trust_1_* mortas).

#### #119 — [RESOLVIDO] Parsing .env inconsistente (com/sem INI_SCANNER_RAW, relido do disco) + mysqli_report() implícito + stripe.php sem require
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Bug / Arquitetura | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/database.php:L19-L60, config/stripe.php:L2-L19, registar.php:L51-L52, admin/includes/email_handler.php:L34-L38`
- **Evidência Técnica:** stripe.php e registar.php usam parse_ini_file() sem INI_SCANNER_RAW (quebra com caracteres especiais) e relêem o ficheiro do disco em cada chamada; stripe.php chama getLojaConfig() sem require_once; database.php não define mysqli_report() explicitamente, gerando tratamento misto de erros.
- **Correção Recomendada:** Criar um loader .env único e com cache estática, definir mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT) em database.php (com try/catch em leituras não críticas) e requerer loja_config.php em stripe.php.

#### #120 — [RESOLVIDO] Logger & Logs locais: Log Injection (\r\n e ), double-logging, constantes mortas em set_error_handler e perda de diretório
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Segurança / Fiabilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/logger.php:L13-L96, logs/app.log:L1-L34, logs/sql.log:L1-L19`
- **Evidência Técnica:** Exceções multi-linha partem registos de log (quebram ver_logs.php e permitem log forging);  e  não são sanitizados; set_error_handler lista constantes fatais inalcançáveis; erro DB grava em sql.log e app.log com formatos distintos; basename() apaga o diretório; app.log local guarda IP/username real.
- **Correção Recomendada:** Sanitizar \r/\n em mensagens e  + basename(), corrigir constantes de erro, logar caminho relativo à raiz do projeto, eliminar double-logging e limpar logs locais.

#### #121 — header.php: query de categorias duplicada/desnecessária em admin, canonical com ?utm_*, CDNs sem SRI e logout mobile duplicado
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Performance / SEO / Segurança | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `templates/header.php:L41-L64, L158-L201, L231-L244, L328, L458-L495, produtos.php:L16-L22`
- **Evidência Técnica:** Query EXISTS de categorias corre em todas as páginas (incluindo admin/dev) e repete-se em produtos.php; canonical/og:url preservam utm_source/fbclid; preconnects admin enviados ao público e popper/tippy sem versão fixa nem SRI; json_encode sem JSON_HEX_*; mobile renderiza 2 botões Sair se cliente+admin; nav_dev ignora global_edit_mode.
- **Correção Recomendada:** Reutilizar  em produtos.php e saltar em admin/dev, limpar query params do canonical, fixar versões+SRI e limitar preconnects a admins, adicionar JSON_HEX_*, unificar logout mobile e usar  em L482.
- **Decisão Humana Necessária:** Todas as correções técnicas são objetivas; apenas confirmar qual das 2 meta tags google-site-verification (L231-L232) deve permanecer.

#### #122 — [RESOLVIDO] produtos.php: paginação href='#' (não rastreável SEO), único <h1> vazio no modal, <img src=''> e queries redundantes
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** SEO / Performance / Acessibilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `produtos.php:L115, L159, L178-L202, L244, L353, L430, L478, L546, L551, L704`
- **Evidência Técnica:** gerarPaginacaoAjax() gera href='#' sem janelamento/ARIA (duplica ui_pagination()); <h1> está vazio dentro do modal oculto (título visível é <h2>); #modalImagemPrincipal tem src='' (pedido HTTP fantasma); SELECT DISTINCT sem JOINs; subquery correlacionada de fotos; falta img_dimensions (CLS); fallback ordenação 'categoria' em 0 resultados; títulos de filtro não são <button>.
- **Correção Recomendada:** Gerar URLs reais com janelamento/ARIA, trocar <h2> por <h1> na página (e <h2> no modal), omitir src='', remover DISTINCT e subquery correlacionada, ativar img_dimensions, usar fallback '' e usar <button type='button'> nos filtros.

#### #123 — [RESOLVIDO] CustomerService.php: operações multi-query sem transação, prepare() não verificados, LIMIT sem clamp e SELECT * expõe password_hash
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Bug / Segurança | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `includes/CustomerService.php:L15, L28, L39-L200`
- **Evidência Técnica:** customer_save_address(), customer_create_reset_token() e customer_create_email_verification_token() fazem UPDATE/DELETE + INSERT sem transação nem validação de prepare(); customer_orders() não faz clamp a ; SELECT * inclui password_hash em arrays gerais de cliente.
- **Correção Recomendada:** Envolver escritas multi-statement em transações, validar , fazer clamp a  (1..500), validar código de país e excluir password_hash das consultas não-login.

#### #124 — ShippingService.php: gap entre escalões cobra taxa máxima,  ignorado, sem cache e código postal sem hífen rejeitado
- **Score:** `12,00` (Benefício: **6/10** | Custo: **3/10**) | **Categoria:** Bug / Performance | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `includes/ShippingService.php:L4-L148`
- **Evidência Técnica:** Havendo gap entre escalões, calculate_shipping_from_rates() cai em end() (o mais caro); pesos negativos não são clamped; const sem defined(); queries não têm cache estática;  passado é ignorado.
- **Correção Recomendada:** Usar define() com guarda, normalizar/clamp de peso, tratar gaps de forma determinística, adicionar cache estática por pedido e ajustar regex do CP conforme decisão.
- **Decisão Humana Necessária:** Confirmar se is_portugal_continental() deve aceitar códigos postais sem hífen (ex.: 1000001 ou 1000 001) ou manter estritamente DDDD-DDD.

#### #125 — editar.php apaga e recria TODOS os IDs de produto_variacoes e produto_imagens em cada edição
- **Score:** `11,57` (Benefício: **9/10** | Custo: **7/10**) | **Categoria:** Bug Crítico / Integridade de Dados | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/editar.php:L119-L122, L167-L195, includes/OrderService.php:L73-L100, admin/duplicar_produto.php:L148`
- **Evidência Técnica:** Mesmo editando apenas o título, editar.php executa DELETE + INSERT em produto_variacoes e produto_imagens gerando IDs novos: parte encomenda_itens.variacao_id (impedindo reposição de stock ao cancelar/expirar!), quebra carrinhos ativos, tem lost-update no stock (sem FOR UPDATE) e apaga produto_variacoes.preco.
- **Correção Recomendada:** Implementar diff/upsert in-place sob SELECT ... FOR UPDATE, mantendo IDs de variações e imagens existentes e apagando apenas os removidos.
- **Decisão Humana Necessária:** A correção para upsert in-place é objetiva; confirmar apenas se a coluna produto_variacoes.preco ainda é usada (para preservar em editar.php) ou foi descontinuada (remover de duplicar_produto.php).

### P3 — Média Prioridade

#### #126 — Vetores de enumeração de contas em ajax_check_email_checkout.php, registar.php e timing/flood em recuperar-conta.php
- **Score:** `9,00` (Benefício: **6/10** | Custo: **4/10**) | **Categoria:** Segurança | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `ajax_check_email_checkout.php:L30-L34, ajax_create_payment_intent.php:L83-L92, registar.php:L37-L38, recuperar-conta.php:L37-L75`
- **Evidência Técnica:** ajax_check_email_checkout e registar.php revelam diretamente se um email tem conta ativa. recuperar-conta.php faz SMTP síncrono apenas quando a conta existe (timing side-channel) e não tem rate-limit por email de destino (spam de inbox).
- **Correção Recomendada:** Adicionar rate-limit por email em recuperar-conta.php; conforme decisão UX, manter com rate-limit forte ou tornar respostas neutras.
- **Decisão Humana Necessária:** Bloquear checkout guest para emails registados e dizer 'Já existe conta' em registar.php são trade-offs deliberados de UX vs privacidade. (Adicionar rate-limit por email em recuperar-conta.php é objetivo.)

#### #127 — [RESOLVIDO] Performance CSS e Tipografia: 35 font-weights não carregados (550..800), 26+ transition:all, layout thrashing e will-change permanente
- **Score:** `9,00` (Benefício: **6/10** | Custo: **4/10**) | **Categoria:** Performance / CSS | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/base/_variables.css:L117, _utilities.css:L39, L69, _header.css, _side-cart.css, _admin-ui.css, _editable-content.css, _quick-edit.css, _modal.css, _back-to-top.css, e 7 ficheiros pages/*.css`
- **Evidência Técnica:** Google Fonts carrega apenas Inter 300;400;500;600;700, mas o CSS usa 550/650/750/760/780/800 em 35 locais. --button-transition: all propaga-se a todos os botões (+25 transition:all). Animações em bottom, width, max-height e box-shadow infinito causam repaints/reflows. will-change fica ativo para sempre em .animate-entry.
- **Correção Recomendada:** Normalizar font-weights para 500/600/700 (ou carregar Inter variável), trocar transition:all por propriedades explícitas, animar .bulk-bar via transform e remover will-change permanente.

#### #128 — [RESOLVIDO] Links internos, canonicals, JSON-LD e AJAX GET usam extensão .php causando redirects 301 em cadeia
- **Score:** `9,00` (Benefício: **6/10** | Custo: **4/10**) | **Categoria:** Performance / SEO / Ligação entre Ficheiros | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `templates/header.php, templates/footer.php, index.php, produtos.php, checkout.php, sucesso.php, estado_encomenda.php:L55-L56, consultar-encomenda.php, contacto.php, 404.php:L28, registar.php:L53, dev/index.php:L7, dev/previews.php, admin/gerir_guias_tamanho.php:L7, admin/gestor_emails.php:L5`
- **Evidência Técnica:** Dezenas de links GET, redirects, URLs canónicos, Stripe return_url e chamadas AJAX GET incluem .php, disparando 301 no .htaccess:L88-L90. Em estado_encomenda.php:L55-L56, strpos(HTTP_REFERER, 'checkout.php') falha em produção.
- **Correção Recomendada:** Padronizar links, canonicals, return_url e redirects para URLs limpos sem .php e atualizar a deteção de referer em estado_encomenda.php.

#### #129 — Queries N+1 severas e payloads excessivos no painel Admin (eliminações em massa, duplicação, listagens e detalhes)
- **Score:** `9,00` (Benefício: **6/10** | Custo: **4/10**) | **Categoria:** Performance | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/apagar_encomendas_massa.php:L47-L87, admin/apagar_encomenda.php:L50-L72, admin/apagar_massa.php:L49-L108, admin/duplicar_produto.php:L44-L58, L163-L167, admin/admin_produtos.php:L107, admin/clientes.php:L61, admin/gerir_atributos.php:L97-L109, admin/detalhes_encomenda.php:L513, admin/mensagens.php:L56-L60`
- **Evidência Técnica:** apagar_encomendas_massa executa 3N+3M queries em loop; duplicar_produto faz prepare() em while(true) + N+1 em variacao_imagens; admin_produtos e clientes usam subqueries correlacionadas; gerir_atributos transfere todos os JSON de produtos para PHP; detalhes_encomenda serializa SELECT * inteiro para JS; mensagens.php faz SELECT * sem LIMIT.
- **Correção Recomendada:** Usar queries batch IN(...), LEFT JOIN agregados, JSON_CONTAINS no MySQL, passar apenas os 6 campos necessários ao JS de detalhes_encomenda.php e paginar mensagens arquivadas.
- **Decisão Humana Necessária:** Otimizações SQL/payload são objetivas; decidir apenas o limite/paginação desejada para mensagens arquivadas em admin/mensagens.php.

#### #130 — Código morto, ficheiros órfãos (back_button.php, admin/logout.php, mbway.png) e higiene menor em Admin/Dev/Config
- **Score:** `9,00` (Benefício: **3/10** | Custo: **1/10**) | **Categoria:** Arquitetura & Limpeza | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/components/back_button.php, admin/logout.php, templates/header.php:L9, public/assets/mbway.png, admin/admin.php:L16, admin/editar.php:L271, L300, L305, admin/apagar_massa.php:L29, admin/detalhes_encomenda.php:L127, L246, L398, admin/encomendas.php:L358, admin/apagar_encomendas_massa.php:L6-L41, dev/galeria_imagens.php, dev/login_logs.php, dev/uso_armazenamento.php`
- **Evidência Técnica:** back_button.php é incluído em todo o admin/dev mas renderBackButton() tem 0 chamadas; admin/logout.php e mbway.png têm 0 referências; várias variáveis mortas, falta rel='noopener noreferrer' no link WhatsApp de detalhes_encomenda.php, falta  em 3 páginas dev, acentos PT em falta, trailing ?> e CRLF/LF mistos.
- **Correção Recomendada:** Eliminar back_button.php (e o seu require em header.php), remover variáveis/tags ?> mortas, corrigir diacritics/noopener/, normalizar LF e remover logout.php/mbway.png após confirmação.
- **Decisão Humana Necessária:** Confirmar se admin/logout.php (sem referências internas) e public/assets/mbway.png podem ser eliminados sem quebrar bookmarks externos.

#### #131 — [RESOLVIDO] Limpeza menor de .htaccess (blocos mod_rewrite duplicados, mod_deflate/mod_expires incompletos, /LICENSE acessível)
- **Score:** `9,00` (Benefício: **3/10** | Custo: **1/10**) | **Categoria:** Performance / Configuração | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `.htaccess:L6-L17, L30-L45, L103-L126`
- **Evidência Técnica:** 3 blocos mod_rewrite separados com RewriteEngine On duplicado; <Files '.env'> redundante; mod_deflate omite application/json, application/xml (sitemap) e image/svg+xml; mod_expires usa image/jpg inválido e omite svg/ico/woff2.
- **Correção Recomendada:** Consolidar num único bloco mod_rewrite, completar MIME types em mod_deflate e mod_expires, e opcionalmente bloquear /LICENSE.

#### #132 — [RESOLVIDO] Polimento e acessibilidade menor em consultar-encomenda.php, carrinho.php, cliente-sidebar.php, contacto.php, entrar.php e registar.php
- **Score:** `9,00` (Benefício: **3/10** | Custo: **1/10**) | **Categoria:** Acessibilidade / UX / Limpeza | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `consultar-encomenda.php:L3-L63, carrinho.php:L9-L32, templates/cliente-sidebar.php:L11-L37, contacto.php:L54-L184, entrar.php:L69, L78, registar.php:L26, L29, L111, recuperar-conta.php:L90-L123, redefinir-conta.php:L47-L52`
- **Evidência Técnica:** consultar-encomenda.php não faz trim() no email e perde inputs em erro; faltam <h1> em 4 páginas, title no iframe Google Maps em contacto.php, <noscript> em carrinho.php, aria-current na sidebar; fbclid hardcoded no WhatsApp fallback; entrar.php ignora ?next= para já logados;  perde-se em erro em registar.php; recuperar-conta.php mostra alerta+popup duplicados; variáveis/queries mortas (, aceita_marketing, username admin).
- **Correção Recomendada:** Aplicar trim(), persistir /inputs, adicionar <h1>/title/aria-current/<noscript>, remover fbclid e código morto (confirmar só se aceita_marketing deve ser apagado ou ter checkbox).

#### #133 — Esquemas JSON inconsistentes, validação frouxa de IDs negativos/existência e falta de GET check em endpoints admin
- **Score:** `8,33` (Benefício: **5/10** | Custo: **3/10**) | **Categoria:** Ligação entre Ficheiros / Arquitetura | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `admin/ajax_toggle_edit_mode.php:L9, admin/ajax_salvar_ordem.php:L19-L37, admin/ajax_toggle_cliente_ativo.php:L21-L31, admin/ajax_mensagem_estado.php:L28-L43, admin/ajax_editar_atributo.php:L24-L34, admin/ajax_get_template.php, admin/ajax_get_valores.php, admin/ajax_validar_produto.php, admin/ajax_gerir_encomenda.php:L106-L189`
- **Evidência Técnica:** ajax_toggle_edit_mode usa 'erro' em vez de 'mensagem'; ajax_salvar_ordem omite 'mensagem' e aceita objetos JSON; IDs negativos passam FILTER_VALIDATE_INT; toggle_cliente_ativo com ativo omitido suspende o cliente!; endpoints de leitura aceitam POST; vários retornam sucesso em ID inexistente.
- **Correção Recomendada:** Exigir  > 0 e devolver 404 quando o registo não existe, exigir ativo estritamente 0 ou 1, validar is_finite/is_string nos campos de encomenda, impor GET em leitura e unificar 'mensagem' via config/http.php.
- **Decisão Humana Necessária:** Rejeitar IDs<=0, devolver 404 em affected_rows=0 e exigir GET são objetivos; confirmar apenas se vale alterar os shapes JSON acoplados a JS (ajax_get_template, ajax_get_valores, ajax_validar_produto).

#### #134 — [RESOLVIDO] Helpers menores com bugs/edge cases (footer_functions, interface_labels, http, formatters, url_helpers)
- **Score:** `8,33` (Benefício: **5/10** | Custo: **3/10**) | **Categoria:** Bug / Limpeza | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `config/footer_functions.php:L5-L129, config/interface_labels.php:L24-L59, config/http.php:L4-L58, config/formatters.php:L4-L58, config/url_helpers.php:L5-L27`
- **Evidência Técnica:** footer_functions.php sem try/catch, regex aceita 99:99 e faz json_decode() 2x; interface_labels.php engole exceções e faz SELECT completo ao gravar; http.php ignora falha de json_encode(), omite nosniff e usa diretiva IE5; format_money(-10) dá '€-10,00'; format_time_ago() falha em datas futuras; criar_slug() concatena palavras em '/' e '_'.
- **Correção Recomendada:** Adicionar try/catch, apertar regex de horas, evitar SELECT/decode redundantes, tratar json_encode===false + nosniff, corrigir sinal negativo em format_money(), tratar <0 e converter [_/] para '-' em criar_slug().

#### #135 — Falhas de contraste WCAG AA (<4.5:1): dourado #D4AF37 em _404.css (1.96:1) e --cor-slate-400/#999/#aaa sobre branco
- **Score:** `8,33` (Benefício: **5/10** | Custo: **3/10**) | **Categoria:** Acessibilidade | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `public/css/pages/_404.css:L118-L292, _pagination.css:L29, _cart.css, _cliente.css, _checkout.css, _estado-encomenda.css, _dashboard.css, _encomendas.css, _paleta-cores.css, e outros 7 ficheiros`
- **Evidência Técnica:** #D4AF37 sobre branco (e branco sobre #D4AF37) tem contraste 1.96:1; --cor-slate-400 (#94a3b8) e #999/#aaa sobre branco ficam entre 1.6:1 e 2.85:1 (mínimo WCAG AA é 4.5:1).
- **Correção Recomendada:** Escurecer rótulos secundários para --cor-slate-500 (4.6:1) ou --cor-slate-600 (7.6:1) e ajustar o dourado/contraste em _404.css.
- **Decisão Humana Necessária:** Requer aprovação visual das tonalidades mais escuras para texto secundário (--cor-slate-500/600) e dourado acessível em _404.css.

#### #136 — dev.php, uso_armazenamento.php, previews.php e ajax_toggle_header_scroll.php: código morto, scan síncrono divergente e CSS ?v=time()
- **Score:** `8,33` (Benefício: **5/10** | Custo: **3/10**) | **Categoria:** Performance / Bug / Limpeza | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `dev/dev.php:L15-L42, L93-L104, L158, L246-L321, dev/uso_armazenamento.php:L16-L70, dev/previews.php:L15-L112, dev/ajax_toggle_header_scroll.php:L18-L29`
- **Evidência Técnica:** dev.php corre 2 queries e handler POST login_as_customer sem UI; calcFolderSize() varre o disco todo sincronamente e diverge de uso_armazenamento.php (omite dev/, includes/, logs/ e glob() sem guarda); _dev.css/_previews.css carregam no <body> com ?v=time(); 9 links target='_blank' sem noopener noreferrer; ajax_toggle_header_scroll ignora retorno de setLojaConfig().
- **Correção Recomendada:** Remover (ou restaurar) login_as_customer, partilhar helper de disco consistente, substituir ?v=time() por style.php/filemtime(), adicionar rel='noopener noreferrer', acessibilidade nos toggles/dropdown e validar retorno de setLojaConfig().
- **Decisão Humana Necessária:** Decidir se o código morto login_as_customer (L15-L42 de dev.php) deve ser removido ou se o botão de UI deve ser restaurado; decidir também se _dev.css/_previews.css entram no bundle style.php ou usam filemtime().

#### #137 — [RESOLVIDO] Bugs secundários em envios.js, game404.js, header-scroll.js, menu.js, popup.js, backToTop.js e conflito de body.style.overflow
- **Score:** `8,33` (Benefício: **5/10** | Custo: **3/10**) | **Categoria:** Bug / Performance | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/envios.js:L12-L42, game404.js:L2-L133, header-scroll.js:L85-L89, menu.js:L21-L31, L100-L101, popup.js:L23, L37, backToTop.js:L2-L20, carrinho.js:L74, search.js:L111, modalProduto.js:L1108`
- **Evidência Técnica:** envios.js aborta todo o loop se 1 ISO falhar, usa BCP-47 errados (SE->Sami, BR->Bretão), filtro ignora acentos e usa innerHTML; game404.js sem null checks; header-scroll.js dispara 2 rAFs em cada scroll no topo; menu.js tem resize sem throttle e timer mouseleave sem clearTimeout; popup.js não reinicia animação; backToTop.js ignora scroll inicial; vários overlays limpam body.style.overflow='' enquanto outro modal ainda está aberto.
- **Correção Recomendada:** Mover try/catch para dentro do loop e expandir isoToLocale + NFD em envios.js, guardar DOM em game404.js, short-circuit em header-scroll.js, throttle rAF + clearTimeout em menu.js, reflow em popup.js, check inicial em backToTop.js e coordenar scroll-lock por classe/contagem.

#### #138 — [RESOLVIDO] 404.php: CDN canvas-confetti sem SRI/defer,  sem escape, modal sem ARIA e desvio do design system
- **Score:** `8,00` (Benefício: **4/10** | Custo: **2/10**) | **Categoria:** Segurança / Acessibilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `404.php:L27-L56`
- **Evidência Técnica:** Script externo jsDelivr carregado sem integrity/crossorigin/defer;  impresso sem escape nem fallback; #win-modal sem role='dialog'/aria-modal; botões/modal fogem de ui_button()/ui_modal() contrariando DESIGN.md.
- **Correção Recomendada:** Adicionar SRI + crossorigin + defer, escapar , adicionar ARIA ao modal e alinhar com ui_*() ou atualizar DESIGN.md.

#### #139 — trocas.php: debounce 700ms perde edições ao sair rápido, paste HTML corrompe editor, falta <h1> e edição mobile inconsistente
- **Score:** `8,00` (Benefício: **4/10** | Custo: **2/10**) | **Categoria:** UX / Bug / Acessibilidade | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `trocas.php:L61-L211`
- **Evidência Técnica:** Sair/recarregar dentro de 700ms descarta alterações (sem beforeunload/visibilitychange flush); colar texto rico corrompe DOM; CSRF ecoado sem json_encode(); falta <h1> e aria-hidden em SVGs.
- **Correção Recomendada:** Adicionar handler paste plain-text + flush em visibilitychange/beforeunload, usar json_encode(..., JSON_HEX_*), trocar <h2> por <h1> e alinhar regra mobile.
- **Decisão Humana Necessária:** Confirmar se a edição inline em trocas.php deve continuar ativa em mobile ou restringir-se a desktop (>992px) como em index.php e footer.php.

#### #140 — [RESOLVIDO] Acessibilidade JS e HTML interativo: falta Escape/ARIA/focus-trap em modais/drawers, aria-live nos toasts e <span> clicáveis
- **Score:** `7,20` (Benefício: **6/10** | Custo: **5/10**) | **Categoria:** Acessibilidade | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/carrinho.js, confirmacao.js, modalProduto.js, menu.js, search.js, popup.js, header-scroll.js, envios.js, game404.js, backToTop.js, admin-ui.js, entrar.php:L289, registar.php:L117, redefinir-conta.php:L143, minha-conta-dados.php:L126, admin/adicionar_admin.php:L167, admin/editar_admin.php:L240, admin/clientes.php:L130`
- **Evidência Técnica:** #side-cart, #modalConfirmacao, #produtoModal, #search-overlay, menu mobile e .qe-modal não têm role='dialog'/aria-modal nem focus trap/restore (#side-cart e #modalConfirmacao sem Escape); #popupMensagem sem aria-live; header oculto sem focusin; toggle-senha e badge-ativo-toggle são <span> sem teclado; game404 cards são <div>; backToTop/game404 ignoram prefers-reduced-motion.
- **Correção Recomendada:** Criar helper de focus-trap/restore em utils.js, adicionar dialog ARIA + Escape nos drawers/modais, aria-live em #popupMensagem, aria-expanded em envios.js, converter <span>/<div> interativos para <button type='button'> e respeitar prefers-reduced-motion.

#### #141 — [RESOLVIDO] Reescritas empilhadas e ~800+ linhas de CSS morto/conflituoso (_modal.css, _header.css, _home.css, _product-page.css, _forms.css, :root)
- **Score:** `6,00` (Benefício: **6/10** | Custo: **6/10**) | **Categoria:** Arquitetura & Limpeza | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/css/components/_modal.css, layout/_header.css, pages/_home.css, pages/_product-page.css, layout/_forms.css, components/_form-components.css, components/_admin-buttons.css, components/_quick-edit.css, pages/_login-logs.css, pages/_portes.css, pages/_uso-armazenamento.css`
- **Evidência Técnica:** _modal.css tem 3 gerações empilhadas (~450 linhas mortas); _header.css tem 2 gerações + .nav-mobile-header morto; _home.css tem blocos 100% duplicados + legacy vs .main-home; _product-page.css declara .acordeao-header 5x e parte media queries; _forms.css vence _form-components.css em especificidade; 4 blocos CSS têm 0 usos no repo (.switch, .input-com-olho, .btn-back-admin, qe-fade); 3 blocos :root poluentes com vars mortas.
- **Correção Recomendada:** Consolidar cada seletor num único bloco final limpo, ordenar media queries em ordem decrescente, apagar os 4 blocos mortos e remover os 3 :root duplicados.

#### #142 — PHPMailer 6.10.0 desatualizado (atual 7.2.0) e texto completo da licença LGPL-2.1 em falta
- **Score:** `5,33` (Benefício: **4/10** | Custo: **3/10**) | **Categoria:** Dependências / Legal | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `phpmailer/LICENSE:L1-L13, phpmailer/src/PHPMailer.php:L771, THIRD_PARTY_NOTICES.md`
- **Evidência Técnica:** phpmailer/LICENSE contém apenas um resumo de 13 linhas em português, não o texto completo exigido pela LGPL-2.1 ao redistribuir num repo público. Versão 6.10.0 está atrás da 7.2.0.
- **Correção Recomendada:** Substituir phpmailer/LICENSE pelo ficheiro oficial completo LGPL-2.1 e planear upgrade seguro para a versão estável mais recente de PHPMailer.
- **Decisão Humana Necessária:** Testar compatibilidade da versão PHP de produção antes de atualizar major 6.10 -> 7.2 (repor texto completo da licença LGPL-2.1 é obrigatório e objetivo).

#### #143 — [RESOLVIDO] Duplicação extensa de JS e PHP utilitário (seleção em massa 6x, scrollRestoration 16x, escapeHtml 5x, países/moradas 3x, email wrapper 3x)
- **Score:** `5,00` (Benefício: **5/10** | Custo: **5/10**) | **Categoria:** Arquitetura & Limpeza | **Classificação:** 🟢 **Não requer opinião humana**
- **Ficheiros e Linhas:** `public/js/utils.js, public/js/admin-ui.js, admin/admin_produtos.php, admin/encomendas.php, admin/clientes.php, admin/listar_admins.php, admin/reservas_stock.php, dev/login_logs.php, checkout.php, minha-conta-moradas.php, admin/portes.php, templates/footer.php, admin/includes/email_handler.php, e 16 páginas PHP`
- **Evidência Técnica:** Lógica de seleção em massa (~80 linhas) copiada em 6 páginas; script scrollRestoration copiado em 16 páginas (já em header.php); escapeHtml copiado 5x; getNomePais/popularPaises/fetchAddressConfig copiados 3x; flash popup 3x; wrapper HTML de email 3x; editor inline do footer (~775 linhas) e home (~615 linhas) embutidos em PHP.
- **Correção Recomendada:** Exportar escapeHtml/debounce em utils.js, initAdminBulkSelection() em admin-ui.js, extrair editores inline para JS externos em cache, remover os 16 scripts scrollRestoration duplicados e partilhar helpers de países/email/deleção.

### P4 — Baixa Prioridade / Refatoração

#### #144 — Guerra dos 5 sistemas de botões (_button-system.css anula contraste primário/secundário) e 417 !important no CSS
- **Score:** `4,50` (Benefício: **6/10** | Custo: **8/10**) | **Categoria:** Arquitetura & Limpeza / UI | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `public/css/components/_button-system.css, _admin-buttons.css, _quick-edit.css, _admin-ui.css, layout/_forms.css, _buttons.css, _product-card.css, _side-cart.css, e 17 pages/*.css`
- **Evidência Técnica:** _button-system.css (carregado por último com 37 !important) torna ~150 linhas de estilos primários/secundários mortas e triplica .btn-adicionar-rapido. O projeto acumula 417 !important.
- **Correção Recomendada:** Após remover bare button, eliminar !important via @layer ou especificidade natural, deduplicar .btn-adicionar-rapido e preservar variantes primária/secundária/perigo por variáveis CSS.
- **Decisão Humana Necessária:** Confirmar se botões primários admin (.btn-admin-primary, .qe-btn-save, login submit) devem permanecer pílulas brancas ou recuperar preenchimento escuro (como .ui-btn--primary em DESIGN.md) e se podemos adotar CSS @layer.

#### #145 — Contradições na documentação (DESIGN.md e README.md) e .agents/ ausente de .gitignore
- **Score:** `4,00` (Benefício: **2/10** | Custo: **1/10**) | **Categoria:** Documentação / Higiene | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `DESIGN.md:L207, L214, L239, README.md:L13, L32, L60, L99, L106, .gitignore:L1-L48`
- **Evidência Técnica:** DESIGN.md L214 diz '9 primitives' mas L134/195/223 dizem '16'; L239 afirma que 404.php usa ui_button/ui_modal (falso); README.md omite pastas de escrita em public/assets/* e sobrestima bloqueios .htaccess; .gitignore não inclui .agents/.
- **Correção Recomendada:** Corrigir discrepâncias em DESIGN.md e README.md após aplicar as correções de infraestrutura e atualizar .gitignore.
- **Decisão Humana Necessária:** Confirmar se .agents/ e AUDITORIA_COMPLETA.md devem ficar em .gitignore e se DESIGN.md deve continuar público no repositório Git (L207 diz 'DON'T make DESIGN.md publicly accessible').

#### #146 — Bypass de Design Tokens (200+ cores hex hardcoded), duplicação de padrões CSS entre páginas e _gerir_categorias.css com underscore
- **Score:** `2,67` (Benefício: **4/10** | Custo: **6/10**) | **Categoria:** Arquitetura & Limpeza | **Classificação:** 🟠 **Requer opinião humana**
- **Ficheiros e Linhas:** `public/css/base/_variables.css, public/css/ui/_primitives.css, public/css/layout/_forms.css:L28, public/css/pages/_gerir_categorias.css, _portes.css, e outros 25 ficheiros CSS`
- **Evidência Técnica:** 200+ cores hex e font-stacks hardcoded fora de _variables.css (inclusive 23 em _primitives.css); --cor-fundo-pagina sem default em _variables.css; 12 tokens não usados; form textarea { resize: none; } global; pill select duplicado 5x, kicker editorial 4x, checkbox SVG 3x; único ficheiro com underscore (_gerir_categorias.css).
- **Correção Recomendada:** Adicionar --cor-fundo-pagina, --cor-slate-700 e --font-mono a _variables.css, permitir resize:vertical em textareas, renomear _gerir_categorias.css para _gerir-categorias.css e consolidar tokens/padrões progressivamente.
- **Decisão Humana Necessária:** Confirmar a profundidade desejada para migrar padrões repetidos (cards admin, pill select, editorial kicker, checkbox SVG) para ui/_primitives.css e se _portes.css deve manter índigo #4f46e5 ou adotar a paleta da loja.

---

## 5. Apêndice — Cobertura Integral da Análise (100% dos Ficheiros)

Todos os ficheiros do projeto em `c:\Projetos\public_html` foram lidos integralmente linha a linha (sem exceções) e validados quanto a ligações entre ficheiros:

| Módulo | Âmbito | Ficheiros Lidos | Estado |
|---|---|---:|---|
| **Módulo 1** | Configuração raiz (`.env`, `.env.example`, `.gitignore`, `.htaccess`, `.vscode/*`), `404.php`, `robots.txt`, `sitemap.php`, `LICENSE`, `README.md`, `DESIGN.md`, `THIRD_PARTY_NOTICES.md`, `logs/*`, `public/images/.htaccess`, verificação `phpmailer/` | 18 | 100% lido |
| **Módulo 2** | `config/*` (17 ficheiros) + `includes/*` (`CartService`, `CustomerService`, `OrderService`, `ShippingService`, `ui/ui.php`) | 22 | 100% lido (2.659 linhas) |
| **Módulo 3** | `templates/*` (3) + páginas públicas da loja (`index`, `produtos`, `produto`, `carrinho`, `checkout`, `sucesso`, `estado_encomenda`, `consultar-encomenda`, `contacto`, `envios`, `trocas`) | 14 | 100% lido (6.217 linhas) |
| **Módulo 4** | Auth & área de cliente (`entrar`, `registar`, `sair`, `recuperar-conta`, `redefinir-conta`, `verificar-email`, `minha-conta*`), `stripe_webhook.php` e 8 `ajax_*.php` públicos | 20 | 100% lido (2.650 linhas) |
| **Módulo 5** | Admin core, produtos, encomendas, `admin/includes/*` e `admin/components/*` | 19 | 100% lido (3.943 linhas) |
| **Módulo 6** | Gestão admin (admins, clientes, atributos, categorias, guias, emails, mensagens, paleta, portes, reservas e ações em massa) | 18 | 100% lido (4.584 linhas) |
| **Módulo 7** | Todos os 20 endpoints `admin/ajax_*.php` e respetivos callers | 20 | 100% lido |
| **Módulo 8** | Todas as 21 ferramentas e endpoints `dev/*` | 21 | 100% lido (2.885 linhas) |
| **Módulo 9** | Todos os 15 ficheiros JavaScript em `public/js/*` | 15 | 100% lido (3.573 linhas) |
| **Módulo 10** | CSS Core (`style.php`, `ui/_primitives.css`, `base/*`, `layout/*`, `components/*`) | 31 | 100% lido (7.543 linhas) |
| **Módulo 11** | CSS de Páginas (`public/css/pages/*`) | 29 | 100% lido (10.836 linhas) |
| **Verificação Estática** | Cruzamento global de `require`/`include`, CSS/JS órfãos, variáveis CSS `var(--*)`, BOM UTF-8 (0 encontrados), duplicações e 111 ficheiros binários em `public/assets/` e `public/images/` | 111 assets + grafo global | 100% verificado |


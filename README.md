# TopTop — Loja Online

Aplicacao web de comercio eletronico desenvolvida em PHP para gestao de
produtos, variacoes, stock, clientes, encomendas, pagamentos e conteudos da
loja.

O repositorio contem o codigo-fonte da aplicacao. Credenciais, configuracao de
producao, logs, backups e imagens comerciais nao estao incluidos.

## Funcionalidades

- Catalogo de produtos, categorias, atributos e variacoes;
- Pesquisa, carrinho e checkout;
- Contas de cliente, moradas e historico de encomendas;
- Consulta de encomendas por identificador seguro;
- Pagamentos Stripe e processamento de webhooks;
- Envio de emails transacionais com PHPMailer;
- Gestao de stock e reservas de checkout;
- Calculo e administracao de portes de envio;
- Painel administrativo com diferentes niveis de acesso;
- Gestao de produtos, clientes, encomendas e conteudos da loja;
- Edicao de elementos visuais e textos da interface;
- Registo interno de erros e eventos relevantes.

## Tecnologias

- PHP 8 ou superior;
- MySQL/MariaDB;
- Apache com `mod_rewrite` e suporte para `.htaccess`;
- HTML, CSS e JavaScript sem framework obrigatorio;
- Stripe API;
- PHPMailer 6.10.0.

## Requisitos PHP

A instalacao deve disponibilizar, pelo menos, as seguintes extensoes:

- `mysqli`;
- `curl`;
- `fileinfo`;
- `json`;
- `mbstring`;
- `openssl`;
- `session`.

O servidor de producao deve usar HTTPS.

## Configuracao

1. Crie uma copia do ficheiro de exemplo:

   ```bash
   cp .env.example .env
   ```

   Em PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Preencha o novo `.env` com valores proprios:

   ```env
   DB_HOST=
   DB_USER=
   DB_PASS=
   DB_NAME=

   STRIPE_LIVE_PUBLIC_KEY=
   STRIPE_LIVE_SECRET_KEY=
   STRIPE_LIVE_WEBHOOK_SECRET=
   STRIPE_TEST_PUBLIC_KEY=
   STRIPE_TEST_SECRET_KEY=
   STRIPE_TEST_WEBHOOK_SECRET=

   MAIL_HOST=
   MAIL_PORT=
   MAIL_USER=
   MAIL_PASS=
   MAIL_FROM_ADDRESS=
   MAIL_FROM_NAME=
   MAIL_REPLY_TO_ADDRESS=
   MAIL_REPLY_TO_NAME=

   APP_URL=https://exemplo.pt
   ```

3. Configure a base de dados com a estrutura exigida pela aplicacao.

4. Aponte a raiz publica do servidor para o projeto e confirme que as regras
   do `.htaccess` sao permitidas pelo Apache.

5. Garanta permissoes de escrita apenas nas pastas e ficheiros que realmente
   necessitam delas, como os diretorios de imagens e logs.

## Imagens e elementos visuais

As fotografias, logotipos e restantes imagens comerciais nao fazem parte do
repositorio publico. As extensoes de imagem raster estao excluidas pelo
`.gitignore`.

Depois de instalar o projeto, os ficheiros autorizados devem ser colocados nas
pastas correspondentes, por exemplo:

```text
public/images/
public/assets/
public/assets/header/
public/assets/img_categorias/
```

O ficheiro `public/images/.htaccess` deve ser preservado, pois impede a
execucao de scripts dentro da pasta de imagens.

## Stripe

A integracao suporta ambientes de teste e producao. Cada ambiente deve usar
as suas proprias chaves e o seu proprio segredo de webhook.

O endpoint de webhook deve ser configurado no painel Stripe para os eventos
utilizados pela aplicacao. Nunca reutilize segredos de exemplo nem publique
chaves secretas no codigo-fonte.

## Email

O envio de email usa SMTP atraves do PHPMailer. As credenciais e os enderecos
do remetente devem ser configurados exclusivamente no `.env`.

O PHPMailer incluido neste repositorio mantem a sua licenca LGPL-2.1. Consulte
`THIRD_PARTY_NOTICES.md` e `phpmailer/LICENSE`.

## Seguranca

- Nunca adicione o ficheiro `.env` ao Git;
- Nao publique dumps SQL, backups, logs ou chaves privadas;
- Use apenas HTTPS em producao;
- Mantenha PHP, PHPMailer e o servidor atualizados;
- Restrinja o acesso aos paineis administrativos;
- Use palavras-passe unicas e fortes para administradores;
- Proteja e teste o endpoint Stripe antes de aceitar pagamentos reais;
- Reveja `git status` e `git ls-files` antes de cada publicacao.

Para confirmar que o `.env` esta ignorado:

```bash
git check-ignore -v .env
```

## Conteudo excluido do repositorio

O `.gitignore` impede a publicacao de:

- Ficheiros `.env` reais;
- Logs e ficheiros de erro;
- Backups e dumps de bases de dados;
- Chaves e certificados privados;
- Fotografias e imagens raster;
- Configuracao de editores;
- Ficheiros temporarios;
- Codigo antigo fora da aplicacao atual.

## Componentes de terceiros

Este projeto inclui PHPMailer 6.10.0. Os componentes de terceiros continuam
sujeitos as suas licencas originais, independentemente da licenca aplicada ao
restante projeto.

Consulte [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) para mais informacao.

## Licenca

Copyright (c) 2026 Henrique Correia. Todos os direitos reservados.

O codigo e disponibilizado publicamente apenas para consulta. Nao e concedida
autorizacao para copiar, modificar, distribuir, sublicenciar, vender ou
utilizar o projeto sem autorizacao previa e expressa por escrito.

Consulte [LICENSE](LICENSE) para os termos completos.

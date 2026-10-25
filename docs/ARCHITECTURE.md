# Arquitetura

## Componentes e responsabilidades

| | Plugin **SciFlow WP** (`SciFlow-WP/`) | Tema **Inscrição Enfrute** (`enfrute-inscricao/`) |
|---|---|---|
| Papel | Regras de negócio, dados, segurança e e-mails | Apresentação |
| Dados | CPTs `enfrute_trabalhos`, `semco_trabalhos`, `sciflow_palestra`; metadados `_sciflow_*`; opção `sciflow_settings` | Nenhum dado próprio além de menus e `theme_mods` |
| Funciona sozinho? | **Sim**: com qualquer tema, as páginas usam shortcodes | **Não** para os fluxos do evento. Sem o plugin, as páginas mostram "indisponível" e o admin recebe um aviso, sem erro fatal |
| Dependências | WordPress ≥ 6.0, PHP ≥ 7.4 (`mbstring`, `fileinfo`, `json`; `zip` opcional). WooCommerce **opcional** | Plugin SciFlow WP. WooCommerce para inscrição, checkout e inscrição de terceiros |

O tema declara `add_theme_support('sciflow')`. O plugin usa isso para escolher entre criar páginas com os **modelos do tema** ou com **shortcodes**.

### Lógica que ainda fica no tema (dívida técnica conhecida)
`functions.php` concentra regras comerciais do checkout WooCommerce: CPF/CNPJ, PayPal apenas para estrangeiros, pedidos "sob encomenda" em espera, prazo de inscrição e o papel `inscritor_terceiros`. O template `template-terceiros.php` cria usuários e pedidos. Essas regras ficaram onde estavam para não mudar o comportamento de um site em produção durante a refatoração. Elas não usam dados do tema e podem migrar para um módulo `includes/woocommerce/` do plugin numa versão futura. Por enquanto, o tema delega ao plugin a regra de "inscrição paga" (`sciflow_user_has_paid_registration()`) e as URLs (`sciflow_get_page_url()`).

## Estrutura do plugin
```
sciflow-wp.php                 bootstrap, autoloader, hooks de ativação/upgrade
includes/
  sciflow-functions.php        API pública para temas (sciflow_get_page_url, sciflow_can_view_submission…)
  class-sciflow-settings.php   leitura, padrões, sanitização e segredos (constantes/env)
  class-sciflow-events.php     registro dos 2 eventos (rótulos configuráveis)
  class-sciflow-pages.php      registro de páginas por função (sem slugs fixos)
  class-sciflow-security.php   autorização por evento, proteção de usuários privilegiados, CSV, uploads, diretórios privados, limite de tentativas
  class-sciflow-installer.php  ativação idempotente, multisite, migrações, cron
  class-sciflow-diagnostics.php verificações de prontidão
  admin/ class-sciflow-admin-setup.php  tela Diagnóstico, assistente, reparo
  workflow/ ranking/ email/ payment/ upload/ certificates/ woocommerce/ frontend/ roles/ meta/ post-types/
admin/       telas do wp-admin (configurações, ranking, e-mail em massa, técnicos, permissões)
public/      templates de shortcodes, e-mails, JS/CSS, vendor/ (bibliotecas locais)
tests/       testes de integração (fora do pacote)
```

## Fluxo de dados (ciclo de um trabalho)
```
Inscrição (WooCommerce) ──pedido concluído──▶ papel sciflow_inscrito / speaker / técnico
        │
Autor ──AJAX sciflow_submit (nonce + login + inscrição paga)──▶ CPT do evento, status "submetido"
        │                                                       └─▶ e-mails: editores do evento + autor
Editor do evento ──sciflow_assign_reviewer──▶ "em_avaliacao" ──▶ e-mail ao revisor
Revisor designado ──sciflow_submit_review──▶ notas e média ponderada ──▶ "aguardando_decisao"
Editor do evento ──sciflow_editorial_decision──▶ aprovado | em_correcao | reprovado …
Autor ──sciflow_upload_poster (PDF validado)──▶ "poster_enviado" ──▶ decisão do pôster ──▶ "apto_publicacao"
Ranking ──seleção top N──▶ "aguardando_confirmacao" ──link com token / AJAX──▶ "confirmado"
Cron horário ──▶ prazos de correção, de pôster e de confirmação
Admin ──▶ Anais (prévia HTML para PDF), exportações CSV/XLS/JSON, certificados
```
As transições válidas ficam em `SciFlow_Status_Manager::get_transitions()`. Toda mudança dispara `do_action('sciflow_status_changed', $post_id, $new, $old)`.

## Autorização
- **Editorial:** `SciFlow_Security::can_manage_submission()` exige `manage_sciflow` **e** `edit_others_{post_type}` do evento do trabalho. Editores de um evento só recebem as capabilities do próprio CPT; administradores e o editor geral (`sciflow_editor`) recebem as dos dois.
- **Autor e revisor:** `is_author()` e `is_assigned_reviewer()` exigem um usuário logado (ID > 0).
- **Leitura do detalhe:** `sciflow_can_view_submission()` permite autor, revisor designado, editor do evento e, para trabalhos publicados que não são rascunho, os papéis específicos do evento.
- **Usuários:** um filtro global `map_meta_cap` impede que quem não tem `manage_options` edite, exclua ou promova administradores. `editable_roles` remove, para essas pessoas, papéis com capabilities sensíveis, inclusive na REST.

## Configurações
Tudo é armazenado na opção `sciflow_settings`, acessada por `SciFlow_Settings::get()`.
- A sanitização **mescla**: chaves que não vieram no envio são preservadas. Checkboxes só são avaliados quando a seção (`_sections[]`) foi exibida.
- **Segredos** (`paygo_integration_key`, `paygo_token`, `paygo_webhook_secret`): nunca são reimpressos. Campo em branco mantém o valor; há uma opção para removê-lo. As constantes ou variáveis de ambiente `SCIFLOW_PAYGO_INTEGRATION_KEY`, `SCIFLOW_PAYGO_TOKEN` e `SCIFLOW_PAYGO_WEBHOOK_SECRET` têm prioridade.
- **Páginas:** o ID salvo em `page_ids[função]`; senão a página marcada com `_sciflow_page_role`; senão o modelo de página; senão a URL legada; senão o slug legado. Mudar de domínio não exige ajuste.

Abas da tela de configurações: Geral, Eventos, Páginas, Inscrição, Prazos, Pagamento, Ranking, Notificações, E-mail, Certificados & Anais e Avançado. A tela Diagnóstico (só administradores) traz o assistente, a criação de páginas e o reparo.

## Integrações
| Integração | Uso | Obrigatória |
|---|---|---|
| WooCommerce | papéis por compra (`woocommerce_order_status_*`), verificação de inscrição paga, checkout | Não |
| PayGo (Gate2all) Pix | cobrança e webhook `POST /wp-json/sciflow/v1/payment-webhook` (status sempre confirmado na API) | Não (legado) |
| `wp_mail` | todas as notificações. Remetente configurável | Sim (com SMTP recomendado) |
| LibreOffice, Ghostscript e Chromium (CLI) | PDF consolidado de palestras nos Anais | Não |
| Dompdf | geração de certificado por trabalho, se instalado por outro plugin | Não |

## Versionamento e atualizações
- SemVer. A versão fica no cabeçalho do plugin e em `SCIFLOW_VERSION` (tema: `style.css` e `INSCRICAO_ENFRUTE_VERSION`).
- `SciFlow_Installer::maybe_upgrade()` roda em `init` quando a versão gravada (`sciflow_version`) difere da do código. Repara papéis e caps sem apagar customizações, agenda o cron, protege diretórios e executa as migrações pendentes registradas em `sciflow_migrations`. Há uma trava por transient contra execuções concorrentes.
- As migrações são **aditivas** e idempotentes. A `1.1.0-legacy-settings` preserva os textos e comportamentos das instalações anteriores (nomes Enfrute/Semco, cabeçalho dos anais, certificado por e-mail) sem sobrescrever valores já definidos.
- `Update URI: false` evita que o WordPress.org ofereça atualizações de outro plugin com o mesmo slug.

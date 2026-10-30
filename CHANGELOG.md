# Changelog

## 1.2.0 — 2026-10-30
### Novidades
- Certificados de vários tipos por pessoa. Cada subpasta de `uploads/certificados/` é um tipo (`poster/`, `apresentacao-oral/`, `palestrante/`, `mediador/`…); os PDFs da raiz continuam sendo os de participação. A página de certificados lista todos os da pessoa, com um botão de download para cada um. Mais de um do mesmo tipo: `<email>-2.pdf`.
- Usuários logados veem os próprios certificados direto na página, procurados pelo e-mail da conta e pelo e-mail de cobrança do WooCommerce.
- Item "Certificados" no menu "Minha conta" do WooCommerce.
- Diagnóstico mostra quantos certificados há de cada tipo.
### Correções
- Depois de um erro no download, a pessoa volta para a página de certificados, e não para a página inicial.
- Teste do assistente dispara `after_switch_theme` com os dois argumentos (falhava com WooCommerce ativo).


## 1.1.1 — 2026-10-25
### Correções
- build: checksum com nome do arquivo sem caminho


## 1.1.0 — 2026-10-25
### Segurança
- Corrige escalada de privilégio: o Gestor Técnico podia se promover a administrador e editar o administrador via REST (proteção global em `map_meta_cap` e `editable_roles`).
- O webhook de pagamento confirma o status na API PayGo antes de aceitar a notificação. Segredo opcional e transição `aguardando_pagamento → submetido`.
- Autorização editorial com escopo por evento (decisão, revisor, pôster, confirmação manual).
- Verificação de posse em `sciflow_create_payment`/`sciflow_check_payment`; helpers `is_author`/`is_assigned_reviewer` exigem usuário logado.
- Certificados: diretório protegido, modo "somente logado", limite de tentativas por IP.
- Uploads validados por conteúdo (`wp_check_filetype_and_ext`, mimes restritos, `is_uploaded_file`), limites configuráveis.
- Proteção contra injeção de fórmulas em CSV/XLS; importação JSON restrita a conteúdo SciFlow.
- Anais: diretório temporário privado e removido após o uso, Ghostscript com escape e `-dSAFER`, HTML de `.doc` sanitizado.
- Segredos PayGo mascarados e definíveis por constante ou variável de ambiente; logs sem dados pessoais.
- Capability nos handlers de ranking; e-mail em massa com placeholders escapados; XSS no DOM da prévia corrigido.
- Bibliotecas servidas localmente (sem CDN); `test-cron.php` removido.
### Anais (reescrito)
- A paginação passa a ser feita pelo navegador a partir do layout real (Paged.js, padrão CSS Paged Media). Antes, o número de caracteres por página era estimado, o que produzia texto cortado, páginas meio vazias e páginas em branco.
- Sumário, rodapés e total de páginas da ficha vêm da posição física real; o sumário não diverge mais da numeração.
- Formatação do modelo aprovado: A4, cabeçalho de 3 linhas, fólio inferior direito, título/autores/afiliações/corpo nos tamanhos do modelo, mesma tipografia em resumos e palestras.
- Leitor de `.docx` reescrito com DOMDocument: preserva tabelas, títulos, imagens, quebras de linha, negrito, itálico e sobrescrito/subscrito.
- Expoentes (`10^5`, `mL^-1`) passam a ser convertidos: a expressão regular anterior nunca casava.
- PDF "arquivos originais" de palestras: inclui o sumário (antes ausente), remove a página "Fim do Arquivo Introdutório" e mede de verdade as páginas preliminares.
- Cache de conversão `.doc` movido para o diretório privado.
- Medição com 80 resumos: 187 → 132 páginas; 0 páginas com estouro (antes 6 nas palestras); 0 páginas em branco (antes 3 + 4); sumário e rodapés 100% coerentes.

### Identidade visual configurável
- Novo módulo **Identidade visual** (SciFlow → Configurações, passo do assistente e Aparência → Personalizar). Inclui logo, cor principal (com tom escuro opcional), cor de destaque, texto, fundo, fontes de títulos e de texto (sistema ou Google Fonts sob demanda), arredondamento e imagens dos Anais.
- Tons derivados (hover, fundos claros) e cor de texto sobre botões calculados automaticamente, com alerta de contraste (WCAG).
- Um único conjunto de variáveis (`--brand-*`) alimenta o tema, os formulários do plugin, o Bootstrap, a tela de certificados e a confirmação de apresentação.
- E-mails padronizados: mesmo cabeçalho na cor principal, com logo e botões da identidade. Cores semânticas ficam só na decisão editorial.
- Anais: capa gerada com cor e logo, ou imagens próprias de capa, folha de rosto e patrocinadores, além de fonte do livro configurável.
- Instalações existentes mantêm o visual Enfrute (migração `1.1.0-legacy-brand`); novas instalações começam neutras.

### Interface administrativa
- Barra superior comum a todas as telas do SciFlow, com navegação entre telas, eventos ativos e o selo de prontidão (Pronto para uso / N pendências).
- Configurações reorganizadas em menu lateral por grupos (Evento, Inscrição e prazos, Comunicação, Avaliação e publicação, Sistema), seções em cartões e barra de salvar fixa.
- Cartões, campos, tabelas, abas internas, botões e chips de status padronizados; a cor de destaque segue a identidade do evento, com contraste garantido.
- Textos administrativos sem nomes fixos de evento/instituição; campos de URL legados identificados como tal.
- Corrigido: o seletor de cor não carregava na tela de configurações (erro de JavaScript).

### Novo
- **Assistente de configuração** que abre automaticamente na primeira ativação do plugin, ou do tema SciFlow quando o plugin já está ativo. Tem 7 passos (Evento, Prazos, Inscrição, Páginas, E-mails e LGPD, Certificados e Anais, Revisão) com validação no servidor. Instalações existentes não são redirecionadas, e o assistente pode ser reaberto em SciFlow → Assistente de configuração.
- Tela **Diagnóstico** com assistente, criação idempotente de páginas e "Reparar instalação".
- Abas Eventos, Páginas, Inscrição, E-mail, Certificados & Anais e Avançado.
- Registro de páginas por função (sem slugs fixos), registro de eventos com rótulos configuráveis, remetente configurável.
- API para temas: `sciflow_get_page_url()`, `sciflow_can_view_submission()`, `sciflow_user_has_paid_registration()`, `sciflow_get_setting()`, `sciflow_event_label()`.
- Suporte a multisite (ativação em rede e novos sites); desinstalação com limpeza opcional (nunca apaga trabalhos).
- Testes de integração (`tests/integration/`) e `bin/build.sh`.
### Corrigido
- A inscrição paga não era verificada no servidor (ordem de inicialização do WooCommerce).
- Atualizações apagavam as permissões customizadas do Gestor Técnico.
- A desativação limpava só 1 dos 3 eventos de cron.
- Aviso "translation loading too early" (WP 6.7+) e parâmetros implicitamente anuláveis (deprecados no PHP 8.4).
### Compatibilidade
- A migração `1.1.0-legacy-settings` preserva nomes, textos dos anais e o modo de certificados das instalações existentes.

## 1.0.6
Versão anterior (sem changelog formal). Veja o histórico Git.

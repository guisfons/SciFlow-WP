# SciFlow WP

Plugin WordPress para **eventos científicos**: inscrição (WooCommerce, opcional), submissão de resumos, avaliação por pares, decisão editorial, envio e avaliação de pôsteres, ranking e seleção para apresentação oral, palestras, certificados, e-mails e geração dos **Anais** para impressão em PDF.

Foi criado para o Enfrute/Semco e generalizado na versão 1.1.0: nomes de eventos, páginas, remetente, textos e anais são configurados pelo painel, sem editar código.

## Funcionalidades
- Até **2 eventos** simultâneos (CPTs `enfrute_trabalhos` e `semco_trabalhos`, com rótulos configuráveis).
- Submissão com limites de caracteres, palavras-chave, coautores, LGPD, rascunho e reenvio após correções.
- Papéis: inscrito, palestrante, revisor, editor (geral ou por evento), técnico conveniado e gestor técnico.
- Fluxo editorial com transições validadas, histórico de mensagens e notificações por e-mail.
- Pôster em PDF (validado), galeria pública, ranking ponderado, seleção top-N com confirmação por token.
- Exportações CSV/XLS/JSON (protegidas contra injeção de fórmulas), importação JSON, e-mail em massa.
- Certificados por e-mail, com acesso configurável e limite de tentativas.
- **Diagnóstico de prontidão** e assistente de configuração inicial.

## Arquitetura
Plugin (regras, dados, segurança) + tema opcional [Inscrição Enfrute](https://github.com/guisfons/enfrute-inscricao) (apresentação). Detalhes em [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Requisitos
WordPress 6.0+ (testado no 7.1) · PHP 7.4+ com `mbstring`, `fileinfo`, `json` (recomendado 8.1+, `zip`). WooCommerce é opcional.

## Instalação rápida
1. Envie e ative `sciflow-wp-<versão>.zip`.
2. Opcional: ative o tema Inscrição Enfrute e o WooCommerce.
3. Abra **SciFlow → Diagnóstico**, clique em **Criar páginas ausentes** e siga os passos até ver "Pronto para uso".

Guia completo (Nginx, segredos, multisite, atualização, backup, solução de problemas): [docs/INSTALL.md](docs/INSTALL.md).

## Dependências
| | Tipo |
|---|---|
| WooCommerce | opcional: inscrição paga e papéis por compra |
| Plugin SMTP | recomendado: entrega de e-mails |
| PayGo/Gate2all | opcional (legado): Pix com webhook |
| libreoffice, gs, chromium-browser | opcional: PDF consolidado de palestras |
| Bootstrap Icons 1.11.3, jQuery Mask 1.14.16 | incluídas em `public/vendor/` (MIT) |

## Testes
```bash
wp eval-file wp-content/plugins/SciFlow-WP/tests/integration/run-tests.php   # apenas em instalação descartável
tests/integration/http-smoke.sh <url> <admin> <senha> <autor> <senha> <id_trabalho>
```

## Distribuição
Cada merge na `main` publica automaticamente uma **Release** com o ZIP instalável (veja [docs/RELEASES.md](docs/RELEASES.md)). Para gerar localmente, `bin/build.sh` gera `dist/sciflow-wp-<versão>.zip` (via `git archive`; `tests/`, `docs/` e `bin/` ficam de fora) e o arquivo `.sha256` correspondente.

## Documentação
[Arquitetura](docs/ARCHITECTURE.md) · [Instalação e operação](docs/INSTALL.md) · [Releases](docs/RELEASES.md) · [Política de segurança](SECURITY.md) · [Checklist de lançamento](docs/RELEASE-CHECKLIST.md) · [Changelog](CHANGELOG.md) · [Estudo de caso](docs/CASE-STUDY.md)

## Licença
MIT. Veja [LICENSE](LICENSE).

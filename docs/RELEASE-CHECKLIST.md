# Checklist de lançamento

- [ ] Versão atualizada em `sciflow-wp.php` (cabeçalho e `SCIFLOW_VERSION`) e no tema (`style.css` e `INSCRICAO_ENFRUTE_VERSION`).
- [ ] `CHANGELOG.md` atualizado (seção "Mudanças de comportamento" quando houver).
- [ ] `php -l` em todos os arquivos e `node --check public/js/sciflow-public.js`.
- [ ] `phpcs --standard=phpcs.xml.dist` sem alertas novos de segurança.
- [ ] `tests/integration/run-tests.php` 100% numa instalação limpa.
- [ ] `tests/integration/http-smoke.sh` nos cenários: com tema, sem tema, com WooCommerce, sem WooCommerce.
- [ ] Atualização testada sobre uma **cópia** do banco de produção (migrações, Diagnóstico "Pronto").
- [ ] `bin/build.sh` nos dois repositórios; conferir `.sha256` e o conteúdo (sem `.git`, `tests`, `docs`, `.env`, logs).
- [ ] Instalação dos ZIPs num WordPress limpo (`wp plugin install <zip> --activate`).
- [ ] Backup de produção (banco + `uploads/`) antes de publicar.
- [ ] Depois de publicar: Diagnóstico, um fluxo completo de teste (submissão → revisão → decisão → pôster) e e-mail de teste.
- [ ] Merge na `main` com título no padrão Conventional Commits: a release é criada automaticamente (docs/RELEASES.md).

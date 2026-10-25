# Releases

Cada push na `main` (ou seja, cada merge) gera uma nova **GitHub Release** com o ZIP instalável. O trabalho é feito por `.github/workflows/release.yml` e `bin/release.sh`.

## Versão
- Se a versão do cabeçalho do plugin for **maior** que a última tag, ela é publicada como está.
- Se não for, a versão é incrementada a partir das mensagens de commit desde a última tag ([Conventional Commits](https://www.conventionalcommits.org/pt-br/)):

| Commit | Incremento | Exemplo |
|---|---|---|
| `feat!:` ou rodapé `BREAKING CHANGE:` | major | 1.4.2 → 2.0.0 |
| `feat:` | minor | 1.4.2 → 1.5.0 |
| qualquer outro (`fix:`, `docs:`, `refactor:`…) | patch | 1.4.2 → 1.4.3 |

O workflow atualiza o cabeçalho, a constante de versão e o `CHANGELOG.md` num commit `chore(release): vX.Y.Z` (que não dispara outro release). Depois cria a tag `vX.Y.Z` e publica a release com o ZIP e o `.sha256`.

## Notas da versão
- Se o `CHANGELOG.md` já tiver a seção `## X.Y.Z`, ela é usada como nota da versão.
- Se não tiver, as notas são geradas a partir dos commits, agrupadas em **Novidades** (`feat`), **Correções** (`fix`), **Desempenho**, **Segurança** e **Outras mudanças**. A seção também é gravada no CHANGELOG.

## Fluxo recomendado
1. Crie uma branch, faça commits no padrão (`feat: …`, `fix: …`) e abra um PR.
2. Ao fazer o merge, prefira **squash merge** com título no padrão, pois é ele que define o incremento.
3. A release aparece em *Releases* em cerca de 1 minuto. Instale o ZIP anexado.

## Simular localmente
```bash
DRY_RUN=1 bin/release.sh   # mostra a próxima versão e as notas, sem alterar arquivos
bin/build.sh               # gera dist/<pacote>-<versão>.zip
```

## Publicar manualmente
Em *Actions → Release → Run workflow*. Se a release da versão atual já existir, nada é feito.

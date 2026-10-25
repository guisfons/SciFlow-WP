# Política de segurança

## Versões com suporte
Recebe correções de segurança apenas a versão mais recente publicada em [Releases](../../releases).

## Como reportar uma vulnerabilidade
**Não abra issue pública.** Use o recurso de relato privado do GitHub (*Security → Report a vulnerability*) neste repositório, informando:
- versão afetada;
- passos para reproduzir;
- impacto esperado.

A confirmação de recebimento sai em até 5 dias úteis. A correção é publicada como uma nova release, e o problema é descrito nas notas da versão depois que a atualização estiver disponível.

## Boas práticas para quem instala
- Mantenha o plugin atualizado pela página de Releases.
- Defina os segredos de pagamento no `wp-config.php` (`SCIFLOW_PAYGO_*`), não no banco.
- No Nginx, bloqueie `uploads/certificados` e `uploads/sciflow-private` (veja `docs/INSTALL.md`).
- Rode **SciFlow → Diagnóstico** após cada atualização.

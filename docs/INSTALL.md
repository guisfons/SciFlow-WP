# Guia de instalação e operação

## 1. Requisitos
| Item | Mínimo | Recomendado |
|---|---|---|
| WordPress | 6.0 | 6.7+ (testado com 7.1) |
| PHP | 7.4 com `mbstring`, `fileinfo`, `json` | 8.1+ e `zip` |
| Banco | MySQL 5.7 / MariaDB 10.4 | MySQL 8 |
| WooCommerce | opcional | 8+ (testado com 11.0) para inscrições pagas |
| E-mail | `wp_mail` funcional | plugin SMTP com SPF e DKIM do domínio |
| Cron | WP-Cron | cron do sistema chamando `wp-cron.php` a cada 5–15 min |
| Opcionais | — | `libreoffice`, `gs`, `chromium-browser` (PDF consolidado de palestras) |

## 2. Instalação em um WordPress limpo
1. **Plugin:** Plugins → Adicionar novo → Enviar plugin → `sciflow-wp-<versão>.zip` → Ativar.
   - Na ativação: papéis, CPTs, cron e proteção de `uploads/certificados` e `uploads/sciflow-private`. Reativar não duplica nada.
2. **Tema (opcional, recomendado):** Aparência → Temas → Enviar → `enfrute-inscricao-<versão>.zip` → Ativar. Sem o plugin, o tema mostra um aviso e não quebra.
3. **WooCommerce (opcional):** instale pelo diretório oficial e crie o produto de inscrição (simples ou variável).
4. Ao ativar o plugin pela primeira vez, o **assistente de configuração** abre sozinho. São 7 passos validados: evento, prazos, inscrição/WooCommerce, páginas (criadas automaticamente), e-mails/LGPD, certificados/Anais e revisão. Também pode ser aberto em SciFlow → Assistente de configuração. Equivalente manual, pelo Diagnóstico:
   1. Eventos: nomes curtos e completos (e edição, para os Anais).
   2. **Criar páginas ausentes**: cria só o que falta e marca cada página com `_sciflow_page_role`. Com o tema SciFlow, usa os modelos do tema; com outro tema, usa shortcodes.
   3. Prazos, texto LGPD e IDs dos produtos WooCommerce.
   4. Remetente dos e-mails. Faça um envio de teste pelo plugin SMTP.
   5. Menus (Aparência → Menus): posições por perfil (Inscritos, Palestrantes, Editor, Revisor, Técnicos).
5. Volte ao Diagnóstico até aparecer **"Pronto para uso"**. Itens "Informativo" não bloqueiam, mas leia cada um.

### Segredos fora do banco (recomendado)
```php
// wp-config.php
define('SCIFLOW_PAYGO_INTEGRATION_KEY', '...');
define('SCIFLOW_PAYGO_TOKEN', '...');
define('SCIFLOW_PAYGO_WEBHOOK_SECRET', '...'); // gere com: openssl rand -hex 24
```
Também valem variáveis de ambiente com os mesmos nomes. Quando definidas, a tela de configurações mostra "definido no servidor".

### Nginx
O `.htaccess` não vale no Nginx. Adicione ao bloco do site:
```nginx
location ~* ^/wp-content/uploads/(certificados|sciflow-private|sciflow-certificates)/ { deny all; }
location ~ /\.(?!well-known) { deny all; }
```

### Multisite
A ativação em rede instala o plugin em cada site, e novos sites são configurados automaticamente. As configurações são **por site**.

## 3. Atualização
1. **Backup** (§4) do banco e de `wp-content/uploads`.
2. Substitua os arquivos enviando o ZIP novo (o WordPress pergunta "substituir a versão atual"). As pastas `SciFlow-WP/` e `enfrute-inscricao/` mantêm o nome, então o plugin continua ativo.
3. Na primeira carga após a atualização, as migrações rodam sozinhas e não apagam dados.
4. Abra o Diagnóstico e confira.

### Atualização da 1.0.x para a 1.1.0 (instalação Enfrute/Semco)
O que **muda** no comportamento:
- Uma submissão sem inscrição paga passa a ser **bloqueada também no servidor** (antes só a interface bloqueava). Desmarque "Exigir inscrição paga" se não quiser isso.
- Editores de um evento deixam de decidir trabalhos do outro evento (o editor geral e o administrador continuam vendo tudo).
- O Gestor Técnico não consegue mais editar ou promover administradores, nem atribuir papéis com `unfiltered_html`.
- Os certificados deixam de ser acessíveis pelo caminho direto `uploads/certificados/…pdf`. O download pela página continua funcionando, no mesmo modo de antes ("por e-mail"). **Recomendado:** mudar para "Somente logado".
- Os e-mails cadastrados em "E-mail do Editor" passam a receber as notificações de novas submissões, além dos usuários com papel de editor.
- Bibliotecas JS/CSS deixam de vir de CDN.

O que **não** muda: dados, status, URLs das páginas existentes, textos dos anais (modo "Modelo original"), nomes dos eventos e permissões customizadas do Gestor.

## 4. Backup e recuperação
- **O que salvar:** o banco inteiro (tabelas `wp_*`: posts, postmeta, users, usermeta, options) e `wp-content/uploads/` (pôsteres, palestras, `certificados/`).
- **Comando:**
  ```bash
  wp db export backup-$(date +%F).sql
  tar czf uploads-$(date +%F).tgz wp-content/uploads
  ```
- **Recuperar uma versão anterior do plugin:** desative, reenvie o ZIP anterior e ative. A 1.1.0 só **acrescenta** opções (`sciflow_migrations`, novas chaves em `sciflow_settings`), que a 1.0.x ignora.
- **Recuperação total:** `wp db import backup.sql` e restaure `uploads/`.
- Excluir o plugin **não** remove dados, a menos que "Remover configurações ao excluir" esteja marcado. Mesmo assim, trabalhos, arquivos e usuários são preservados.

## 5. Diagnóstico e solução de problemas
| Sintoma | Causa provável | Ação |
|---|---|---|
| Aviso "configuração inicial pendente" | itens bloqueantes no Diagnóstico | SciFlow → Diagnóstico |
| Links levam à página errada ou 404 | página não configurada | Configurações → Páginas, ou "Criar páginas ausentes" |
| "Você precisa ter uma inscrição paga" | sem pedido concluído/processando do produto | conferir IDs dos produtos e pedido; ou desmarcar a exigência |
| Prazos não aplicados automaticamente | WP-Cron desativado ou sem visitas | cron do sistema chamando `wp-cron.php` |
| E-mails não chegam | `wp_mail` sem SMTP | plugin SMTP; veja WooCommerce → Status → Logs (`sciflow_email`) |
| Certificado "não encontrado" | arquivo ausente ou nome diferente do e-mail | o PDF deve ser `uploads/certificados/<email>.pdf` (participação) ou `uploads/certificados/<tipo>/<email>.pdf` (veja §7) |
| PDF consolidado de palestras falha | binários ausentes ou `shell_exec` bloqueado | Diagnóstico → "Ferramentas dos Anais". A prévia para impressão continua funcionando |
| Papéis ou permissões estranhos | papéis corrompidos | Diagnóstico → **Reparar instalação** |
| Páginas do tema "indisponíveis" | plugin inativo | ativar o SciFlow WP |

## 6. Executando os testes
Só em uma instalação **descartável**: os testes criam usuários e trabalhos.
```bash
wp plugin activate sciflow-wp
wp eval-file wp-content/plugins/<pasta>/tests/integration/run-tests.php
tests/integration/http-smoke.sh http://site admin <senha> <autor> <senha> <id_trabalho>
```
Análise estática: `phpcs --standard=phpcs.xml.dist` (WPCS e PHPCompatibilityWP).

## 7. Certificados
Os PDFs são enviados por FTP/SFTP (ou gerenciador de arquivos) para `wp-content/uploads/certificados/`, sempre com o nome `<e-mail da inscrição>.pdf`:

```
uploads/certificados/
├── fulano@exemplo.com.pdf          ← participação (raiz, como sempre foi)
├── poster/fulano@exemplo.com.pdf
├── poster/fulano@exemplo.com-2.pdf ← segundo pôster da mesma pessoa
├── apresentacao-oral/…
├── palestrante/…
└── mediador/…
```

- Cada subpasta é um tipo; basta criá-la. Nomes em minúsculas, sem acentos nem espaços. Nomes conhecidos ganham rótulo acentuado (`poster` → "Pôster", `apresentacao-oral` → "Apresentação oral", `comissao-organizadora`…); os demais viram "Nome da pasta".
- A pessoa vê todos os seus certificados na página de certificados (e no menu "Minha conta"). Logada, a busca usa o e-mail da conta e o de cobrança do WooCommerce.
- Conferência: SciFlow → Diagnóstico → "Certificados enviados" mostra a contagem por tipo.
- A pasta inteira (com as subpastas) é bloqueada para acesso direto; no Nginx, a regra da §2 já cobre as subpastas.


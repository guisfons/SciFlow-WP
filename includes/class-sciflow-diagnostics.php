<?php
/**
 * Readiness diagnostics.
 *
 * Each check returns a status:
 *  - error:   blocks readiness (SciFlow will not work correctly);
 *  - warning: works, but something should be reviewed;
 *  - info:    optional feature not configured / not verifiable;
 *  - ok.
 *
 * External services are never contacted here. A configured credential is
 * reported as "configurada (conectividade não verificada)".
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Diagnostics
{
    const MIN_PHP = '7.4';
    const RECOMMENDED_PHP = '8.1';
    const MIN_WP = '6.0';

    /**
     * Run every check.
     *
     * @return array<int, array{group:string, id:string, label:string, status:string, message:string, action:string}>
     */
    public static function run()
    {
        $checks = array_merge(
            self::environment_checks(),
            self::configuration_checks(),
            self::page_checks(),
            self::integration_checks()
        );

        /**
         * Filter the diagnostic results (add custom checks).
         *
         * @param array $checks
         */
        return apply_filters('sciflow_diagnostics', $checks);
    }

    /**
     * Whether there are no blocking errors.
     */
    public static function is_ready($checks = null)
    {
        $checks = null === $checks ? self::run() : $checks;
        foreach ($checks as $check) {
            if ('error' === $check['status']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Count checks by status.
     */
    public static function summary($checks)
    {
        $summary = array('error' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0);
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }
        return $summary;
    }

    private static function item($group, $id, $label, $status, $message, $action = '')
    {
        return compact('group', 'id', 'label', 'status', 'message', 'action');
    }

    private static function environment_checks()
    {
        global $wp_version;
        $g = __('Ambiente', 'sciflow-wp');
        $out = array();

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $out[] = self::item($g, 'php', 'PHP', 'error', sprintf(__('PHP %1$s detectado; mínimo %2$s.', 'sciflow-wp'), PHP_VERSION, self::MIN_PHP));
        } elseif (version_compare(PHP_VERSION, self::RECOMMENDED_PHP, '<')) {
            $out[] = self::item($g, 'php', 'PHP', 'warning', sprintf(__('PHP %1$s funciona, mas %2$s ou superior é recomendado.', 'sciflow-wp'), PHP_VERSION, self::RECOMMENDED_PHP));
        } else {
            $out[] = self::item($g, 'php', 'PHP', 'ok', PHP_VERSION);
        }

        $out[] = version_compare($wp_version, self::MIN_WP, '<')
            ? self::item($g, 'wp', 'WordPress', 'error', sprintf(__('WordPress %1$s detectado; mínimo %2$s.', 'sciflow-wp'), $wp_version, self::MIN_WP), admin_url('update-core.php'))
            : self::item($g, 'wp', 'WordPress', 'ok', $wp_version);

        $extensions = array(
            'mbstring' => array('error', __('usada na contagem de caracteres dos resumos', 'sciflow-wp')),
            'fileinfo' => array('error', __('usada na validação dos arquivos enviados', 'sciflow-wp')),
            'json'     => array('error', __('usada na comunicação AJAX', 'sciflow-wp')),
            'zip'      => array('warning', __('usada para ler arquivos .docx nos Anais', 'sciflow-wp')),
        );
        foreach ($extensions as $ext => $info) {
            $out[] = extension_loaded($ext)
                ? self::item($g, 'ext_' . $ext, sprintf(__('Extensão PHP %s', 'sciflow-wp'), $ext), 'ok', __('Carregada', 'sciflow-wp'))
                : self::item($g, 'ext_' . $ext, sprintf(__('Extensão PHP %s', 'sciflow-wp'), $ext), $info[0], sprintf(__('Ausente — %s.', 'sciflow-wp'), $info[1]));
        }

        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || !wp_is_writable($uploads['basedir'])) {
            $out[] = self::item($g, 'uploads', __('Diretório de uploads', 'sciflow-wp'), 'error', __('Sem permissão de escrita. Pôsteres e palestras não poderão ser enviados.', 'sciflow-wp'));
        } else {
            $out[] = self::item($g, 'uploads', __('Diretório de uploads', 'sciflow-wp'), 'ok', __('Gravável', 'sciflow-wp'));
        }

        $base = trailingslashit($uploads['basedir']);
        $unprotected = array();
        foreach (array('certificados', 'sciflow-private') as $dir) {
            if (is_dir($base . $dir) && !file_exists($base . $dir . '/.htaccess')) {
                $unprotected[] = $dir;
            }
        }
        $server = isset($_SERVER['SERVER_SOFTWARE']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))) : '';
        if ($unprotected) {
            $out[] = self::item($g, 'private_dirs', __('Diretórios privados', 'sciflow-wp'), 'error', sprintf(__('Sem proteção contra acesso direto: %s. Reative o plugin ou use "Reparar instalação".', 'sciflow-wp'), implode(', ', $unprotected)));
        } elseif (false !== strpos($server, 'nginx')) {
            $out[] = self::item($g, 'private_dirs', __('Diretórios privados', 'sciflow-wp'), 'warning', __('Servidor Nginx detectado: arquivos .htaccess são ignorados. Adicione a regra "deny all" para uploads/certificados e uploads/sciflow-private (veja docs/INSTALL.md).', 'sciflow-wp'));
        } else {
            $out[] = self::item($g, 'private_dirs', __('Diretórios privados', 'sciflow-wp'), 'ok', __('Protegidos por .htaccess/web.config', 'sciflow-wp'));
        }

        if (is_dir($base . 'certificados') && class_exists('SciFlow_Certificates')) {
            $counts = array();
            foreach (SciFlow_Certificates::types() as $type => $label) {
                $dirs = SciFlow_Certificates::ROOT_TYPE === $type ? array($base . 'certificados/', $base . 'certificados/' . $type . '/') : array($base . 'certificados/' . $type . '/');
                $n = 0;
                foreach ($dirs as $dir) {
                    $n += count(preg_grep('/\.pdf$/i', glob($dir . '*') ?: array()));
                }
                if ($n) {
                    $counts[] = sprintf('%s: %d', $label, $n);
                }
            }
            $out[] = self::item($g, 'certificates', __('Certificados enviados', 'sciflow-wp'), 'info', $counts ? implode(' · ', $counts) : __('Nenhum PDF em uploads/certificados/.', 'sciflow-wp'));
        }

        $missing_cron = array();
        foreach (SciFlow_Installer::CRON_HOOKS as $hook) {
            if (!wp_next_scheduled($hook)) {
                $missing_cron[] = $hook;
            }
        }
        if ($missing_cron) {
            $out[] = self::item($g, 'cron', __('Tarefas agendadas', 'sciflow-wp'), 'warning', sprintf(__('Não agendadas: %s. Use "Reparar instalação".', 'sciflow-wp'), implode(', ', $missing_cron)));
        } elseif (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $out[] = self::item($g, 'cron', __('Tarefas agendadas', 'sciflow-wp'), 'info', __('WP-Cron interno desativado (DISABLE_WP_CRON). Confirme que existe um cron do sistema chamando wp-cron.php — os prazos dependem dele.', 'sciflow-wp'));
        } else {
            $out[] = self::item($g, 'cron', __('Tarefas agendadas', 'sciflow-wp'), 'ok', __('Agendadas', 'sciflow-wp'));
        }

        return $out;
    }

    private static function configuration_checks()
    {
        $g = __('Configuração', 'sciflow-wp');
        $settings_url = admin_url('admin.php?page=sciflow-settings');
        $out = array();

        $enabled = SciFlow_Events::enabled();
        if (!$enabled) {
            $out[] = self::item($g, 'events', __('Eventos', 'sciflow-wp'), 'error', __('Nenhum evento habilitado.', 'sciflow-wp'), $settings_url . '#tab-eventos');
        } else {
            $generic = array();
            foreach ($enabled as $event) {
                if (in_array($event['label'], array('Evento A', 'Evento B'), true)) {
                    $generic[] = $event['label'];
                }
            }
            $out[] = $generic
                ? self::item($g, 'events', __('Eventos', 'sciflow-wp'), 'warning', sprintf(__('Nomes genéricos em uso (%s). Defina os nomes reais dos eventos.', 'sciflow-wp'), implode(', ', $generic)), $settings_url . '#tab-eventos')
                : self::item($g, 'events', __('Eventos', 'sciflow-wp'), 'ok', implode(', ', wp_list_pluck($enabled, 'label')));
        }

        $missing_roles = array();
        foreach (array('sciflow_inscrito', 'sciflow_revisor', 'sciflow_editor', 'sciflow_speaker') as $role) {
            if (!get_role($role)) {
                $missing_roles[] = $role;
            }
        }
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap('manage_sciflow')) {
            $missing_roles[] = 'administrator:manage_sciflow';
        }
        $out[] = $missing_roles
            ? self::item($g, 'roles', __('Papéis e permissões', 'sciflow-wp'), 'error', sprintf(__('Ausentes: %s. Use "Reparar instalação".', 'sciflow-wp'), implode(', ', $missing_roles)))
            : self::item($g, 'roles', __('Papéis e permissões', 'sciflow-wp'), 'ok', __('Registrados', 'sciflow-wp'));

        $from = SciFlow_Email::get_from_address();
        $out[] = is_email($from)
            ? self::item($g, 'email_from', __('Remetente dos e-mails', 'sciflow-wp'), 'ok', $from)
            : self::item($g, 'email_from', __('Remetente dos e-mails', 'sciflow-wp'), 'error', __('Endereço de remetente inválido.', 'sciflow-wp'), $settings_url . '#tab-email');

        $out[] = self::item($g, 'email_delivery', __('Entrega de e-mails', 'sciflow-wp'), 'info', __('A entrega depende do servidor de e-mail do WordPress (wp_mail). Recomenda-se um plugin SMTP e um envio de teste — o SciFlow não verifica a entrega.', 'sciflow-wp'));

        if ('' === trim(wp_strip_all_tags((string) SciFlow_Settings::get('lgpd_consent_text', '')))) {
            $out[] = self::item($g, 'lgpd', __('Texto de consentimento LGPD', 'sciflow-wp'), 'warning', __('Não definido — um texto genérico será exibido.', 'sciflow-wp'), $settings_url . '#tab-geral');
        }

        foreach (array('article_submission_deadline' => __('Prazo de submissão', 'sciflow-wp'), 'poster_submission_deadline' => __('Prazo de pôsteres', 'sciflow-wp')) as $key => $label) {
            if ('' === (string) SciFlow_Settings::get($key, '')) {
                $out[] = self::item($g, $key, $label, 'info', __('Sem prazo definido (sem bloqueio automático).', 'sciflow-wp'), $settings_url . '#tab-prazos');
            }
        }

        return $out;
    }

    private static function page_checks()
    {
        $g = __('Páginas', 'sciflow-wp');
        $out = array();
        $create_url = wp_nonce_url(admin_url('admin-post.php?action=sciflow_create_pages'), 'sciflow_create_pages');

        foreach (SciFlow_Pages::definitions() as $role => $def) {
            $page_id = SciFlow_Pages::get_id($role);
            if ($page_id) {
                $out[] = self::item($g, 'page_' . $role, $def['title'], 'ok', get_permalink($page_id), get_edit_post_link($page_id, 'raw'));
                continue;
            }
            $needs_theme = 'theme' === $def['provider'] && '' === $def['shortcode'];
            if ($needs_theme && !current_theme_supports('sciflow')) {
                $out[] = self::item($g, 'page_' . $role, $def['title'], 'info', __('Disponível apenas com o tema SciFlow ativo.', 'sciflow-wp'));
                continue;
            }
            $out[] = self::item($g, 'page_' . $role, $def['title'], $def['required'] ? 'error' : 'info', $def['required']
                ? __('Página obrigatória não encontrada.', 'sciflow-wp')
                : __('Página opcional não criada.', 'sciflow-wp'), $create_url);
        }

        return $out;
    }

    private static function integration_checks()
    {
        $g = __('Integrações', 'sciflow-wp');
        $out = array();
        $settings_url = admin_url('admin.php?page=sciflow-settings');

        // Theme.
        $out[] = current_theme_supports('sciflow')
            ? self::item($g, 'theme', __('Tema', 'sciflow-wp'), 'ok', sprintf(__('%s (compatível com SciFlow)', 'sciflow-wp'), wp_get_theme()->get('Name')))
            : self::item($g, 'theme', __('Tema', 'sciflow-wp'), 'warning', sprintf(__('O tema "%s" não declara suporte ao SciFlow. As páginas usarão shortcodes; detalhe do trabalho, inscrição de terceiros e trabalhos publicados exigem o tema SciFlow.', 'sciflow-wp'), wp_get_theme()->get('Name')), admin_url('themes.php'));

        // WooCommerce.
        $woo_active = class_exists('WooCommerce');
        $product_ids = SciFlow_Settings::get_id_list('woo_product_ids');
        $require_paid = '1' === (string) SciFlow_Settings::get('require_paid_registration', '1');

        if (!$woo_active) {
            if ($require_paid && $product_ids) {
                $out[] = self::item($g, 'woocommerce', 'WooCommerce', 'error', __('Inscrição paga exigida e produtos configurados, mas o WooCommerce não está ativo. A verificação de pagamento não acontece.', 'sciflow-wp'), admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'));
            } else {
                $out[] = self::item($g, 'woocommerce', 'WooCommerce', 'info', __('Não ativo. Inscrições pagas e atribuição automática de papéis por compra ficam desativadas.', 'sciflow-wp'));
            }
        } else {
            $out[] = self::item($g, 'woocommerce', 'WooCommerce', 'ok', defined('WC_VERSION') ? WC_VERSION : __('Ativo', 'sciflow-wp'));

            if (!$product_ids) {
                $out[] = self::item($g, 'woo_products', __('Produto de inscrição', 'sciflow-wp'), $require_paid ? 'warning' : 'info', __('Nenhum produto configurado: todos os usuários logados podem submeter.', 'sciflow-wp'), $settings_url . '#tab-geral');
            }

            foreach (array('woo_product_ids' => __('Produto de inscrição', 'sciflow-wp'), 'woo_speaker_product_ids' => __('Produto de palestrante', 'sciflow-wp'), 'woo_tecnico_epagri_product_ids' => __('Produto de técnico', 'sciflow-wp')) as $key => $label) {
                $ids = SciFlow_Settings::get_id_list($key);
                if (!$ids) {
                    continue;
                }
                $invalid = array();
                foreach ($ids as $id) {
                    $product = function_exists('wc_get_product') ? wc_get_product($id) : null;
                    if (!$product || 'publish' !== get_post_status($product->is_type('variation') ? $product->get_parent_id() : $id)) {
                        $invalid[] = $id;
                    }
                }
                $out[] = $invalid
                    ? self::item($g, $key, $label, 'error', sprintf(__('ID(s) inexistente(s) ou não publicado(s): %s', 'sciflow-wp'), implode(', ', $invalid)), $settings_url . '#tab-geral')
                    : self::item($g, $key, $label, 'ok', implode(', ', $ids));
            }
        }

        // PayGo (legacy Pix gateway).
        $has_key = '' !== (string) SciFlow_Settings::get('paygo_integration_key', '');
        $has_token = '' !== (string) SciFlow_Settings::get('paygo_token', '');
        if (!$has_key && !$has_token) {
            $out[] = self::item($g, 'paygo', 'PayGo (Pix)', 'info', __('Não configurado (opcional).', 'sciflow-wp'));
        } elseif ($has_key xor $has_token) {
            $out[] = self::item($g, 'paygo', 'PayGo (Pix)', 'error', __('Credenciais incompletas: informe a chave de integração e o token.', 'sciflow-wp'), $settings_url . '#tab-pagamento');
        } else {
            $out[] = self::item($g, 'paygo', 'PayGo (Pix)', 'info', sprintf(__('Credenciais configuradas (ambiente: %s). Conectividade NÃO verificada — confirme com uma cobrança de teste no ambiente sandbox.', 'sciflow-wp'), SciFlow_Settings::get('paygo_ambiente', 'sandbox')));
            if ('' === (string) SciFlow_Settings::get('paygo_webhook_secret', '')) {
                $out[] = self::item($g, 'paygo_webhook', __('Webhook PayGo', 'sciflow-wp'), 'warning', __('Sem segredo de webhook. As notificações continuam sendo confirmadas na API antes de aprovar o pagamento, mas recomenda-se definir um segredo.', 'sciflow-wp'), $settings_url . '#tab-pagamento');
            }
        }

        // Optional tooling for the consolidated lectures PDF.
        $shell = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true);
        if (!$shell) {
            $out[] = self::item($g, 'anais_tools', __('Ferramentas dos Anais', 'sciflow-wp'), 'info', __('shell_exec indisponível: o PDF consolidado de palestras não pode ser gerado no servidor. A pré-visualização para impressão continua funcionando.', 'sciflow-wp'));
        } else {
            $missing = array();
            foreach (array('libreoffice', 'gs', 'chromium-browser') as $bin) {
                $path = trim((string) shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null')); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
                if ('' === $path) {
                    $missing[] = $bin;
                }
            }
            $out[] = $missing
                ? self::item($g, 'anais_tools', __('Ferramentas dos Anais', 'sciflow-wp'), 'info', sprintf(__('Opcional. Ausentes: %s. Necessárias apenas para o PDF consolidado de palestras.', 'sciflow-wp'), implode(', ', $missing)))
                : self::item($g, 'anais_tools', __('Ferramentas dos Anais', 'sciflow-wp'), 'ok', 'libreoffice, gs, chromium-browser');
        }

        return $out;
    }
}

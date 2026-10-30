<?php
/**
 * Certificate generation (PDF via Dompdf).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Certificates
{
    /**
     * Certificates in the root of uploads/certificados/ (the original layout)
     * belong to this type. Every sub-folder is another type.
     */
    const ROOT_TYPE = 'participacao';

    /**
     * WooCommerce "Minha conta" tab: /minha-conta/certificados/.
     */
    const ACCOUNT_ENDPOINT = 'certificados';

    /**
     * Certificates found by an e-mail lookup, shown by the shortcode when
     * there is more than one to choose from.
     *
     * @var array|null
     */
    private static $lookup = null;

    public function __construct()
    {
        add_action('template_redirect', array($this, 'handle_shortcode_download'));
        add_action('admin_post_sciflow_download_certificate', array($this, 'handle_admin_download'));

        // Participant area: "Minha conta" → Certificados.
        if (class_exists('WooCommerce')) {
            add_filter('woocommerce_get_query_vars', array($this, 'account_query_var'));
            add_action('init', array($this, 'maybe_flush_account_endpoint'), 99);
            add_filter('woocommerce_account_menu_items', array($this, 'account_menu_item'));
            add_filter('woocommerce_endpoint_' . self::ACCOUNT_ENDPOINT . '_title', array($this, 'account_endpoint_title'));
            add_action('woocommerce_account_' . self::ACCOUNT_ENDPOINT . '_endpoint', array($this, 'render_account_endpoint'));
            add_action('woocommerce_account_dashboard', array($this, 'account_dashboard_notice'));
            add_action('template_redirect', array($this, 'redirect_page_to_account'), 20);
        }
    }

    /**
     * Absolute path of uploads/certificados/ (protected against direct access).
     *
     * @return string
     */
    public static function base_dir()
    {
        $upload_dir = wp_upload_dir();
        $cert_dir = trailingslashit($upload_dir['basedir']) . 'certificados/';

        // Personal data: deny direct HTTP access to the directory (Apache
        // applies the rule to every sub-folder as well).
        SciFlow_Security::protect_directory($cert_dir);

        return $cert_dir;
    }

    /**
     * Certificate types available on disk: slug => label.
     *
     * Uploading a new folder (e.g. certificados/palestrante/) is enough to
     * create a type; folder names must be lowercase slugs.
     *
     * @return array
     */
    public static function types()
    {
        $types = array(self::ROOT_TYPE => self::type_label(self::ROOT_TYPE));
        $dirs = glob(self::base_dir() . '*', GLOB_ONLYDIR) ?: array();
        $slugs = array();
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) && self::ROOT_TYPE !== $slug) {
                $slugs[] = $slug;
            }
        }

        // Known types first, in the usual order of an event; the rest alphabetically.
        $order = array_keys(self::known_labels());
        usort($slugs, function ($a, $b) use ($order) {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            $ia = false === $ia ? PHP_INT_MAX : $ia;
            $ib = false === $ib ? PHP_INT_MAX : $ib;
            return $ia === $ib ? strcmp($a, $b) : $ia - $ib;
        });
        foreach ($slugs as $slug) {
            $types[$slug] = self::type_label($slug);
        }

        return apply_filters('sciflow_certificate_types', $types);
    }

    /**
     * Labels for the usual folder names.
     *
     * @return array
     */
    private static function known_labels()
    {
        return array(
            'participacao'          => __('Participação', 'sciflow-wp'),
            'poster'                => __('Pôster', 'sciflow-wp'),
            'apresentacao'          => __('Apresentação', 'sciflow-wp'),
            'apresentacao-oral'     => __('Apresentação oral', 'sciflow-wp'),
            'palestrante'           => __('Palestrante', 'sciflow-wp'),
            'mediador'              => __('Mediador', 'sciflow-wp'),
            'moderador'             => __('Moderador', 'sciflow-wp'),
            'avaliador'             => __('Avaliador', 'sciflow-wp'),
            'comissao-organizadora' => __('Comissão organizadora', 'sciflow-wp'),
            'comissao-cientifica'   => __('Comissão científica', 'sciflow-wp'),
            'monitor'               => __('Monitor', 'sciflow-wp'),
            'ouvinte'               => __('Ouvinte', 'sciflow-wp'),
        );
    }

    /**
     * Human label for a type slug.
     *
     * @param string $slug
     * @return string
     */
    public static function type_label($slug)
    {
        $known = self::known_labels();
        if (isset($known[$slug])) {
            return $known[$slug];
        }
        return ucfirst(str_replace(array('-', '_'), ' ', $slug));
    }

    /**
     * Every certificate issued to the given e-mail addresses, across all types.
     *
     * Matches "<email>.pdf" and numbered copies ("<email>-2.pdf", "<email> (2).pdf")
     * for people with more than one certificate of the same type, ignoring case.
     *
     * @param string|string[] $emails
     * @return array[] Each item: key, type, label, path.
     */
    public static function find_certificates($emails)
    {
        $targets = array();
        foreach ((array) $emails as $email) {
            $email = strtolower(trim((string) $email));
            if ('' !== $email && is_email($email)) {
                $targets[] = preg_quote($email, '/');
            }
        }
        if (!$targets) {
            return array();
        }
        $pattern = '/^(?:' . implode('|', array_unique($targets)) . ')(?:[ _-]*\(?(\d+)\)?)?\.pdf$/';

        $base = self::base_dir();
        $found = array();
        foreach (self::types() as $type => $label) {
            $dirs = self::ROOT_TYPE === $type ? array($base, $base . self::ROOT_TYPE . '/') : array($base . $type . '/');
            foreach ($dirs as $dir) {
                $files = is_dir($dir) ? scandir($dir) : array();
                foreach ($files ?: array() as $file) {
                    if (!preg_match($pattern, strtolower($file)) || !is_file($dir . $file)) {
                        continue;
                    }
                    $key = $type . '/' . $file;
                    $found[$key] = array(
                        'key'   => $key,
                        'type'  => $type,
                        'label' => $label,
                        'path'  => $dir . $file,
                    );
                }
            }
        }

        // Number repeated types ("Pôster 1", "Pôster 2").
        $counts = array_count_values(wp_list_pluck($found, 'type'));
        $seen = array();
        foreach ($found as &$item) {
            if ($counts[$item['type']] > 1) {
                $seen[$item['type']] = ($seen[$item['type']] ?? 0) + 1;
                $item['label'] .= ' ' . $seen[$item['type']];
            }
        }
        unset($item);

        return array_values($found);
    }

    /**
     * Locate a certificate file by email (first match, any type).
     *
     * @deprecated 1.1.2 Use find_certificates(): a person may have several.
     * @param string $input_email
     * @return string|false
     */
    public static function find_certificate_file($input_email)
    {
        $found = self::find_certificates($input_email);
        return $found ? $found[0]['path'] : false;
    }

    /**
     * E-mail addresses that identify the logged-in user's certificates:
     * the account e-mail and the WooCommerce billing e-mail.
     *
     * @return string[]
     */
    public static function current_user_emails()
    {
        if (!is_user_logged_in()) {
            return array();
        }
        $user = wp_get_current_user();
        $emails = array($user->user_email, get_user_meta($user->ID, 'billing_email', true));
        return array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', $emails)), 'is_email')));
    }

    /**
     * Handle download submission from the frontend shortcode.
     *
     * Logged-in users always get their own certificates. Visitors may look
     * up an e-mail only when the "email" access mode is enabled. With a
     * single certificate the download starts right away; with several the
     * shortcode lists them (one button per certificate).
     */
    public function handle_shortcode_download()
    {
        if (!isset($_POST['sciflow_download_cert_submit'])) {
            return;
        }

        $back = wp_get_referer() ?: (is_singular() ? get_permalink() : home_url());

        $nonce = $_POST['sciflow_cert_nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'sciflow_download_cert_action')) {
            wp_safe_redirect(add_query_arg('cert_status', 'expired', $back));
            exit;
        }

        $by_email = 'email' === (string) SciFlow_Settings::get('certificate_access', 'logged_in');
        $typed = $by_email ? sanitize_email(wp_unslash($_POST['sciflow_cert_email'] ?? '')) : '';
        if ($by_email && ('' !== $typed || !is_user_logged_in())) {
            $emails = array($typed);
        } elseif (is_user_logged_in()) {
            $emails = self::current_user_emails();
        } else {
            wp_safe_redirect(add_query_arg('cert_status', 'login_required', $back));
            exit;
        }

        // Limit lookups per IP (prevents e-mail enumeration).
        if (!SciFlow_Security::rate_limit('cert_download', (int) SciFlow_Settings::get('certificate_rate_limit', 20), 10 * MINUTE_IN_SECONDS)) {
            wp_safe_redirect(add_query_arg('cert_status', 'rate_limited', $back));
            exit;
        }

        if (!array_filter($emails, 'is_email')) {
            wp_safe_redirect(add_query_arg('cert_status', 'invalid_email', $back));
            exit;
        }

        $found = self::find_certificates($emails);
        if (!$found) {
            wp_safe_redirect(add_query_arg('cert_status', 'not_found', $back));
            exit;
        }

        // The key is only compared against the files found for this person,
        // never used to build a path.
        $key = sanitize_text_field(wp_unslash($_POST['sciflow_cert_key'] ?? ''));
        if ('' !== $key) {
            $match = wp_list_filter($found, array('key' => $key));
            if (!$match) {
                wp_safe_redirect(add_query_arg('cert_status', 'not_found', $back));
                exit;
            }
            self::send_pdf(reset($match));
        }

        if (1 === count($found)) {
            self::send_pdf($found[0]);
        }

        // Several certificates: let the shortcode render the list.
        self::$lookup = array(
            'email' => $typed,
            'items' => $found,
        );
    }

    /**
     * Stream a certificate PDF and stop.
     *
     * @param array $item Item from find_certificates().
     */
    private static function send_pdf($item)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'certificado-' . sanitize_file_name(remove_accents(strtolower(str_replace(' ', '-', $item['label'])))) . '.pdf';

        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: private, no-store, must-revalidate');
        header('Pragma: public');
        header('X-Robots-Tag: noindex');
        header('Content-Length: ' . filesize($item['path']));
        readfile($item['path']);
        exit;
    }

    /**
     * Register /minha-conta/certificados/ (WooCommerce adds the rewrite endpoint).
     *
     * @param array $vars
     * @return array
     */
    public function account_query_var($vars)
    {
        $vars[self::ACCOUNT_ENDPOINT] = self::ACCOUNT_ENDPOINT;
        return $vars;
    }

    /**
     * New endpoints only resolve after the rewrite rules are rebuilt: do it
     * once per plugin version, after WooCommerce registered its endpoints.
     */
    public function maybe_flush_account_endpoint()
    {
        if (get_option('sciflow_account_endpoint_version') !== SCIFLOW_VERSION) {
            flush_rewrite_rules(false);
            update_option('sciflow_account_endpoint_version', SCIFLOW_VERSION, false);
        }
    }

    /**
     * WooCommerce "Minha conta": add a "Certificados" entry before "Sair".
     *
     * @param array $items
     * @return array
     */
    public function account_menu_item($items)
    {
        $logout = isset($items['customer-logout']) ? array('customer-logout' => $items['customer-logout']) : array();
        unset($items['customer-logout']);
        $items[self::ACCOUNT_ENDPOINT] = __('Certificados', 'sciflow-wp');
        return $items + $logout;
    }

    /**
     * Title of the "Certificados" tab.
     *
     * @return string
     */
    public function account_endpoint_title()
    {
        return __('Certificados', 'sciflow-wp');
    }

    /**
     * Content of /minha-conta/certificados/: the participant's certificates,
     * inside the participant area.
     */
    public function render_account_endpoint()
    {
        echo self::render_shortcode(array(
            'title'    => __('Meus certificados', 'sciflow-wp'),
            'subtitle' => __('Estes são os certificados emitidos para você. Baixe cada um em PDF.', 'sciflow-wp'),
        )); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in render_shortcode().
    }

    /**
     * "Minha conta" dashboard: tell the participant how many certificates are ready.
     */
    public function account_dashboard_notice()
    {
        $count = count(self::find_certificates(self::current_user_emails()));
        if (!$count) {
            return;
        }
        printf(
            '<p class="sciflow-account-certificates">%s <a href="%s">%s</a></p>',
            esc_html(sprintf(_n('Você tem %d certificado disponível.', 'Você tem %d certificados disponíveis.', $count, 'sciflow-wp'), $count)),
            esc_url(self::account_url()),
            esc_html__('Ver e baixar certificados', 'sciflow-wp')
        );
    }

    /**
     * URL of the certificates tab in "Minha conta" ('' without WooCommerce).
     *
     * @return string
     */
    public static function account_url()
    {
        if (!function_exists('wc_get_account_endpoint_url') || wc_get_page_id('myaccount') <= 0) {
            return '';
        }
        return wc_get_account_endpoint_url(self::ACCOUNT_ENDPOINT);
    }

    /**
     * With "logged in only" access the certificates live in the participant
     * area: the standalone certificates page sends visitors there (WooCommerce
     * asks for login first and returns to the tab).
     */
    public function redirect_page_to_account()
    {
        if ('GET' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET') || 'logged_in' !== (string) SciFlow_Settings::get('certificate_access', 'logged_in')) {
            return;
        }
        $page_id = SciFlow_Pages::get_id('certificates');
        $target = self::account_url();
        if ($page_id && '' !== $target && is_page($page_id)) {
            wp_safe_redirect($target);
            exit;
        }
    }

    /**
     * Handle certificate download triggered from WP admin.
     */
    public function handle_admin_download()
    {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $nonce   = $_GET['_wpnonce'] ?? '';

        if (!$post_id || !wp_verify_nonce($nonce, 'sciflow_cert_' . $post_id)) {
            wp_die(__('Link de download inválido ou expirado.', 'sciflow-wp'));
        }

        $this->serve_download($post_id);
    }

    /**
     * Render the shortcode UI with a lightweight and modern design.
     */
    public static function render_shortcode($atts = array())
    {
        $atts = shortcode_atts(array(
            'title'     => __('Baixar Certificado', 'sciflow-wp'),
            'subtitle'  => __('Informe o e-mail utilizado na sua inscrição para localizar e baixar seu certificado em PDF.', 'sciflow-wp'),
            'btn_text'  => __('Baixar Arquivo (PDF)', 'sciflow-wp'),
        ), $atts, 'baixar_certificado');

        $email = '';
        $login_only = 'logged_in' === (string) SciFlow_Settings::get('certificate_access', 'logged_in');

        // null = show the search form; array = list of certificates.
        $items = null;
        $lookup_email = '';
        if (null !== self::$lookup) {
            $items = self::$lookup['items'];
            $lookup_email = self::$lookup['email'];
        } elseif (is_user_logged_in()) {
            $email = wp_get_current_user()->user_email;
            $items = self::find_certificates(self::current_user_emails());
        }
        if (null !== $items && __('Informe o e-mail utilizado na sua inscrição para localizar e baixar seu certificado em PDF.', 'sciflow-wp') === $atts['subtitle']) {
            $atts['subtitle'] = __('Estes são os certificados emitidos para você. Baixe cada um em PDF.', 'sciflow-wp');
        }

        $status = isset($_GET['cert_status']) ? sanitize_text_field($_GET['cert_status']) : '';
        $alert_html = '';

        if ($status === 'not_found') {
            $alert_html = '<div class="sciflow-cert-alert sciflow-cert-alert--error" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>' . esc_html__('Nenhum certificado encontrado para o e-mail informado.', 'sciflow-wp') . '</span>
            </div>';
        } elseif ($status === 'invalid_email') {
            $alert_html = '<div class="sciflow-cert-alert sciflow-cert-alert--error" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>' . esc_html__('Por favor, insira um endereço de e-mail válido.', 'sciflow-wp') . '</span>
            </div>';
        } elseif ($status === 'rate_limited') {
            $alert_html = '<div class="sciflow-cert-alert sciflow-cert-alert--warning" role="alert"><span>' . esc_html__('Muitas tentativas. Aguarde alguns minutos e tente novamente.', 'sciflow-wp') . '</span></div>';
        } elseif ($status === 'login_required') {
            $alert_html = '<div class="sciflow-cert-alert sciflow-cert-alert--warning" role="alert"><span>' . esc_html__('Faça login para baixar o seu certificado.', 'sciflow-wp') . '</span></div>';
        } elseif ($status === 'expired') {
            $alert_html = '<div class="sciflow-cert-alert sciflow-cert-alert--warning" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>' . esc_html__('Sessão expirada. Por favor, recarregue a página e tente novamente.', 'sciflow-wp') . '</span>
            </div>';
        }

        ob_start();
        ?>
        <div class="sciflow-cert-wrapper">
            <style>
                .sciflow-cert-wrapper {
                    max-width: 520px;
                    margin: 32px auto;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                }
                .sciflow-cert-card {
                    background: #ffffff;
                    border: 1px solid #e2e8f0;
                    border-radius: 16px;
                    padding: 36px 32px;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
                    text-align: center;
                    transition: border-color 0.2s ease;
                }
                .sciflow-cert-icon-container {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    background: var(--brand-primary-light, #e9f1f2);
                    color: var(--brand-primary, #1f6f78);
                    margin-bottom: 18px;
                    border: 1px solid rgba(var(--brand-primary-rgb, 31, 111, 120), 0.2);
                }
                .sciflow-cert-title {
                    font-size: 1.4rem;
                    font-weight: 700;
                    color: #1e293b;
                    margin: 0 0 8px;
                    line-height: 1.3;
                }
                .sciflow-cert-subtitle {
                    font-size: 0.92rem;
                    color: #64748b;
                    margin: 0 0 24px;
                    line-height: 1.5;
                }
                .sciflow-cert-alert {
                    border-radius: 10px;
                    padding: 12px 16px;
                    font-size: 0.88rem;
                    margin-bottom: 22px;
                    text-align: left;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    line-height: 1.4;
                }
                .sciflow-cert-alert--error {
                    background: #fef2f2;
                    color: #991b1b;
                    border: 1px solid #fecaca;
                }
                .sciflow-cert-alert--warning {
                    background: #fffbeb;
                    color: #92400e;
                    border: 1px solid #fde68a;
                }
                .sciflow-cert-alert svg {
                    flex-shrink: 0;
                }
                .sciflow-cert-form-group {
                    margin-bottom: 20px;
                    text-align: left;
                }
                .sciflow-cert-label {
                    display: block;
                    font-size: 0.88rem;
                    font-weight: 600;
                    color: #334155;
                    margin-bottom: 8px;
                }
                .sciflow-cert-input {
                    width: 100% !important;
                    box-sizing: border-box !important;
                    padding: 13px 16px !important;
                    font-size: 0.95rem !important;
                    line-height: 1.4 !important;
                    border: 1.5px solid #cbd5e1 !important;
                    border-radius: 10px !important;
                    background: #f8fafc !important;
                    color: #1e293b !important;
                    transition: all 0.2s ease !important;
                    outline: none !important;
                    height: auto !important;
                }
                .sciflow-cert-input:focus {
                    background: #ffffff !important;
                    border-color: var(--brand-primary, #1f6f78) !important;
                    box-shadow: 0 0 0 3px rgba(var(--brand-primary-rgb, 31, 111, 120), 0.15) !important;
                }
                .sciflow-cert-hint {
                    display: block;
                    font-size: 0.8rem;
                    color: #94a3b8;
                    margin-top: 6px;
                }
                .sciflow-cert-btn {
                    width: 100% !important;
                    box-sizing: border-box !important;
                    padding: 13px 22px !important;
                    font-size: 1rem !important;
                    font-weight: 600 !important;
                    color: #ffffff !important;
                    background: var(--brand-primary, #1f6f78) !important;
                    border: none !important;
                    border-radius: 10px !important;
                    cursor: pointer !important;
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 8px !important;
                    text-decoration: none !important;
                    box-shadow: 0 4px 12px rgba(var(--brand-primary-rgb, 31, 111, 120), 0.25) !important;
                }
                .sciflow-cert-btn:hover {
                    background: var(--brand-primary-dark, #17545b) !important;
                    transform: translateY(-1px) !important;
                    box-shadow: 0 6px 16px rgba(var(--brand-primary-rgb, 31, 111, 120), 0.35) !important;
                }
                .sciflow-cert-btn:active {
                    transform: translateY(0) !important;
                }
                .sciflow-cert-list {
                    list-style: none;
                    margin: 0 0 18px;
                    padding: 0;
                    text-align: left;
                }
                .sciflow-cert-item {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 12px 0;
                    border-bottom: 1px solid #e2e8f0;
                }
                .sciflow-cert-item:last-child {
                    border-bottom: none;
                }
                .sciflow-cert-item form {
                    margin: 0;
                    flex-shrink: 0;
                }
                .sciflow-cert-item-label {
                    font-weight: 600;
                    color: #1e293b;
                }
                .sciflow-cert-btn.sciflow-cert-btn--small {
                    width: auto !important;
                    padding: 8px 16px !important;
                    font-size: 0.88rem !important;
                }
                .sciflow-cert-footer {
                    margin-top: 18px;
                    font-size: 0.78rem;
                    color: #94a3b8;
                    line-height: 1.4;
                }
            </style>

            <div class="sciflow-cert-card">
                <div class="sciflow-cert-icon-container">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                </div>

                <h2 class="sciflow-cert-title"><?php echo esc_html($atts['title']); ?></h2>
                <p class="sciflow-cert-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>

                <?php echo $alert_html; ?>

                <?php if (null !== $items) : ?>
                    <?php if ($items) : ?>
                    <ul class="sciflow-cert-list">
                        <?php foreach ($items as $item) : ?>
                        <li class="sciflow-cert-item">
                            <span class="sciflow-cert-item-label"><?php echo esc_html($item['label']); ?></span>
                            <form method="post" action="">
                                <?php wp_nonce_field('sciflow_download_cert_action', 'sciflow_cert_nonce'); ?>
                                <input type="hidden" name="sciflow_cert_key" value="<?php echo esc_attr($item['key']); ?>">
                                <?php if ('' !== $lookup_email) : ?>
                                <input type="hidden" name="sciflow_cert_email" value="<?php echo esc_attr($lookup_email); ?>">
                                <?php endif; ?>
                                <button type="submit" name="sciflow_download_cert_submit" class="sciflow-cert-btn sciflow-cert-btn--small" aria-label="<?php echo esc_attr(sprintf(__('Baixar certificado de %s', 'sciflow-wp'), $item['label'])); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                    <span><?php esc_html_e('PDF', 'sciflow-wp'); ?></span>
                                </button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else : ?>
                    <div class="sciflow-cert-alert sciflow-cert-alert--warning" role="status"><span><?php esc_html_e('Ainda não há certificados emitidos para a sua conta.', 'sciflow-wp'); ?></span></div>
                    <?php endif; ?>

                    <?php if ('' !== $lookup_email) : ?>
                    <p class="sciflow-cert-hint"><?php echo esc_html(sprintf(__('E-mail: %s', 'sciflow-wp'), $lookup_email)); ?> · <a href="<?php echo esc_url(get_permalink()); ?>"><?php esc_html_e('buscar outro e-mail', 'sciflow-wp'); ?></a></p>
                    <?php elseif (is_user_logged_in()) : ?>
                    <p class="sciflow-cert-hint"><?php echo esc_html(sprintf(__('Conta: %s', 'sciflow-wp'), implode(', ', self::current_user_emails()))); ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (null === $items || (!$items && !$login_only)) : ?>
                <form method="post" action="" class="sciflow-cert-form">
                    <?php wp_nonce_field('sciflow_download_cert_action', 'sciflow_cert_nonce'); ?>

                    <?php if ($login_only && !is_user_logged_in()) : ?>
                        <p><a class="sciflow-cert-btn" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>"><?php esc_html_e('Fazer login', 'sciflow-wp'); ?></a></p>
                    <?php else : ?>
                    <div class="sciflow-cert-form-group">
                        <label for="sciflow_cert_email" class="sciflow-cert-label">
                            <?php esc_html_e('E-mail cadastrado no evento:', 'sciflow-wp'); ?>
                        </label>
                        <input type="email"
                               id="sciflow_cert_email"
                               name="sciflow_cert_email"
                               class="sciflow-cert-input"
                               value="<?php echo esc_attr($email); ?>"
                               required
                               placeholder="<?php esc_attr_e('seu.email@exemplo.com', 'sciflow-wp'); ?>"
                               autocomplete="email">
                        <span class="sciflow-cert-hint">
                            <?php esc_html_e('O certificado é localizado utilizando exatamente o e-mail da sua inscrição.', 'sciflow-wp'); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!$login_only || is_user_logged_in()) : ?>
                    <button type="submit" name="sciflow_download_cert_submit" class="sciflow-cert-btn">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span><?php echo esc_html($atts['btn_text']); ?></span>
                    </button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

                <div class="sciflow-cert-footer">
                    <?php esc_html_e('Arquivos emitidos em formato PDF oficial.', 'sciflow-wp'); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Check if an author is eligible for a certificate.
     */
    public function is_eligible($post_id)
    {
        $payment = get_post_meta($post_id, '_sciflow_payment_status', true);
        $confirmed = get_post_meta($post_id, '_sciflow_presentation_confirmed', true);
        $status = get_post_meta($post_id, '_sciflow_status', true);

        // Must have paid.
        if ($payment !== 'confirmed') {
            return false;
        }

        // Must be confirmed (presented) or approved.
        return in_array($status, array('confirmado', 'aprovado', 'poster_enviado', 'poster_em_correcao', 'poster_reenviado', 'poster_aprovado', 'poster_reprovado', 'apto_publicacao', 'aguardando_confirmacao'), true);
    }

    /**
     * Generate a certificate PDF.
     *
     * @param int $post_id The article post ID.
     * @return string|WP_Error Path to generated PDF.
     */
    public function generate($post_id)
    {
        if (!$this->is_eligible($post_id)) {
            return new WP_Error('not_eligible', __('Este trabalho não é elegível para certificado.', 'sciflow-wp'));
        }

        $post = get_post($post_id);
        $author_id = get_post_meta($post_id, '_sciflow_author_id', true);
        $author = get_userdata($author_id);
        $event = get_post_meta($post_id, '_sciflow_event', true);
        $coauthors = get_post_meta($post_id, '_sciflow_coauthors', true);


        $html = $this->render_certificate_html(array(
            'title' => $post->post_title,
            'author_name' => $author ? $author->display_name : __('Autor Desconhecido', 'sciflow-wp'),
            'coauthors' => $coauthors ?: array(),
            'event' => SciFlow_Events::full_label($event),
            'date' => wp_date('d/m/Y'),
            'site_name' => get_bloginfo('name'),
        ));

        // Generate PDF using Dompdf (if available) or save HTML.
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/sciflow-certificates/';

        SciFlow_Security::protect_directory($cert_dir);

        $filename = 'certificado-' . $post_id . '-' . time() . '.pdf';
        $filepath = $cert_dir . $filename;

        // Try Dompdf.
        if (class_exists('\\Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            file_put_contents($filepath, $dompdf->output());
        } else {
            // Fallback: save as HTML file for manual conversion.
            $filepath = str_replace('.pdf', '.html', $filepath);
            file_put_contents($filepath, $html);
        }

        return $filepath;
    }

    /**
     * Render certificate HTML.
     */
    private function render_certificate_html($vars)
    {
        $template = SCIFLOW_PATH . 'public/templates/certificate.php';

        if (file_exists($template)) {
            extract($vars, EXTR_SKIP);
            ob_start();
            include $template;
            return ob_get_clean();
        }

        // Built-in template.
        $authors_list = esc_html($vars['author_name']);
        if (!empty($vars['coauthors'])) {
            foreach ($vars['coauthors'] as $ca) {
                $authors_list .= ', ' . esc_html($ca['name'] ?? '');
            }
        }

        $brand = SciFlow_Branding::get();
        return str_replace(array('#2c5530', '#1a3a1e'), array($brand['primary'], $brand['primary_dark']), '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
    @page { margin: 0; }
    body {
        font-family: "Georgia", serif;
        margin: 0; padding: 60px;
        display: flex; align-items: center; justify-content: center;
        min-height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }
    .certificate {
        border: 8px double #2c5530; padding: 60px;
        text-align: center; background: #fff;
        max-width: 900px; margin: auto;
    }
    .certificate h1 { color: #2c5530; font-size: 32px; margin-bottom: 10px; }
    .certificate h2 { color: #333; font-size: 22px; font-weight: normal; margin-bottom: 30px; }
    .certificate .author { font-size: 26px; font-weight: bold; color: #1a3a1e; margin: 20px 0; }
    .certificate .title { font-size: 18px; font-style: italic; margin: 15px 0; color: #444; }
    .certificate .event { font-size: 16px; color: #666; margin: 10px 0; }
    .certificate .date { font-size: 14px; color: #888; margin-top: 40px; }
</style>
</head>
<body>
<div class="certificate">
    <h1>' . esc_html($vars['site_name']) . '</h1>
    <h2>Certificado de Participação</h2>
    <p>Certificamos que</p>
    <p class="author">' . $authors_list . '</p>
    <p>apresentou o trabalho</p>
    <p class="title">' . esc_html($vars['title']) . '</p>
    <p class="event">' . esc_html($vars['event']) . '</p>
    <p class="date">' . esc_html($vars['date']) . '</p>
</div>
</body>
</html>');
    }

    /**
     * Serve a certificate download.
     */
    public function serve_download($post_id)
    {
        $user_id = get_current_user_id();
        $author_id = (int) get_post_meta($post_id, '_sciflow_author_id', true);

        if ($user_id !== $author_id && !current_user_can('manage_sciflow')) {
            wp_die(__('Acesso negado.', 'sciflow-wp'));
        }

        $filepath = $this->generate($post_id);

        if (is_wp_error($filepath)) {
            wp_die($filepath->get_error_message());
        }

        $ext = pathinfo($filepath, PATHINFO_EXTENSION);
        $mime = $ext === 'pdf' ? 'application/pdf' : 'text/html';
        $filename = 'certificado-' . $post_id . '.' . $ext;

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

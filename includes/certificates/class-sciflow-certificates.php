<?php
/**
 * Certificate generation (PDF via Dompdf).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Certificates
{
    public function __construct()
    {
        add_action('template_redirect', array($this, 'handle_shortcode_download'));
        add_action('admin_post_sciflow_download_certificate', array($this, 'handle_admin_download'));
    }

    /**
     * Locate certificate file in uploads/certificados/ by email.
     *
     * @param string $input_email
     * @return string|false
     */
    public static function find_certificate_file($input_email)
    {
        if (empty($input_email) || !is_email($input_email)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $cert_dir = trailingslashit($upload_dir['basedir']) . 'certificados/';

        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
            @file_put_contents($cert_dir . 'index.php', '<?php // Silence is golden.');
        }

        $email_clean = strtolower(trim($input_email));
        $raw_email   = trim($input_email);

        $candidates = array(
            $cert_dir . $email_clean . '.pdf',
            $cert_dir . $email_clean . '.PDF',
            $cert_dir . $raw_email . '.pdf',
            $cert_dir . $raw_email . '.PDF',
        );

        foreach ($candidates as $file) {
            if (file_exists($file) && is_file($file)) {
                return $file;
            }
        }

        // Case-insensitive scan fallback
        if (is_dir($cert_dir)) {
            $files = scandir($cert_dir);
            if ($files) {
                $target = $email_clean . '.pdf';
                foreach ($files as $f) {
                    if (strtolower($f) === $target) {
                        $matched = $cert_dir . $f;
                        if (is_file($matched)) {
                            return $matched;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Handle download submission from the frontend shortcode.
     */
    public function handle_shortcode_download()
    {
        if (!isset($_POST['sciflow_download_cert_submit'])) {
            return;
        }

        $nonce = $_POST['sciflow_cert_nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'sciflow_download_cert_action')) {
            $redirect = add_query_arg('cert_status', 'expired', wp_get_referer() ?: home_url());
            wp_safe_redirect($redirect);
            exit;
        }

        $input_email = sanitize_email($_POST['sciflow_cert_email'] ?? '');
        if (empty($input_email) || !is_email($input_email)) {
            $redirect = add_query_arg(array(
                'cert_status' => 'invalid_email',
                'cert_email'  => rawurlencode($input_email),
            ), wp_get_referer() ?: home_url());
            wp_safe_redirect($redirect);
            exit;
        }

        $file_path = self::find_certificate_file($input_email);

        if ($file_path && file_exists($file_path)) {
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            $redirect = add_query_arg(array(
                'cert_status' => 'not_found',
                'cert_email'  => rawurlencode($input_email),
            ), wp_get_referer() ?: home_url());
            wp_safe_redirect($redirect);
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
        if (isset($_GET['cert_email'])) {
            $email = sanitize_email(urldecode($_GET['cert_email']));
        } elseif (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $email = $current_user->user_email;
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
                    background: #f0fdf4;
                    color: #16a34a;
                    margin-bottom: 18px;
                    border: 1px solid #dcfce7;
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
                    border-color: #16a34a !important;
                    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15) !important;
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
                    background: #16a34a !important;
                    border: none !important;
                    border-radius: 10px !important;
                    cursor: pointer !important;
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    gap: 8px !important;
                    text-decoration: none !important;
                    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25) !important;
                }
                .sciflow-cert-btn:hover {
                    background: #15803d !important;
                    transform: translateY(-1px) !important;
                    box-shadow: 0 6px 16px rgba(22, 163, 74, 0.35) !important;
                }
                .sciflow-cert-btn:active {
                    transform: translateY(0) !important;
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

                <form method="post" action="" class="sciflow-cert-form">
                    <?php wp_nonce_field('sciflow_download_cert_action', 'sciflow_cert_nonce'); ?>
                    
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

                    <button type="submit" name="sciflow_download_cert_submit" class="sciflow-cert-btn">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span><?php echo esc_html($atts['btn_text']); ?></span>
                    </button>
                </form>

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

        $event_labels = array(
            'enfrute' => 'Enfrute — Encontro Nacional sobre Fruticultura de Clima Temperado',
            'semco' => 'Semco — Seminário Catarinense de Olericultura',
        );

        $html = $this->render_certificate_html(array(
            'title' => $post->post_title,
            'author_name' => $author ? $author->display_name : __('Autor Desconhecido', 'sciflow-wp'),
            'coauthors' => $coauthors ?: array(),
            'event' => $event_labels[$event] ?? $event,
            'date' => wp_date('d/m/Y'),
            'site_name' => get_bloginfo('name'),
        ));

        // Generate PDF using Dompdf (if available) or save HTML.
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/sciflow-certificates/';

        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
            // Protect directory.
            file_put_contents($cert_dir . '.htaccess', 'Deny from all');
            file_put_contents($cert_dir . 'index.php', '<?php // Silence is golden.');
        }

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

        return '<!DOCTYPE html>
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
</html>';
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

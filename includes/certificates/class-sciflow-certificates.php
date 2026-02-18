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
        return in_array($status, array('confirmado', 'aprovado', 'poster_enviado'), true);
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
            'enfrute' => 'Enfrute — Congresso Nacional',
            'senco' => 'Senco — Seminário Catarinense de Olericultura',
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

<?php
/**
 * Template Name: SciFlow — Confirmar Apresentação
 *
 * Page template (registered by the plugin via filter) for the author to confirm
 * their presentation date using a one-time, expirable token sent by e-mail.
 *
 * URL format: /confirmar-apresentacao/?article_id=X&token=Y
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$article_id = absint($_GET['article_id'] ?? 0);
$token      = sanitize_text_field($_GET['token'] ?? '');
$error      = '';
$success    = false;
$already    = false;
$post_title = '';
$pdate      = '';

if ($article_id && $token) {
    $post = get_post($article_id);

    if (!$post || !in_array($post->post_type, array('enfrute_trabalhos', 'semco_trabalhos'), true)) {
        $error = __('Trabalho não encontrado.', 'sciflow-wp');
    } else {
        $stored_token   = get_post_meta($article_id, '_sciflow_confirm_token', true);
        $token_expires  = (int) get_post_meta($article_id, '_sciflow_confirm_token_expires', true);
        $already_confirmed = get_post_meta($article_id, '_sciflow_presentation_confirmed', true);

        $post_title = wp_strip_all_tags($post->post_title);
        $pdate      = get_post_meta($article_id, '_sciflow_presentation_date', true);

        if ($already_confirmed) {
            $already = true;
        } elseif (!hash_equals((string) $stored_token, $token)) {
            $error = __('Link de confirmação inválido.', 'sciflow-wp');
        } elseif ($token_expires < time()) {
            $error = __('Este link de confirmação expirou. Por favor, entre em contato com a organização do evento.', 'sciflow-wp');
        } else {
            // Token valid → confirm presentation.
            if (!class_exists('SciFlow_Ranking')) {
                require_once SCIFLOW_PATH . 'includes/ranking/class-sciflow-ranking.php';
            }

            // Bypass the user-ownership check: we confirm directly via meta.
            $status = get_post_meta($article_id, '_sciflow_status', true);

            // Allow confirmation for any status that is in the approved/presentation pipeline.
            $allowed_statuses = array(
                'aprovado', 'poster_enviado', 'poster_em_correcao', 'poster_reenviado',
                'poster_aprovado', 'poster_reprovado', 'apto_publicacao', 'aguardando_confirmacao',
            );

            if (!in_array($status, $allowed_statuses, true)) {
                $error = __('Este trabalho não está em um estado que permita confirmação de apresentação.', 'sciflow-wp');
            } else {
                update_post_meta($article_id, '_sciflow_presentation_confirmed', true);
                update_post_meta($article_id, '_sciflow_status', 'confirmado');
                // Invalidate token after use.
                delete_post_meta($article_id, '_sciflow_confirm_token');
                delete_post_meta($article_id, '_sciflow_confirm_token_expires');
                $success = true;
            }
        }
    }
} else {
    $error = __('Parâmetros inválidos. Por favor, use o link enviado por e-mail.', 'sciflow-wp');
}
?>

<div class="sciflow-confirm-page" style="max-width:640px;margin:60px auto;padding:40px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);font-family:sans-serif;">

    <?php if ($success): ?>
        <div style="text-align:center;">
            <div style="font-size:64px;margin-bottom:16px;">✅</div>
            <h1 style="color:#2c5530;margin-bottom:8px;"><?php esc_html_e('Apresentação Confirmada!', 'sciflow-wp'); ?></h1>
            <p style="font-size:16px;color:#555;margin-bottom:16px;">
                <?php esc_html_e('Sua participação foi registrada com sucesso.', 'sciflow-wp'); ?>
            </p>
            <?php if ($post_title): ?>
            <p style="font-weight:bold;font-size:15px;"><?php echo esc_html($post_title); ?></p>
            <?php endif; ?>
            <?php if ($pdate): ?>
            <p style="font-size:15px;color:#2c5530;font-weight:bold;">📅 <?php echo esc_html($pdate); ?></p>
            <?php endif; ?>
            <p style="color:#777;margin-top:20px;font-size:13px;">
                <?php esc_html_e('Aguardamos você no evento. Em caso de dúvidas, entre em contato com a organização.', 'sciflow-wp'); ?>
            </p>
        </div>

    <?php elseif ($already): ?>
        <div style="text-align:center;">
            <div style="font-size:64px;margin-bottom:16px;">✅</div>
            <h1 style="color:#2c5530;margin-bottom:8px;"><?php esc_html_e('Apresentação já confirmada!', 'sciflow-wp'); ?></h1>
            <?php if ($post_title): ?>
            <p style="font-weight:bold;font-size:15px;"><?php echo esc_html($post_title); ?></p>
            <?php endif; ?>
            <?php if ($pdate): ?>
            <p style="font-size:15px;color:#2c5530;font-weight:bold;">📅 <?php echo esc_html($pdate); ?></p>
            <?php endif; ?>
            <p style="color:#777;margin-top:20px;font-size:13px;">
                <?php esc_html_e('Sua confirmação já foi registrada anteriormente. Não é necessária nenhuma ação adicional.', 'sciflow-wp'); ?>
            </p>
        </div>

    <?php elseif ($error): ?>
        <div style="text-align:center;">
            <div style="font-size:64px;margin-bottom:16px;">⚠️</div>
            <h1 style="color:#c0392b;margin-bottom:8px;"><?php esc_html_e('Link Inválido', 'sciflow-wp'); ?></h1>
            <p style="font-size:16px;color:#555;"><?php echo esc_html($error); ?></p>
        </div>

    <?php else: ?>
        <div style="text-align:center;">
            <div style="font-size:64px;margin-bottom:16px;">📅</div>
            <h1 style="color:#2c5530;margin-bottom:8px;"><?php esc_html_e('Confirmar Apresentação', 'sciflow-wp'); ?></h1>
            <p style="color:#555;"><?php esc_html_e('Use o link enviado por e-mail para confirmar sua apresentação.', 'sciflow-wp'); ?></p>
        </div>
    <?php endif; ?>

</div>

<?php get_footer(); ?>

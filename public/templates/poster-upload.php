<?php
/**
 * Poster upload template.
 *
 * Available vars:
 *   $approved       - array of WP_Post (approved works)
 *   $status_manager - SciFlow_Status_Manager
 */

if (!defined('ABSPATH'))
    exit;

$settings = get_option('sciflow_settings', array());
$poster_template_url = $settings['poster_template_url'] ?? '';
?>

<div class="sciflow-poster-upload" id="sciflow-poster-upload">
    <h2 class="sciflow-dashboard__title">
        <?php esc_html_e('Enviar Pôster (PDF)', 'sciflow-wp'); ?>
    </h2>

    <?php if ($poster_template_url): ?>
        <div class="sciflow-poster-template-download" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2c5530;">
            <p style="margin-top:0; font-weight: 500;">
                <?php esc_html_e('Antes de enviar seu pôster, certifique-se de utilizar o modelo padrão do evento:', 'sciflow-wp'); ?>
            </p>
            <a href="<?php echo esc_url($poster_template_url); ?>" class="sciflow-btn sciflow-btn--outline" target="_blank" download>
                <?php esc_html_e('Baixar Modelo do Pôster', 'sciflow-wp'); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="sciflow-notice" id="sciflow-poster-messages" style="display:none;"></div>

    <?php if (empty($approved)): ?>
        <div class="sciflow-empty">
            <p>
                <?php esc_html_e('Nenhum trabalho aprovado disponível para upload de pôster.', 'sciflow-wp'); ?>
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($approved as $post):
            $poster_id = get_post_meta($post->ID, '_sciflow_poster_id', true);
            $status = $status_manager->get_status($post->ID);
            $needs_correction = ($status === 'poster_em_correcao');
            ?>
            <div class="sciflow-upload-card">
                <h3>
                    <?php echo esc_html($post->post_title); ?>
                </h3>

                <?php if ($poster_id): ?>
                    <div class="sciflow-notice sciflow-notice--success">
                        <?php esc_html_e('Pôster já enviado.', 'sciflow-wp'); ?>
                        <a href="<?php echo esc_url(wp_get_attachment_url($poster_id)); ?>" target="_blank">
                            <?php esc_html_e('Visualizar', 'sciflow-wp'); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($needs_correction): ?>
                    <div class="sciflow-notice sciflow-notice--warning" style="margin-bottom: 15px;">
                        <strong><?php esc_html_e('O editor solicitou o envio de um novo pôster.', 'sciflow-wp'); ?></strong>
                        <?php
                        $hist = SciFlow_Editorial::get_message_history($post->ID);
                        $last_msg = !empty($hist) ? end($hist) : null;
                        if ($last_msg && $last_msg['role'] === 'editor'):
                        ?>
                            <br><em><?php echo esc_html($last_msg['content']); ?></em>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$poster_id || $needs_correction): ?>
                    <form class="sciflow-poster-form" data-post-id="<?php echo esc_attr($post->ID); ?>"
                        enctype="multipart/form-data">
                        <div class="sciflow-field">
                            <label class="sciflow-field__label">
                                <?php echo $poster_id
                                    ? esc_html__('Substituir Pôster (PDF)', 'sciflow-wp')
                                    : esc_html__('Selecionar Pôster (PDF) *', 'sciflow-wp'); ?>
                            </label>
                            <input type="file" name="poster" accept="application/pdf" required class="sciflow-field__file">
                            <p class="sciflow-field__help">
                                <?php esc_html_e('Apenas PDF. Tamanho máximo: 5 MB.', 'sciflow-wp'); ?>
                            </p>
                        </div>
                        <button type="submit" class="sciflow-btn sciflow-btn--primary">
                            <?php esc_html_e('Enviar Pôster', 'sciflow-wp'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
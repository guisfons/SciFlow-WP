<?php
/**
 * Author dashboard template.
 *
 * Available vars:
 *   $submissions    - array of WP_Post
 *   $status_manager - SciFlow_Status_Manager
 */

if (!defined('ABSPATH'))
    exit;
?>

<div class="sciflow-dashboard">
    <h2 class="sciflow-dashboard__title">
        <?php esc_html_e('Meus Trabalhos', 'sciflow-wp'); ?>
    </h2>

    <div class="sciflow-notice" id="sciflow-dashboard-messages" style="display:none;"></div>

    <?php if (empty($submissions)): ?>
        <div class="sciflow-empty">
            <p>
                <?php esc_html_e('Você ainda não submeteu nenhum trabalho.', 'sciflow-wp'); ?>
            </p>
        </div>
    <?php else: ?>
        <div class="sciflow-works-list">
            <?php foreach ($submissions as $post):
                $status = $status_manager->get_status($post->ID);
                $label = $status_manager->get_status_label($status);
                $event = get_post_meta($post->ID, '_sciflow_event', true);
                $payment = get_post_meta($post->ID, '_sciflow_payment_status', true);
                $poster_id = get_post_meta($post->ID, '_sciflow_poster_id', true);
                $score = get_post_meta($post->ID, '_sciflow_ranking_score', true);
                $keywords = get_post_meta($post->ID, '_sciflow_keywords', true);
                $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
                $ed_notes = get_post_meta($post->ID, '_sciflow_editorial_notes', true);
                $confirmed = get_post_meta($post->ID, '_sciflow_presentation_confirmed', true);
                $deadline = get_post_meta($post->ID, '_sciflow_confirmation_deadline', true);
                ?>
                <div class="sciflow-work-card" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <div class="sciflow-work-card__header">
                        <h3 class="sciflow-work-card__title">
                            <?php echo esc_html($post->post_title); ?>
                        </h3>
                        <span class="sciflow-badge sciflow-badge--<?php echo esc_attr($status); ?>">
                            <?php echo esc_html($label); ?>
                        </span>
                    </div>

                    <div class="sciflow-work-card__meta">
                        <span class="sciflow-meta-item">
                            <strong>
                                <?php esc_html_e('Evento:', 'sciflow-wp'); ?>
                            </strong>
                            <?php echo esc_html(ucfirst($event)); ?>
                        </span>
                        <span class="sciflow-meta-item">
                            <strong>
                                <?php esc_html_e('Pagamento:', 'sciflow-wp'); ?>
                            </strong>
                            <?php echo $payment === 'confirmed'
                                ? '<span class="sciflow-text--success">' . esc_html__('Confirmado', 'sciflow-wp') . '</span>'
                                : '<span class="sciflow-text--warning">' . esc_html__('Pendente', 'sciflow-wp') . '</span>'; ?>
                        </span>
                        <?php if ($score): ?>
                            <span class="sciflow-meta-item">
                                <strong>
                                    <?php esc_html_e('Nota:', 'sciflow-wp'); ?>
                                </strong>
                                <?php echo number_format($score, 2, ',', ''); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($keywords && is_array($keywords)): ?>
                        <div class="sciflow-work-card__keywords">
                            <?php foreach ($keywords as $kw): ?>
                                <span class="sciflow-tag">
                                    <?php echo esc_html($kw); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($ed_notes && $status === 'em_correcao'): ?>
                        <div class="sciflow-notice sciflow-notice--warning">
                            <strong>
                                <?php esc_html_e('Observações do Editor:', 'sciflow-wp'); ?>
                            </strong><br>
                            <?php echo wp_kses_post($ed_notes); ?>
                        </div>
                    <?php endif; ?>

                    <div class="sciflow-work-card__actions">
                        <?php if ($status === 'aguardando_pagamento'): ?>
                            <button class="sciflow-btn sciflow-btn--primary sciflow-btn--sm sciflow-payment-btn"
                                data-post-id="<?php echo esc_attr($post->ID); ?>">
                                <?php esc_html_e('Pagar via Pix', 'sciflow-wp'); ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($status === 'em_correcao'): ?>
                            <button class="sciflow-btn sciflow-btn--primary sciflow-btn--sm sciflow-edit-btn"
                                data-post-id="<?php echo esc_attr($post->ID); ?>">
                                <?php esc_html_e('Editar e Reenviar', 'sciflow-wp'); ?>
                            </button>
                        <?php endif; ?>

                        <?php if (in_array($status, array('aprovado', 'poster_enviado'), true)): ?>
                            <a href="#sciflow-poster-upload" class="sciflow-btn sciflow-btn--secondary sciflow-btn--sm">
                                <?php echo $poster_id
                                    ? esc_html__('Reenviar Pôster', 'sciflow-wp')
                                    : esc_html__('Enviar Pôster (PDF)', 'sciflow-wp'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($status === 'aguardando_confirmacao'): ?>
                            <button class="sciflow-btn sciflow-btn--success sciflow-btn--sm sciflow-confirm-btn"
                                data-post-id="<?php echo esc_attr($post->ID); ?>">
                                <?php esc_html_e('Confirmar Apresentação', 'sciflow-wp'); ?>
                            </button>
                            <?php if ($deadline): ?>
                                <small class="sciflow-text--muted">
                                    <?php printf(
                                        esc_html__('Prazo: %s', 'sciflow-wp'),
                                        wp_date('d/m/Y H:i', strtotime($deadline))
                                    ); ?>
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
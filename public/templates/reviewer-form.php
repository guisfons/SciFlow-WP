<?php
/**
 * Reviewer panel template.
 *
 * Available vars:
 *   $articles       - array of WP_Post (assigned to this reviewer)
 *   $status_manager - SciFlow_Status_Manager
 */

if (!defined('ABSPATH'))
    exit;

$criteria = array(
    'originalidade' => __('Originalidade', 'sciflow-wp'),
    'objetividade' => __('Objetividade', 'sciflow-wp'),
    'organizacao' => __('Organização', 'sciflow-wp'),
    'metodologia' => __('Metodologia', 'sciflow-wp'),
    'aderencia' => __('Aderência aos Objetivos', 'sciflow-wp'),
);
?>

<div class="sciflow-reviewer-panel">
    <h2 class="sciflow-dashboard__title">
        <?php esc_html_e('Painel do Revisor', 'sciflow-wp'); ?>
    </h2>

    <div class="sciflow-notice" id="sciflow-reviewer-messages" style="display:none;"></div>

    <?php if (empty($articles)): ?>
        <div class="sciflow-empty">
            <p>
                <?php esc_html_e('Nenhum trabalho atribuído para revisão.', 'sciflow-wp'); ?>
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($articles as $post):
            $status = $status_manager->get_status($post->ID);
            $label = $status_manager->get_status_label($status);
            $event = get_post_meta($post->ID, '_sciflow_event', true);
            $scores = get_post_meta($post->ID, '_sciflow_scores', true);
            $prev_decision = get_post_meta($post->ID, '_sciflow_reviewer_decision', true);
            $can_review = ($status === 'em_avaliacao');
            ?>
            <div class="sciflow-review-card" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <div class="sciflow-work-card__header">
                    <h3>
                        <?php echo esc_html($post->post_title); ?>
                    </h3>
                    <?php echo $status_manager->get_status_badge($status); ?>
                </div>

                <div class="sciflow-work-card__meta">
                    <span><strong>
                            <?php esc_html_e('Evento:', 'sciflow-wp'); ?>
                        </strong>
                        <?php echo esc_html(ucfirst($event)); ?>
                    </span>
                </div>

                <!-- Article content (read-only) -->
                <div class="sciflow-review-card__content">
                    <h4>
                        <?php esc_html_e('Conteúdo do Trabalho', 'sciflow-wp'); ?>
                    </h4>
                    <div class="sciflow-content-preview">
                        <?php echo wp_kses_post($post->post_content); ?>
                    </div>
                </div>

                <?php if ($can_review): ?>
                    <!-- Review form -->
                    <form class="sciflow-review-form" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <h4>
                            <?php esc_html_e('Avaliação', 'sciflow-wp'); ?>
                        </h4>

                        <div class="sciflow-scores-grid">
                            <?php foreach ($criteria as $key => $label_text): ?>
                                <div class="sciflow-score-field">
                                    <label for="score-<?php echo esc_attr($key); ?>-<?php echo esc_attr($post->ID); ?>">
                                        <?php echo esc_html($label_text); ?> *
                                    </label>
                                    <input type="number" id="score-<?php echo esc_attr($key); ?>-<?php echo esc_attr($post->ID); ?>"
                                        name="scores[<?php echo esc_attr($key); ?>]" min="0" max="10" step="0.1" required
                                        class="sciflow-field__input sciflow-field__input--score" placeholder="0-10">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="sciflow-field">
                            <label class="sciflow-field__label">
                                <?php esc_html_e('Parecer *', 'sciflow-wp'); ?>
                            </label>
                            <select name="decision" required class="sciflow-field__select">
                                <option value="">
                                    <?php esc_html_e('Selecione...', 'sciflow-wp'); ?>
                                </option>
                                <option value="approved">
                                    <?php esc_html_e('Aprovar', 'sciflow-wp'); ?>
                                </option>
                                <option value="approved_with_considerations">
                                    <?php esc_html_e('Aprovado com Considerações', 'sciflow-wp'); ?>
                                </option>
                                <option value="rejected">
                                    <?php esc_html_e('Reprovar', 'sciflow-wp'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="sciflow-field">
                            <label class="sciflow-field__label">
                                <?php esc_html_e('Observações', 'sciflow-wp'); ?>
                            </label>
                            <textarea name="notes" rows="4" class="sciflow-field__textarea"
                                placeholder="<?php esc_attr_e('Observações e comentários sobre o trabalho...', 'sciflow-wp'); ?>"></textarea>
                        </div>

                        <button type="submit" class="sciflow-btn sciflow-btn--primary">
                            <?php esc_html_e('Enviar Avaliação', 'sciflow-wp'); ?>
                        </button>
                    </form>
                <?php elseif ($prev_decision): ?>
                    <div class="sciflow-notice sciflow-notice--info">
                        <strong>
                            <?php esc_html_e('Avaliação já enviada:', 'sciflow-wp'); ?>
                        </strong>
                        <?php
                        $decisions = array(
                            'approved' => __('Aprovado', 'sciflow-wp'),
                            'approved_with_considerations' => __('Aprovado com Considerações', 'sciflow-wp'),
                            'rejected' => __('Reprovado', 'sciflow-wp'),
                        );
                        echo esc_html($decisions[$prev_decision] ?? $prev_decision);
                        ?>
                        <?php if ($scores && is_array($scores)): ?>
                            <br><strong>
                                <?php esc_html_e('Média:', 'sciflow-wp'); ?>
                            </strong>
                            <?php echo number_format(get_post_meta($post->ID, '_sciflow_ranking_score', true), 2, ',', ''); ?>
                        <?php endif; ?>
                        <p class="mt-2 text-muted small">
                            <em><i class="dashicons dashicons-lock"></i>
                                <?php esc_html_e('Esta avaliação está bloqueada para o revisor. Apenas o editor pode reabri-la.', 'sciflow-wp'); ?></em>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
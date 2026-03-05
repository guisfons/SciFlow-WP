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

                <!-- Flex container for Content and Review Form -->
                <div class="sciflow-reviewer-wrapper"
                    style="display: flex; gap: 30px; flex-wrap: wrap; text-align: left; margin-top: 15px;">

                    <!-- LEFT COLUMN: Article content (read-only) -->
                    <div class="sciflow-reviewer-main"
                        style="flex: 2; min-width: 300px; background:#fff; padding:20px; border:1px solid #eee; border-radius:6px;">
                        <h4 style="margin-top:0;">
                            <?php esc_html_e('Conteúdo do Trabalho', 'sciflow-wp'); ?>
                        </h4>
                        <div class="sciflow-content-preview">
                            <?php echo wp_kses_post($post->post_content); ?>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Review Form or Previous Decision -->
                    <div class="sciflow-reviewer-sidebar"
                        style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <?php if ($can_review): ?>
                            <!-- Review form -->
                            <form class="sciflow-review-form" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                <h4 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                                    <?php esc_html_e('Avaliação', 'sciflow-wp'); ?>
                                </h4>

                                <div class="sciflow-scores-grid" style="margin-bottom: 20px;">
                                    <?php foreach ($criteria as $key => $label_text): ?>
                                        <div class="sciflow-score-field" style="margin-bottom: 10px;">
                                            <label for="score-<?php echo esc_attr($key); ?>-<?php echo esc_attr($post->ID); ?>"
                                                style="display:block; font-weight:600; margin-bottom:5px; font-size:14px;">
                                                <?php echo esc_html($label_text); ?> *
                                            </label>
                                            <input type="number"
                                                id="score-<?php echo esc_attr($key); ?>-<?php echo esc_attr($post->ID); ?>"
                                                name="scores[<?php echo esc_attr($key); ?>]" min="0" max="10" step="0.1" required
                                                class="sciflow-field__input sciflow-field__input--score" placeholder="0-10"
                                                style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="sciflow-field" style="margin-bottom: 15px;">
                                    <label class="sciflow-field__label" style="display:block; font-weight:600; margin-bottom:5px;">
                                        <?php esc_html_e('Parecer *', 'sciflow-wp'); ?>
                                    </label>
                                    <select name="decision" required class="sciflow-field__select"
                                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;">
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

                                <div class="sciflow-field" style="margin-bottom: 15px;">
                                    <label class="sciflow-field__label" style="display:block; font-weight:600; margin-bottom:5px;">
                                        <?php esc_html_e('Observações', 'sciflow-wp'); ?>
                                    </label>
                                    <textarea name="notes" rows="4" class="sciflow-field__textarea"
                                        placeholder="<?php esc_attr_e('Observações e comentários sobre o trabalho...', 'sciflow-wp'); ?>"
                                        style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;"></textarea>
                                </div>

                                <button type="submit" class="sciflow-btn sciflow-btn--primary" style="width:100%; padding:10px;">
                                    <?php esc_html_e('Enviar Avaliação', 'sciflow-wp'); ?>
                                </button>
                            </form>
                        <?php elseif ($prev_decision): ?>
                            <div class="sciflow-notice sciflow-notice--info"
                                style="background:#e9f5ff; padding:15px; border-radius:6px; color:#00509e; border:1px solid #b6d4fe;">
                                <strong style="display:block; margin-bottom:10px; font-size:16px;">
                                    <?php esc_html_e('Avaliação já enviada:', 'sciflow-wp'); ?>
                                </strong>
                                <div style="margin-bottom:10px;">
                                    <?php
                                    $decisions = array(
                                        'approved' => __('Aprovado', 'sciflow-wp'),
                                        'approved_with_considerations' => __('Aprovado com Considerações', 'sciflow-wp'),
                                        'rejected' => __('Reprovado', 'sciflow-wp'),
                                    );
                                    echo esc_html($decisions[$prev_decision] ?? $prev_decision);
                                    ?>
                                </div>
                                <?php if ($scores && is_array($scores)): ?>
                                    <div style="font-size:18px;"><strong>
                                            <?php esc_html_e('Média:', 'sciflow-wp'); ?>
                                        </strong>
                                        <?php echo number_format(get_post_meta($post->ID, '_sciflow_ranking_score', true), 2, ',', ''); ?>
                                    </div>
                                <?php endif; ?>
                                <p class="mt-2 text-muted small" style="margin-top:10px; font-size:12px;">
                                    <em><i class="dashicons dashicons-lock"></i>
                                        <?php esc_html_e('Esta avaliação está bloqueada para o revisor. Apenas o editor pode reabri-la.', 'sciflow-wp'); ?></em>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
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
        <!-- Filters -->
        <div class="sciflow-filters"
            style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; display: flex; gap: 15px; flex-wrap: wrap;">
            <div class="sciflow-filter-group">
                <label
                    style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Buscar (Título, Autor, Cultura, Área)', 'sciflow-wp'); ?></label>
                <input type="text" class="sciflow-filter-input sciflow-filter-text-reviewer"
                    placeholder="<?php esc_attr_e('Digite para buscar...', 'sciflow-wp'); ?>"
                    style="padding:6px; border:1px solid #ccc; border-radius:4px; width: 250px;">
            </div>
            <div class="sciflow-filter-group">
                <label
                    style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Evento', 'sciflow-wp'); ?></label>
                <select class="sciflow-filter-input sciflow-filter-event-reviewer"
                    style="padding:6px; border:1px solid #ccc; border-radius:4px;">
                    <option value=""><?php esc_html_e('Todos', 'sciflow-wp'); ?></option>
                    <option value="enfrute">Enfrute</option>
                    <option value="semco">Semco</option>
                </select>
            </div>
            <div class="sciflow-filter-group">
                <label
                    style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Cultura', 'sciflow-wp'); ?></label>
                <select class="sciflow-filter-input sciflow-filter-cultura-reviewer"
                    style="padding:6px; border:1px solid #ccc; border-radius:4px; max-width: 150px;">
                    <option value=""><?php esc_html_e('Todas', 'sciflow-wp'); ?></option>
                    <optgroup label="Frutas de clima temperado">
                        <option value="Figo">Figo</option>
                        <option value="Frutas de caroço">Frutas de caroço</option>
                        <option value="Goiaba/Caqui">Goiaba/Caqui</option>
                        <option value="Maçã/Pera">Maçã/Pera</option>
                        <option value="Pequenas frutas">Pequenas frutas</option>
                        <option value="Frutas nativas">Frutas nativas</option>
                        <option value="Uva">Uva</option>
                        <option value="Outras (Frutas)">Outras</option>
                    </optgroup>
                    <optgroup label="Olerícolas">
                        <option value="Alho">Alho</option>
                        <option value="Cebola">Cebola</option>
                        <option value="Tomate">Tomate</option>
                        <option value="Morango">Morango</option>
                        <option value="Aipim/mandioca">Aipim/mandioca</option>
                        <option value="Cenoura">Cenoura</option>
                        <option value="Pimentão">Pimentão</option>
                        <option value="Folhosas">Folhosas</option>
                        <option value="Outras (Olerícolas)">Outras</option>
                    </optgroup>
                </select>
            </div>
            <div class="sciflow-filter-group">
                <label
                    style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Área do Conhecimento', 'sciflow-wp'); ?></label>
                <select class="sciflow-filter-input sciflow-filter-area-reviewer"
                    style="padding:6px; border:1px solid #ccc; border-radius:4px; max-width: 200px;">
                    <option value=""><?php esc_html_e('Todas', 'sciflow-wp'); ?></option>
                    <option value="Biotecnologia/Genética e Melhoramento">Biotecnologia/Genética e Melhoramento</option>
                    <option value="Botânica e Fisiologia">Botânica e Fisiologia</option>
                    <option value="Colheita e Pós-Colheita">Colheita e Pós-Colheita</option>
                    <option value="Fitossanidade">Fitossanidade</option>
                    <option value="Economia/Estatística">Economia/Estatística</option>
                    <option value="Fitotecnia">Fitotecnia</option>
                    <option value="Irrigação">Irrigação</option>
                    <option value="Processamento (Química e Bioquímica)">Processamento (Química e Bioquímica)</option>
                    <option value="Propagação">Propagação</option>
                    <option value="Sementes">Sementes</option>
                    <option value="Solos e Nutrição de Plantas">Solos e Nutrição de Plantas</option>
                    <option value="Outros">Outros</option>
                </select>
            </div>
        </div>

        <?php foreach ($articles as $post):
            $status = $status_manager->get_status($post->ID);
            $label = $status_manager->get_status_label($status);
            $event = get_post_meta($post->ID, '_sciflow_event', true);
            $scores = get_post_meta($post->ID, '_sciflow_scores', true);
            $prev_decision = get_post_meta($post->ID, '_sciflow_reviewer_decision', true);
            $can_review = ($status === 'em_avaliacao');
            $cultura = get_post_meta($post->ID, '_sciflow_cultura', true);
            $area = get_post_meta($post->ID, '_sciflow_knowledge_area', true);

            $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
            $author = get_userdata($author_id);
            $is_editor = current_user_can('manage_sciflow');
            $search_author = ($is_editor || current_user_can('administrator')) ? ($author ? $author->display_name : '') : '';
            ?>
            <div class="sciflow-review-card" data-post-id="<?php echo esc_attr($post->ID); ?>"
                data-event="<?php echo esc_attr($event); ?>" data-cultura="<?php echo esc_attr($cultura); ?>"
                data-area="<?php echo esc_attr($area); ?>"
                data-search="<?php echo esc_attr(strtolower($post->post_title . ' ' . $search_author . ' ' . $cultura . ' ' . $area)); ?>">
                <div class="sciflow-work-card__header">
                    <h3>
                        #<?php echo SciFlow_Status_Manager::get_visual_id($post->ID); ?> -
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

                        <?php
                        $cultura = get_post_meta($post->ID, '_sciflow_cultura', true);
                        $area = get_post_meta($post->ID, '_sciflow_knowledge_area', true);
                        ?>
                        <?php if ($cultura || $area): ?>
                            <div class="sciflow-meta"
                                style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px;">
                                <?php if ($cultura): ?>
                                    <div style="margin-bottom: 5px;">
                                        <strong><?php esc_html_e('Cultura / Fruta:', 'sciflow-wp'); ?></strong>
                                        <?php echo esc_html($cultura); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($area): ?>
                                    <div>
                                        <strong><?php esc_html_e('Área do Conhecimento:', 'sciflow-wp'); ?></strong>
                                        <?php echo esc_html($area); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

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
                                        'reject' => __('Reprovado', 'sciflow-wp'),
                                        'rejected' => __('Reprovado', 'sciflow-wp'),
                                    );
                                    echo esc_html($decisions[$prev_decision] ?? $prev_decision);
                                    ?>
                                </div>
                                <?php if ($scores && is_array($scores)): 
                                    $settings = get_option('sciflow_settings', array());
                                    $weights  = $settings['ranking_weights'] ?? array();
                                    $total_weighted = 0;
                                    $total_weight   = 0;
                                ?>
                                    <table class="sciflow-score-breakdown" style="width:100%; border-collapse:collapse; margin-top:15px; font-size:12px;">
                                        <thead>
                                            <tr style="border-bottom:1px solid #ddd; text-align:left;">
                                                <th style="padding:4px 0;"><?php esc_html_e('Critério', 'sciflow-wp'); ?></th>
                                                <th style="padding:4px 0; text-align:center;"><?php esc_html_e('Nota', 'sciflow-wp'); ?></th>
                                                <th style="padding:4px 0; text-align:center;"><?php esc_html_e('Peso', 'sciflow-wp'); ?></th>
                                                <th style="padding:4px 0; text-align:right;"><?php esc_html_e('Pond.', 'sciflow-wp'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($criteria as $key => $label_text): 
                                                $s_val = $scores[$key] ?? 0;
                                                $w_val = $weights[$key] ?? 1;

                                                // Handle comma in raw data
                                                if (is_string($s_val)) $s_val = str_replace(',', '.', $s_val);
                                                if (is_string($w_val)) $w_val = str_replace(',', '.', $w_val);
                                                
                                                $s_val = floatval($s_val);
                                                $w_val = floatval($w_val);
                                                if ($w_val <= 0) $w_val = 1;

                                                $weighted = $s_val * $w_val;
                                                $total_weighted += $weighted;
                                                $total_weight   += $w_val;
                                            ?>
                                                <tr style="border-bottom:1px solid #eee;">
                                                    <td style="padding:4px 0;"><?php echo esc_html($label_text); ?></td>
                                                    <td style="padding:4px 0; text-align:center;"><?php echo number_format($s_val, 1, ',', ''); ?></td>
                                                    <td style="padding:4px 0; text-align:center;"><?php echo number_format($w_val, 1, ',', ''); ?></td>
                                                    <td style="padding:4px 0; text-align:right;"><?php echo number_format($weighted, 2, ',', ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr style="font-weight:bold; font-size:14px;">
                                                <td colspan="3" style="padding:8px 0;"><?php esc_html_e('Nota Final (Média Ponderada):', 'sciflow-wp'); ?></td>
                                                <td style="padding:8px 0; text-align:right; color:#2c5530;">
                                                    <?php 
                                                    $final = $total_weight > 0 ? ($total_weighted / $total_weight) : 0;
                                                    echo number_format($final, 2, ',', ''); 
                                                    ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
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
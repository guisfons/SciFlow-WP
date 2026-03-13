<?php
/**
 * Editorial panel template.
 *
 * Available vars:
 *   $editorial      - SciFlow_Editorial
 *   $status_manager - SciFlow_Status_Manager
 */

if (!defined('ABSPATH'))
    exit;

$reviewers = $editorial->get_reviewers();
$events = array(
    'enfrute' => 'Enfrute',
    'semco' => 'Semco',
);
?>

<div class="sciflow-editor-panel">
    <h2 class="sciflow-dashboard__title">
        <?php esc_html_e('Painel Editorial', 'sciflow-wp'); ?>
    </h2>

    <div class="sciflow-notice" id="sciflow-editor-messages" style="display:none;"></div>

    <!-- Event tabs -->
    <div class="sciflow-tabs">
        <?php foreach ($events as $key => $label): ?>
            <button class="sciflow-tab <?php echo $key === 'enfrute' ? 'sciflow-tab--active' : ''; ?>"
                data-event="<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($events as $event_key => $event_label):
        $articles = $editorial->get_event_articles($event_key);
        ?>
        <div class="sciflow-tab-content <?php echo $event_key === 'enfrute' ? 'sciflow-tab-content--active' : ''; ?>"
            id="sciflow-tab-<?php echo esc_attr($event_key); ?>">

            <?php if (empty($articles)): ?>
                <div class="sciflow-empty">
                    <p>
                        <?php esc_html_e('Nenhum trabalho neste evento.', 'sciflow-wp'); ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Filters -->
                <div class="sciflow-filters"
                    style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="sciflow-filter-group">
                        <label
                            style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Buscar (Título, Autor, Revisor, Fruta, Área)', 'sciflow-wp'); ?></label>
                        <input type="text" class="sciflow-filter-input sciflow-filter-text"
                            data-event="<?php echo esc_attr($event_key); ?>"
                            placeholder="<?php esc_attr_e('Digite para buscar...', 'sciflow-wp'); ?>"
                            style="padding:6px; border:1px solid #ccc; border-radius:4px; width: 250px;">
                    </div>
                        <div class="sciflow-filter-group">
                            <label style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Status', 'sciflow-wp'); ?></label>
                            <select class="sciflow-filter-input sciflow-filter-status"
                                data-event="<?php echo esc_attr($event_key); ?>"
                                style="padding:6px; border:1px solid #ccc; border-radius:4px;">
                                <option value=""><?php esc_html_e('Todos', 'sciflow-wp'); ?></option>
                                <?php foreach ($status_manager->get_statuses() as $s_key => $s_label): ?>
                                    <option value="<?php echo esc_attr($s_key); ?>"><?php echo esc_html($s_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sciflow-filter-group">
                            <label
                                style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Cultura', 'sciflow-wp'); ?></label>
                            <select class="sciflow-filter-input sciflow-filter-cultura"
                                data-event="<?php echo esc_attr($event_key); ?>"
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
                            <select class="sciflow-filter-input sciflow-filter-area"
                                data-event="<?php echo esc_attr($event_key); ?>"
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

                    <table class="sciflow-table sciflow-table-<?php echo esc_attr($event_key); ?>">
                        <thead>
                            <tr>
                                <th>
                                    <?php esc_html_e('Título', 'sciflow-wp'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Autor', 'sciflow-wp'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Status', 'sciflow-wp'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Revisor', 'sciflow-wp'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Nota', 'sciflow-wp'); ?>
                                </th>
                                <th>
                                    <?php esc_html_e('Ações', 'sciflow-wp'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $post):
                                $status = $status_manager->get_status($post->ID);
                                $status_label = $status_manager->get_status_label($status);
                                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
                                $author = get_userdata($author_id);
                                $reviewer_id = get_post_meta($post->ID, '_sciflow_reviewer_id', true);
                                $reviewer = $reviewer_id ? get_userdata($reviewer_id) : null;
                                $score = get_post_meta($post->ID, '_sciflow_ranking_score', true);
                                $rev_notes = get_post_meta($post->ID, '_sciflow_reviewer_notes', true);
                                $rev_decision = get_post_meta($post->ID, '_sciflow_reviewer_decision', true);
                                $cultura = get_post_meta($post->ID, '_sciflow_cultura', true);
                                $area = get_post_meta($post->ID, '_sciflow_knowledge_area', true);
                                ?>
                                <tr data-post-id="<?php echo esc_attr($post->ID); ?>" class="sciflow-table__row"
                                    data-status="<?php echo esc_attr($status); ?>"
                                    data-cultura="<?php echo esc_attr($cultura); ?>"
                                    data-area="<?php echo esc_attr($area); ?>"
                                    data-search="<?php 
                                        $is_editor = current_user_can('manage_sciflow');
                                        $search_author = ($is_editor || current_user_can('administrator')) ? ($author ? $author->display_name : '') : '';
                                        echo esc_attr(strtolower($post->post_title . ' ' . $search_author . ' ' . ($reviewer ? $reviewer->display_name : '') . ' ' . $cultura . ' ' . $area)); 
                                    ?>">
                                    <td>
                                        <strong>
                                            #<?php echo SciFlow_Status_Manager::get_visual_id($post->ID); ?> - <?php echo esc_html($post->post_title); ?>
                                        </strong>
                                        <div class="sciflow-table-meta-links" style="display: flex; gap: 10px; margin-top: 5px;">
                                            <?php 
                                            $can_decide = array('aguardando_decisao', 'submetido_com_revisao', 'em_avaliacao', 'submetido', 'aprovado', 'poster_enviado', 'poster_reenviado');
                                            $finalized_poster = array('apto_publicacao', 'poster_aprovado', 'poster_reprovado');
                                            if (in_array($status, $can_decide, true) && !in_array($status, $finalized_poster, true)): ?>
                                                <!-- Decision dropdown -->
                                                <button class="sciflow-btn sciflow-btn--link sciflow-toggle-content"
                                                    data-target="content-<?php echo esc_attr($post->ID); ?>" style="padding:0; font-size:12px;">
                                                    <?php esc_html_e('Tomar Decisão', 'sciflow-wp'); ?>
                                                </button>
                                            <?php endif; ?>
                                            <button class="sciflow-btn sciflow-btn--link sciflow-toggle-content"
                                                data-target="content-<?php echo esc_attr($post->ID); ?>" style="padding:0; font-size:12px;">
                                                <?php esc_html_e('Ver conteúdo', 'sciflow-wp'); ?>
                                            </button>
                                            <?php 
                                            $poster_id = get_post_meta($post->ID, '_sciflow_poster_id', true);
                                            if ($poster_id): ?>
                                                <a href="<?php echo esc_url(wp_get_attachment_url($poster_id)); ?>" target="_blank" 
                                                   class="sciflow-text--success" style="font-size:12px; text-decoration:none;">
                                                    <i class="bi bi-file-earmark-pdf"></i> <?php esc_html_e('Ver Pôster', 'sciflow-wp'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $is_editor = current_user_can('manage_sciflow');
                                        if (!$is_editor && !current_user_can('administrator')): ?>
                                            <i style="color:#999;font-size:11px;"><?php esc_html_e('Ocultado (Blind Review)', 'sciflow-wp'); ?></i>
                                        <?php else: ?>
                                            <?php echo $author ? esc_html($author->display_name) : '—'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $status_manager->get_status_badge($status); ?>
                                    </td>
                                    <td>
                                        <?php echo $reviewer ? esc_html($reviewer->display_name) : '—'; ?>
                                    </td>
                                    <td>
                                        <?php echo $score ? number_format($score, 2, ',', '') : '—'; ?>
                                    </td>
                                    <td class="sciflow-table__actions">
                                        <?php if ($status === 'submetido'): ?>
                                            <!-- Assign reviewer -->
                                            <div class="sciflow-inline-form">
                                                <select class="sciflow-field__select sciflow-field__select--sm sciflow-reviewer-select">
                                                    <option value="">
                                                        <?php esc_html_e('Revisor...', 'sciflow-wp'); ?>
                                                    </option>
                                                    <?php foreach ($reviewers as $rev): ?>
                                                        <option value="<?php echo esc_attr($rev->ID); ?>">
                                                            <?php echo esc_html($rev->display_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="sciflow-btn sciflow-btn--primary sciflow-btn--sm sciflow-assign-btn"
                                                    data-post-id="<?php echo esc_attr($post->ID); ?>">
                                                    <?php esc_html_e('Atribuir', 'sciflow-wp'); ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($poster_id && !in_array($status, array('apto_publicacao', 'poster_reprovado'))): ?>
                                            <div class="sciflow-decision-form" data-post-id="<?php echo esc_attr($post->ID); ?>" style="display:flex; gap:5px;">
                                                <button class="sciflow-btn sciflow-btn--success sciflow-btn--sm sciflow-poster-decision-btn" data-decision="approve_poster" title="<?php esc_attr_e('Aprovar Pôster', 'sciflow-wp'); ?>">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                <button class="sciflow-btn sciflow-btn--warning sciflow-btn--sm sciflow-poster-decision-btn" data-decision="request_new_poster" title="<?php esc_attr_e('Pedir Correção', 'sciflow-wp'); ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="sciflow-btn sciflow-btn--danger sciflow-btn--sm sciflow-poster-decision-btn" data-decision="reject_poster" title="<?php esc_attr_e('Reprovar', 'sciflow-wp'); ?>">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Expandable content row -->
                                <tr class="sciflow-content-row" id="content-<?php echo esc_attr($post->ID); ?>"
                                    style="display:none;" data-parent="<?php echo esc_attr($post->ID); ?>">
                                    <td colspan="6" style="padding: 20px; background-color: #fcfcfc;">
                                        <div class="sciflow-editorial-wrapper"
                                            style="display: flex; gap: 30px; flex-wrap: wrap; text-align: left;">

                                            <!-- LEFT COLUMN: Content & Details -->
                                            <div class="sciflow-editorial-main" style="flex: 2; min-width: 300px;">
                                                <?php
                                                $cultura = get_post_meta($post->ID, '_sciflow_cultura', true);
                                                $area = get_post_meta($post->ID, '_sciflow_knowledge_area', true);
                                                ?>
                                                <?php if ($cultura || $area): ?>
                                                    <div class="sciflow-meta"
                                                        style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 6px;">
                                                        <?php if ($cultura): ?>
                                                            <div style="margin-bottom: 5px;">
                                                                <strong><?php esc_html_e('Cultura / Fruta:', 'sciflow-wp'); ?></strong>
                                                                <?php echo esc_html($cultura); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($area): ?>
                                                            <div>
                                                                <strong><?php esc_html_e('Área do Conhecimento:', 'sciflow-wp'); ?></strong>
                                                                <?php echo esc_html($area); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="sciflow-content-preview"
                                                    style="background:#fff; padding:20px; border:1px solid #eee; border-radius:6px;">
                                                    <h4 style="margin-top:0;">
                                                        <?php esc_html_e('Resumo do Trabalho', 'sciflow-wp'); ?>
                                                    </h4>
                                                    <?php echo wp_kses_post($post->post_content); ?>
                                                </div>

                                                <?php
                                                $ack = get_post_meta($post->ID, '_sciflow_acknowledgement', true);
                                                if (!empty($ack)): ?>
                                                    <div style="margin-top:12px; padding:12px; background:#f9f9f9; border:1px solid #eee; border-radius:6px;">
                                                        <strong><?php esc_html_e('Agradecimentos:', 'sciflow-wp'); ?></strong>
                                                        <p style="margin:5px 0 0; white-space:pre-wrap;"><?php echo esc_html($ack); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- RIGHT COLUMN: Decisions & History -->
                                            <div class="sciflow-editorial-sidebar"
                                                style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">

                                                <?php 
                                                $decision_ready = array('aguardando_decisao', 'em_correcao', 'em_avaliacao', 'submetido', 'aprovado', 'poster_enviado', 'poster_aprovado', 'poster_em_correcao', 'poster_reprovado', 'poster_reenviado');
                                                if (in_array($status, $decision_ready, true)):
                                                    $history = SciFlow_Editorial::get_message_history($post->ID);
                                                    ?>
                                                    <!-- Editorial History -->
                                                    <?php if (!empty($history)): ?>
                                                        <div class="sciflow-message-history sciflow-message-history--admin"
                                                            style="margin-bottom: 20px;">
                                                            <h5 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                                                                <?php esc_html_e('Histórico e Pareceres', 'sciflow-wp'); ?>
                                                            </h5>
                                                            <div class="sciflow-messages"
                                                                style="max-height: 300px; overflow-y: auto; padding-right: 10px;">
                                                                <?php foreach ($history as $msg): ?>
                                                                    <div class="sciflow-message sciflow-message--<?php echo esc_attr($msg['role']); ?>"
                                                                        style="margin-bottom:15px; padding:10px; background:#f9f9f9; border-left:3px solid #ccc;">
                                                                        <div class="sciflow-message__header"
                                                                            style="font-size:0.85em; color:#666; margin-bottom:5px;">
                                                                            <strong
                                                                                class="sciflow-message__author"><?php echo esc_html(ucfirst($msg['role'])); ?></strong>
                                                                            &bull;
                                                                            <span
                                                                                class="sciflow-message__date"><?php echo date_i18n('d/m/Y H:i', strtotime($msg['timestamp'])); ?></span>
                                                                        </div>
                                                                        <div class="sciflow-message__content" style="font-size:0.95em;">
                                                                            <?php echo wp_kses_post($msg['content']); ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php 
                                                    $direct_decision = array('aguardando_decisao', 'submetido_com_revisao', 'submetido', 'em_avaliacao');
                                                    if (in_array($status, $direct_decision, true)): 
                                                        ?>
                                                        <!-- Reviewer Recommendation -->
                                                        <?php if ($rev_decision): 
                                                            $scores   = get_post_meta($post->ID, '_sciflow_scores', true);
                                                            $settings = get_option('sciflow_settings', array());
                                                            $weights  = $settings['ranking_weights'] ?? array();
                                                            $criteria = array(
                                                                'originalidade' => __('Originalidade', 'sciflow-wp'),
                                                                'objetividade'  => __('Objetividade', 'sciflow-wp'),
                                                                'organizacao'   => __('Organização', 'sciflow-wp'),
                                                                'metodologia'   => __('Metodologia', 'sciflow-wp'),
                                                                'aderencia'     => __('Aderência aos Objetivos', 'sciflow-wp'),
                                                            );
                                                        ?>
                                                            <div class="sciflow-reviewer-recommendation mb-3"
                                                                style="padding:15px; background:#f0f7ff; border-radius:6px; color:#00509e; border:1px solid #cce3ff;">
                                                                <strong style="display:block; margin-bottom:10px;"><?php esc_html_e('Detalhamento da Avaliação:', 'sciflow-wp'); ?></strong>
                                                                
                                                                <?php if ($scores && is_array($scores)): ?>
                                                                    <table style="width:100%; border-collapse:collapse; font-size:12px; margin-bottom:15px;">
                                                                        <thead>
                                                                            <tr style="border-bottom:1px solid #b6d4fe;">
                                                                                <th style="padding:4px 0; text-align:left;"><?php esc_html_e('Critério', 'sciflow-wp'); ?></th>
                                                                                <th style="padding:4px 0; text-align:center;"><?php esc_html_e('Nota', 'sciflow-wp'); ?></th>
                                                                                <th style="padding:4px 0; text-align:center;"><?php esc_html_e('Peso', 'sciflow-wp'); ?></th>
                                                                                <th style="padding:4px 0; text-align:right;"><?php esc_html_e('Pond.', 'sciflow-wp'); ?></th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php 
                                                                            $total_weighted = 0;
                                                                            $total_weight   = 0;
                                                                            foreach ($criteria as $key => $label_text): 
                                                                                $s_val = $scores[$key] ?? 0;
                                                                                $w_val = $weights[$key] ?? 1;

                                                                                if (is_string($s_val)) $s_val = str_replace(',', '.', $s_val);
                                                                                if (is_string($w_val)) $w_val = str_replace(',', '.', $w_val);
                                                                                
                                                                                $s_val = floatval($s_val);
                                                                                $w_val = floatval($w_val);
                                                                                if ($w_val <= 0) $w_val = 1;

                                                                                $weighted = $s_val * $w_val;
                                                                                $total_weighted += $weighted;
                                                                                $total_weight   += $w_val;
                                                                            ?>
                                                                                <tr style="border-bottom:1px solid #e2efff;">
                                                                                    <td style="padding:4px 0;"><?php echo esc_html($label_text); ?></td>
                                                                                    <td style="padding:4px 0; text-align:center;"><?php echo number_format($s_val, 1, ',', ''); ?></td>
                                                                                    <td style="padding:4px 0; text-align:center;"><?php echo number_format($w_val, 1, ',', ''); ?></td>
                                                                                    <td style="padding:4px 0; text-align:right;"><?php echo number_format($weighted, 2, ',', ''); ?></td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        </tbody>
                                                                        <tfoot>
                                                                            <tr style="font-weight:bold; font-size:13px;">
                                                                                <td colspan="3" style="padding:8px 0;"><?php esc_html_e('Média Ponderada:', 'sciflow-wp'); ?></td>
                                                                                <td style="padding:8px 0; text-align:right;">
                                                                                    <?php 
                                                                                    $final = $total_weight > 0 ? ($total_weighted / $total_weight) : 0;
                                                                                    echo number_format($final, 2, ',', ''); 
                                                                                    ?>
                                                                                </td>
                                                                            </tr>
                                                                        </tfoot>
                                                                    </table>
                                                                <?php endif; ?>

                                                                <div style="margin-top:10px; padding-top:10px; border-top:1px solid #b6d4fe;">
                                                                    <strong><?php esc_html_e('Recomendação Final:', 'sciflow-wp'); ?></strong>
                                                                    <span style="margin-left:5px;">
                                                                        <?php
                                                                        $recs = array(
                                                                            'approved' => __('Aprovar', 'sciflow-wp'),
                                                                            'approved_with_considerations' => __('Aprovar com Considerações', 'sciflow-wp'),
                                                                            'reject' => __('Reprovado', 'sciflow-wp'),
                                                                            'rejected' => __('Reprovar', 'sciflow-wp'),
                                                                        );
                                                                        echo esc_html($recs[$rev_decision] ?? $rev_decision);
                                                                        ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Editorial decision form -->
                                                        <div class="sciflow-decision-form"
                                                            data-post-id="<?php echo esc_attr($post->ID); ?>">
                                                            <h5 style="margin-top:0;"><?php esc_html_e('Tomar Decisão', 'sciflow-wp'); ?>
                                                            </h5>
                                                            <textarea class="sciflow-field__textarea sciflow-decision-notes"
                                                                placeholder="<?php esc_attr_e('Observações gerais e orientações para o autor...', 'sciflow-wp'); ?>"
                                                                rows="4" style="width:100%; margin-bottom:10px;"></textarea>

                                                            <div class="sciflow-decision-buttons"
                                                                style="display:flex; flex-direction:column; gap:8px;">
                                                                <button class="sciflow-btn sciflow-btn--success sciflow-decision-btn"
                                                                    data-decision="approve" style="width:100%;">
                                                                    <?php esc_html_e('Aprovar', 'sciflow-wp'); ?>
                                                                </button>
                                                                <button class="sciflow-btn sciflow-btn--info sciflow-decision-btn"
                                                                    data-decision="approved_with_considerations" style="width:100%;">
                                                                    <?php esc_html_e('Revisão Necessária', 'sciflow-wp'); ?>
                                                                </button>
                                                                <button class="sciflow-btn sciflow-btn--warning sciflow-decision-btn"
                                                                    data-decision="return_to_author" style="width:100%;">
                                                                    <?php esc_html_e('Devolver para Alterações', 'sciflow-wp'); ?>
                                                                </button>
                                                                <!-- Optional second cycle -->
                                                                <button class="sciflow-btn sciflow-btn--secondary sciflow-decision-btn"
                                                                    data-decision="return_to_reviewer" style="width:100%;">
                                                                    <?php esc_html_e('Opcional: Voltar para Revisor', 'sciflow-wp'); ?>
                                                                </button>
                                                                <button class="sciflow-btn sciflow-btn--danger sciflow-decision-btn"
                                                                    data-decision="reject" style="width:100%;">
                                                                    <?php esc_html_e('Reprovar', 'sciflow-wp'); ?>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php 
                                                $poster_id = get_post_meta($post->ID, '_sciflow_poster_id', true);
                                                if ($poster_id):
                                                    $history   = SciFlow_Editorial::get_message_history($post->ID);
                                                    ?>
                                                    <!-- Poster Decision Form -->
                                                    <div class="sciflow-decision-form" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                                        <h5 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:8px;">
                                                            <?php esc_html_e('Decisão do Pôster', 'sciflow-wp'); ?>
                                                        </h5>
                                                        <?php if ($poster_id): ?>
                                                            <p style="margin-bottom:12px;">
                                                                <a href="<?php echo esc_url(wp_get_attachment_url($poster_id)); ?>" target="_blank" class="sciflow-btn sciflow-btn--link">
                                                                    📄 <?php esc_html_e('Visualizar Pôster Enviado', 'sciflow-wp'); ?>
                                                                </a>
                                                            </p>
                                                        <?php endif; ?>

                                                        <?php if (!empty($history)): ?>
                                                            <div style="max-height:200px; overflow-y:auto; margin-bottom:12px;">
                                                                <?php foreach ($history as $msg): ?>
                                                                    <div style="margin-bottom:10px; padding:8px; background:#f9f9f9; border-left:3px solid #ccc;">
                                                                        <div style="font-size:0.85em; color:#666; margin-bottom:4px;">
                                                                            <strong><?php echo esc_html(ucfirst($msg['role'])); ?></strong>
                                                                            &bull; <?php echo date_i18n('d/m/Y H:i', strtotime($msg['timestamp'])); ?>
                                                                        </div>
                                                                        <div style="font-size:0.9em;"><?php echo wp_kses_post($msg['content']); ?></div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <textarea class="sciflow-field__textarea sciflow-poster-decision-notes"
                                                            placeholder="<?php esc_attr_e('Observações para o autor sobre o pôster...', 'sciflow-wp'); ?>"
                                                            rows="3" style="width:100%; margin-bottom:10px;"></textarea>
                                                        <div style="display:flex; flex-direction:column; gap:8px;">
                                                            <button class="sciflow-btn sciflow-btn--success sciflow-poster-decision-btn" data-decision="approve_poster" style="width:100%;">
                                                                <?php esc_html_e('Aprovar Pôster', 'sciflow-wp'); ?>
                                                            </button>
                                                            <button class="sciflow-btn sciflow-btn--warning sciflow-poster-decision-btn" data-decision="request_new_poster" style="width:100%;">
                                                                <?php esc_html_e('Pedir Novo Pôster (com considerações)', 'sciflow-wp'); ?>
                                                            </button>
                                                            <button class="sciflow-btn sciflow-btn--danger sciflow-poster-decision-btn" data-decision="reject_poster" style="width:100%;">
                                                                <?php esc_html_e('Reprovar Pôster', 'sciflow-wp'); ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
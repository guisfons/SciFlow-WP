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
                        <div class="sciflow-filter-group">
                            <label
                                style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Buscar (Título, Autor, Revisor, Fruta, Área)', 'sciflow-wp'); ?></label>
                            <input type="text" class="sciflow-filter-input sciflow-filter-text"
                                data-event="<?php echo esc_attr($event_key); ?>"
                                placeholder="<?php esc_attr_e('Digite para buscar...', 'sciflow-wp'); ?>"
                                style="padding:6px; border:1px solid #ccc; border-radius:4px; width: 250px;">
                        </div>
                        <div class="sciflow-filter-group">
                            <label
                                style="display:block; font-size:12px; font-weight:bold; margin-bottom:5px;"><?php esc_html_e('Status', 'sciflow-wp'); ?></label>
                            <select class="sciflow-filter-input sciflow-filter-status"
                                data-event="<?php echo esc_attr($event_key); ?>"
                                style="padding:6px; border:1px solid #ccc; border-radius:4px;">
                                <option value=""><?php esc_html_e('Todos', 'sciflow-wp'); ?></option>
                                <?php foreach ($status_manager->get_statuses() as $s_key => $s_label): ?>
                                    <option value="<?php echo esc_attr($s_key); ?>"><?php echo esc_html($s_label['label']); ?>
                                    </option>
                                <?php endforeach; ?>
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
                                    data-search="<?php echo esc_attr(strtolower($post->post_title . ' ' . ($author ? $author->display_name : '') . ' ' . ($reviewer ? $reviewer->display_name : '') . ' ' . $cultura . ' ' . $area)); ?>">
                                    <td>
                                        <strong>
                                            <?php echo esc_html($post->post_title); ?>
                                        </strong>
                                        <button class="sciflow-btn sciflow-btn--link sciflow-toggle-content"
                                            data-target="content-<?php echo esc_attr($post->ID); ?>">
                                            <?php esc_html_e('Ver conteúdo', 'sciflow-wp'); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <?php echo $author ? esc_html($author->display_name) : '—'; ?>
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
                                            </div>

                                            <!-- RIGHT COLUMN: Decisions & History -->
                                            <div class="sciflow-editorial-sidebar"
                                                style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">

                                                <?php if ($status === 'aguardando_decisao' || $status === 'em_correcao' || $status === 'em_avaliacao'):
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

                                                    <?php if ($status === 'aguardando_decisao' || $status === 'submetido_com_revisao'): ?>
                                                        <!-- Reviewer Recommendation -->
                                                        <?php if ($rev_decision): ?>
                                                            <div class="sciflow-reviewer-recommendation mb-3"
                                                                style="padding:10px; background:#e9f5ff; border-radius:4px; color:#00509e;">
                                                                <strong><?php esc_html_e('Recomendação do Revisor:', 'sciflow-wp'); ?></strong><br>
                                                                <?php
                                                                $recs = array(
                                                                    'approved' => __('Aprovar', 'sciflow-wp'),
                                                                    'approved_with_considerations' => __('Aprovar com Considerações', 'sciflow-wp'),
                                                                    'rejected' => __('Reprovar', 'sciflow-wp'),
                                                                );
                                                                echo esc_html($recs[$rev_decision] ?? $rev_decision);
                                                                ?>
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
                                                                    <?php esc_html_e('Devolver para Correções', 'sciflow-wp'); ?>
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
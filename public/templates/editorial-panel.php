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
    'senco' => 'Senco',
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
                <table class="sciflow-table">
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
                            ?>
                            <tr data-post-id="<?php echo esc_attr($post->ID); ?>" class="sciflow-table__row">
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
                                    <span class="sciflow-badge sciflow-badge--<?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
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

                                    <?php if ($status === 'aguardando_decisao'): ?>
                                        <!-- Editorial decision -->
                                        <?php if ($rev_notes): ?>
                                            <div class="sciflow-reviewer-notes">
                                                <small><strong>
                                                        <?php esc_html_e('Parecer do Revisor:', 'sciflow-wp'); ?>
                                                    </strong>
                                                    <?php echo wp_kses_post($rev_notes); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        <div class="sciflow-decision-form" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                            <textarea class="sciflow-field__textarea sciflow-decision-notes"
                                                placeholder="<?php esc_attr_e('Observações para o autor...', 'sciflow-wp'); ?>"
                                                rows="2"></textarea>
                                            <div class="sciflow-decision-buttons">
                                                <button class="sciflow-btn sciflow-btn--success sciflow-btn--sm sciflow-decision-btn"
                                                    data-decision="approve">
                                                    <?php esc_html_e('Aprovar', 'sciflow-wp'); ?>
                                                </button>
                                                <button class="sciflow-btn sciflow-btn--warning sciflow-btn--sm sciflow-decision-btn"
                                                    data-decision="return_to_author">
                                                    <?php esc_html_e('Devolver', 'sciflow-wp'); ?>
                                                </button>
                                                <button class="sciflow-btn sciflow-btn--danger sciflow-btn--sm sciflow-decision-btn"
                                                    data-decision="reject">
                                                    <?php esc_html_e('Reprovar', 'sciflow-wp'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Expandable content row -->
                            <tr class="sciflow-content-row" id="content-<?php echo esc_attr($post->ID); ?>" style="display:none;">
                                <td colspan="6">
                                    <div class="sciflow-content-preview">
                                        <?php echo wp_kses_post($post->post_content); ?>
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
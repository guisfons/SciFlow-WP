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
        <?php esc_html_e('Meus Resumos', 'sciflow-wp'); ?>
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

                // Agrupa status internos como "Em Avaliação" para não confundir o autor
                $display_status = $status;
                if (in_array($status, array('submetido', 'apto_revisao', 'aguardando_decisao'), true)) {
                    $display_status = 'em_avaliacao';
                }
                // Status 'aprovado' = trabalho aprovado, aguardando envio do pôster.
                if ($status === 'aprovado') {
                    $display_status = 'aguardando_poster';
                }

                $event = get_post_meta($post->ID, '_sciflow_event', true);
                $payment = get_post_meta($post->ID, '_sciflow_payment_status', true);
                $poster_id = get_post_meta($post->ID, '_sciflow_poster_id', true);
                $score = get_post_meta($post->ID, '_sciflow_ranking_score', true);
                $keywords = get_post_meta($post->ID, '_sciflow_keywords', true);
                $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
                $ed_notes = get_post_meta($post->ID, '_sciflow_editorial_notes', true);
                $confirmed = get_post_meta($post->ID, '_sciflow_presentation_confirmed', true);
                $deadline = get_post_meta($post->ID, '_sciflow_confirmation_deadline', true);

                // Resolve poster upload page URL.
                $poster_pages = get_pages(array('meta_key' => '_wp_page_template', 'meta_value' => 'template-poster-upload.php', 'number' => 1, 'post_status' => 'publish'));
                $poster_upload_url = !empty($poster_pages) ? get_permalink($poster_pages[0]->ID) : home_url('/');
                ?>
                <div class="sciflow-work-card" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <div class="sciflow-work-card__header">
                        <h3 class="sciflow-work-card__title">
                            #<?php echo SciFlow_Status_Manager::get_visual_id($post->ID); ?> -
                            <?php echo SciFlow_Status_Manager::render_title($post->post_title); ?>
                        </h3>
                        <?php echo $status_manager->get_status_badge($display_status); ?>
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

                    <?php
                    $history = SciFlow_Editorial::get_message_history($post->ID);
                    $show_history_statuses = array('submetido', 'em_revisao', 'aprovado_com_consideracoes', 'em_correcao', 'submetido_com_revisao', 'aprovado', 'reprovado', 'apto_publicacao', 'poster_enviado', 'poster_reprovado', 'poster_aprovado', 'confirmado', 'poster_reenviado');

                    if (!empty($history) && in_array($status, $show_history_statuses, true)): ?>
                        <div class="sciflow-message-history">
                            <h4 class="sciflow-message-history__title">
                                <?php esc_html_e('Histórico de Considerações:', 'sciflow-wp'); ?>
                            </h4>
                            <div class="sciflow-messages">
                                <?php foreach ($history as $msg):
                                    $role_label = array(
                                        'revisor' => __('Comitê Científico', 'sciflow-wp'),
                                        'editor' => __('Comitê Científico', 'sciflow-wp'),
                                        'autor' => __('Você', 'sciflow-wp'),
                                        'sistema' => __('Sistema', 'sciflow-wp'),
                                    );
                                    $role_class = $msg['role'];
                                    ?>
                                    <div class="sciflow-message sciflow-message--<?php echo esc_attr($role_class); ?>">
                                        <div class="sciflow-message__header">
                                            <span
                                                class="sciflow-message__author"><?php echo esc_html($role_label[$msg['role']] ?? $msg['role']); ?></span>
                                            <span
                                                class="sciflow-message__date"><?php echo date_i18n('d/m/Y H:i', strtotime($msg['timestamp'])); ?></span>
                                        </div>
                                        <div class="sciflow-message__content">
                                            <?php echo wp_kses_post($msg['content']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="sciflow-work-card__actions">

                        <?php 
                        $current_time = current_time('timestamp');
                        $settings = get_option('sciflow_settings', array());
                        
                        // Corrections deadline
                        $deadline_str = $settings['corrections_deadline'] ?? '';
                        $is_past_deadline = false;
                        $deadline_formatted = '';
                        if (!empty($deadline_str)) {
                            $deadline_time = strtotime($deadline_str);
                            $is_past_deadline = $current_time > $deadline_time;
                            $deadline_formatted = wp_date('d/m \à\s H:i', $deadline_time);
                        }
                        
                        // Poster deadline
                        $poster_deadline_str = $settings['poster_submission_deadline'] ?? '';
                        $is_past_poster_deadline = false;
                        if (!empty($poster_deadline_str)) {
                            $poster_deadline_time = strtotime($poster_deadline_str);
                            $is_past_poster_deadline = $current_time > $poster_deadline_time;
                        }
                        
                        $is_blocked_edit = $is_past_deadline && $status === 'em_correcao' && in_array(strtolower($event), array('enfrute', 'semco', 'senco'), true);
                        
                        if (in_array($status, array('rascunho', 'em_correcao', 'aprovado_com_consideracoes'), true) && !$is_blocked_edit): ?>
                            <button class="sciflow-btn sciflow-btn--primary sciflow-btn--sm sciflow-edit-btn"
                                data-post-id="<?php echo esc_attr($post->ID); ?>">
                                <?php
                                if ($status === 'rascunho') {
                                    esc_html_e('Ver/Editar', 'sciflow-wp');
                                } else {
                                    esc_html_e('Editar e Reenviar', 'sciflow-wp');
                                }
                                ?>
                            </button>
                        <?php elseif ($is_blocked_edit): ?>
                            <div class="sciflow-notice sciflow-notice--error" style="margin-bottom:10px; border-left: 4px solid #dc3545; padding: 10px 14px; background: #f8d7da; border-radius: 6px;">
                                <strong>⛔ <?php esc_html_e('Prazo Encerrado', 'sciflow-wp'); ?></strong><br>
                                <?php printf( esc_html__('O prazo para envio de correções foi encerrado em %s.', 'sciflow-wp'), esc_html($deadline_formatted) ); ?>
                            </div>
                        <?php else: ?>
                            <?php
                            $detail_page = get_pages(array('meta_key' => '_wp_page_template', 'meta_value' => 'page-templates/template-article-detail.php'));
                            $detail_url = !empty($detail_page) ? get_permalink($detail_page[0]->ID) : home_url('/avaliar-artigo');
                            $view_url = add_query_arg('article_id', $post->ID, $detail_url);
                            ?>
                            <a href="<?php echo esc_url($view_url); ?>" class="sciflow-btn sciflow-btn--light sciflow-btn--sm">
                                <i class="bi bi-eye me-1"></i> <?php esc_html_e('Ver Detalhes', 'sciflow-wp'); ?>
                            </a>
                        <?php endif; ?>

                        <?php if (in_array($status, array('aprovado', 'poster_enviado', 'poster_em_correcao'), true)): ?>
                            <?php
                            // Banner visible when poster is still needed
                            if ($status === 'aprovado'):
                                // Format the poster deadline for display
                                $poster_deadline_formatted = '';
                                if (!empty($poster_deadline_str)) {
                                    $poster_deadline_formatted = wp_date('d/m/Y', $poster_deadline_time);
                                }
                            ?>
                                <?php if (!$is_past_poster_deadline): ?>
                                    <div class="sciflow-notice sciflow-notice--warning" style="margin-bottom:10px; border-left: 4px solid #f0ad4e; padding: 10px 14px; background: #fff8e7; border-radius: 6px;">
                                        <strong>⚠️ <?php esc_html_e( 'Atenção: Prazo para envio do pôster!', 'sciflow-wp' ); ?></strong><br>
                                        <?php if ($poster_deadline_formatted): ?>
                                            <?php printf(
                                                esc_html__( 'Seu trabalho foi aprovado. Envie o pôster em PDF até %s. Após essa data não será mais possível realizar o envio.', 'sciflow-wp' ),
                                                '<strong>' . esc_html($poster_deadline_formatted) . '</strong>'
                                            ); ?>
                                        <?php else: ?>
                                            <?php esc_html_e( 'Seu trabalho foi aceito. Agora envie o pôster em formato PDF para concluir o processo.', 'sciflow-wp' ); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="sciflow-notice sciflow-notice--info" style="margin-bottom:10px; border-left: 4px solid #0d6efd; padding: 10px 14px; background: #e7f1ff; border-radius: 6px;">
                                        <strong>🎉 <?php esc_html_e( 'Trabalho Aprovado!', 'sciflow-wp' ); ?></strong><br>
                                        <?php esc_html_e( 'Seu trabalho foi aceito. Agora envie o pôster em formato PDF para concluir o processo.', 'sciflow-wp' ); ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($status === 'poster_em_correcao'): ?>
                                <div class="sciflow-notice sciflow-notice--warning" style="margin-bottom:10px; border-left: 4px solid #f0ad4e; padding: 10px 14px; background: #fff8e7; border-radius: 6px;">
                                    <strong>📋 <?php esc_html_e( 'Pôster Necessita Correção', 'sciflow-wp' ); ?></strong><br>
                                    <?php esc_html_e( 'O comitê solicitou o envio de um novo pôster. Veja as observações acima e reenvie o arquivo.', 'sciflow-wp' ); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($is_past_poster_deadline && in_array(strtolower($event), array('enfrute', 'semco', 'senco'), true) && in_array($status, array('aprovado', 'poster_em_correcao'), true)): ?>
                                <div class="sciflow-notice sciflow-notice--error" style="margin-bottom:10px; border-left: 4px solid #dc3545; padding: 10px 14px; background: #f8d7da; border-radius: 6px;">
                                    <strong>⛔ <?php esc_html_e('Prazo Encerrado', 'sciflow-wp'); ?></strong><br>
                                    <?php esc_html_e('O prazo para envio de pôsteres foi encerrado. Não é mais possível enviar o arquivo.', 'sciflow-wp'); ?>
                                </div>
                            <?php else: ?>
                                <a href="<?php echo esc_url($poster_upload_url); ?>" class="sciflow-btn sciflow-btn--secondary sciflow-btn--sm">
                                    <?php echo $poster_id
                                        ? esc_html__('Reenviar Pôster', 'sciflow-wp')
                                        : esc_html__('Enviar Pôster (PDF)', 'sciflow-wp'); ?>
                                </a>
                            <?php endif; ?>

                            <?php /* Co-author edit button — available for all poster-related statuses */ ?>
                            <button type="button"
                                class="sciflow-btn sciflow-btn--light sciflow-btn--sm sciflow-edit-coauthors-btn"
                                style="margin-left:6px;"
                                data-post-id="<?php echo esc_attr($post->ID); ?>"
                                data-coauthors="<?php echo esc_attr(json_encode(is_array($coauthors) ? array_values($coauthors) : [])); ?>">
                                ✏️ <?php esc_html_e('Editar Coautores', 'sciflow-wp'); ?>
                            </button>
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

<!-- Co-author Edit Modal -->
<div id="sciflow-coauthor-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:10px; padding:28px 32px; max-width:680px; width:95%; max-height:90vh; overflow-y:auto; box-shadow:0 8px 40px rgba(0,0,0,.25); position:relative;">
        <button type="button" id="sciflow-coauthor-modal-close" style="position:absolute; top:14px; right:18px; background:none; border:none; font-size:22px; cursor:pointer; color:#666;">&times;</button>
        <h3 style="margin-top:0; margin-bottom:18px; font-size:1.15rem;"><?php esc_html_e('Editar Coautores', 'sciflow-wp'); ?></h3>
        <div id="sciflow-modal-notice" style="display:none; padding:10px 14px; border-radius:6px; margin-bottom:14px;"></div>
        <input type="hidden" id="sciflow-modal-post-id" value="">
        <div id="sciflow-modal-coauthors-list"></div>
        <button type="button" id="sciflow-modal-add-coauthor" class="sciflow-btn sciflow-btn--secondary sciflow-btn--sm" style="margin-top:10px;">
            + <?php esc_html_e('Adicionar Coautor', 'sciflow-wp'); ?>
        </button>
        <p style="font-size:12px; color:#777; margin-top:8px;"><?php esc_html_e('Máximo de 6 autores por resumo (incluindo autor principal).', 'sciflow-wp'); ?></p>
        <div style="margin-top:18px; display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" id="sciflow-coauthor-modal-cancel" class="sciflow-btn sciflow-btn--light sciflow-btn--sm"><?php esc_html_e('Cancelar', 'sciflow-wp'); ?></button>
            <button type="button" id="sciflow-coauthor-modal-save" class="sciflow-btn sciflow-btn--primary sciflow-btn--sm"><?php esc_html_e('Salvar Coautores', 'sciflow-wp'); ?></button>
        </div>
    </div>
</div>

<script>
(function(){
    var modal   = document.getElementById('sciflow-coauthor-modal');
    var list    = document.getElementById('sciflow-modal-coauthors-list');
    var notice  = document.getElementById('sciflow-modal-notice');
    var postInput = document.getElementById('sciflow-modal-post-id');

    function coauthorRowHtml(index, ca) {
        ca = ca || {};
        return '<div class="sciflow-modal-ca-row" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;padding:10px;background:#f8f9fa;border-radius:6px;position:relative;">'
            + '<input type="text" name="ca_name[]" placeholder="Nome *" required value="' + escAttr(ca.name||'') + '" style="grid-column:span 2;" class="sciflow-field__input">'
            + '<input type="email" name="ca_email[]" placeholder="E-mail *" required value="' + escAttr(ca.email||'') + '" class="sciflow-field__input">'
            + '<input type="text" name="ca_institution[]" placeholder="Instituição" value="' + escAttr(ca.institution||'') + '" class="sciflow-field__input">'
            + '<input type="text" name="ca_telefone[]" placeholder="Telefone" value="' + escAttr(ca.telefone||'') + '" class="sciflow-field__input">'
            + '<button type="button" class="sciflow-modal-ca-remove" title="Remover" style="position:absolute;top:8px;right:10px;background:none;border:none;font-size:18px;cursor:pointer;color:#c33;">&times;</button>'
            + '</div>';
    }

    function escAttr(str) {
        return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function showNotice(msg, type) {
        notice.style.display = 'block';
        notice.style.background = type === 'success' ? '#d1fae5' : '#f8d7da';
        notice.style.borderLeft = '4px solid ' + (type === 'success' ? '#10b981' : '#dc3545');
        notice.style.color = type === 'success' ? '#065f46' : '#842029';
        notice.textContent = msg;
    }

    function openModal(postId, coauthors) {
        postInput.value = postId;
        notice.style.display = 'none';
        list.innerHTML = '';
        if (coauthors && coauthors.length) {
            coauthors.forEach(function(ca, i) { list.insertAdjacentHTML('beforeend', coauthorRowHtml(i, ca)); });
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.sciflow-edit-coauthors-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var postId = btn.dataset.postId;
            var coauthors = [];
            try { coauthors = JSON.parse(btn.dataset.coauthors || '[]'); } catch(e) {}
            openModal(postId, coauthors);
        });
    });

    document.getElementById('sciflow-coauthor-modal-close').addEventListener('click', closeModal);
    document.getElementById('sciflow-coauthor-modal-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    document.getElementById('sciflow-modal-add-coauthor').addEventListener('click', function() {
        var rows = list.querySelectorAll('.sciflow-modal-ca-row');
        if (rows.length >= 5) { showNotice('<?php esc_html_e('Máximo de 5 coautores atingido.', 'sciflow-wp'); ?>', 'error'); return; }
        list.insertAdjacentHTML('beforeend', coauthorRowHtml(rows.length, {}));
    });

    list.addEventListener('click', function(e) {
        if (e.target.classList.contains('sciflow-modal-ca-remove')) {
            e.target.closest('.sciflow-modal-ca-row').remove();
        }
    });

    document.getElementById('sciflow-coauthor-modal-save').addEventListener('click', function() {
        var btn = this;
        var postId = postInput.value;
        var rows = list.querySelectorAll('.sciflow-modal-ca-row');
        var coauthors = [];
        var valid = true;
        rows.forEach(function(row) {
            var name = row.querySelector('[name="ca_name[]"]').value.trim();
            var email = row.querySelector('[name="ca_email[]"]').value.trim();
            var institution = row.querySelector('[name="ca_institution[]"]').value.trim();
            var telefone = row.querySelector('[name="ca_telefone[]"]').value.trim();
            if (!name || !email) { valid = false; return; }
            coauthors.push({name:name, email:email, institution:institution, telefone:telefone});
        });
        if (!valid) { showNotice('<?php esc_html_e('Preencha pelo menos nome e e-mail de todos os coautores.', 'sciflow-wp'); ?>', 'error'); return; }

        btn.disabled = true;
        btn.textContent = '<?php esc_html_e('Salvando...', 'sciflow-wp'); ?>';
        notice.style.display = 'none';

        var fd = new FormData();
        fd.append('action', 'sciflow_update_coauthors');
        fd.append('nonce', '<?php echo esc_js(wp_create_nonce('sciflow_nonce')); ?>');
        fd.append('post_id', postId);
        fd.append('coauthors', JSON.stringify(coauthors));

        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotice('<?php esc_html_e('Coautores atualizados com sucesso!', 'sciflow-wp'); ?>', 'success');
                    setTimeout(closeModal, 1400);
                } else {
                    showNotice(data.data && data.data.message ? data.data.message : '<?php esc_html_e('Erro ao salvar. Tente novamente.', 'sciflow-wp'); ?>', 'error');
                }
            })
            .catch(function() { showNotice('<?php esc_html_e('Erro de conexão. Tente novamente.', 'sciflow-wp'); ?>', 'error'); })
            .finally(function() {
                btn.disabled = false;
                btn.textContent = '<?php esc_html_e('Salvar Coautores', 'sciflow-wp'); ?>';
            });
    });
})();
</script>
/**
 * SciFlow WP — Public JavaScript
 */
(function ($) {
    'use strict';

    const ajax = sciflow_ajax;

    // ─── Helpers ───

    function showMessage(container, message, type) {
        const $c = $(container);
        $c.removeClass('sciflow-notice--info sciflow-notice--success sciflow-notice--warning sciflow-notice--error')
            .addClass('sciflow-notice--' + (type || 'info'))
            .html(message)
            .slideDown(200);
        setTimeout(() => $c.slideUp(400), 8000);
    }

    function ajaxPost(action, data, $btn) {
        if (typeof data === 'string') {
            data += '&action=' + encodeURIComponent(action);
            data += '&nonce=' + encodeURIComponent(ajax.nonce);
        } else {
            data.action = action;
            data.nonce = ajax.nonce;
        }

        const origText = $btn ? $btn.text() : '';
        if ($btn) {
            $btn.prop('disabled', true).html('<span class="sciflow-spinner"></span> ' + (ajax.strings.submitting || 'Enviando...'));
        }

        return $.post(ajax.ajax_url, data).always(function () {
            if ($btn) {
                $btn.prop('disabled', false).text(origText);
            }
        });
    }

    // ─── Character Counter ───

    function updateCharCount() {
        if (typeof tinyMCE === 'undefined') return;
        const editor = tinyMCE.get('sciflow_content');
        if (!editor) return;

        const content = editor.getContent({ format: 'text' });
        const title = $('#sciflow-title').val() || '';
        const authorsText = $('#sciflow-authors-text').val() || '';
        const total = (title + ' ' + content + ' ' + authorsText).length;

        const $counter = $('#sciflow-char-count');
        const $wrapper = $('#sciflow-char-counter');
        $counter.text(total);

        $wrapper.removeClass('is-over is-valid');
        if (total > 4000) {
            $wrapper.addClass('is-over');
        } else if (total >= 3000) {
            $wrapper.addClass('is-valid');
        }
    }

    $(document).ready(function () {
        // Setup TinyMCE char counter.
        if (typeof tinyMCE !== 'undefined') {
            const waitForEditor = setInterval(function () {
                const editor = tinyMCE.get('sciflow_content');
                if (editor) {
                    clearInterval(waitForEditor);
                    editor.on('keyup change', updateCharCount);
                    updateCharCount();
                }
            }, 500);
        }

        $('#sciflow-title').on('input', updateCharCount);
    });

    // ─── Co-authors ───

    let coauthorIndex = 0;

    $(document).on('click', '#sciflow-add-coauthor', function () {
        if (coauthorIndex >= 5) {
            alert('Máximo de 5 coautores.');
            return;
        }
        const i = coauthorIndex++;
        const row = `<div class="sciflow-coauthor-row" data-index="${i}">
            <input type="text" name="coauthors[${i}][name]" placeholder="Nome" class="sciflow-field__input" required>
            <input type="email" name="coauthors[${i}][email]" placeholder="E-mail" class="sciflow-field__input">
            <input type="text" name="coauthors[${i}][institution]" placeholder="Instituição" class="sciflow-field__input">
            <input type="text" name="coauthors[${i}][telefone]" placeholder="Telefone" class="sciflow-field__input">
            <button type="button" class="sciflow-coauthor-remove" title="Remover">×</button>
        </div>`;
        $('#sciflow-coauthors-list').append(row);
    });

    $(document).on('click', '.sciflow-coauthor-remove', function () {
        $(this).closest('.sciflow-coauthor-row').remove();
        updatePresentingAuthorOptions();
    });

    $(document).on('input', '.sciflow-coauthor-row input[name$="[name]"]', function () {
        updatePresentingAuthorOptions();
    });

    function updatePresentingAuthorOptions() {
        const $select = $('#sciflow-presenting-author');
        if (!$select.length) return;

        const currentValue = $select.val();
        const mainAuthorName = $('#sciflow-authors-text').val() || 'Autor Principal';

        $select.empty();
        $select.append(`<option value="main">${mainAuthorName} (Autor Principal)</option>`);

        $('.sciflow-coauthor-row').each(function () {
            const index = $(this).data('index');
            const name = $(this).find('input[name$="[name]"]').val();
            if (name) {
                $select.append(`<option value="${index}">${name}</option>`);
            }
        });

        // Restore selection if it still exists
        if ($select.find(`option[value="${currentValue}"]`).length) {
            $select.val(currentValue);
        } else {
            $select.val('main');
        }
    }

    // ─── Submission Form ───

    $(document).on('submit', '#sciflow-submit-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('#sciflow-submit-btn');

        if (!confirm(ajax.strings.confirm_submit || 'Confirma a submissão?')) return;

        // Sync TinyMCE.
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get('sciflow_content');
            if (editor) editor.save();
        }

        const formData = $form.serialize();

        ajaxPost('sciflow_submit', formData, $btn)
            .done(function (res) {
                if (res.success) {
                    showMessage('#sciflow-form-messages', res.data.message, 'success');
                    // Update hidden post_id field so next save/submit updates this post
                    if (res.data.post_id) {
                        $('#sciflow_post_id').val(res.data.post_id);
                    }

                    if (!res.data.is_draft) {
                        $form[0].reset();
                        $('#sciflow_post_id').val('');
                        if (res.data.redirect_url) {
                            window.location.href = res.data.redirect_url;
                        }
                    } else {
                        // Hide draft button after success
                        $('#sciflow-draft-btn').fadeOut(200);
                    }
                } else {
                    showMessage('#sciflow-form-messages', res.data.message, 'error');
                }
            })
            .fail(function () {
                showMessage('#sciflow-form-messages', 'Erro de conexão.', 'error');
            });
    });

    $(document).on('click', '#sciflow-draft-btn', function (e) {
        const $btn = $(this);
        const $form = $('#sciflow-submit-form');

        // Sync TinyMCE.
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get('sciflow_content');
            if (editor) editor.save();
        }

        const formData = $form.serialize() + '&is_draft=1';

        ajaxPost('sciflow_submit', formData, $btn)
            .done(function (res) {
                if (res.success) {
                    showMessage('#sciflow-form-messages', res.data.message, 'success');
                    // Update hidden post_id field
                    if (res.data.post_id) {
                        $('#sciflow_post_id').val(res.data.post_id);
                    }
                    // Hide draft button after success
                    $('#sciflow-draft-btn').fadeOut(200);
                } else {
                    showMessage('#sciflow-form-messages', res.data.message, 'error');
                }
            })
            .fail(function () {
                showMessage('#sciflow-form-messages', 'Erro de conexão.', 'error');
            });
    });

    // Change detection to show draft button again
    $(document).on('input change', '#sciflow-submit-form input, #sciflow-submit-form select, #sciflow-submit-form textarea', function () {
        $('#sciflow-draft-btn').fadeIn(200);
    });

    // Also detect TinyMCE changes
    $(document).ready(function () {
        if (typeof tinyMCE !== 'undefined') {
            const waitForEditor = setInterval(function () {
                const editor = tinyMCE.get('sciflow_content');
                if (editor) {
                    clearInterval(waitForEditor);
                    editor.on('input change keyup', function () {
                        $('#sciflow-draft-btn').fadeIn(200);
                    });
                }
            }, 500);
        }
    });

    // ─── Payment ───

    function triggerPayment(postId) {
        ajaxPost('sciflow_create_payment', { post_id: postId })
            .done(function (res) {
                if (res.success) {
                    showPaymentModal(postId, res.data);
                } else {
                    showMessage('#sciflow-form-messages', res.data.message, 'error');
                }
            });
    }

    function showPaymentModal(postId, data) {
        const modal = `<div class="sciflow-payment-modal" id="sciflow-payment-modal">
            <div class="sciflow-payment-modal__content">
                <h3>Pagamento via Pix</h3>
                <div class="sciflow-payment-modal__qr">
                    ${data.qr_code ? `<img src="${data.qr_code}" alt="QR Code Pix" style="max-width:200px;">` : ''}
                </div>
                ${data.pix_copia_cola ? `
                    <p><strong>Pix Copia e Cola:</strong></p>
                    <div class="sciflow-payment-modal__pix-code">${data.pix_copia_cola}</div>
                    <button class="sciflow-btn sciflow-btn--secondary sciflow-btn--sm sciflow-copy-pix" data-pix="${data.pix_copia_cola}">Copiar código</button>
                ` : ''}
                <p style="margin-top:15px;font-size:13px;color:#666;">Após o pagamento, o status será atualizado automaticamente.</p>
                <button class="sciflow-btn sciflow-btn--secondary sciflow-btn--sm sciflow-check-payment-btn" data-post-id="${postId}" style="margin-top:10px;">Verificar Pagamento</button>
                <br>
                <button class="sciflow-btn sciflow-btn--link sciflow-payment-modal__close">Fechar</button>
            </div>
        </div>`;
        $('body').append(modal);
    }

    $(document).on('click', '.sciflow-payment-modal__close', function () {
        $('#sciflow-payment-modal').remove();
    });

    $(document).on('click', '.sciflow-copy-pix', function () {
        const pix = $(this).data('pix');
        navigator.clipboard.writeText(pix).then(() => {
            $(this).text('Copiado!');
            setTimeout(() => $(this).text('Copiar código'), 2000);
        });
    });

    $(document).on('click', '.sciflow-payment-btn', function () {
        const postId = $(this).data('post-id');
        triggerPayment(postId);
    });

    $(document).on('click', '.sciflow-check-payment-btn', function () {
        const $btn = $(this);
        const postId = $btn.data('post-id');

        ajaxPost('sciflow_check_payment', { post_id: postId }, $btn)
            .done(function (res) {
                if (res.success && res.data.confirmed) {
                    $('#sciflow-payment-modal').remove();
                    showMessage('#sciflow-dashboard-messages', res.data.message, 'success');
                    location.reload();
                } else {
                    showMessage('#sciflow-dashboard-messages', res.data ? res.data.message : 'Pagamento pendente.', 'warning');
                }
            });
    });

    // ─── Reviewer Form ───

    $(document).on('submit', '.sciflow-review-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const postId = $form.data('post-id');

        const data = $form.serialize() + '&post_id=' + postId;

        ajaxPost('sciflow_submit_review', data, $btn)
            .done(function (res) {
                if (res.success) {
                    showMessage('#sciflow-reviewer-messages', res.data.message, 'success');
                    $form.find('input, select, textarea, button').prop('disabled', true);
                    location.reload(); // Force reload to show locked state correctly
                } else {
                    showMessage('#sciflow-reviewer-messages', res.data.message, 'error');
                }
            });
    });

    // ─── Editor Panel: Tabs ───

    $(document).on('click', '.sciflow-tab[data-event]', function () {
        const event = $(this).data('event');
        $(this).siblings().removeClass('sciflow-tab--active');
        $(this).addClass('sciflow-tab--active');
        $(this).closest('.sciflow-editor-panel').find('.sciflow-tab-content').removeClass('sciflow-tab-content--active');
        $('#sciflow-tab-' + event).addClass('sciflow-tab-content--active');
    });

    // Ranking tabs.
    $(document).on('click', '.sciflow-tab[data-ranking]', function () {
        const target = $(this).data('ranking');
        $(this).siblings().removeClass('sciflow-tab--active');
        $(this).addClass('sciflow-tab--active');
        $('.sciflow-ranking-content').hide();
        $('#ranking-' + target).show();
    });

    // ─── Editor Panel: Assign Reviewer ───

    $(document).on('click', '.sciflow-assign-btn', function () {
        const $btn = $(this);
        const postId = $btn.data('post-id');
        const reviewerId = $btn.closest('.sciflow-inline-form').find('.sciflow-reviewer-select').val();

        if (!reviewerId) {
            alert('Selecione um revisor.');
            return;
        }

        ajaxPost('sciflow_assign_reviewer', { post_id: postId, reviewer_id: reviewerId }, $btn)
            .done(function (res) {
                if (res.success) {
                    showMessage('#sciflow-editor-messages', res.data.message, 'success');
                    location.reload();
                } else {
                    showMessage('#sciflow-editor-messages', res.data.message, 'error');
                }
            });
    });

    // ─── Editor Panel: Decision ───

    $(document).on('click', '.sciflow-decision-btn', function () {
        const $btn = $(this);
        const $form = $btn.closest('.sciflow-decision-form');
        const postId = $form.data('post-id');
        const decision = $btn.data('decision');
        const notes = $form.find('.sciflow-decision-notes').val();

        const labels = {
            approve: 'aprovar',
            reject: 'reprovar',
            return_to_author: 'devolver',
            approved_with_considerations: 'aprovar com considerações',
            return_to_reviewer: 'mandar de volta para o revisor'
        };
        if (!confirm('Tem certeza que deseja ' + (labels[decision] || decision) + ' este trabalho?')) return;

        ajaxPost('sciflow_editorial_decision', { post_id: postId, decision: decision, notes: notes }, $btn)
            .done(function (res) {
                if (res.success) {
                    showMessage('#sciflow-editor-messages', res.data.message, 'success');
                    location.reload();
                } else {
                    showMessage('#sciflow-editor-messages', res.data.message, 'error');
                }
            });
    });

    // ─── Toggle Content ───

    $(document).on('click', '.sciflow-toggle-content', function () {
        const target = $(this).data('target');
        $('#' + target).toggle();
    });

    // ─── Poster Upload ───

    $(document).on('submit', '.sciflow-poster-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const postId = $form.data('post-id');

        const formData = new FormData($form[0]);
        formData.append('action', 'sciflow_upload_poster');
        formData.append('nonce', ajax.nonce);
        formData.append('post_id', postId);

        const origText = $btn.text();
        $btn.prop('disabled', true).html('<span class="sciflow-spinner"></span> Enviando...');

        $.ajax({
            url: ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        })
            .done(function (res) {
                if (res.success) {
                    showMessage('#sciflow-poster-messages', res.data.message, 'success');
                    location.reload();
                } else {
                    showMessage('#sciflow-poster-messages', res.data.message, 'error');
                }
            })
            .fail(function () {
                showMessage('#sciflow-poster-messages', ajax.strings.upload_error || 'Erro no upload.', 'error');
            })
            .always(function () {
                $btn.prop('disabled', false).text(origText);
            });
    });

    // ─── Confirm Presentation ───

    $(document).on('click', '.sciflow-confirm-btn', function () {
        const $btn = $(this);
        const postId = $btn.data('post-id');

        if (!confirm('Confirma sua apresentação?')) return;

        ajaxPost('sciflow_confirm_presentation', { post_id: postId }, $btn)
            .done(function (res) {
                if (res.success) {
                    showMessage('#sciflow-dashboard-messages', res.data.message, 'success');
                    location.reload();
                } else {
                    showMessage('#sciflow-dashboard-messages', res.data.message, 'error');
                }
            });
    });

})(jQuery);

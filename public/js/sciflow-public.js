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

        const content = editor.getContent({ format: 'text' }).replace(/\n/g, ' ').trim();
        const title = $('#sciflow-title').val() || '';

        // Title counter
        const titleLen = title.length;
        $('#sciflow-title-count').text(titleLen);
        const $titleWrapper = $('#sciflow-title-counter');
        if (titleLen > 180) {
            $titleWrapper.css('color', 'red');
        } else {
            $titleWrapper.css('color', '#666');
        }

        // Combined counter (Title + Abstract)
        const total = titleLen + content.length;

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

    function applyMasks() {
        if ($.isFunction($.fn.mask)) {
            $('.sciflow-mask-cpf').mask('000.000.000-00');

            const spMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
            };
            const spOptions = {
                onKeyPress: function (val, e, field, options) {
                    field.mask(spMaskBehavior.apply({}, arguments), options);
                }
            };
            $('.sciflow-mask-phone').mask(spMaskBehavior, spOptions);
        }
    }

    $(document).ready(function () {
        // Setup TinyMCE char counter.
        if (typeof tinyMCE !== 'undefined') {
            const waitForEditor = setInterval(function () {
                const editor = tinyMCE.get('sciflow_content');
                if (editor) {
                    clearInterval(waitForEditor);
                    editor.on('keyup change blur', function() {
                        // Strip <p>, <br> and any line breaks
                        const content = editor.getContent();
                        if (content.includes('<p>') || content.includes('<br')) {
                            const stripped = content.replace(/<\/?p>/g, '').replace(/<br\s*\/?>/g, ' ');
                            editor.setContent(stripped);
                        }
                        updateCharCount();
                    });

                    // Prevent line breaks (Enter and Shift+Enter)
                    editor.on('keydown', function (e) {
                        if (e.keyCode === 13) {
                            e.preventDefault();
                            return false;
                        }
                    });

                    // Strip line breaks on paste
                    editor.on('paste', function (e) {
                        e.preventDefault();
                        let text = '';
                        if (e.clipboardData || e.originalEvent.clipboardData) {
                            text = (e.originalEvent || e).clipboardData.getData('text/plain');
                        } else if (window.clipboardData) {
                            text = window.clipboardData.getData('Text');
                        }
                        
                        // Replace newlines/carriage returns with space and trim
                        const sanitized = text.replace(/[\r\n]+/g, ' ').trim();
                        editor.insertContent(sanitized);
                    });

                    updateCharCount();
                }
            }, 500);
        }

        $('#sciflow-title').on('input change', updateCharCount);

        // Strip line breaks on paste for Title
        $('#sciflow-title').on('paste', function (e) {
            e.preventDefault();
            let text = '';
            if (e.originalEvent.clipboardData) {
                text = e.originalEvent.clipboardData.getData('text/plain');
            } else if (window.clipboardData) {
                text = window.clipboardData.getData('Text');
            }

            const sanitized = text.replace(/[\r\n]+/g, ' ').trim();
            
            // Insert at cursor position or replace selection
            const start = this.selectionStart;
            const end = this.selectionEnd;
            const val = $(this).val();
            const newVal = val.slice(0, start) + sanitized + val.slice(end);
            
            $(this).val(newVal);
            
            // Update cursor position
            this.setSelectionRange(start + sanitized.length, start + sanitized.length);
            $(this).trigger('change');
        });

        // Conditional Cultura Select
        $('#sciflow-event').on('change', function () {
            const val = $(this).val();
            const $culturaField = $('#sciflow-cultura-field');
            const $culturaSelect = $('#sciflow-cultura-select');

            if (!val) {
                $culturaField.hide();
                $culturaSelect.val('');
                return;
            }

            $culturaField.show();

            if (val === 'enfrute') {
                $culturaField.find('.sciflow-optgroup-frutas').show().prop('disabled', false);
                $culturaField.find('.sciflow-optgroup-olericolas').hide().prop('disabled', true);
            } else if (val === 'semco') {
                $culturaField.find('.sciflow-optgroup-frutas').hide().prop('disabled', true);
                $culturaField.find('.sciflow-optgroup-olericolas').show().prop('disabled', false);
            } else {
                $culturaField.find('optgroup').show().prop('disabled', false);
            }

            // Clear selection if it's now hidden
            const selectedOption = $culturaSelect.find('option:selected');
            if (selectedOption.parent().is(':disabled')) {
                $culturaSelect.val('');
            }
        }).trigger('change');

        applyMasks();
    });

    // ─── Co-authors ───

    let coauthorIndex = 0;
    $(document).ready(function () {
        $('.sciflow-coauthor-row').each(function () {
            const idx = parseInt($(this).data('index'));
            if (!isNaN(idx) && idx >= coauthorIndex) {
                coauthorIndex = idx + 1;
            }
        });
    });

    $(document).on('click', '#sciflow-add-coauthor', function () {
        if (coauthorIndex >= 5) {
            alert('Máximo de 5 coautores.');
            return;
        }
        const i = coauthorIndex++;
        const row = `<div class="sciflow-coauthor-row" data-index="${i}">
            <input type="text" name="coauthors[${i}][name]" placeholder="Nome" class="sciflow-field__input" required>
            <input type="email" name="coauthors[${i}][email]" placeholder="E-mail" class="sciflow-field__input" required>
            <input type="text" name="coauthors[${i}][institution]" placeholder="Instituição" class="sciflow-field__input" required>
            <input type="text" name="coauthors[${i}][telefone]" placeholder="Telefone" class="sciflow-field__input sciflow-mask-phone" required>
            <button type="button" class="sciflow-coauthor-remove" title="Remover">×</button>
        </div>`;
        $('#sciflow-coauthors-list').append(row);
        applyMasks();
    });

    $(document).on('click', '.sciflow-coauthor-remove', function () {
        $(this).closest('.sciflow-coauthor-row').remove();
        updatePresentingAuthorOptions();
    });

    $(document).on('focus mousedown', '#sciflow-presenting-author', function () {
        updatePresentingAuthorOptions();
    });

    $(document).ready(function () {
        if ($('#sciflow-presenting-author').length) {
            updatePresentingAuthorOptions();
        }
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

        // Keyword uniqueness validation
        const keywords = [];
        $('.sciflow-keyword-input').each(function () {
            const val = $(this).val().trim().toLowerCase();
            if (val) keywords.push(val);
        });

        const uniqueKeywords = new Set(keywords);
        if (uniqueKeywords.size !== keywords.length) {
            alert('As palavras-chave não podem ser repetidas.');
            return;
        }

        // Populate preview modal
        const title = $('#sciflow-title').val() || '';
        const event = $('#sciflow-event option:selected').text();
        const cultura = $('select[name="cultura"]').val() || '';
        const area = $('select[name="knowledge_area"]').val() || '';

        let contentHtml = '';
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get('sciflow_content');
            if (editor) contentHtml = editor.getContent();
        } else {
            contentHtml = $('#sciflow_content').val() || '';
        }

        let authors = '<strong>Autor Principal:</strong> ' + ($('#sciflow-authors-text').val() || '') + '<br>';
        $('.sciflow-coauthor-row').each(function () {
            const name = $(this).find('input[name$="[name]"]').val();
            if (name) authors += '<strong>Coautor:</strong> ' + name + '<br>';
        });

        const previewHtml = `
            <p><strong>Evento:</strong> ${event}</p>
            <p><strong>Cultura:</strong> ${cultura}</p>
            <p><strong>Área:</strong> ${area}</p>
            <hr>
            <h4>${title}</h4>
            <div style="margin-bottom: 15px; font-size: 13px;">${authors}</div>
            <div>${contentHtml}</div>
        `;

        $('#sciflow-preview-content').html(previewHtml);
        $('#sciflow-preview-modal').fadeIn(200);
    });

    $(document).on('click', '#sciflow-cancel-preview-btn', function () {
        $('#sciflow-preview-modal').fadeOut(200);
    });

    $(document).on('click', '#sciflow-confirm-submit-btn', function (e) {
        const $form = $('#sciflow-submit-form');
        const $btn = $(this);

        // Sync TinyMCE.
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get('sciflow_content');
            if (editor) editor.save();
        }

        const formData = $form.serialize();
        const action = $form.find('input[name="action"]').val() || 'sciflow_submit';

        ajaxPost(action, formData, $btn)
            .done(function (res) {
                $('#sciflow-preview-modal').fadeOut(200);
                if (res.success) {
                    showMessage('#sciflow-form-messages', res.data.message, 'success');
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
                        $('#sciflow-draft-btn').fadeOut(200);
                    }
                } else {
                    alert(res.data.message);
                    showMessage('#sciflow-form-messages', res.data.message, 'error');
                }
                $(window).scrollTop(0);
            })
            .fail(function () {
                $('#sciflow-preview-modal').fadeOut(200);
                alert('Erro de conexão.');
                showMessage('#sciflow-form-messages', 'Erro de conexão.', 'error');
                $(window).scrollTop(0);
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
        const action = 'sciflow_submit'; // drafts are always submit, not resubmit

        ajaxPost(action, formData, $btn)
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
                    alert(res.data.message);
                    showMessage('#sciflow-form-messages', res.data.message, 'error');
                }
            })
            .fail(function () {
                alert('Erro de conexão.');
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
            approved: 'aprovar',
            rejected: 'reprovar',
            return_to_author: 'devolver',
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
        const fileInput = $form.find('input[type="file"]')[0];

        if (fileInput.files.length > 0) {
            const fileName = fileInput.files[0].name;
            const extension = fileName.split('.').pop().toLowerCase();
            if (extension !== 'pdf') {
                alert('Apenas arquivos PDF são aceitos.');
                return;
            }
        }

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

    // ─── Editorial Filters ───

    $('.sciflow-filter-input').on('keyup change', function () {
        const eventKey = $(this).data('event');
        const $table = $('.sciflow-table-' + eventKey);
        const search = $('.sciflow-filter-text[data-event="' + eventKey + '"]').val().toLowerCase();
        const status = $('.sciflow-filter-status[data-event="' + eventKey + '"]').val();
        const cultura = $('.sciflow-filter-cultura[data-event="' + eventKey + '"]').val();
        const area = $('.sciflow-filter-area[data-event="' + eventKey + '"]').val();

        $table.find('tbody > tr.sciflow-table__row').each(function () {
            const $row = $(this);
            const rowSearch = $row.data('search') || '';
            const rowStatus = $row.data('status') || '';
            const rowCultura = $row.data('cultura') || '';
            const rowArea = $row.data('area') || '';
            const $contentRow = $('#content-' + $row.data('post-id'));

            let show = true;
            if (search && rowSearch.indexOf(search) === -1) {
                show = false;
            }
            if (status && rowStatus !== status) {
                show = false;
            }
            if (cultura && rowCultura !== cultura) {
                show = false;
            }
            if (area && rowArea !== area) {
                show = false;
            }

            if (show) {
                $row.show();
            } else {
                $row.hide();
                $contentRow.hide(); // Hide the expanded details if the row gets filtered out
            }
        });
    });
    // ─── Reviewer Filters ───

    $('.sciflow-filter-input').on('keyup change', function () {
        if (!$(this).hasClass('sciflow-filter-text-reviewer') &&
            !$(this).hasClass('sciflow-filter-event-reviewer') &&
            !$(this).hasClass('sciflow-filter-cultura-reviewer') &&
            !$(this).hasClass('sciflow-filter-area-reviewer')) {
            return;
        }

        const search = $('.sciflow-filter-text-reviewer').val().toLowerCase();
        const event = $('.sciflow-filter-event-reviewer').val();
        const cultura = $('.sciflow-filter-cultura-reviewer').val();
        const area = $('.sciflow-filter-area-reviewer').val();

        $('.sciflow-review-card').each(function () {
            const $card = $(this);
            const cardSearch = $card.data('search') || '';
            const cardEvent = $card.data('event') || '';
            const cardCultura = $card.data('cultura') || '';
            const cardArea = $card.data('area') || '';

            let show = true;
            if (search && cardSearch.indexOf(search) === -1) {
                show = false;
            }
            if (event && cardEvent !== event) {
                show = false;
            }
            if (cultura && cardCultura !== cultura) {
                show = false;
            }
            if (area && cardArea !== area) {
                show = false;
            }

            if (show) {
                $card.show();
            } else {
                $card.hide();
            }
        });
    });

    // ─── Speaker Form ───

    function updateSpeakerCharCount() {
        let content = '';
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get('sciflow_content');
            if (editor) {
                content = editor.getContent({ format: 'text' }).replace(/\n/g, ' ').trim();
            } else {
                content = $('#sciflow-speaker-content').val() || '';
            }
        } else {
            content = $('#sciflow-speaker-content').val() || '';
        }

        const title = $('#sciflow-speaker-title').val() || '';
        const total = title.length + content.length;

        const $counter = $('#speaker-char-count');
        $counter.text('Caracteres: ' + total + ' / 25000 (Mínimo: 16000)');

        if (total < 16000 || total > 25000) {
            $counter.css('color', '#dc3545'); // red
        } else {
            $counter.css('color', '#198754'); // green
        }
    }

    $('#sciflow-speaker-title, #sciflow-speaker-content').on('input', updateSpeakerCharCount);

    // Strip line breaks on paste for Speaker Title
    $('#sciflow-speaker-title').on('paste', function (e) {
        e.preventDefault();
        let text = '';
        if (e.originalEvent.clipboardData) {
            text = e.originalEvent.clipboardData.getData('text/plain');
        } else if (window.clipboardData) {
            text = window.clipboardData.getData('Text');
        }

        const sanitized = text.replace(/[\r\n]+/g, ' ').trim();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        const val = $(this).val();
        const newVal = val.slice(0, start) + sanitized + val.slice(end);
        
        $(this).val(newVal);
        this.setSelectionRange(start + sanitized.length, start + sanitized.length);
        $(this).trigger('input'); // Speaker form uses 'input' for counter
    });

    $('#sciflow-speaker-form').on('submit', function (e) {
        e.preventDefault();

        // Sync TinyMCE if present.
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get('sciflow_content');
            if (editor) editor.save();
        }

        const title = $('#sciflow-speaker-title').val() || '';
        const content = $('#sciflow-speaker-content').val() || '';
        const total = title.length + content.length;

        if (total < 16000) {
            alert('O texto deve ter no mínimo 16.000 caracteres. Atual: ' + total);
            return;
        }
        if (total > 25000) {
            alert('O texto excedeu o limite de 25.000 caracteres. Atual: ' + total);
            return;
        }

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const data = new FormData(this);

        const origText = $btn.text();
        $btn.prop('disabled', true).html('<span class="sciflow-spinner"></span> ' + (ajax.strings.submitting || 'Enviando...'));

        $.ajax({
            url: ajax.ajax_url,
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.success) {
                    showMessage('#sciflow-form-messages', res.data.message, 'success');
                    $form[0].reset();
                    updateSpeakerCharCount();
                } else {
                    alert(res.data.message || 'Erro ao enviar.');
                    showMessage('#sciflow-form-messages', res.data.message || 'Erro ao enviar.', 'error');
                }
            },
            error: function () {
                alert('Erro de conexão.');
                showMessage('#sciflow-form-messages', 'Erro de conexão.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).text(origText);
            }
        });
    });

    // Also detect TinyMCE changes for Speaker Form
    $(document).ready(function () {
        if (typeof tinyMCE !== 'undefined') {
            const waitForEditor = setInterval(function () {
                const editor = tinyMCE.get('sciflow_content');
                if (editor) {
                    // Check if we are on the speaker form (the editor ID is shared for now but handled differently)
                    if ($('#sciflow-speaker-form').length) {
                        clearInterval(waitForEditor);
                        editor.on('input change keyup blur', function() {
                            // Strip <p>, <br> and any line breaks
                            const content = editor.getContent();
                            if (content.includes('<p>') || content.includes('<br')) {
                                const stripped = content.replace(/<\/?p>/g, '').replace(/<br\s*\/?>/g, ' ');
                                editor.setContent(stripped);
                            }
                            updateSpeakerCharCount();
                        });

                        // Prevent line breaks (Enter and Shift+Enter)
                        editor.on('keydown', function (e) {
                            if (e.keyCode === 13) {
                                e.preventDefault();
                                return false;
                            }
                        });

                        updateSpeakerCharCount();
                    }
                }
            }, 500);
        }
    });

})(jQuery);

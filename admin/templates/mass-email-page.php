<?php
/**
 * Mass Email Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$roles = array(
    '' => __('Todos os cargos do SciFlow', 'sciflow-wp'),
    'sciflow_inscrito' => __('Inscritos (Autores)', 'sciflow-wp'),
    'sciflow_revisor' => __('Revisores (Geral)', 'sciflow-wp'),
    'sciflow_speaker' => __('Palestrantes', 'sciflow-wp'),
    'sciflow_enfrute_revisor' => __('Revisores Enfrute', 'sciflow-wp'),
    'sciflow_semco_revisor' => __('Revisores Semco', 'sciflow-wp'),
    'sciflow_editor' => __('Editores (Geral)', 'sciflow-wp'),
    'sciflow_enfrute_editor' => __('Editores Enfrute', 'sciflow-wp'),
    'sciflow_semco_editor' => __('Editores Semco', 'sciflow-wp'),
    'administrator' => __('Administradores', 'sciflow-wp'),
);

$events = array(
    '' => __('Todos os Eventos', 'sciflow-wp'),
    'enfrute' => 'Enfrute',
    'semco' => 'Semco',
);

?>

<div class="wrap sciflow-mass-email-wrap">
    <h1><?php esc_html_e('SciFlow — Disparo de E-mail em Massa', 'sciflow-wp'); ?></h1>
    
    <div id="sciflow-mass-email-form" class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px;">
        <p class="description">
            <?php esc_html_e('Utilize esta ferramenta para enviar comunicados, convites ou avisos gerais.', 'sciflow-wp'); ?>
        </p>

        <form id="sciflow-sender-form">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="email-role"><?php esc_html_e('Destinatários (Cargo)', 'sciflow-wp'); ?></label></th>
                    <td>
                        <select id="email-role" name="role" class="regular-text">
                            <?php foreach ($roles as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="email-event"><?php esc_html_e('Filtrar por Evento', 'sciflow-wp'); ?></label></th>
                    <td>
                        <select id="email-event" name="event" class="regular-text">
                            <?php foreach ($events as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Selecione para filtrar usuários vinculados a um evento específico.', 'sciflow-wp'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Total Estimado', 'sciflow-wp'); ?></th>
                    <td>
                        <span id="recipient-count" style="font-weight: bold; font-size: 1.2em;">0</span> usuarios
                        <span id="count-spinner" class="spinner" style="float:none;"></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="email-subject"><?php esc_html_e('Assunto do E-mail', 'sciflow-wp'); ?></label></th>
                    <td>
                        <input type="text" id="email-subject" name="subject" class="large-text" required placeholder="<?php esc_attr_e('Ex: Convite para Avaliação - Enfrute 2026', 'sciflow-wp'); ?>" style="width:100%;">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Conteúdo', 'sciflow-wp'); ?></label></th>
                    <td>
                        <?php 
                        wp_editor('', 'email_content', array(
                            'textarea_name' => 'content',
                            'media_buttons' => true,
                            'textarea_rows' => 15,
                            'teeny'         => false,
                            'quicktags'     => true,
                        )); 
                        ?>
                        <p class="description">
                            <strong><?php esc_html_e('Placeholders disponíveis:', 'sciflow-wp'); ?></strong><br>
                            <code>{{name}}</code>, <code>{{first_name}}</code>, <code>{{site_url}}</code>, <code>{{login_url}}</code>, <code>{{email}}</code>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="button" id="start-dispatch" class="button button-primary button-large">
                    <?php esc_html_e('Iniciar Disparo', 'sciflow-wp'); ?>
                </button>
            </p>
        </form>

        <div id="dispatch-progress" style="display:none; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
            <h3><?php esc_html_e('Progresso do Envio', 'sciflow-wp'); ?></h3>
            <div class="progress-bar-wrapper" style="background: #eee; border: 1px solid #ccc; height: 25px; border-radius: 4px; overflow: hidden; position: relative; margin-bottom: 10px;">
                <div id="progress-fill" style="background: #2c5530; width: 0%; height: 100%; transition: width 0.3s ease;"></div>
                <div id="progress-text" style="position: absolute; width: 100%; text-align: center; top: 0; line-height: 25px; font-weight: bold; color: #000; mix-blend-mode: difference;">0%</div>
            </div>
            <p id="progress-status"></p>
            <div id="dispatch-log" style="background: #fdfdfd; border: 1px solid #ddd; height: 150px; overflow-y: scroll; padding: 10px; font-family: monospace; font-size: 12px;">
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const $role = $('#email-role');
    const $event = $('#email-event');
    const $count = $('#recipient-count');
    const $spinner = $('#count-spinner');
    const $dispatch = $('#dispatch-progress');
    const $progressFill = $('#progress-fill');
    const $progressText = $('#progress-text');
    const $status = $('#progress-status');
    const $log = $('#dispatch-log');
    
    const nonce = '<?php echo wp_create_nonce("sciflow_mass_email_nonce"); ?>';

    function updateCount() {
        $spinner.addClass('is-active');
        $.post(ajaxurl, {
            action: 'sciflow_get_recipient_count',
            role: $role.val(),
            event: $event.val(),
            nonce: nonce
        }, function(response) {
            $spinner.removeClass('is-active');
            if (response.success) {
                $count.text(response.data.count);
            }
        });
    }

    $role.add($event).on('change', updateCount);
    updateCount();

    $('#start-dispatch').on('click', function() {
        const subject = $('#email-subject').val();
        let content = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
            content = tinymce.get('email_content').getContent();
        } else {
            content = $('#email_content').val();
        }

        if (!subject || !content) {
            alert('<?php echo esc_js(__("Por favor, preencha o assunto e o conteúdo.", "sciflow-wp")); ?>');
            return;
        }

        const count_val = parseInt($count.text(), 10);
        if (count_val <= 0) {
            alert('<?php echo esc_js(__("Não há destinatários selecionados.", "sciflow-wp")); ?>');
            return;
        }

        if (!confirm('<?php echo esc_js(__("Confirmar o disparo para ", "sciflow-wp")); ?>' + count_val + ' <?php echo esc_js(__("destinatários?", "sciflow-wp")); ?>')) {
            return;
        }

        $(this).prop('disabled', true);
        $role.add($event).add('#email-subject').prop('disabled', true);
        
        $dispatch.show();
        $log.empty();
        $progressFill.css('width', '0%');
        $progressText.text('0%');
        $status.text('<?php echo esc_js(__("Iniciando envios...", "sciflow-wp")); ?>');
        
        sendBatch(0);
    });

    function sendBatch(offset) {
        let content = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
            content = tinymce.get('email_content').getContent();
        } else {
            content = $('#email_content').val();
        }

        $.post(ajaxurl, {
            action: 'sciflow_send_mass_email_batch',
            role: $role.val(),
            event: $event.val(),
            subject: $('#email-subject').val(),
            content: content,
            offset: offset,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                const data = response.data;
                const percent = data.total > 0 ? Math.round((data.new_offset / data.total) * 100) : 100;
                const displayPercent = percent > 100 ? 100 : percent;
                const displayOffset = data.new_offset > data.total ? data.total : data.new_offset;
                
                $progressFill.css('width', displayPercent + '%');
                $progressText.text(displayPercent + '% (' + displayOffset + '/' + data.total + ')');
                
                $log.append('<div>[' + new Date().toLocaleTimeString() + '] Enviados lote de ' + data.sent + ' e-mails... (' + displayPercent + '%)</div>');
                $log.scrollTop($log[0].scrollHeight);

                if (data.done) {
                    $status.html('<strong style="color: green;"><?php echo esc_js(__("Disparo concluído com sucesso!", "sciflow-wp")); ?></strong>');
                    $('#start-dispatch').prop('disabled', false).text('<?php echo esc_js(__("Reiniciar Disparo", "sciflow-wp")); ?>');
                    $role.add($event).add('#email-subject').prop('disabled', false);
                } else {
                    sendBatch(data.new_offset);
                }
            } else {
                alert('Erro: ' + (response.data || 'Ocorreu um erro no servidor.'));
                $('#start-dispatch').prop('disabled', false).text('<?php echo esc_js(__("Tentar Novamente", "sciflow-wp")); ?>');
                $role.add($event).add('#email-subject').prop('disabled', false);
                $status.html('<strong style="color: red;"><?php echo esc_js(__("Erro no disparo.", "sciflow-wp")); ?></strong>');
            }
        }).fail(function() {
            alert('A rota do servidor falhou. Verifique o log de erro do PHP.');
            $('#start-dispatch').prop('disabled', false).text('<?php echo esc_js(__("Tentar Novamente", "sciflow-wp")); ?>');
            $role.add($event).add('#email-subject').prop('disabled', false);
        });
    }
});
</script>

<style>
.sciflow-mass-email-wrap .card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
.sciflow-mass-email-wrap input.large-text { padding: 10px; font-size: 1.1em; }
.sciflow-mass-email-wrap #dispatch-log div { padding: 4px 0; border-bottom: 1px solid #f0f0f0; }
</style>

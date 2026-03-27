<?php
/**
 * Admin template: Técnicos Epagri management page.
 *
 * Lists all users with role sciflow_tecnico_epagri and allows
 * promoting them to reviewer (Enfrute/Semco) and/or speaker.
 */

if (!defined('ABSPATH')) {
    exit;
}

$tecnicos = get_users(array(
    'role'    => 'sciflow_tecnico_epagri',
    'orderby' => 'display_name',
    'order'   => 'ASC',
    'fields'  => 'all',
));
?>
<div class="wrap">
    <h1><?php esc_html_e('Técnicos Epagri', 'sciflow-wp'); ?></h1>
    <p class="description" style="margin-bottom: 20px;">
        <?php esc_html_e('Gerencie os papéis adicionais dos Técnicos Epagri. Ative os toggles para conceder acesso de Revisor Enfrute, Revisor Semco e/ou Palestrante. O papel de Técnico Epagri é mantido em todos os casos.', 'sciflow-wp'); ?>
    </p>

    <div style="margin-bottom: 20px; padding: 15px; background: #fff8e5; border-left: 4px solid #f0ad4e; border-radius: 3px;">
        <strong><?php esc_html_e('Inscritos anteriores', 'sciflow-wp'); ?>:</strong>
        <?php esc_html_e('Clique no botão abaixo para atribuir o papel de Técnico Epagri a todos os usuários que já possuem pedidos concluídos com esse produto (não afeta usuários que já têm o papel).', 'sciflow-wp'); ?>
        <br><br>
        <button type="button" id="sciflow-backfill-btn" class="button button-primary">
            <?php esc_html_e('Reprocessar pedidos históricos', 'sciflow-wp'); ?>
        </button>
        <span id="sciflow-backfill-result" style="margin-left: 15px; display:none;"></span>
    </div>

    <div id="sciflow-tecnico-notice" style="display:none; margin-bottom:15px;"></div>

    <?php if (empty($tecnicos)) : ?>
        <p><?php esc_html_e('Nenhum Técnico Epagri cadastrado ainda.', 'sciflow-wp'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped" id="sciflow-tecnicos-table">
            <thead>
                <tr>
                    <th style="width: 25%"><?php esc_html_e('Nome', 'sciflow-wp'); ?></th>
                    <th style="width: 25%"><?php esc_html_e('E-mail', 'sciflow-wp'); ?></th>
                    <th style="width: 16%; text-align:center;"><?php esc_html_e('Revisor Enfrute', 'sciflow-wp'); ?></th>
                    <th style="width: 16%; text-align:center;"><?php esc_html_e('Revisor Semco', 'sciflow-wp'); ?></th>
                    <th style="width: 16%; text-align:center;"><?php esc_html_e('Palestrante', 'sciflow-wp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tecnicos as $tecnico) :
                    $roles = (array) $tecnico->roles;
                    $is_enfrute  = in_array('sciflow_enfrute_revisor', $roles, true);
                    $is_semco    = in_array('sciflow_semco_revisor',   $roles, true);
                    $is_speaker  = in_array('sciflow_speaker',          $roles, true);
                ?>
                <tr data-user-id="<?php echo esc_attr($tecnico->ID); ?>">
                    <td>
                        <strong><?php echo esc_html($tecnico->display_name); ?></strong>
                    </td>
                    <td><?php echo esc_html($tecnico->user_email); ?></td>

                    <!-- Revisor Enfrute -->
                    <td style="text-align:center;">
                        <label class="sciflow-toggle" title="<?php esc_attr_e('Revisor Enfrute', 'sciflow-wp'); ?>">
                            <input type="checkbox"
                                class="sciflow-role-toggle"
                                data-user-id="<?php echo esc_attr($tecnico->ID); ?>"
                                data-role="sciflow_enfrute_revisor"
                                <?php checked($is_enfrute); ?>>
                            <span class="sciflow-toggle__slider"></span>
                        </label>
                    </td>

                    <!-- Revisor Semco -->
                    <td style="text-align:center;">
                        <label class="sciflow-toggle" title="<?php esc_attr_e('Revisor Semco', 'sciflow-wp'); ?>">
                            <input type="checkbox"
                                class="sciflow-role-toggle"
                                data-user-id="<?php echo esc_attr($tecnico->ID); ?>"
                                data-role="sciflow_semco_revisor"
                                <?php checked($is_semco); ?>>
                            <span class="sciflow-toggle__slider"></span>
                        </label>
                    </td>

                    <!-- Palestrante -->
                    <td style="text-align:center;">
                        <label class="sciflow-toggle" title="<?php esc_attr_e('Palestrante', 'sciflow-wp'); ?>">
                            <input type="checkbox"
                                class="sciflow-role-toggle"
                                data-user-id="<?php echo esc_attr($tecnico->ID); ?>"
                                data-role="sciflow_speaker"
                                <?php checked($is_speaker); ?>>
                            <span class="sciflow-toggle__slider"></span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
/* Toggle switch */
.sciflow-toggle {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
    cursor: pointer;
}
.sciflow-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.sciflow-toggle__slider {
    position: absolute;
    inset: 0;
    background-color: #ccc;
    border-radius: 24px;
    transition: background-color .25s;
}
.sciflow-toggle__slider:before {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    transition: transform .25s;
}
.sciflow-toggle input:checked + .sciflow-toggle__slider {
    background-color: #2c7a2c;
}
.sciflow-toggle input:checked + .sciflow-toggle__slider:before {
    transform: translateX(22px);
}
.sciflow-toggle input:disabled + .sciflow-toggle__slider {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<script>
(function ($) {
    var nonce = '<?php echo esc_js(wp_create_nonce('sciflow_toggle_tecnico_role')); ?>';
    var backfillNonce = '<?php echo esc_js(wp_create_nonce('sciflow_backfill_tecnico_roles')); ?>';

    function showNotice(msg, type) {
        var $n = $('#sciflow-tecnico-notice');
        $n.attr('class', 'notice notice-' + type + ' is-dismissible')
          .html('<p>' + msg + '</p>')
          .show();
        setTimeout(function () { $n.fadeOut(); }, 4000);
    }

    // Backfill button
    $('#sciflow-backfill-btn').on('click', function () {
        var $btn = $(this);
        var $result = $('#sciflow-backfill-result');
        $btn.prop('disabled', true).text('Processando...');
        $result.hide();

        $.post(ajaxurl, {
            action : 'sciflow_backfill_tecnico_roles',
            nonce  : backfillNonce,
        }, function (res) {
            if (res.success) {
                $result.text(res.data.assigned + ' usuário(s) atualizado(s), ' + res.data.skipped + ' já tinham o papel.').show();
                // Reload to show newly added users in the table
                if (res.data.assigned > 0) {
                    setTimeout(function () { location.reload(); }, 2000);
                }
            } else {
                showNotice('Erro: ' + (res.data || 'Falha no reprocessamento.'), 'error');
            }
        }).fail(function () {
            showNotice('Erro de comunicação com o servidor.', 'error');
        }).always(function () {
            $btn.prop('disabled', false).text('Reprocessar pedidos históricos');
        });
    });

    // Toggle roles
    $(document).on('change', '.sciflow-role-toggle', function () {
        var $cb      = $(this);
        var userId   = $cb.data('user-id');
        var role     = $cb.data('role');
        var action   = $cb.is(':checked') ? 'add' : 'remove';

        $cb.prop('disabled', true);

        $.post(ajaxurl, {
            action        : 'sciflow_toggle_tecnico_role',
            nonce         : nonce,
            user_id       : userId,
            role          : role,
            toggle_action : action
        }, function (res) {
            if (res.success) {
                var label = {
                    sciflow_enfrute_revisor : 'Revisor Enfrute',
                    sciflow_semco_revisor   : 'Revisor Semco',
                    sciflow_speaker         : 'Palestrante'
                }[role] || role;
                var verb = action === 'add' ? 'concedido' : 'removido';
                showNotice('Papel <strong>' + label + '</strong> ' + verb + ' com sucesso.', 'success');
            } else {
                showNotice('Erro: ' + (res.data || 'Falha ao alterar o papel.'), 'error');
                // Revert toggle
                $cb.prop('checked', action !== 'add');
            }
        }).fail(function () {
            showNotice('Erro de comunicação com o servidor.', 'error');
            $cb.prop('checked', action !== 'add');
        }).always(function () {
            $cb.prop('disabled', false);
        });
    });
}(jQuery));
</script>

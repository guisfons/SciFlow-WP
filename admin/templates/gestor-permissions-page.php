<?php
/**
 * Admin template: Gestor Técnico permission management page.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get the role to check its current capabilities
$gestor_role = get_role('sciflow_tecnico_admin');
$roles = array();
if ($gestor_role) {
    $roles = $gestor_role->capabilities;
}

// Define capability groups for display
$permission_groups = array(
    'sciflow' => array(
        'label' => __('SciFlow', 'sciflow-wp'),
        'desc'  => __('Acesso ao gerenciamento de trabalhos, revisores e técnicos.', 'sciflow-wp'),
        'caps'  => array('manage_sciflow', 'manage_sciflow_tecnicos')
    ),
    'woocommerce' => array(
        'label' => __('WooCommerce', 'sciflow-wp'),
        'desc'  => __('Acesso aos pedidos, relatórios e configurações de loja.', 'sciflow-wp'),
        'caps'  => array('manage_woocommerce', 'edit_shop_orders')
    ),
    'content' => array(
        'label' => __('Conteúdo (Posts/Páginas)', 'sciflow-wp'),
        'desc'  => __('Permissão para editar posts, páginas e subir arquivos.', 'sciflow-wp'),
        'caps'  => array('edit_posts', 'edit_pages', 'upload_files')
    ),
    'settings' => array(
        'label' => __('Configurações do Site', 'sciflow-wp'),
        'desc'  => __('Acesso às opções gerais do WordPress e temas (Cuidado!).', 'sciflow-wp'),
        'caps'  => array('manage_options', 'edit_theme_options')
    ),
    'users' => array(
        'label' => __('Usuários', 'sciflow-wp'),
        'desc'  => __('Permissão para listar e editar perfis de usuários.', 'sciflow-wp'),
        'caps'  => array('list_users', 'edit_users')
    )
);

// Get users with this role
$gestores = get_users(array(
    'role'    => 'sciflow_tecnico_admin',
    'orderby' => 'display_name',
    'order'   => 'ASC',
));
?>

<div class="wrap">
    <h1><?php esc_html_e('Permissões do Gestor Técnico', 'sciflow-wp'); ?></h1>
    <p class="description">
        <?php esc_html_e('Gerencie as permissões globais do papel "Gestor Técnico". Ative os toggles abaixo para conceder acesso a diferentes áreas do painel administrativo.', 'sciflow-wp'); ?>
    </p>

    <div id="sciflow-gestor-notice" style="display:none; margin-bottom:15px;"></div>

    <div class="sciflow-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-top: 20px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Permissões Globais da Role', 'sciflow-wp'); ?></h2>
        <p style="color: #666; margin-bottom: 20px;"><?php esc_html_e('Estas configurações afetam TODOS os usuários que possuem o cargo de Gestor Técnico.', 'sciflow-wp'); ?></p>

        <table class="wp-list-table widefat fixed striped" style="border:none;">
            <thead>
                <tr>
                    <th style="width: 30%"><?php esc_html_e('Grupo de Funcionalidades', 'sciflow-wp'); ?></th>
                    <th style="width: 50%"><?php esc_html_e('Descrição', 'sciflow-wp'); ?></th>
                    <th style="width: 20%; text-align:center;"><?php esc_html_e('Acesso', 'sciflow-wp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permission_groups as $key => $group) : 
                    // Check if the role has the main cap of the group
                    $has_access = false;
                    foreach ($group['caps'] as $cap) {
                        if (isset($roles[$cap]) && $roles[$cap]) {
                            $has_access = true;
                            break;
                        }
                    }
                ?>
                <tr>
                    <td><strong><?php echo esc_html($group['label']); ?></strong></td>
                    <td style="color: #666; font-size: 0.9em;"><?php echo esc_html($group['desc']); ?></td>
                    <td style="text-align:center;">
                        <label class="sciflow-toggle">
                            <input type="checkbox" 
                                   class="sciflow-gestor-cap-toggle" 
                                   data-group="<?php echo esc_attr($key); ?>"
                                   <?php checked($has_access); ?>>
                            <span class="sciflow-toggle__slider"></span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="sciflow-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-top: 30px;">
        <h2 style="margin-top:0;"><?php esc_html_e('Usuários com este Cargo', 'sciflow-wp'); ?></h2>
        
        <?php if (empty($gestores)) : ?>
            <p><?php esc_html_e('Nenhum usuário possui o cargo de Gestor Técnico no momento.', 'sciflow-wp'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="border:none;">
                <thead>
                    <tr>
                        <th style="width: 40%"><?php esc_html_e('Nome', 'sciflow-wp'); ?></th>
                        <th style="width: 40%"><?php esc_html_e('E-mail', 'sciflow-wp'); ?></th>
                        <th style="width: 20%; text-align:center;"><?php esc_html_e('Ações', 'sciflow-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gestores as $user) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td style="text-align:center;">
                            <button type="button" 
                                    class="button button-link-delete sciflow-remove-gestor" 
                                    data-user-id="<?php echo esc_attr($user->ID); ?>"
                                    data-name="<?php echo esc_attr($user->display_name); ?>">
                                <?php esc_html_e('Remover Cargo', 'sciflow-wp'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
/* Estilos herdados e adaptados */
.sciflow-card { border: 1px solid #e5e5e5; }
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
(function($) {
    var nonce = '<?php echo esc_js(wp_create_nonce("sciflow_gestor_permissions_nonce")); ?>';

    function showNotice(msg, type) {
        var $n = $('#sciflow-gestor-notice');
        $n.attr('class', 'notice notice-' + type + ' is-dismissible')
          .html('<p>' + msg + '</p>')
          .show();
        setTimeout(function() { $n.fadeOut(); }, 4000);
    }

    // Toggle capabilities
    $('.sciflow-gestor-cap-toggle').on('change', function() {
        var $cb = $(this);
        var group = $cb.data('group');
        var action = $cb.is(':checked') ? 'add' : 'remove';

        $cb.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'sciflow_toggle_gestor_capability',
            nonce: nonce,
            group: group,
            toggle_action: action
        }, function(res) {
            if (res.success) {
                var verb = action === 'add' ? 'concedida' : 'removida';
                showNotice('Permissão do grupo <strong>' + group + '</strong> ' + verb + ' com sucesso.', 'success');
            } else {
                showNotice('Erro: ' + (res.data || 'Falha ao alterar permissão.'), 'error');
                $cb.prop('checked', action !== 'add');
            }
        }).fail(function() {
            showNotice('Erro de comunicação.', 'error');
            $cb.prop('checked', action !== 'add');
        }).always(function() {
            $cb.prop('disabled', false);
        });
    });

    // Remove role from user
    $('.sciflow-remove-gestor').on('click', function() {
        var $btn = $(this);
        var userId = $btn.data('user-id');
        var name = $btn.data('name');

        if (!confirm('Tem certeza que deseja remover o cargo de Gestor Técnico de ' + name + '?')) {
            return;
        }

        $btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'sciflow_remove_gestor_role',
            nonce: nonce,
            user_id: userId
        }, function(res) {
            if (res.success) {
                showNotice('Cargo removido com sucesso.', 'success');
                $btn.closest('tr').fadeOut(function() { $(this).remove(); });
            } else {
                showNotice('Erro: ' + res.data, 'error');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            showNotice('Erro de comunicação.', 'error');
            $btn.prop('disabled', false);
        });
    });
})(jQuery);
</script>

<?php
/**
 * "Diagnóstico" screen, onboarding notice and repair actions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Admin_Setup
{
    const PAGE = 'sciflow-diagnostics';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'), 20);
        add_action('admin_notices', array($this, 'onboarding_notice'));
        add_action('admin_post_sciflow_create_pages', array($this, 'handle_create_pages'));
        add_action('admin_post_sciflow_repair', array($this, 'handle_repair'));
        add_action('admin_post_sciflow_dismiss_onboarding', array($this, 'handle_dismiss'));
        add_filter('plugin_action_links_' . SCIFLOW_BASENAME, array($this, 'action_links'));
    }

    public function add_menu()
    {
        add_submenu_page(
            'sciflow-settings',
            __('Diagnóstico', 'sciflow-wp'),
            __('Diagnóstico', 'sciflow-wp'),
            'manage_options',
            self::PAGE,
            array($this, 'render')
        );
    }

    public function action_links($links)
    {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('admin.php?page=sciflow-settings')) . '">' . esc_html__('Configurações', 'sciflow-wp') . '</a>',
            '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE)) . '">' . esc_html__('Diagnóstico', 'sciflow-wp') . '</a>'
        );
        return $links;
    }

    /**
     * Shown to administrators until the installation has no blocking errors
     * (or the notice is dismissed). Limited to a few screens.
     */
    public function onboarding_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('dashboard', 'plugins', 'toplevel_page_sciflow-settings'), true)) {
            return;
        }
        if ('1' !== get_option('sciflow_show_onboarding', '0')) {
            return;
        }

        $checks = SciFlow_Diagnostics::run();
        if (SciFlow_Diagnostics::is_ready($checks)) {
            update_option('sciflow_show_onboarding', '0', false);
            return;
        }
        $summary = SciFlow_Diagnostics::summary($checks);
        $dismiss = wp_nonce_url(admin_url('admin-post.php?action=sciflow_dismiss_onboarding'), 'sciflow_dismiss_onboarding');
        ?>
        <div class="notice notice-warning">
            <p><strong><?php esc_html_e('SciFlow: configuração inicial pendente.', 'sciflow-wp'); ?></strong>
                <?php echo esc_html(sprintf(_n('%d item impede o funcionamento.', '%d itens impedem o funcionamento.', $summary['error'], 'sciflow-wp'), $summary['error'])); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(SciFlow_Setup_Wizard::url()); ?>"><?php esc_html_e('Abrir assistente de configuração', 'sciflow-wp'); ?></a>
                <a class="button-link" href="<?php echo esc_url($dismiss); ?>"><?php esc_html_e('Dispensar', 'sciflow-wp'); ?></a>
            </p>
        </div>
        <?php
    }

    public function handle_dismiss()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'sciflow-wp'), 403);
        }
        check_admin_referer('sciflow_dismiss_onboarding');
        update_option('sciflow_show_onboarding', '0', false);
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    public function handle_create_pages()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'sciflow-wp'), 403);
        }
        check_admin_referer('sciflow_create_pages');

        $roles = null;
        if (!empty($_REQUEST['roles']) && is_array($_REQUEST['roles'])) {
            $roles = array_map('sanitize_key', wp_unslash($_REQUEST['roles']));
        }
        $created = SciFlow_Pages::create_missing($roles);
        SciFlow_Admin_UI::flush_status();

        wp_safe_redirect(add_query_arg(array('page' => self::PAGE, 'sciflow_created' => count($created)), admin_url('admin.php')));
        exit;
    }

    public function handle_repair()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'sciflow-wp'), 403);
        }
        check_admin_referer('sciflow_repair');

        SciFlow_Installer::install_site();
        SciFlow_Admin_UI::flush_status();

        wp_safe_redirect(add_query_arg(array('page' => self::PAGE, 'sciflow_repaired' => 1), admin_url('admin.php')));
        exit;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'sciflow-wp'), 403);
        }

        $checks = SciFlow_Diagnostics::run();
        $summary = SciFlow_Diagnostics::summary($checks);
        $ready = SciFlow_Diagnostics::is_ready($checks);
        $icons = array('ok' => 'yes-alt', 'warning' => 'warning', 'error' => 'dismiss', 'info' => 'info-outline');
        $colors = array('ok' => '#008a20', 'warning' => '#996800', 'error' => '#d63638', 'info' => '#2271b1');
        $labels = array('ok' => __('OK', 'sciflow-wp'), 'warning' => __('Atenção', 'sciflow-wp'), 'error' => __('Bloqueante', 'sciflow-wp'), 'info' => __('Informativo', 'sciflow-wp'));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Diagnóstico', 'sciflow-wp'); ?></h1>
            <p class="description"><?php esc_html_e('Verifica o ambiente, a configuração, as páginas e as integrações. Execute de novo depois de qualquer mudança.', 'sciflow-wp'); ?></p>

            <?php if (isset($_GET['sciflow_created'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('%d página(s) criada(s). Páginas existentes não foram alteradas.', 'sciflow-wp'), absint($_GET['sciflow_created']))); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['sciflow_setup_done'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Configuração inicial concluída. Revise abaixo os itens restantes, se houver.', 'sciflow-wp'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['sciflow_repaired'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Instalação reparada: papéis, tarefas agendadas, regras de URL e proteção de diretórios verificados.', 'sciflow-wp'); ?></p></div>
            <?php endif; ?>

            <div class="card" style="max-width:none;">
                <h2 style="margin-top:0;">
                    <?php if ($ready) : ?>
                        <span class="dashicons dashicons-yes-alt" style="color:#008a20;"></span> <?php esc_html_e('Pronto para uso', 'sciflow-wp'); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-dismiss" style="color:#d63638;"></span> <?php esc_html_e('Ainda não está pronto', 'sciflow-wp'); ?>
                    <?php endif; ?>
                </h2>
                <p><?php echo esc_html(sprintf(__('%1$d bloqueante(s), %2$d alerta(s), %3$d informativo(s), %4$d OK.', 'sciflow-wp'), $summary['error'], $summary['warning'], $summary['info'], $summary['ok'])); ?></p>
                <p><?php esc_html_e('"Pronto" significa que nenhuma verificação obrigatória falhou. Serviços externos (pagamento, entrega de e-mail) não são contatados: credenciais configuradas aparecem como informativo até serem testadas por você.', 'sciflow-wp'); ?></p>

                <p><a class="button button-primary" href="<?php echo esc_url(SciFlow_Setup_Wizard::url()); ?>"><?php esc_html_e('Abrir assistente de configuração', 'sciflow-wp'); ?></a></p>
                <h3><?php esc_html_e('Passos', 'sciflow-wp'); ?></h3>
                <ol>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=sciflow-settings#tab-eventos')); ?>"><?php esc_html_e('Defina os nomes dos eventos', 'sciflow-wp'); ?></a></li>
                    <li><?php esc_html_e('Crie as páginas que faltam:', 'sciflow-wp'); ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                            <input type="hidden" name="action" value="sciflow_create_pages">
                            <?php wp_nonce_field('sciflow_create_pages'); ?>
                            <button class="button"><?php esc_html_e('Criar páginas ausentes', 'sciflow-wp'); ?></button>
                        </form>
                    </li>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=sciflow-settings#tab-prazos')); ?>"><?php esc_html_e('Configure os prazos', 'sciflow-wp'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=sciflow-settings#tab-geral')); ?>"><?php esc_html_e('Configure WooCommerce (opcional) e o texto LGPD', 'sciflow-wp'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('admin.php?page=sciflow-settings#tab-email')); ?>"><?php esc_html_e('Defina o remetente e faça um envio de teste pelo seu plugin SMTP', 'sciflow-wp'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('nav-menus.php')); ?>"><?php esc_html_e('Monte os menus por perfil (tema SciFlow)', 'sciflow-wp'); ?></a></li>
                </ol>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_repair">
                    <?php wp_nonce_field('sciflow_repair'); ?>
                    <button class="button"><?php esc_html_e('Reparar instalação', 'sciflow-wp'); ?></button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE)); ?>"><?php esc_html_e('Executar diagnóstico novamente', 'sciflow-wp'); ?></a>
                </form>
            </div>

            <table class="widefat striped" style="margin-top:20px;">
                <thead><tr>
                    <th><?php esc_html_e('Grupo', 'sciflow-wp'); ?></th>
                    <th><?php esc_html_e('Verificação', 'sciflow-wp'); ?></th>
                    <th><?php esc_html_e('Resultado', 'sciflow-wp'); ?></th>
                    <th><?php esc_html_e('Detalhes', 'sciflow-wp'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($checks as $check) : ?>
                    <tr>
                        <td><?php echo esc_html($check['group']); ?></td>
                        <td><?php echo esc_html($check['label']); ?></td>
                        <td><span class="sfa-pill is-<?php echo esc_attr($check['status']); ?>"><span class="dashicons dashicons-<?php echo esc_attr($icons[$check['status']]); ?>" aria-hidden="true"></span><?php echo esc_html($labels[$check['status']]); ?></span></td>
                        <td><?php echo esc_html($check['message']); ?>
                            <?php if (!empty($check['action'])) : ?>
                                — <a href="<?php echo esc_url($check['action']); ?>"><?php esc_html_e('resolver', 'sciflow-wp'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

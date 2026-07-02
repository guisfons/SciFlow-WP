<?php
/**
 * Admin pages and meta boxes for SciFlow.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Admin
{

    private $payment;

    public function __construct(SciFlow_PayGo_Gateway $payment)
    {
        $this->payment = $payment;

        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_admin_grades'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Handle manual payment confirmation.
        add_action('admin_post_sciflow_manual_confirm', array($this, 'handle_manual_confirm'));

        // Handle ranking selection.
        add_action('admin_post_sciflow_select_top', array($this, 'handle_select_top'));

        // Handle forgotten article notifications.
        add_action('wp_ajax_sciflow_notify_forgotten_ajax', array($this, 'ajax_notify_forgotten'));
        add_action('wp_ajax_sciflow_notify_forgotten_poster_ajax', array($this, 'ajax_notify_forgotten_poster'));
        add_action('wp_ajax_sciflow_notify_unsubmitted_poster_ajax', array($this, 'ajax_notify_unsubmitted_poster'));

        // Handle manual check deadlines.
        add_action('wp_ajax_sciflow_force_check_deadlines_ajax', array($this, 'ajax_force_check_deadlines'));

        // AJAX for mass email.
        add_action('wp_ajax_sciflow_get_recipient_count', array($this, 'ajax_get_recipient_count'));
        add_action('wp_ajax_sciflow_send_mass_email_batch', array($this, 'ajax_send_mass_email_batch'));

        // AJAX for tecnico role toggle.
        add_action('wp_ajax_sciflow_toggle_tecnico_role', array($this, 'ajax_toggle_tecnico_role'));

        // AJAX for historical backfill of tecnico roles.
        add_action('wp_ajax_sciflow_backfill_tecnico_roles', array($this, 'ajax_backfill_tecnico_roles'));

        // AJAX for gestor tecnico permission management.
        add_action('wp_ajax_sciflow_toggle_gestor_capability', array($this, 'ajax_toggle_gestor_capability'));
        add_action('wp_ajax_sciflow_remove_gestor_role', array($this, 'ajax_remove_gestor_role'));

        // Restrict editable roles for Gestor Técnico.
        add_filter('editable_roles', array($this, 'filter_editable_roles_for_gestor'));

        // Hide unwanted menus for Gestor Técnico.
        add_action('admin_menu', array($this, 'restrict_gestor_menus'), 999);
    }

    /**
     * Register admin menu.
     */
    public function add_menu_pages()
    {
        add_menu_page(
            __('SciFlow', 'sciflow-wp'),
            __('SciFlow', 'sciflow-wp'),
            'manage_sciflow_tecnicos',
            'sciflow-settings',
            array($this, 'render_main_page'),
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'sciflow-settings',
            __('Configurações', 'sciflow-wp'),
            __('Configurações', 'sciflow-wp'),
            'manage_sciflow',
            'sciflow-settings',
            array($this, 'render_main_page')
        );

        add_submenu_page(
            'sciflow-settings',
            __('Ranking & Seleção', 'sciflow-wp'),
            __('Ranking & Seleção', 'sciflow-wp'),
            'manage_sciflow',
            'sciflow-ranking',
            array($this, 'render_ranking_page')
        );

        add_submenu_page(
            'sciflow-settings',
            __('Certificados', 'sciflow-wp'),
            __('Certificados', 'sciflow-wp'),
            'manage_sciflow',
            'sciflow-certificates',
            array($this, 'render_certificates_page')
        );

        add_submenu_page(
            'sciflow-settings',
            __('Disparo de E-mail', 'sciflow-wp'),
            __('Disparo de E-mail', 'sciflow-wp'),
            'manage_sciflow',
            'sciflow-mass-email',
            array($this, 'render_mass_email_page')
        );

        add_submenu_page(
            'sciflow-settings',
            __('Técnicos Epagri', 'sciflow-wp'),
            __('Técnicos Epagri', 'sciflow-wp'),
            'manage_sciflow_tecnicos',
            'sciflow-tecnicos',
            array($this, 'render_tecnicos_page')
        );

        add_submenu_page(
            'sciflow-settings',
            __('Permissões Gestor', 'sciflow-wp'),
            __('Permissões Gestor', 'sciflow-wp'),
            'manage_options',
            'sciflow-gestor-permissions',
            array($this, 'render_gestor_permissions_page')
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings()
    {
        register_setting('sciflow_settings_group', 'sciflow_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
    }

    /**
     * Sanitize settings.
     */
    public function sanitize_settings($input)
    {
        $clean = array();

        // Editor emails (multiple supported separated by comma).
        $clean['enfrute_editor_email'] = $this->sanitize_multiple_emails($input['enfrute_editor_email'] ?? '');
        $clean['semco_editor_email'] = $this->sanitize_multiple_emails($input['semco_editor_email'] ?? '');

        // Dashboard URLs.
        $clean['dashboard_url'] = esc_url_raw($input['dashboard_url'] ?? '');
        $clean['editor_dashboard_url'] = esc_url_raw($input['editor_dashboard_url'] ?? '');
        $clean['reviewer_dashboard_url'] = esc_url_raw($input['reviewer_dashboard_url'] ?? '');
        $clean['poster_template_url'] = esc_url_raw($input['poster_template_url'] ?? '');

        // PayGo settings.
        $clean['paygo_integration_key'] = sanitize_text_field($input['paygo_integration_key'] ?? '');
        $clean['paygo_token'] = sanitize_text_field($input['paygo_token'] ?? '');
        $clean['paygo_pix_key'] = sanitize_text_field($input['paygo_pix_key'] ?? '');
        $clean['paygo_ambiente'] = in_array($input['paygo_ambiente'] ?? '', array('sandbox', 'producao'))
            ? $input['paygo_ambiente'] : 'sandbox';
        $clean['submission_price'] = floatval($input['submission_price'] ?? 50);

        // Ranking weights.
        $criteria = array('originalidade', 'objetividade', 'organizacao', 'metodologia', 'aderencia');
        foreach ($criteria as $key) {
            $w_val = $input['ranking_weights'][$key] ?? 1;
            if (is_string($w_val)) {
                $w_val = str_replace(',', '.', $w_val);
            }
            $clean['ranking_weights'][$key] = floatval($w_val);
        }

        // WooCommerce product IDs.
        $clean['woo_product_ids'] = sanitize_text_field($input['woo_product_ids'] ?? '');
        $clean['woo_speaker_product_ids'] = sanitize_text_field($input['woo_speaker_product_ids'] ?? '');
        $clean['woo_tecnico_epagri_product_ids'] = sanitize_text_field($input['woo_tecnico_epagri_product_ids'] ?? '');

        $clean['article_submission_deadline'] = sanitize_text_field($input['article_submission_deadline'] ?? '');
        $clean['corrections_deadline'] = sanitize_text_field($input['corrections_deadline'] ?? '');
        $clean['poster_submission_deadline'] = sanitize_text_field($input['poster_submission_deadline'] ?? '');

        $clean['forgotten_article_email_text'] = wp_kses_post($input['forgotten_article_email_text'] ?? '');
        $clean['forgotten_poster_email_text'] = wp_kses_post($input['forgotten_poster_email_text'] ?? '');
        $clean['unsubmitted_poster_email_text'] = wp_kses_post($input['unsubmitted_poster_email_text'] ?? '');

        return $clean;
    }

    /**
     * Sanitize a comma-separated list of emails.
     */
    private function sanitize_multiple_emails($string)
    {
        $emails = explode(',', $string);
        $clean = array();
        foreach ($emails as $email) {
            $email = sanitize_email(trim($email));
            if (!empty($email)) {
                $clean[] = $email;
            }
        }
        return implode(', ', $clean);
    }

    /**
     * Render main SciFlow page based on user capabilities.
     * If user can manage_sciflow, show settings.
     * Otherwise, if they can manage_sciflow_tecnicos, show tecnicos page.
     */
    public function render_main_page()
    {
        if (current_user_can('manage_sciflow')) {
            $this->render_settings_page();
        } else {
            $this->render_tecnicos_page();
        }
    }

    /**
     * Render settings page.
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }
        $settings = get_option('sciflow_settings', array());
        
        if (isset($_GET['sciflow_notified'])) {
            $count = absint($_GET['sciflow_notified']);
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Foram notificados %d trabalhos com sucesso.', 'sciflow-wp'), $count) . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('SciFlow — Configurações', 'sciflow-wp'); ?>
            </h1>

            <h2 class="nav-tab-wrapper sciflow-nav-tabs">
                <a href="#tab-geral" class="nav-tab nav-tab-active"><?php esc_html_e('Geral', 'sciflow-wp'); ?></a>
                <a href="#tab-prazos" class="nav-tab"><?php esc_html_e('Prazos', 'sciflow-wp'); ?></a>
                <a href="#tab-pagamento" class="nav-tab"><?php esc_html_e('Pagamento', 'sciflow-wp'); ?></a>
                <a href="#tab-ranking" class="nav-tab"><?php esc_html_e('Ranking', 'sciflow-wp'); ?></a>
                <a href="#tab-notificacoes" class="nav-tab"><?php esc_html_e('Notificações', 'sciflow-wp'); ?></a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('sciflow_settings_group'); ?>

                <div id="tab-geral" class="sciflow-tab-content">
                    <h2>
                        <?php esc_html_e('Editores por Evento', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('E-mail do Editor Enfrute', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="text" name="sciflow_settings[enfrute_editor_email]"
                                    value="<?php echo esc_attr($settings['enfrute_editor_email'] ?? ''); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Separe múltiplos e-mails por vírgula.', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('E-mail do Editor Semco', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="text" name="sciflow_settings[semco_editor_email]"
                                    value="<?php echo esc_attr($settings['semco_editor_email'] ?? ''); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Separe múltiplos e-mails por vírgula.', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('URL do Painel do Autor', 'sciflow-wp'); ?>
                            </th>
                            <td><input type="url" name="sciflow_settings[dashboard_url]"
                                    value="<?php echo esc_attr($settings['dashboard_url'] ?? ''); ?>" class="regular-text"
                                    placeholder="<?php echo esc_attr(home_url('/meu-painel/')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('URL do Painel do Editor', 'sciflow-wp'); ?>
                            </th>
                            <td><input type="url" name="sciflow_settings[editor_dashboard_url]"
                                    value="<?php echo esc_attr($settings['editor_dashboard_url'] ?? ''); ?>" class="regular-text"
                                    placeholder="<?php echo esc_attr(home_url('/painel-do-editor/')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('URL do Painel do Revisor', 'sciflow-wp'); ?>
                            </th>
                            <td><input type="url" name="sciflow_settings[reviewer_dashboard_url]"
                                    value="<?php echo esc_attr($settings['reviewer_dashboard_url'] ?? ''); ?>" class="regular-text"
                                    placeholder="<?php echo esc_attr(home_url('/painel-do-revisor/')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('URL do Template do Pôster', 'sciflow-wp'); ?>
                            </th>
                            <td><input type="url" name="sciflow_settings[poster_template_url]"
                                    value="<?php echo esc_attr($settings['poster_template_url'] ?? ''); ?>" class="regular-text"
                                    placeholder="https://...">
                                <p class="description">
                                    <?php esc_html_e('Link para o modelo de pôster disponibilizado para download (.ppt, .pptx, etc).', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2>
                        <?php esc_html_e('Integração WooCommerce', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('ID(s) do Produto de Inscrição', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="text" name="sciflow_settings[woo_product_ids]"
                                    value="<?php echo esc_attr($settings['woo_product_ids'] ?? ''); ?>" class="regular-text"
                                    placeholder="ex: 123 ou 123,456">
                                <p class="description">
                                    <?php esc_html_e('ID(s) do produto WooCommerce que atribui o cargo de Inscrito. Separe múltiplos IDs por vírgula.', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('ID(s) do Produto/Variação de Palestrante', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="text" name="sciflow_settings[woo_speaker_product_ids]"
                                    value="<?php echo esc_attr($settings['woo_speaker_product_ids'] ?? ''); ?>" class="regular-text"
                                    placeholder="ex: 789 ou 789,1011">
                                <p class="description">
                                    <?php esc_html_e('ID(s) do produto ou variação WooCommerce que atribui o cargo de Palestrante.', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('ID(s) do Produto/Variação de Técnico Epagri', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="text" name="sciflow_settings[woo_tecnico_epagri_product_ids]"
                                    value="<?php echo esc_attr($settings['woo_tecnico_epagri_product_ids'] ?? ''); ?>" class="regular-text"
                                    placeholder="ex: 321 ou 321,654">
                                <p class="description">
                                    <?php esc_html_e('ID(s) do produto ou variação WooCommerce que atribui o cargo de Técnico Epagri. Técnicos podem ser promovidos a Revisor e/ou Palestrante na página "Técnicos Epagri".', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-prazos" class="sciflow-tab-content" style="display:none;">
                    <h2>
                        <?php esc_html_e('Prazos de Submissão', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Data/Hora Limite para Novas Submissões', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="datetime-local" name="sciflow_settings[article_submission_deadline]"
                                    value="<?php echo esc_attr($settings['article_submission_deadline'] ?? ''); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('A partir desta data e hora, novas submissões de artigos (Enfrute e Semco) serão bloqueadas. Edições e ajustes de artigos já criados/submetidos continuarão funcionando normalmente.', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Data/Hora Limite para Correções (Necessita Alterações)', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="datetime-local" name="sciflow_settings[corrections_deadline]"
                                    value="<?php echo esc_attr($settings['corrections_deadline'] ?? ''); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('A partir desta data e hora, trabalhos que estejam com status "Necessita Alterações" não poderão mais ser editados e reenviados pelos autores.', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Data/Hora Limite para Envio de Pôster', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <input type="datetime-local" name="sciflow_settings[poster_submission_deadline]"
                                    value="<?php echo esc_attr($settings['poster_submission_deadline'] ?? ''); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('A partir desta data e hora, os autores não poderão mais enviar ou reenviar pôsteres.', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <hr>
                    <h2><?php esc_html_e('Forçar Verificação de Prazos', 'sciflow-wp'); ?></h2>
                    <div id="sciflow-check-deadlines-form" style="max-width: 800px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                        <p><?php esc_html_e('A verificação de prazos ocorre automaticamente a cada hora. Porém, se você acabou de alterar uma data para o passado, pode forçar a verificação agora mesmo. Todos os artigos e pôsteres com prazo vencido serão reprovados.', 'sciflow-wp'); ?></p>
                        <button type="button" id="sciflow-check-deadlines-btn" class="button button-secondary"><?php esc_html_e('Verificar Prazos Agora', 'sciflow-wp'); ?></button>
                        <span id="sciflow-check-deadlines-spinner" class="spinner" style="float:none; margin-top:5px;"></span>
                        <div id="sciflow-check-deadlines-progress" style="margin-top:15px; font-weight:bold;"></div>
                    </div>
                </div>

                <div id="tab-pagamento" class="sciflow-tab-content" style="display:none;">
                    <h2>
                        <?php esc_html_e('Pagamento PayGo (Pix)', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Chave de Integração (Integration Key)</th>
                            <td><input type="text" name="sciflow_settings[paygo_integration_key]"
                                    value="<?php echo esc_attr($settings['paygo_integration_key'] ?? ''); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Token / Senha</th>
                            <td><input type="password" name="sciflow_settings[paygo_token]"
                                    value="<?php echo esc_attr($settings['paygo_token'] ?? ''); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Chave Pix (opcional)', 'sciflow-wp'); ?>
                            </th>
                            <td><input type="text" name="sciflow_settings[paygo_pix_key]"
                                    value="<?php echo esc_attr($settings['paygo_pix_key'] ?? ''); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Ambiente', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <select name="sciflow_settings[paygo_ambiente]">
                                    <option value="sandbox" <?php selected($settings['paygo_ambiente'] ?? '', 'sandbox'); ?>>
                                        Sandbox</option>
                                    <option value="producao" <?php selected($settings['paygo_ambiente'] ?? '', 'producao'); ?>>
                                        Produção</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Valor da Submissão (R$)', 'sciflow-wp'); ?>
                            </th>
                            <td><input type="number" step="0.01" name="sciflow_settings[submission_price]"
                                    value="<?php echo esc_attr($settings['submission_price'] ?? '50.00'); ?>" class="small-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="tab-ranking" class="sciflow-tab-content" style="display:none;">
                    <h2>
                        <?php esc_html_e('Pesos do Ranking', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <?php
                        $criteria = array(
                            'originalidade' => __('Originalidade', 'sciflow-wp'),
                            'objetividade' => __('Objetividade', 'sciflow-wp'),
                            'organizacao' => __('Organização', 'sciflow-wp'),
                            'metodologia' => __('Metodologia', 'sciflow-wp'),
                            'aderencia' => __('Aderência aos Objetivos', 'sciflow-wp'),
                        );
                        foreach ($criteria as $key => $label): ?>
                            <tr>
                                <th scope="row">
                                    <?php echo esc_html($label); ?>
                                </th>
                                <td><input type="number" step="0.1" min="0" max="10"
                                        name="sciflow_settings[ranking_weights][<?php echo esc_attr($key); ?>]"
                                        value="<?php echo esc_attr($settings['ranking_weights'][$key] ?? 1); ?>" class="small-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div id="tab-notificacoes" class="sciflow-tab-content" style="display:none;">
                    <h2>
                        <?php esc_html_e('E-mail Artigos "Esquecidos"', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Texto do E-mail', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <textarea name="sciflow_settings[forgotten_article_email_text]" rows="10" class="large-text" placeholder="Prezado(a) autor(a)..."><?php echo esc_textarea($settings['forgotten_article_email_text'] ?? ''); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Texto que será enviado para os autores que não fizeram as alterações solicitadas. Você pode usar as tags [NOME], [NOME DO RESUMO] e [EVENTO].', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2>
                        <?php esc_html_e('E-mail Pôsteres "Esquecidos"', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Texto do E-mail (Pôster)', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <textarea name="sciflow_settings[forgotten_poster_email_text]" rows="10" class="large-text" placeholder="Prezado(a) autor(a)..."><?php echo esc_textarea($settings['forgotten_poster_email_text'] ?? ''); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Texto que será enviado para os autores que não reenviaram o pôster com as correções solicitadas. Tags: [NOME], [NOME DO RESUMO], [EVENTO].', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2>
                        <?php esc_html_e('E-mail Pôsteres "Não Enviados"', 'sciflow-wp'); ?>
                    </h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Texto do E-mail (Não Enviado)', 'sciflow-wp'); ?>
                            </th>
                            <td>
                                <textarea name="sciflow_settings[unsubmitted_poster_email_text]" rows="10" class="large-text" placeholder="Prezado(a) autor(a)..."><?php echo esc_textarea($settings['unsubmitted_poster_email_text'] ?? ''); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Texto que será enviado para os autores com trabalho aprovado, mas que ainda não enviaram a versão final (em PDF) do pôster. Tags: [NOME], [NOME DO RESUMO], [EVENTO].', 'sciflow-wp'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <hr>
                    
                    <h2><?php esc_html_e('Notificar Artigos "Esquecidos"', 'sciflow-wp'); ?></h2>
                    <div id="sciflow-notify-forgotten-form" style="max-width: 800px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                        <p><?php esc_html_e('Isso enviará o texto configurado acima para todos os autores que possuem trabalhos aguardando correções (status: Necessita Alterações). O envio será feito em pequenos lotes para evitar sobrecarga no servidor.', 'sciflow-wp'); ?></p>
                        <button type="button" id="sciflow-notify-forgotten-btn" class="button button-secondary"><?php esc_html_e('Enviar E-mails Agora', 'sciflow-wp'); ?></button>
                        <span id="sciflow-notify-spinner" class="spinner" style="float:none; margin-top:5px;"></span>
                        <div id="sciflow-notify-progress" style="margin-top:15px; font-weight:bold;"></div>
                    </div>

                    <hr>
                    
                    <h2><?php esc_html_e('Notificar Pôsteres "Esquecidos"', 'sciflow-wp'); ?></h2>
                    <div id="sciflow-notify-forgotten-poster-form" style="max-width: 800px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-top:20px;">
                        <p><?php esc_html_e('Isso enviará o texto configurado acima para todos os autores que possuem pôsteres aguardando correções (status: Pôster Necessita Correção). O envio será feito em pequenos lotes.', 'sciflow-wp'); ?></p>
                        <button type="button" id="sciflow-notify-forgotten-poster-btn" class="button button-secondary"><?php esc_html_e('Enviar E-mails Agora (Pôsteres)', 'sciflow-wp'); ?></button>
                        <span id="sciflow-notify-poster-spinner" class="spinner" style="float:none; margin-top:5px;"></span>
                        <div id="sciflow-notify-poster-progress" style="margin-top:15px; font-weight:bold;"></div>
                    </div>

                    <hr>
                    
                    <h2><?php esc_html_e('Notificar Pôsteres "Não Enviados"', 'sciflow-wp'); ?></h2>
                    <div id="sciflow-notify-unsubmitted-poster-form" style="max-width: 800px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-top:20px;">
                        <p><?php esc_html_e('Isso enviará o texto configurado acima para todos os autores de trabalhos aprovados que ainda NÃO enviaram o pôster inicial (status: Aprovado / Aguardando Pôster).', 'sciflow-wp'); ?></p>
                        <button type="button" id="sciflow-notify-unsubmitted-poster-btn" class="button button-secondary"><?php esc_html_e('Enviar E-mails Agora (Não Enviados)', 'sciflow-wp'); ?></button>
                        <span id="sciflow-notify-unsubmitted-poster-spinner" class="spinner" style="float:none; margin-top:5px;"></span>
                        <div id="sciflow-notify-unsubmitted-poster-progress" style="margin-top:15px; font-weight:bold;"></div>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>

            <script>
            jQuery(document).ready(function($) {
                // Tabs logic
                $('.sciflow-nav-tabs .nav-tab').on('click', function(e) {
                    e.preventDefault();
                    $('.sciflow-nav-tabs .nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    
                    $('.sciflow-tab-content').hide();
                    var target = $(this).attr('href');
                    $(target).show();
                    
                    // Update URL hash to maintain state
                    window.history.replaceState(null, null, target);
                });

                // Check if there's a hash in the URL on load
                if (window.location.hash) {
                    var hash = window.location.hash;
                    var $tab = $('.sciflow-nav-tabs .nav-tab[href="' + hash + '"]');
                    if ($tab.length) {
                        $tab.trigger('click');
                    }
                }

                // Notification logic
                function setupNotifyBtn(btnId, spinnerId, progressId, action, nonce, confirmText) {
                    $(btnId).on('click', function() {
                        if (!confirm(confirmText)) {
                            return;
                        }

                        const $btn = $(this);
                        const $spinner = $(spinnerId);
                        const $progress = $(progressId);

                        $btn.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $progress.html('<span style="color:#000;">Iniciando envio... por favor, aguarde e não feche esta página.</span>');

                        function sendBatch(offset) {
                            $.post(ajaxurl, {
                                action: action,
                                offset: offset,
                                nonce: nonce
                            }, function(response) {
                                if (response.success) {
                                    const data = response.data;
                                    $progress.html('<span style="color:#000;">Enviando: ' + data.sent_total + ' de ' + data.total + ' trabalhos...</span>');
                                    
                                    if (data.done) {
                                        $spinner.removeClass('is-active');
                                        $progress.html('<span style="color:green;">Envio concluído com sucesso! Total enviado: ' + data.sent_total + '</span>');
                                        $btn.prop('disabled', false);
                                    } else {
                                        sendBatch(data.new_offset);
                                    }
                                } else {
                                    $spinner.removeClass('is-active');
                                    $progress.html('<span style="color:red;">Erro: ' + (response.data || 'Ocorreu um erro no envio.') + '</span>');
                                    $btn.prop('disabled', false);
                                }
                            }).fail(function() {
                                $spinner.removeClass('is-active');
                                $progress.html('<span style="color:red;">Erro fatal: Falha na conexão com o servidor. Verifique os logs de erro.</span>');
                                $btn.prop('disabled', false);
                            });
                        }

                        sendBatch(0);
                    });
                }

                setupNotifyBtn(
                    '#sciflow-notify-forgotten-btn',
                    '#sciflow-notify-spinner',
                    '#sciflow-notify-progress',
                    'sciflow_notify_forgotten_ajax',
                    '<?php echo wp_create_nonce("sciflow_notify_forgotten_ajax"); ?>',
                    'Tem certeza que deseja notificar todos os autores com trabalhos com status Necessita Alterações?'
                );

                setupNotifyBtn(
                    '#sciflow-notify-forgotten-poster-btn',
                    '#sciflow-notify-poster-spinner',
                    '#sciflow-notify-poster-progress',
                    'sciflow_notify_forgotten_poster_ajax',
                    '<?php echo wp_create_nonce("sciflow_notify_forgotten_poster_ajax"); ?>',
                    'Tem certeza que deseja notificar todos os autores com pôsteres aguardando correções?'
                );

                setupNotifyBtn(
                    '#sciflow-notify-unsubmitted-poster-btn',
                    '#sciflow-notify-unsubmitted-poster-spinner',
                    '#sciflow-notify-unsubmitted-poster-progress',
                    'sciflow_notify_unsubmitted_poster_ajax',
                    '<?php echo wp_create_nonce("sciflow_notify_unsubmitted_poster_ajax"); ?>',
                    'Tem certeza que deseja notificar todos os autores que ainda NÃO enviaram seus pôsteres?'
                );

                $('#sciflow-check-deadlines-btn').on('click', function() {
                    if (!confirm('Tem certeza que deseja forçar a verificação de prazos? Isso irá reprovar automaticamente os artigos/pôsteres que estiverem fora do prazo configurado.')) {
                        return;
                    }
                    
                    const $btn = $(this);
                    const $spinner = $('#sciflow-check-deadlines-spinner');
                    const $progress = $('#sciflow-check-deadlines-progress');
                    
                    $btn.prop('disabled', true);
                    $spinner.addClass('is-active');
                    $progress.html('<span style="color:#000;">Verificando prazos...</span>');
                    
                    $.post(ajaxurl, {
                        action: 'sciflow_force_check_deadlines_ajax',
                        nonce: '<?php echo wp_create_nonce("sciflow_force_check_deadlines_ajax"); ?>'
                    }, function(response) {
                        $spinner.removeClass('is-active');
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $progress.html('<span style="color:green;">' + response.data + '</span>');
                        } else {
                            $progress.html('<span style="color:red;">Erro: ' + (response.data || 'Falha ao verificar os prazos.') + '</span>');
                        }
                    }).fail(function() {
                        $spinner.removeClass('is-active');
                        $btn.prop('disabled', false);
                        $progress.html('<span style="color:red;">Erro fatal: Falha na conexão com o servidor.</span>');
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Render ranking/selection admin page.
     */
    public function render_ranking_page()
    {
        if (!current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }
        $ranking = new SciFlow_Ranking();
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Ranking & Seleção', 'sciflow-wp'); ?>
            </h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sciflow_select_top">
                <?php wp_nonce_field('sciflow_select_top'); ?>

                <p>
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Selecionar Melhores Trabalhos e Notificar', 'sciflow-wp'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php esc_html_e('Seleciona os 6 melhores por evento e 3 melhores gerais, e notifica por e-mail.', 'sciflow-wp'); ?>
                </p>
            </form>

            <hr>

            <h2>Enfrute</h2>
            <?php $this->render_admin_ranking_table($ranking->get_event_ranking('enfrute')); ?>

            <h2>Semco</h2>
            <?php $this->render_admin_ranking_table($ranking->get_event_ranking('semco')); ?>

            <h2>
                <?php esc_html_e('Geral', 'sciflow-wp'); ?>
            </h2>
            <?php $this->render_admin_ranking_table($ranking->get_general_ranking()); ?>
        </div>
        <?php
    }

    /**
     * Admin ranking table.
     */
    private function render_admin_ranking_table($posts)
    {
        if (empty($posts)) {
            echo '<p>' . esc_html__('Nenhum trabalho ranqueado.', 'sciflow-wp') . '</p>';
            return;
        }

        $sm = new SciFlow_Status_Manager();
        echo '<table class="wp-list-table widefat striped"><thead><tr>';
        echo '<th>#</th><th>' . esc_html__('Título', 'sciflow-wp') . '</th>';
        echo '<th>' . esc_html__('Nota', 'sciflow-wp') . '</th>';
        echo '<th>' . esc_html__('Status', 'sciflow-wp') . '</th>';
        echo '<th>' . esc_html__('Selecionado', 'sciflow-wp') . '</th>';
        echo '<th>' . esc_html__('Confirmado', 'sciflow-wp') . '</th>';
        echo '<th>' . esc_html__('Pagamento', 'sciflow-wp') . '</th>';
        echo '</tr></thead><tbody>';

        $pos = 1;
        foreach ($posts as $post) {
            $score = get_post_meta($post->ID, '_sciflow_ranking_score', true);
            $status = get_post_meta($post->ID, '_sciflow_status', true);
            $selected = get_post_meta($post->ID, '_sciflow_selected_for_presentation', true);
            $confirmed = get_post_meta($post->ID, '_sciflow_presentation_confirmed', true);
            $payment = get_post_meta($post->ID, '_sciflow_payment_status', true);

            echo '<tr>';
            echo '<td>' . $pos . '</td>';
            echo '<td><strong>' . SciFlow_Status_Manager::render_title($post->post_title) . '</strong></td>';
            echo '<td>' . number_format($score, 2, ',', '') . '</td>';
            echo '<td>' . $sm->get_status_badge($status) . '</td>';
            echo '<td>' . ($selected ? '✅' : '—') . '</td>';
            echo '<td>' . ($confirmed ? '✅' : '—') . '</td>';
            echo '<td>' . ($payment === 'confirmed' ? '✅' : '⏳');

            if ($payment !== 'confirmed') {
                echo ' <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="sciflow_manual_confirm">';
                echo '<input type="hidden" name="post_id" value="' . esc_attr($post->ID) . '">';
                wp_nonce_field('sciflow_manual_confirm_' . $post->ID);
                echo '<button type="submit" class="button button-small">' . esc_html__('Confirmar', 'sciflow-wp') . '</button>';
                echo '</form>';
            }

            echo '</td>';
            echo '</tr>';
            $pos++;
        }

        echo '</tbody></table>';
    }

    /**
     * Render certificates admin page.
     */
    public function render_certificates_page()
    {
        if (!current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }
        $certificates = new SciFlow_Certificates();
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Certificados', 'sciflow-wp'); ?>
            </h1>
            <p>
                <?php esc_html_e('Trabalhos elegíveis para certificado (pagamento confirmado + trabalho apresentado/premiado).', 'sciflow-wp'); ?>
            </p>

            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>
                            <?php esc_html_e('Título', 'sciflow-wp'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Autor', 'sciflow-wp'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Evento', 'sciflow-wp'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Elegível', 'sciflow-wp'); ?>
                        </th>
                        <th>
                            <?php esc_html_e('Ação', 'sciflow-wp'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
                        $query = new WP_Query(array(
                            'post_type' => $pt,
                            'posts_per_page' => -1,
                            'post_status' => 'any',
                            'meta_query' => array(
                                array(
                                    'key' => '_sciflow_status',
                                    'value' => array('aprovado', 'poster_enviado', 'confirmado'),
                                    'compare' => 'IN',
                                ),
                            ),
                        ));

                        foreach ($query->posts as $post) {
                            $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
                            $author = get_userdata($author_id);
                            $event = get_post_meta($post->ID, '_sciflow_event', true);
                            $eligible = $certificates->is_eligible($post->ID);

                            echo '<tr>';
                            echo '<td>' . SciFlow_Status_Manager::render_title($post->post_title) . '</td>';
                            echo '<td>' . ($author ? esc_html($author->display_name) : '—') . '</td>';
                            echo '<td>' . esc_html(ucfirst($event)) . '</td>';
                            echo '<td>' . ($eligible ? '✅' : '❌') . '</td>';
                            echo '<td>';
                            if ($eligible) {
                                echo '<a href="' . esc_url(add_query_arg(array(
                                    'action' => 'sciflow_download_certificate',
                                    'post_id' => $post->ID,
                                    '_wpnonce' => wp_create_nonce('sciflow_cert_' . $post->ID),
                                ), admin_url('admin-post.php'))) . '" class="button button-small">';
                                echo esc_html__('Gerar Certificado', 'sciflow-wp');
                                echo '</a>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Add meta boxes to CPT edit screens.
     */
    public function add_meta_boxes()
    {
        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
            add_meta_box(
                'sciflow_status_meta',
                __('SciFlow — Status e Dados', 'sciflow-wp'),
                array($this, 'render_status_meta_box'),
                $pt,
                'side',
                'high'
            );
        }

        add_meta_box(
            'sciflow_palestra_meta',
            __('SciFlow — Dados da Palestra', 'sciflow-wp'),
            array($this, 'render_palestra_meta_box'),
            'sciflow_palestra',
            'normal',
            'high'
        );
    }

    /**
     * Render the status meta box.
     */
    public function render_status_meta_box($post)
    {
        $sm = new SciFlow_Status_Manager();
        $status = $sm->get_status($post->ID);
        $event = get_post_meta($post->ID, '_sciflow_event', true);
        $payment = get_post_meta($post->ID, '_sciflow_payment_status', true);
        $reviewer = get_post_meta($post->ID, '_sciflow_reviewer_id', true);
        $score = get_post_meta($post->ID, '_sciflow_ranking_score', true);
        $keywords = get_post_meta($post->ID, '_sciflow_keywords', true);

        $author_inst = get_post_meta($post->ID, '_sciflow_main_author_instituicao', true);
        $author_cpf = get_post_meta($post->ID, '_sciflow_main_author_cpf', true);
        $author_email = get_post_meta($post->ID, '_sciflow_main_author_email', true);
        $author_phone = get_post_meta($post->ID, '_sciflow_main_author_telefone', true);

        $presenting_author = get_post_meta($post->ID, '_sciflow_presenting_author', true);
        $presenting_author_name = esc_html__('Não informado', 'sciflow-wp');

        if ($presenting_author === 'main') {
            $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
            $user = get_userdata($author_id);
            $presenting_author_name = $user ? esc_html($user->display_name) . ' (Autor Principal)' : esc_html__('Autor Principal (Desconhecido)', 'sciflow-wp');
        } elseif (is_numeric($presenting_author)) {
            $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
            if (is_array($coauthors) && isset($coauthors[$presenting_author])) {
                $presenting_author_name = esc_html($coauthors[$presenting_author]['name']) . ' (Coautor)';
            }
        }

        $cultura = get_post_meta($post->ID, '_sciflow_cultura', true);
        $knowledge_area = get_post_meta($post->ID, '_sciflow_knowledge_area', true);

        echo '<p><strong>' . esc_html__('Status:', 'sciflow-wp') . '</strong> ' . $sm->get_status_badge($status) . '</p>';
        echo '<p><strong>' . esc_html__('Evento:', 'sciflow-wp') . '</strong> ' . esc_html(ucfirst($event)) . '</p>';
        echo '<p><strong>' . esc_html__('Pagamento:', 'sciflow-wp') . '</strong> ' . ($payment === 'confirmed' ? '✅ Confirmado' : '⏳ Pendente') . '</p>';

        echo '<p><strong>' . esc_html__('Autor Apresentador:', 'sciflow-wp') . '</strong> ' . esc_html($presenting_author_name) . '</p>';

        if (current_user_can('administrator')) {
            echo '<p><label><strong>' . esc_html__('Cultura:', 'sciflow-wp') . '</strong><br>';
            echo '<select name="sciflow_admin_cultura" style="width:100%;">';
            echo '<option value="">' . esc_html__('Selecione uma cultura...', 'sciflow-wp') . '</option>';
            
            echo '<optgroup label="' . esc_attr__('Frutas de clima temperado', 'sciflow-wp') . '">';
            $frutas = array('Figo', 'Frutas de caroço', 'Goiaba/Caqui', 'Maçã/Pera', 'Pequenas frutas', 'Frutas nativas', 'Uva', 'Outras (Frutas)');
            foreach ($frutas as $f) {
                echo '<option value="' . esc_attr($f) . '" ' . selected($cultura, $f, false) . '>' . esc_html($f) . '</option>';
            }
            echo '</optgroup>';
            
            echo '<optgroup label="' . esc_attr__('Olerícolas', 'sciflow-wp') . '">';
            $olericolas = array('Alho', 'Cebola', 'Tomate', 'Morango', 'Aipim/mandioca', 'Cenoura', 'Pimentão', 'Folhosas', 'Outras (Olerícolas)');
            foreach ($olericolas as $o) {
                echo '<option value="' . esc_attr($o) . '" ' . selected($cultura, $o, false) . '>' . esc_html($o) . '</option>';
            }
            echo '</optgroup>';
            echo '</select></label></p>';

            echo '<p><label><strong>' . esc_html__('Área de Conhecimento:', 'sciflow-wp') . '</strong><br>';
            echo '<select name="sciflow_admin_knowledge_area" style="width:100%;">';
            echo '<option value="">' . esc_html__('Selecione uma área...', 'sciflow-wp') . '</option>';
            $areas = array(
                'Biotecnologia/Genética e Melhoramento',
                'Botânica e Fisiologia',
                'Colheita e Pós-Colheita',
                'Fitossanidade',
                'Economia/Estatística',
                'Fitotecnia',
                'Irrigação',
                'Processamento (Química e Bioquímica)',
                'Propagação',
                'Sementes',
                'Solos e Nutrição de Plantas',
                'Outros'
            );
            foreach ($areas as $a) {
                echo '<option value="' . esc_attr($a) . '" ' . selected($knowledge_area, $a, false) . '>' . esc_html($a) . '</option>';
            }
            echo '</select></label></p>';
        } else {
            if ($cultura) {
                echo '<p><strong>' . esc_html__('Cultura:', 'sciflow-wp') . '</strong> ' . esc_html($cultura) . '</p>';
            }
            if ($knowledge_area) {
                echo '<p><strong>' . esc_html__('Área de Conhecimento:', 'sciflow-wp') . '</strong> ' . esc_html($knowledge_area) . '</p>';
            }
        }

        if ($reviewer) {
            $rev_user = get_userdata($reviewer);
            echo '<p><strong>' . esc_html__('Revisor:', 'sciflow-wp') . '</strong> ' . ($rev_user ? esc_html($rev_user->display_name) : '—') . '</p>';
        }

        if (current_user_can('administrator')) {
            $scores = get_post_meta($post->ID, '_sciflow_scores', true) ?: array();
            $criteria = array(
                'originalidade' => __('Originalidade', 'sciflow-wp'),
                'objetividade'  => __('Objetividade', 'sciflow-wp'),
                'organizacao'   => __('Organização', 'sciflow-wp'),
                'metodologia'   => __('Metodologia', 'sciflow-wp'),
                'aderencia'     => __('Aderência aos Objetivos', 'sciflow-wp'),
            );
            
            echo '<hr><p><strong>' . esc_html__('Editar Notas (Apenas Admin):', 'sciflow-wp') . '</strong></p>';
            wp_nonce_field('sciflow_save_grades', 'sciflow_grades_nonce');

            if ($reviewer) {
                echo '<div style="background:#fffcf0; border:1px solid #f2e3be; padding:10px; margin-bottom:15px; border-radius:4px;">';
                echo '<p><strong>' . esc_html__('Controle de Avaliação:', 'sciflow-wp') . '</strong></p>';
                echo '<p><label><input type="checkbox" name="sciflow_reopen_review" value="1"> ' . esc_html__('Reabrir avaliação para o revisor', 'sciflow-wp') . '</label></p>';
                echo '<p class="description" style="font-size:11px;color:#7a6229;margin-top:2px;line-height:1.4;">' . esc_html__('Ao salvar, o status voltará para "Em Avaliação", mantendo as notas/comentários atuais e liberando o formulário para edição.', 'sciflow-wp') . '</p>';
                echo '</div>';
            }
            
            foreach ($criteria as $key => $label) {
                $val = isset($scores[$key]) ? $scores[$key] : '';
                if (is_string($val)) {
                    $val = str_replace(',', '.', $val);
                }
                echo '<p><label><strong>' . esc_html($label) . ':</strong><br>';
                echo '<input type="number" step="0.1" min="0" max="10" name="sciflow_admin_scores[' . esc_attr($key) . ']" value="' . esc_attr($val) . '" style="width:100%; max-width:200px;">';
                echo '</label></p>';
            }
            if ($score) {
                echo '<p><strong>' . esc_html__('Nota Final Atual:', 'sciflow-wp') . '</strong> ' . number_format($score, 2, ',', '') . '</p>';
            }
        } elseif ($score) {
            echo '<p><strong>' . esc_html__('Nota:', 'sciflow-wp') . '</strong> ' . number_format($score, 2, ',', '') . '</p>';
        }

        if (current_user_can('administrator')) {
            echo '<hr><p><strong>' . esc_html__('Editar Palavras-chave (Apenas Admin):', 'sciflow-wp') . '</strong></p>';
            echo '<div style="display:flex; flex-direction:column; gap:5px;">';
            for ($i = 0; $i < 5; $i++) {
                $kw_val = isset($keywords[$i]) ? $keywords[$i] : '';
                echo '<input type="text" name="sciflow_admin_keywords[]" value="' . esc_attr($kw_val) . '" placeholder="Palavra-chave ' . ($i + 1) . '" style="width:100%;">';
            }
            echo '</div>';

            $acknowledgement = get_post_meta($post->ID, '_sciflow_acknowledgement', true);
            echo '<p><strong>' . esc_html__('Editar Agradecimentos (Apenas Admin):', 'sciflow-wp') . '</strong><br>';
            echo '<textarea name="sciflow_admin_acknowledgement" rows="3" style="width:100%;">' . esc_textarea($acknowledgement) . '</textarea></p>';
            
            // Coauthors Editing & Reordering
            $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
            if (!is_array($coauthors)) $coauthors = array();
            
            echo '<hr><p><strong>' . esc_html__('Coautores (Apenas Admin):', 'sciflow-wp') . '</strong></p>';
            echo '<div id="sciflow-admin-coauthors-container">';
            foreach ($coauthors as $index => $ca) {
                echo '<div class="sciflow-admin-coauthor-row" style="background:#f9f9f9; border:1px solid #ddd; padding:10px; margin-bottom:10px; display:flex; gap:10px; align-items:flex-start;">';
                echo '<div style="display:flex; flex-direction:column; gap:5px; flex:1;">';
                echo '<input type="text" name="sciflow_admin_coauthors[' . $index . '][name]" value="' . esc_attr($ca['name'] ?? '') . '" placeholder="Nome">';
                echo '<input type="email" name="sciflow_admin_coauthors[' . $index . '][email]" value="' . esc_attr($ca['email'] ?? '') . '" placeholder="E-mail">';
                echo '<input type="text" name="sciflow_admin_coauthors[' . $index . '][institution]" value="' . esc_attr($ca['institution'] ?? '') . '" placeholder="Instituição">';
                echo '<input type="text" name="sciflow_admin_coauthors[' . $index . '][telefone]" value="' . esc_attr($ca['telefone'] ?? '') . '" placeholder="Telefone">';
                echo '</div>';
                echo '<div style="display:flex; flex-direction:column; gap:5px;">';
                echo '<button type="button" class="button sciflow-coauthor-up" title="Subir">⬆️</button>';
                echo '<button type="button" class="button sciflow-coauthor-down" title="Descer">⬇️</button>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '<script>
                jQuery(document).ready(function($) {
                    $(".sciflow-coauthor-up").on("click", function(e) {
                        e.preventDefault();
                        var row = $(this).closest(".sciflow-admin-coauthor-row");
                        row.prev(".sciflow-admin-coauthor-row").before(row);
                        updateCoauthorIndexes();
                    });
                    $(".sciflow-coauthor-down").on("click", function(e) {
                        e.preventDefault();
                        var row = $(this).closest(".sciflow-admin-coauthor-row");
                        row.next(".sciflow-admin-coauthor-row").after(row);
                        updateCoauthorIndexes();
                    });
                    function updateCoauthorIndexes() {
                        $(".sciflow-admin-coauthor-row").each(function(index) {
                            $(this).find("input").each(function() {
                                var name = $(this).attr("name");
                                if (name) {
                                    name = name.replace(/sciflow_admin_coauthors\[\d+\]/, "sciflow_admin_coauthors[" + index + "]");
                                    $(this).attr("name", name);
                                }
                            });
                        });
                    }
                });
            </script>';

        } else {
            if ($keywords && is_array($keywords)) {
                echo '<p><strong>' . esc_html__('Palavras-chave:', 'sciflow-wp') . '</strong> ' . esc_html(implode(', ', $keywords)) . '</p>';
            }
        }

        echo '<hr><p><strong>' . esc_html__('Dados do Autor Principal:', 'sciflow-wp') . '</strong></p>';
        if ($author_inst)
            echo '<p><strong>' . esc_html__('Instituição:', 'sciflow-wp') . '</strong> ' . esc_html($author_inst) . '</p>';
        if ($author_cpf)
            echo '<p><strong>' . esc_html__('CPF:', 'sciflow-wp') . '</strong> ' . esc_html($author_cpf) . '</p>';
        if ($author_email)
            echo '<p><strong>' . esc_html__('E-mail:', 'sciflow-wp') . '</strong> ' . esc_html($author_email) . '</p>';
        if ($author_phone)
            echo '<p><strong>' . esc_html__('Telefone:', 'sciflow-wp') . '</strong> ' . esc_html($author_phone) . '</p>';

        if ($payment !== 'confirmed') {
            echo '<hr><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="sciflow_manual_confirm">';
            echo '<input type="hidden" name="post_id" value="' . esc_attr($post->ID) . '">';
            wp_nonce_field('sciflow_manual_confirm_' . $post->ID);
            echo '<button type="submit" class="button">' . esc_html__('Confirmar Pagamento Manualmente', 'sciflow-wp') . '</button>';
            echo '</form>';
        }
    }

    /**
     * Render the palestra meta box.
     */
    public function render_palestra_meta_box($post)
    {
        $event = get_post_meta($post->ID, '_sciflow_event', true);
        $duration = get_post_meta($post->ID, '_sciflow_duration', true);
        $file_id = get_post_meta($post->ID, '_sciflow_attachment_id', true);
        $file_url = $file_id ? wp_get_attachment_url($file_id) : '';
        $references = get_post_meta($post->ID, '_sciflow_references', true);

        echo '<div class="sciflow-admin-meta-box">';
        echo '<p><strong>' . esc_html__('Evento:', 'sciflow-wp') . '</strong> ' . esc_html(ucfirst($event)) . '</p>';
        echo '<p><strong>' . esc_html__('Duração:', 'sciflow-wp') . '</strong> ' . ($duration ? esc_html($duration) . ' min' : '—') . '</p>';
        
        echo '<hr><h3>' . esc_html__('Conteúdo da Palestra', 'sciflow-wp') . '</h3>';
        
        if ($file_url) {
            echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; display: inline-block; min-width: 300px;">';
            echo '<div style="display: flex; align-items: center; margin-bottom: 15px;">';
            echo '<span class="dashicons dashicons-media-document" style="font-size: 40px; width: 40px; height: 40px; margin-right: 15px; color: #2b6cb0;"></span>';
            echo '<div>';
            echo '<div style="font-weight: bold; font-size: 14px;">Arquivo Word Enviado</div>';
            echo '<div style="font-size: 11px; color: #666;">ID do Anexo: ' . $file_id . '</div>';
            echo '</div>';
            echo '</div>';
            echo '<a href="' . esc_url($file_url) . '" target="_blank" class="button button-primary button-large" style="width: 100%; text-align: center;">';
            echo '<span class="dashicons dashicons-download" style="margin-top: 4px;"></span> ';
            echo esc_html__('Baixar Documento', 'sciflow-wp');
            echo '</a>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Nenhum arquivo Word anexado.', 'sciflow-wp') . '</p></div>';
            if (!empty($post->post_content)) {
                echo '<h4>' . esc_html__('Resumo (Texto Legado):', 'sciflow-wp') . '</h4>';
                echo '<div style="background: #fff; border: 1px solid #ccc; padding: 10px;">' . wp_kses_post($post->post_content) . '</div>';
            }
        }

        if ($references) {
            echo '<hr><h3>' . esc_html__('Referências / Links', 'sciflow-wp') . '</h3>';
            echo '<div style="white-space: pre-wrap; font-family: monospace; background: #fafafa; padding: 10px; border: 1px solid #eee; font-size: 12px;">' . esc_html($references) . '</div>';
        }
        echo '</div>';
    }

    /**
     * Handle manual payment confirmation.
     */
    public function handle_manual_confirm()
    {
        $post_id = absint($_POST['post_id'] ?? 0);
        check_admin_referer('sciflow_manual_confirm_' . $post_id);

        $this->payment->manual_confirm($post_id);

        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }

    /**
     * Handle top selection.
     */
    public function handle_select_top()
    {
        check_admin_referer('sciflow_select_top');

        $ranking = new SciFlow_Ranking();
        $selected = $ranking->select_top_works();
        $ranking->notify_selected_authors($selected);

        wp_safe_redirect(add_query_arg('selected', count($selected), wp_get_referer() ?: admin_url('admin.php?page=sciflow-ranking')));
        exit;
    }

    /**
     * Handle notify forgotten articles via AJAX.
     */
    public function ajax_notify_forgotten()
    {
        check_ajax_referer('sciflow_notify_forgotten_ajax', 'nonce');

        if (!current_user_can('manage_sciflow')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = get_option('sciflow_settings', array());
        $text = $settings['forgotten_article_email_text'] ?? '';
        
        if (empty(trim(wp_strip_all_tags($text)))) {
            wp_send_json_error('Texto do e-mail não configurado. Por favor, salve o texto nas configurações antes de enviar.');
        }

        if (!class_exists('SciFlow_Email')) {
            require_once SCIFLOW_PATH . 'includes/email/class-sciflow-email.php';
        }
        
        // Find all articles with status 'em_correcao'
        $posts_to_process = array();
        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type' => $pt,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => '_sciflow_status',
                        'value' => 'em_correcao', // status for Necessita Alterações
                    ),
                ),
            ));

            foreach ($query->posts as $p) {
                $posts_to_process[] = $p->ID;
            }
        }

        $total = count($posts_to_process);
        $offset = absint($_POST['offset'] ?? 0);
        $batch_size = 10;
        
        $slice = array_slice($posts_to_process, $offset, $batch_size);
        
        $email = new SciFlow_Email();
        $sent_in_batch = 0;
        foreach ($slice as $post_id) {
            $email->send_forgotten_article_notification($post_id, $text);
            $sent_in_batch++;
        }

        $new_offset = $offset + $sent_in_batch;

        wp_send_json_success(array(
            'total' => $total,
            'sent_total' => $new_offset,
            'new_offset' => $new_offset,
            'done' => $new_offset >= $total
        ));
    }

    /**
     * AJAX: Handle notify forgotten poster via AJAX.
     */
    public function ajax_notify_forgotten_poster()
    {
        check_ajax_referer('sciflow_notify_forgotten_poster_ajax', 'nonce');

        if (!current_user_can('manage_sciflow')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = get_option('sciflow_settings', array());
        $text = $settings['forgotten_poster_email_text'] ?? '';
        
        if (empty(trim(wp_strip_all_tags($text)))) {
            wp_send_json_error('Texto do e-mail de pôster não configurado. Por favor, salve o texto nas configurações antes de enviar.');
        }

        if (!class_exists('SciFlow_Email')) {
            require_once SCIFLOW_PATH . 'includes/email/class-sciflow-email.php';
        }
        
        // Find all articles with status 'poster_em_correcao'
        $posts_to_process = array();
        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type' => $pt,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => '_sciflow_status',
                        'value' => 'poster_em_correcao', // status for Pôster Necessita Correção
                    ),
                ),
            ));

            foreach ($query->posts as $p) {
                $posts_to_process[] = $p->ID;
            }
        }

        $total = count($posts_to_process);
        $offset = absint($_POST['offset'] ?? 0);
        $batch_size = 10;
        
        $slice = array_slice($posts_to_process, $offset, $batch_size);
        
        $email = new SciFlow_Email();
        $sent_in_batch = 0;
        foreach ($slice as $post_id) {
            $email->send_forgotten_poster_notification($post_id, $text);
            $sent_in_batch++;
        }

        $new_offset = $offset + $sent_in_batch;

        wp_send_json_success(array(
            'total' => $total,
            'sent_total' => $new_offset,
            'new_offset' => $new_offset,
            'done' => $new_offset >= $total
        ));
    }

    /**
     * AJAX: Handle notify unsubmitted poster via AJAX.
     */
    public function ajax_notify_unsubmitted_poster()
    {
        check_ajax_referer('sciflow_notify_unsubmitted_poster_ajax', 'nonce');

        if (!current_user_can('manage_sciflow')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = get_option('sciflow_settings', array());
        $text = $settings['unsubmitted_poster_email_text'] ?? '';
        
        if (empty(trim(wp_strip_all_tags($text)))) {
            wp_send_json_error('Texto do e-mail para pôsteres não enviados não configurado. Por favor, salve o texto nas configurações antes de enviar.');
        }

        if (!class_exists('SciFlow_Email')) {
            require_once SCIFLOW_PATH . 'includes/email/class-sciflow-email.php';
        }
        
        // Find all articles with status 'aprovado'
        $posts_to_process = array();
        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type' => $pt,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => '_sciflow_status',
                        'value' => 'aprovado', // status for Aprovado / Aguardando Pôster
                    ),
                ),
            ));

            foreach ($query->posts as $p) {
                $posts_to_process[] = $p->ID;
            }
        }

        $total = count($posts_to_process);
        $offset = absint($_POST['offset'] ?? 0);
        $batch_size = 10;
        
        $slice = array_slice($posts_to_process, $offset, $batch_size);
        
        $email = new SciFlow_Email();
        $sent_in_batch = 0;
        foreach ($slice as $post_id) {
            $email->send_unsubmitted_poster_notification($post_id, $text);
            $sent_in_batch++;
        }

        $new_offset = $offset + $sent_in_batch;

        wp_send_json_success(array(
            'total' => $total,
            'sent_total' => $new_offset,
            'new_offset' => $new_offset,
            'done' => $new_offset >= $total
        ));
    }

    /**
     * AJAX: Force checking the deadlines manually.
     */
    public function ajax_force_check_deadlines()
    {
        check_ajax_referer('sciflow_force_check_deadlines_ajax', 'nonce');

        if (!current_user_can('manage_sciflow')) {
            wp_send_json_error('Unauthorized');
        }

        if (!class_exists('SciFlow_Status_Manager')) {
            require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-status-manager.php';
        }

        $sm = new SciFlow_Status_Manager();
        $sm->check_corrections_deadlines();
        $sm->check_poster_deadlines();

        wp_send_json_success('Prazos verificados com sucesso. Trabalhos vencidos (se houverem) foram reprovados.');
    }

    /**
     * Render tecnico epagri management page.
     */
    public function render_tecnicos_page()
    {
        include SCIFLOW_PATH . 'admin/templates/tecnicos-page.php';
    }

    /**
     * Render mass email page.
     */
    public function render_mass_email_page()
    {
        if (!current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }
        include SCIFLOW_PATH . 'admin/templates/mass-email-page.php';
    }

    /**
     * AJAX: Get recipient count for mass email.
     */
    public function ajax_get_recipient_count()
    {
        check_ajax_referer('sciflow_mass_email_nonce', 'nonce');
        
        if (!current_user_can('manage_sciflow')) {
            wp_send_json_error('Unauthorized');
        }

        $role = sanitize_text_field($_POST['role'] ?? '');
        $event = sanitize_text_field($_POST['event'] ?? '');

        require_once SCIFLOW_PATH . 'includes/email/class-sciflow-mass-email.php';
        $mass_email = new SciFlow_Mass_Email();
        $recipients = $mass_email->get_recipients($role, $event);

        wp_send_json_success(array('count' => count($recipients)));
    }

    /**
     * AJAX: Send mass email batch.
     */
    public function ajax_send_mass_email_batch()
    {
        check_ajax_referer('sciflow_mass_email_nonce', 'nonce');

        if (!current_user_can('manage_sciflow')) {
            wp_send_json_error('Unauthorized');
        }

        $role    = sanitize_text_field($_POST['role'] ?? '');
        $event   = sanitize_text_field($_POST['event'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        $offset  = absint($_POST['offset'] ?? 0);
        $batchsize = 20;

        require_once SCIFLOW_PATH . 'includes/email/class-sciflow-mass-email.php';
        $mass_email = new SciFlow_Mass_Email();
        $recipients = $mass_email->get_recipients($role, $event);
        
        $slice = array_slice($recipients, $offset, $batchsize);
        $sent_count = 0;

        foreach ($slice as $user_data) {
            $mass_email->send_individual_mail($user_data, $subject, $content);
            $sent_count++;
        }

        wp_send_json_success(array(
            'sent' => $sent_count,
            'new_offset' => $offset + $sent_count,
            'total' => count($recipients),
            'done' => ($offset + $sent_count) >= count($recipients)
        ));
    }

    /**
     * AJAX: Backfill sciflow_tecnico_epagri role for users with existing completed orders.
     */
    public function ajax_backfill_tecnico_roles()
    {
        check_ajax_referer('sciflow_backfill_tecnico_roles', 'nonce');

        if (!current_user_can('manage_sciflow_tecnicos')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = get_option('sciflow_settings', array());
        $raw = $settings['woo_tecnico_epagri_product_ids'] ?? '';
        $product_ids = array_filter(array_map('absint', explode(',', $raw)));

        if (empty($product_ids)) {
            wp_send_json_error('Nenhum ID de produto Técnico Epagri configurado nas settings.');
        }

        $orders = wc_get_orders(array(
            'status' => array('wc-completed', 'wc-processing'),
            'limit'  => -1,
        ));

        $assigned = 0;
        $skipped  = 0;

        foreach ($orders as $order) {
            $user_id = $order->get_user_id();
            if (!$user_id) {
                continue;
            }

            $found = false;
            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                $vid = $item->get_variation_id();

                if (in_array($pid, $product_ids, true) || ($vid && in_array($vid, $product_ids, true))) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                continue;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                continue;
            }

            if (!$user->has_cap('sciflow_tecnico_epagri')) {
                $user->add_role('sciflow_tecnico_epagri');
                $assigned++;
            } else {
                $skipped++;
            }
        }

        wp_send_json_success(array(
            'assigned' => $assigned,
            'skipped'  => $skipped,
        ));
    }

    /**
     * AJAX: Toggle a secondary role (reviewer / speaker) for a tecnico epagri user.
     */
    public function ajax_toggle_tecnico_role()
    {
        check_ajax_referer('sciflow_toggle_tecnico_role', 'nonce');

        if (!current_user_can('manage_sciflow_tecnicos')) {
            wp_send_json_error('Unauthorized');
        }

        $user_id  = absint($_POST['user_id'] ?? 0);
        $role     = sanitize_key($_POST['role'] ?? '');
        $action   = sanitize_key($_POST['toggle_action'] ?? ''); // 'add' or 'remove'

        $allowed = array(
            'sciflow_enfrute_revisor',
            'sciflow_semco_revisor',
            'sciflow_speaker',
        );

        if (!$user_id || !in_array($role, $allowed, true) || !in_array($action, array('add', 'remove'), true)) {
            wp_send_json_error('Dados inválidos.');
        }

        $user = get_userdata($user_id);
        if (!$user || !$user->has_cap('sciflow_tecnico_epagri')) {
            wp_send_json_error('Usuário não é um Técnico Epagri.');
        }

        if ($action === 'add') {
            $user->add_role($role);
        } else {
            $user->remove_role($role);
        }

        wp_send_json_success(array(
            'user_id' => $user_id,
            'role'    => $role,
            'action'  => $action,
        ));
    }

    /**
     * Render gestor tecnico permission management page.
     */
    public function render_gestor_permissions_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }
        include SCIFLOW_PATH . 'admin/templates/gestor-permissions-page.php';
    }

    /**
     * AJAX: Toggle a capability for the gestor tecnico role.
     */
    public function ajax_toggle_gestor_capability()
    {
        check_ajax_referer('sciflow_gestor_permissions_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $group_key = sanitize_text_field($_POST['group'] ?? '');
        $action    = sanitize_text_field($_POST['toggle_action'] ?? 'add');

        $role = get_role('sciflow_tecnico_admin');
        if (!$role) {
            wp_send_json_error('Role Gestor Técnico não encontrada.');
        }

        // Define capability groups
        $groups = array(
            'sciflow' => array(
                'manage_sciflow',
                'manage_sciflow_tecnicos',
                'assign_sciflow_reviewers',
                'sciflow_review'
            ),
            'woocommerce' => array(
                'manage_woocommerce',
                'view_woocommerce_reports',
                'edit_products',
                'edit_others_products',
                'publish_products',
                'read_private_products',
                'delete_products',
                'delete_private_products',
                'delete_published_products',
                'delete_others_products',
                'edit_product_terms',
                'assign_product_terms',
                'edit_shop_orders',
                'read_shop_order',
                'edit_others_shop_orders',
                'publish_shop_orders',
                'read_private_shop_orders',
                'delete_shop_orders',
                'delete_private_shop_orders',
                'delete_published_shop_orders',
                'delete_others_shop_orders',
                'edit_shop_order_terms',
                'assign_shop_order_terms',
                'edit_shop_coupons',
                'edit_others_shop_coupons',
                'publish_shop_coupons',
                'read_private_shop_coupons',
                'delete_shop_coupons',
                'delete_private_shop_coupons',
                'delete_published_shop_coupons',
                'delete_others_shop_coupons',
                'edit_shop_coupon_terms',
                'assign_shop_coupon_terms',
                'upload_files'
            ),
            'content' => array(
                'edit_posts',
                'edit_others_posts',
                'edit_published_posts',
                'publish_posts',
                'edit_pages',
                'edit_others_pages',
                'edit_published_pages',
                'publish_pages',
                'upload_files'
            ),
            'settings' => array(
                'manage_options',
                'edit_theme_options'
            ),
            'users' => array(
                'list_users',
                'edit_users',
                'promote_users'
            )
        );

        if (!isset($groups[$group_key])) {
            wp_send_json_error('Grupo de permissões inválido.');
        }

        foreach ($groups[$group_key] as $cap) {
            if ($action === 'add') {
                $role->add_cap($cap);
            } else {
                $role->remove_cap($cap);
            }
        }

        wp_send_json_success(array(
            'group'  => $group_key,
            'action' => $action
        ));
    }

    /**
     * AJAX: Remove sciflow_tecnico_admin role from a user.
     */
    public function ajax_remove_gestor_role()
    {
        check_ajax_referer('sciflow_gestor_permissions_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $user_id = absint($_POST['user_id'] ?? 0);
        $user = get_userdata($user_id);

        if (!$user) {
            wp_send_json_error('Usuário não encontrado.');
        }

        $user->remove_role('sciflow_tecnico_admin');

        wp_send_json_success('Cargo removido com sucesso.');
    }

    /**
     * Restrict the roles that a Gestor Técnico can assign.
     */
    public function filter_editable_roles_for_gestor($roles)
    {
        $user = wp_get_current_user();
        
        // Only apply if the user is a Gestor Técnico and NOT an Administrator.
        if (in_array('sciflow_tecnico_admin', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            $allowed_roles = array(
                'subscriber', 
                'contributor', 
                'author', 
                'editor',
                'sciflow_inscrito',
                'sciflow_speaker',
                'sciflow_revisor',
                'sciflow_editor',
                'sciflow_semco_editor',
                'sciflow_semco_revisor',
                'sciflow_enfrute_editor',
                'sciflow_enfrute_revisor',
                'sciflow_tecnico_epagri'
            );

            foreach ($roles as $role_key => $role_data) {
                if (!in_array($role_key, $allowed_roles)) {
                    unset($roles[$role_key]);
                }
            }
        }

        return $roles;
    }

    /**
     * Hide unwanted menu pages for Gestor Técnico role.
     */
    public function restrict_gestor_menus()
    {
        $user = wp_get_current_user();
        
        // Only apply if the user is a Gestor Técnico and NOT an Administrator.
        if (in_array('sciflow_tecnico_admin', (array) $user->roles) && !in_array('administrator', (array) $user->roles)) {
            remove_menu_page('edit.php'); // Posts
            remove_menu_page('edit.php?post_type=page'); // Pages
            remove_menu_page('edit-comments.php'); // Comments
            remove_menu_page('edit.php?post_type=enfrute_trabalhos');
            remove_menu_page('edit.php?post_type=semco_trabalhos');
            remove_menu_page('edit.php?post_type=sciflow_palestra');
            
            // Also hide Tools if they don't need it
            // remove_menu_page('tools.php');
        }
    }

    /**
     * Enqueue admin CSS.
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'sciflow') !== false) {
            wp_enqueue_style('sciflow-admin', SCIFLOW_URL . 'admin/css/admin.css', array(), SCIFLOW_VERSION);
        }
    }

    public function save_admin_grades($post_id, $post)
    {
        if (!isset($_POST['sciflow_grades_nonce']) || !wp_verify_nonce($_POST['sciflow_grades_nonce'], 'sciflow_save_grades')) {
            return;
        }

        if (!current_user_can('administrator')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!in_array($post->post_type, array('enfrute_trabalhos', 'semco_trabalhos'), true)) {
            return;
        }

        // Reopen review if requested (Only Admin)
        if (isset($_POST['sciflow_reopen_review']) && $_POST['sciflow_reopen_review'] === '1') {
            $reviewer_id = get_post_meta($post_id, '_sciflow_reviewer_id', true);
            if ($reviewer_id) {
                $old_status = get_post_meta($post_id, '_sciflow_status', true) ?: 'rascunho';
                
                // Revert status to em_avaliacao
                update_post_meta($post_id, '_sciflow_status', 'em_avaliacao');
                
                // Clear only editorial decision
                delete_post_meta($post_id, '_sciflow_decision');

                // Trigger action hook
                do_action('sciflow_status_changed', $post_id, 'em_avaliacao', $old_status);

                // Send email to reviewer informing them the review is open again
                $email = new SciFlow_Email();
                $email->send_returned_to_reviewer($post_id);
            }
            
            // Save Cultura and Area if provided, then return early
            if (isset($_POST['sciflow_admin_cultura'])) {
                update_post_meta($post_id, '_sciflow_cultura', sanitize_text_field($_POST['sciflow_admin_cultura']));
            }
            if (isset($_POST['sciflow_admin_knowledge_area'])) {
                update_post_meta($post_id, '_sciflow_knowledge_area', sanitize_text_field($_POST['sciflow_admin_knowledge_area']));
            }
            return;
        }

        // Save Cultura if provided
        if (isset($_POST['sciflow_admin_cultura'])) {
            update_post_meta($post_id, '_sciflow_cultura', sanitize_text_field($_POST['sciflow_admin_cultura']));
        }

        // Save Área de Conhecimento if provided
        if (isset($_POST['sciflow_admin_knowledge_area'])) {
            update_post_meta($post_id, '_sciflow_knowledge_area', sanitize_text_field($_POST['sciflow_admin_knowledge_area']));
        }

        if (isset($_POST['sciflow_admin_scores']) && is_array($_POST['sciflow_admin_scores'])) {
            $raw_scores = $_POST['sciflow_admin_scores'];
            $criteria = array('originalidade', 'objetividade', 'organizacao', 'metodologia', 'aderencia');
            $scores = array();

            foreach ($criteria as $key) {
                if (isset($raw_scores[$key]) && $raw_scores[$key] !== '') {
                    $val = str_replace(',', '.', $raw_scores[$key]);
                    $scores[$key] = floatval($val);
                }
            }

            if (!empty($scores)) {
                update_post_meta($post_id, '_sciflow_scores', $scores);

                // Recalculate average
                $settings = get_option('sciflow_settings', array());
                $weights = $settings['ranking_weights'] ?? array();
                $total_weight = 0;
                $weighted_sum = 0;

                foreach ($criteria as $key) {
                    $w_val = $weights[$key] ?? 1;
                    if (is_string($w_val)) $w_val = str_replace(',', '.', $w_val);
                    $weight = floatval($w_val);
                    if ($weight <= 0) $weight = 1;

                    $s_val = $scores[$key] ?? 0;
                    $weighted_sum += $s_val * $weight;
                    $total_weight += $weight;
                }

                $ranking_score = $total_weight > 0 ? round($weighted_sum / $total_weight, 2) : 0;
                update_post_meta($post_id, '_sciflow_ranking_score', $ranking_score);
            }
        }

        // Save Keywords
        if (isset($_POST['sciflow_admin_keywords']) && is_array($_POST['sciflow_admin_keywords'])) {
            $keywords = array_filter(array_map('sanitize_text_field', $_POST['sciflow_admin_keywords']));
            $unique_keywords = array_unique(array_map('mb_strtolower', $keywords));
            if (!empty($keywords) && count($unique_keywords) === count($keywords)) {
                update_post_meta($post_id, '_sciflow_keywords', array_values($keywords));
            }
        }

        // Save Acknowledgement
        if (isset($_POST['sciflow_admin_acknowledgement'])) {
            $ack = sanitize_textarea_field($_POST['sciflow_admin_acknowledgement']);
            if (mb_strlen($ack) > 250) {
                $ack = mb_substr($ack, 0, 250);
            }
            update_post_meta($post_id, '_sciflow_acknowledgement', $ack);
        }

        // Save Coauthors
        if (isset($_POST['sciflow_admin_coauthors']) && is_array($_POST['sciflow_admin_coauthors'])) {
            $coauthors = array();
            foreach ($_POST['sciflow_admin_coauthors'] as $ca) {
                $has_any_data = !empty($ca['name']) || !empty($ca['email']) || !empty($ca['institution']) || !empty($ca['telefone']);
                if ($has_any_data) {
                    $coauthors[] = array(
                        'name' => sanitize_text_field($ca['name'] ?? ''),
                        'email' => sanitize_email($ca['email'] ?? ''),
                        'institution' => sanitize_text_field($ca['institution'] ?? ''),
                        'telefone' => preg_replace('/[^0-9() -]/', '', $ca['telefone'] ?? ''),
                    );
                }
            }
            update_post_meta($post_id, '_sciflow_coauthors', $coauthors);
        }
    }
}



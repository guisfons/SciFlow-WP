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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Handle manual payment confirmation.
        add_action('admin_post_sciflow_manual_confirm', array($this, 'handle_manual_confirm'));

        // Handle ranking selection.
        add_action('admin_post_sciflow_select_top', array($this, 'handle_select_top'));
    }

    /**
     * Register admin menu.
     */
    public function add_menu_pages()
    {
        add_menu_page(
            __('SciFlow', 'sciflow-wp'),
            __('SciFlow', 'sciflow-wp'),
            'manage_sciflow',
            'sciflow-settings',
            array($this, 'render_settings_page'),
            'dashicons-welcome-learn-more',
            30
        );

        add_submenu_page(
            'sciflow-settings',
            __('Configurações', 'sciflow-wp'),
            __('Configurações', 'sciflow-wp'),
            'manage_sciflow',
            'sciflow-settings',
            array($this, 'render_settings_page')
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
        $clean['senco_editor_email'] = $this->sanitize_multiple_emails($input['senco_editor_email'] ?? '');

        // Dashboard URL.
        $clean['dashboard_url'] = esc_url_raw($input['dashboard_url'] ?? '');

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
            $clean['ranking_weights'][$key] = floatval($input['ranking_weights'][$key] ?? 1);
        }

        // WooCommerce product IDs.
        $clean['woo_product_ids'] = sanitize_text_field($input['woo_product_ids'] ?? '');

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
     * Render settings page.
     */
    public function render_settings_page()
    {
        $settings = get_option('sciflow_settings', array());
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('SciFlow — Configurações', 'sciflow-wp'); ?>
            </h1>

            <form method="post" action="options.php">
                <?php settings_fields('sciflow_settings_group'); ?>

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
                            <?php esc_html_e('E-mail do Editor Senco', 'sciflow-wp'); ?>
                        </th>
                        <td>
                            <input type="text" name="sciflow_settings[senco_editor_email]"
                                value="<?php echo esc_attr($settings['senco_editor_email'] ?? ''); ?>" class="regular-text">
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
                </table>

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
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render ranking/selection admin page.
     */
    public function render_ranking_page()
    {
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

            <h2>Senco</h2>
            <?php $this->render_admin_ranking_table($ranking->get_event_ranking('senco')); ?>

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
            echo '<td><strong>' . esc_html($post->post_title) . '</strong></td>';
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
                    foreach (array('enfrute_trabalhos', 'senco_trabalhos') as $pt) {
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
                            echo '<td>' . esc_html($post->post_title) . '</td>';
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
        foreach (array('enfrute_trabalhos', 'senco_trabalhos') as $pt) {
            add_meta_box(
                'sciflow_status_meta',
                __('SciFlow — Status e Dados', 'sciflow-wp'),
                array($this, 'render_status_meta_box'),
                $pt,
                'side',
                'high'
            );
        }
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

        if ($cultura) {
            echo '<p><strong>' . esc_html__('Cultura:', 'sciflow-wp') . '</strong> ' . esc_html($cultura) . '</p>';
        }
        if ($knowledge_area) {
            echo '<p><strong>' . esc_html__('Área de Conhecimento:', 'sciflow-wp') . '</strong> ' . esc_html($knowledge_area) . '</p>';
        }

        if ($reviewer) {
            $rev_user = get_userdata($reviewer);
            echo '<p><strong>' . esc_html__('Revisor:', 'sciflow-wp') . '</strong> ' . ($rev_user ? esc_html($rev_user->display_name) : '—') . '</p>';
        }

        if ($score) {
            echo '<p><strong>' . esc_html__('Nota:', 'sciflow-wp') . '</strong> ' . number_format($score, 2, ',', '') . '</p>';
        }

        if ($keywords && is_array($keywords)) {
            echo '<p><strong>' . esc_html__('Palavras-chave:', 'sciflow-wp') . '</strong> ' . esc_html(implode(', ', $keywords)) . '</p>';
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
     * Enqueue admin CSS.
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'sciflow') !== false) {
            wp_enqueue_style('sciflow-admin', SCIFLOW_URL . 'admin/css/admin.css', array(), SCIFLOW_VERSION);
        }
    }
}

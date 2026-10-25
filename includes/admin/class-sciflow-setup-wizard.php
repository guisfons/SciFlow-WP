<?php
/**
 * First-run setup wizard.
 *
 * Opens automatically after the first activation of the plugin (or of the
 * SciFlow theme while the plugin is active) and walks the administrator
 * through every setting the system needs. Each step is validated on the
 * server; the wizard can be reopened at any time from SciFlow → Assistente.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Setup_Wizard
{
    const PAGE = 'sciflow-setup';
    const REDIRECT_OPTION = 'sciflow_setup_redirect';
    const STATUS_OPTION = 'sciflow_setup_status'; // '' | 'pending' | 'completed' | 'skipped'

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_menu'), 15);
        add_action('admin_init', array($this, 'maybe_redirect'), 1);
        add_action('admin_post_sciflow_setup_save', array($this, 'handle_save'));
        add_action('admin_post_sciflow_setup_skip', array($this, 'handle_skip'));
    }

    /**
     * Steps: slug => label.
     */
    public static function steps()
    {
        return array(
            'evento'       => __('Evento', 'sciflow-wp'),
            'identidade'   => __('Identidade visual', 'sciflow-wp'),
            'prazos'       => __('Prazos', 'sciflow-wp'),
            'inscricao'    => __('Inscrição', 'sciflow-wp'),
            'paginas'      => __('Páginas', 'sciflow-wp'),
            'emails'       => __('E-mails e LGPD', 'sciflow-wp'),
            'certificados' => __('Certificados e Anais', 'sciflow-wp'),
            'revisao'      => __('Revisão', 'sciflow-wp'),
        );
    }

    /**
     * Ask for the wizard to open on the next admin page load.
     */
    public static function request_redirect()
    {
        if ('completed' === get_option(self::STATUS_OPTION, '')) {
            return;
        }
        update_option(self::STATUS_OPTION, 'pending', false);
        update_option(self::REDIRECT_OPTION, get_current_user_id() ?: 1, false);
    }

    public static function url($step = '')
    {
        $args = array('page' => self::PAGE);
        if ($step) {
            $args['step'] = $step;
        }
        return add_query_arg($args, admin_url('admin.php'));
    }

    public function add_menu()
    {
        add_submenu_page(
            'sciflow-settings',
            __('Assistente de configuração', 'sciflow-wp'),
            __('Assistente de configuração', 'sciflow-wp'),
            'manage_options',
            self::PAGE,
            array($this, 'render')
        );
    }

    /**
     * Redirect once, right after activation, for the user who activated.
     */
    public function maybe_redirect()
    {
        $flag = (int) get_option(self::REDIRECT_OPTION, 0);
        if (!$flag || wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        // Bulk plugin activation and network admin are left alone.
        if (isset($_GET['activate-multi']) || is_network_admin()) { // phpcs:ignore WordPress.Security.NonceVerification
            delete_option(self::REDIRECT_OPTION);
            return;
        }
        delete_option(self::REDIRECT_OPTION);
        if (isset($_GET['page']) && self::PAGE === $_GET['page']) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }
        wp_safe_redirect(self::url());
        exit;
    }

    public function handle_skip()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'sciflow-wp'), 403);
        }
        check_admin_referer('sciflow_setup_skip');
        update_option(self::STATUS_OPTION, 'skipped', false);
        wp_safe_redirect(admin_url('admin.php?page=sciflow-diagnostics'));
        exit;
    }

    /**
     * Save one step (server-side validation; errors keep the user on the step).
     */
    public function handle_save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'sciflow-wp'), 403);
        }
        check_admin_referer('sciflow_setup_save');

        $steps = array_keys(self::steps());
        $step = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
        if (!in_array($step, $steps, true)) {
            wp_safe_redirect(self::url());
            exit;
        }

        $input = isset($_POST['sciflow_settings']) && is_array($_POST['sciflow_settings']) ? wp_unslash($_POST['sciflow_settings']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by SciFlow_Settings::sanitize()
        $errors = $this->validate($step, $input);

        if (!$errors) {
            if ('paginas' === $step) {
                $errors = $this->save_pages($input);
            } elseif ('revisao' === $step) {
                update_option(self::STATUS_OPTION, 'completed', false);
                update_option('sciflow_show_onboarding', '0', false);
                wp_safe_redirect(add_query_arg('sciflow_setup_done', 1, admin_url('admin.php?page=sciflow-diagnostics')));
                exit;
            } else {
                update_option(SciFlow_Settings::OPTION, SciFlow_Settings::sanitize(wp_slash($input)));
                SciFlow_Settings::flush();
                // sanitize() may reject values (e.g. invalid dates) after validation.
                foreach (get_settings_errors(SciFlow_Settings::OPTION) as $e) {
                    $errors[] = $e['message'];
                }
            }
        }

        if ($errors) {
            set_transient('sciflow_setup_errors_' . get_current_user_id(), array('step' => $step, 'errors' => $errors, 'input' => $input), 10 * MINUTE_IN_SECONDS);
            wp_safe_redirect(self::url($step));
            exit;
        }

        $index = array_search($step, $steps, true);
        $next = $steps[min(count($steps) - 1, $index + 1)];
        if (!empty($_POST['go_back']) && $index > 0) {
            $next = $steps[$index - 1];
        }
        wp_safe_redirect(self::url($next));
        exit;
    }

    /**
     * Server-side validation per step.
     *
     * @return string[] Error messages.
     */
    private function validate($step, array $input)
    {
        $e = array();
        switch ($step) {
            case 'evento':
                $any = false;
                foreach (array_keys(SciFlow_Events::SLOTS) as $slug) {
                    $enabled = !empty($input['event_' . $slug . '_enabled']);
                    $any = $any || $enabled;
                    $label = trim((string) ($input['event_' . $slug . '_label'] ?? ''));
                    if ($enabled && '' === $label) {
                        $e[] = __('Informe o nome curto de cada evento habilitado.', 'sciflow-wp');
                    }
                    if ($enabled && in_array($label, array('Evento A', 'Evento B'), true)) {
                        $e[] = sprintf(__('Substitua o nome genérico "%s" pelo nome real do evento.', 'sciflow-wp'), $label);
                    }
                    if ($enabled && '' === trim((string) ($input['event_' . $slug . '_full_label'] ?? ''))) {
                        $e[] = __('Informe o nome completo de cada evento habilitado (usado em e-mails e certificados).', 'sciflow-wp');
                    }
                }
                if (!$any) {
                    $e[] = __('Habilite pelo menos um evento.', 'sciflow-wp');
                }
                if ('' === trim((string) ($input['organizer_name'] ?? ''))) {
                    $e[] = __('Informe a organização responsável.', 'sciflow-wp');
                }
                break;

            case 'identidade':
                $text = sanitize_hex_color((string) ($input['brand_text'] ?? '')) ?: '#1d2327';
                $bg = sanitize_hex_color((string) ($input['brand_background'] ?? '')) ?: '#ffffff';
                foreach (array('brand_primary', 'brand_secondary', 'brand_text', 'brand_background', 'brand_primary_dark') as $k) {
                    $raw = trim((string) ($input[$k] ?? ''));
                    if ('' !== $raw && !sanitize_hex_color($raw)) {
                        $e[] = sprintf(__('Cor inválida: %s (use #RRGGBB).', 'sciflow-wp'), $raw);
                    }
                }
                if (SciFlow_Branding::ratio($text, $bg) < 4.5) {
                    $e[] = __('A cor do texto tem pouco contraste com o fundo (mínimo 4,5:1). Escolha um texto mais escuro ou um fundo mais claro.', 'sciflow-wp');
                }
                break;

            case 'prazos':
                $dates = array();
                foreach (array('registration_deadline', 'article_submission_deadline', 'corrections_deadline', 'poster_submission_deadline') as $k) {
                    $v = trim((string) ($input[$k] ?? ''));
                    if ('' !== $v && false === strtotime($v)) {
                        $e[] = sprintf(__('Data inválida: %s.', 'sciflow-wp'), $k);
                    }
                    $dates[$k] = '' !== $v ? strtotime($v) : null;
                }
                if ('' === trim((string) ($input['article_submission_deadline'] ?? ''))) {
                    $e[] = __('Informe o prazo de submissão de resumos.', 'sciflow-wp');
                }
                if ($dates['article_submission_deadline'] && $dates['corrections_deadline'] && $dates['corrections_deadline'] < $dates['article_submission_deadline']) {
                    $e[] = __('O prazo de correções deve ser posterior ao prazo de submissão.', 'sciflow-wp');
                }
                if ($dates['corrections_deadline'] && $dates['poster_submission_deadline'] && $dates['poster_submission_deadline'] < $dates['corrections_deadline']) {
                    $e[] = __('O prazo de pôsteres deve ser posterior ao prazo de correções.', 'sciflow-wp');
                }
                break;

            case 'inscricao':
                $require = !empty($input['require_paid_registration']);
                $ids = array_filter(array_map('absint', explode(',', (string) ($input['woo_product_ids'] ?? ''))));
                if ($require && !class_exists('WooCommerce')) {
                    $e[] = __('Para exigir inscrição paga, instale e ative o WooCommerce, ou desmarque a opção.', 'sciflow-wp');
                }
                if ($require && class_exists('WooCommerce') && !$ids) {
                    $e[] = __('Selecione o produto de inscrição, ou desmarque "Exigir inscrição paga".', 'sciflow-wp');
                }
                foreach (array('woo_product_ids', 'woo_speaker_product_ids', 'woo_tecnico_epagri_product_ids') as $k) {
                    foreach (array_filter(array_map('absint', explode(',', (string) ($input[$k] ?? '')))) as $id) {
                        if (!function_exists('wc_get_product') || !wc_get_product($id)) {
                            $e[] = sprintf(__('Produto #%d não encontrado.', 'sciflow-wp'), $id);
                        }
                    }
                }
                break;

            case 'emails':
                $from = trim((string) ($input['email_from_address'] ?? ''));
                if ('' === $from || !is_email($from)) {
                    $e[] = __('Informe um e-mail de remetente válido.', 'sciflow-wp');
                }
                if ('' === trim((string) ($input['email_from_name'] ?? ''))) {
                    $e[] = __('Informe o nome do remetente.', 'sciflow-wp');
                }
                foreach (array('enfrute_editor_email', 'semco_editor_email') as $k) {
                    foreach (array_filter(array_map('trim', explode(',', (string) ($input[$k] ?? '')))) as $mail) {
                        if (!is_email($mail)) {
                            $e[] = sprintf(__('E-mail inválido: %s', 'sciflow-wp'), $mail);
                        }
                    }
                }
                if ('' === trim(wp_strip_all_tags((string) ($input['lgpd_consent_text'] ?? '')))) {
                    $e[] = __('Informe o texto de consentimento LGPD exibido nos formulários.', 'sciflow-wp');
                }
                break;

            case 'certificados':
                if ('' === trim((string) ($input['anais_title'] ?? ''))) {
                    $e[] = __('Informe o título dos Anais.', 'sciflow-wp');
                }
                break;
        }
        return array_values(array_unique($e));
    }

    /**
     * Pages step: apply selections, then create what was requested.
     *
     * @return string[] errors
     */
    private function save_pages(array $input)
    {
        $selected = isset($input['page_ids']) && is_array($input['page_ids']) ? $input['page_ids'] : array();
        $create = isset($input['create_pages']) && is_array($input['create_pages']) ? array_map('sanitize_key', $input['create_pages']) : array();

        $clean = SciFlow_Settings::sanitize(wp_slash(array('page_ids' => $selected)));
        update_option(SciFlow_Settings::OPTION, $clean);
        SciFlow_Settings::flush();
        SciFlow_Pages::flush();

        // Never create a page for a role the user mapped to an existing page.
        $create = array_values(array_filter($create, function ($role) use ($selected) {
            return empty($selected[$role]);
        }));
        if ($create) {
            SciFlow_Pages::create_missing($create);
        }

        $errors = array();
        foreach (SciFlow_Pages::definitions() as $role => $def) {
            $creatable = 'plugin' === $def['provider'] || '' !== $def['shortcode'] || current_theme_supports('sciflow');
            if ($def['required'] && $creatable && !SciFlow_Pages::exists($role)) {
                $errors[] = sprintf(__('A página obrigatória "%s" não foi definida nem criada.', 'sciflow-wp'), $def['title']);
            }
        }
        return $errors;
    }

    /**
     * Wizard UI.
     */
    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão insuficiente.', 'sciflow-wp'), 403);
        }

        $steps = self::steps();
        $keys = array_keys($steps);
        $step = isset($_GET['step']) ? sanitize_key(wp_unslash($_GET['step'])) : $keys[0]; // phpcs:ignore WordPress.Security.NonceVerification
        if (!isset($steps[$step])) {
            $step = $keys[0];
        }
        $index = array_search($step, $keys, true);

        $flash = get_transient('sciflow_setup_errors_' . get_current_user_id());
        $errors = array();
        $old = array();
        if (is_array($flash) && $flash['step'] === $step) {
            $errors = $flash['errors'];
            $old = $flash['input'];
            delete_transient('sciflow_setup_errors_' . get_current_user_id());
        }
        $s = array_merge(SciFlow_Settings::all(), $old);
        ?>
        <div class="wrap sf-wizard">
            <style>
                .sf-wizard { max-width: 860px; }
                .sf-steps { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; margin: 18px 0 22px; padding: 0; counter-reset: sfstep; }
                .sf-steps li { counter-increment: sfstep; flex: 1 1 100px; padding: 8px 10px; border-radius: 6px; background: #f0f0f1; color: #50575e; font-size: 12px; }
                .sf-steps li::before { content: counter(sfstep) ". "; font-weight: 600; }
                .sf-steps li.is-done { background: #e6f2ea; color: #1e5631; }
                .sf-steps li.is-current { background: #1f3b2c; color: #fff; font-weight: 600; }
                .sf-steps a { color: inherit; text-decoration: none; }
                .sf-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 22px 26px; }
                .sf-card h2 { margin-top: 0; }
                .sf-card .lead { color: #50575e; margin-top: -6px; }
                .sf-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 22px; }
                .sf-actions .sf-skip { margin-left: auto; }
                .sf-req::after { content: " *"; color: #d63638; }
                .sf-event { border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 16px; margin-bottom: 14px; }
                .sf-page-row td { vertical-align: middle; }
                .sf-ok { color: #008a20; } .sf-bad { color: #d63638; } .sf-info { color: #2271b1; }
            </style>
            <h1><?php esc_html_e('Configuração do SciFlow', 'sciflow-wp'); ?></h1>
            <p><?php esc_html_e('Preencha os passos abaixo para deixar o sistema pronto para receber inscrições e trabalhos. Tudo pode ser alterado depois em SciFlow → Configurações.', 'sciflow-wp'); ?></p>

            <ol class="sf-steps">
                <?php foreach ($keys as $i => $key) : ?>
                    <li class="<?php echo esc_attr($i < $index ? 'is-done' : ($i === $index ? 'is-current' : '')); ?>">
                        <?php if ($i <= $index) : ?>
                            <a href="<?php echo esc_url(self::url($key)); ?>"><?php echo esc_html($steps[$key]); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($steps[$key]); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($errors) : ?>
                <div class="notice notice-error"><p><strong><?php esc_html_e('Corrija os itens abaixo para continuar:', 'sciflow-wp'); ?></strong></p>
                    <ul style="list-style:disc;padding-left:20px;"><?php foreach ($errors as $err) : ?><li><?php echo esc_html($err); ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sf-card" novalidate>
                <input type="hidden" name="action" value="sciflow_setup_save">
                <input type="hidden" name="step" value="<?php echo esc_attr($step); ?>">
                <?php wp_nonce_field('sciflow_setup_save'); ?>
                <?php $this->{'step_' . $step}($s); ?>
                <div class="sf-actions">
                    <?php if ($index > 0) : ?>
                        <button type="submit" name="go_back" value="1" class="button" formnovalidate><?php esc_html_e('Voltar', 'sciflow-wp'); ?></button>
                    <?php endif; ?>
                    <button type="submit" class="button button-primary button-large">
                        <?php echo 'revisao' === $step ? esc_html__('Concluir configuração', 'sciflow-wp') : esc_html__('Salvar e continuar', 'sciflow-wp'); ?>
                    </button>
                    <a class="sf-skip" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=sciflow_setup_skip'), 'sciflow_setup_skip')); ?>"><?php esc_html_e('Configurar depois', 'sciflow-wp'); ?></a>
                </div>
            </form>
        </div>
        <?php
    }

    private function field_text($s, $key, $label, $args = array())
    {
        $args = array_merge(array('type' => 'text', 'required' => false, 'help' => '', 'class' => 'regular-text', 'placeholder' => ''), $args);
        $id = 'sf-' . str_replace('_', '-', $key);
        ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr($id); ?>" class="<?php echo $args['required'] ? 'sf-req' : ''; ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input type="<?php echo esc_attr($args['type']); ?>" id="<?php echo esc_attr($id); ?>" name="sciflow_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) ($s[$key] ?? '')); ?>" class="<?php echo esc_attr($args['class']); ?>" placeholder="<?php echo esc_attr($args['placeholder']); ?>" <?php echo $args['required'] ? 'required' : ''; ?>>
                <?php if ($args['help']) : ?><p class="description"><?php echo esc_html($args['help']); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function step_evento($s)
    {
        ?>
        <h2><?php esc_html_e('Evento e organização', 'sciflow-wp'); ?></h2>
        <p class="lead"><?php esc_html_e('O SciFlow atende até dois eventos simultâneos (por exemplo, um encontro e um seminário realizados juntos).', 'sciflow-wp'); ?></p>
        <input type="hidden" name="sciflow_settings[_sections][]" value="eventos">
        <table class="form-table" role="presentation">
            <?php $this->field_text($s, 'organizer_name', __('Organização responsável', 'sciflow-wp'), array('required' => true, 'class' => 'large-text', 'help' => __('Aparece como remetente padrão dos e-mails e nos Anais.', 'sciflow-wp'))); ?>
        </table>
        <?php foreach (array('enfrute' => __('Evento principal', 'sciflow-wp'), 'semco' => __('Segundo evento', 'sciflow-wp')) as $slug => $title) : ?>
            <div class="sf-event">
                <label><input type="checkbox" name="sciflow_settings[event_<?php echo esc_attr($slug); ?>_enabled]" value="1" <?php checked('1', (string) $s['event_' . $slug . '_enabled']); ?>> <strong><?php echo esc_html($title); ?></strong> — <?php esc_html_e('aceitar submissões', 'sciflow-wp'); ?></label>
                <table class="form-table" role="presentation">
                    <?php
                    $this->field_text($s, 'event_' . $slug . '_label', __('Nome curto', 'sciflow-wp'), array('required' => true, 'placeholder' => 'Ex.: Enfrute', 'help' => __('Usado em menus, filtros e no painel do editor.', 'sciflow-wp')));
                    $this->field_text($s, 'event_' . $slug . '_full_label', __('Nome completo', 'sciflow-wp'), array('required' => true, 'class' => 'large-text', 'placeholder' => 'Ex.: Encontro Nacional sobre Fruticultura de Clima Temperado'));
                    $this->field_text($s, 'event_' . $slug . '_edition', __('Edição', 'sciflow-wp'), array('class' => 'small-text', 'placeholder' => 'XIX', 'help' => __('Opcional. Usada nos Anais (ex.: "XIX Enfrute").', 'sciflow-wp')));
                    ?>
                </table>
            </div>
        <?php endforeach; ?>
        <table class="form-table" role="presentation">
            <?php $this->field_text($s, 'tecnico_label', __('Nome do papel "Técnico"', 'sciflow-wp'), array('help' => __('Inscrição especial de técnicos de instituição parceira. Pode manter o padrão.', 'sciflow-wp'))); ?>
        </table>
        <?php
    }

    private function step_identidade($s)
    {
        ?>
        <h2><?php esc_html_e('Identidade visual', 'sciflow-wp'); ?></h2>
        <p class="lead"><?php esc_html_e('Logo, cores e fontes do evento. Aplicados ao site, aos formulários, aos e-mails, aos certificados e aos Anais.', 'sciflow-wp'); ?></p>
        <?php
        SciFlow_Branding_Admin::fields($s);
    }

    private function step_prazos($s)
    {
        $to_local = function ($v) {
            return $v ? gmdate('Y-m-d\TH:i', strtotime($v)) : '';
        };
        foreach (array('registration_deadline', 'article_submission_deadline', 'corrections_deadline', 'poster_submission_deadline') as $k) {
            $s[$k] = $to_local($s[$k] ?? '');
        }
        ?>
        <h2><?php esc_html_e('Prazos', 'sciflow-wp'); ?></h2>
        <p class="lead"><?php echo esc_html(sprintf(__('Horário de Brasília? Os prazos usam o fuso horário do site (%s), definido em Configurações → Geral.', 'sciflow-wp'), wp_timezone_string())); ?></p>
        <table class="form-table" role="presentation">
            <?php
            $this->field_text($s, 'article_submission_deadline', __('Fim das submissões de resumos', 'sciflow-wp'), array('type' => 'datetime-local', 'required' => true, 'class' => '', 'help' => __('Após esta data, novos resumos não são aceitos.', 'sciflow-wp')));
            $this->field_text($s, 'corrections_deadline', __('Fim do envio de correções', 'sciflow-wp'), array('type' => 'datetime-local', 'class' => '', 'help' => __('Trabalhos ainda em correção depois desta data são reprovados automaticamente.', 'sciflow-wp')));
            $this->field_text($s, 'poster_submission_deadline', __('Fim do envio de pôsteres', 'sciflow-wp'), array('type' => 'datetime-local', 'class' => '', 'help' => __('Pôsteres não enviados até esta data são reprovados automaticamente.', 'sciflow-wp')));
            $this->field_text($s, 'registration_deadline', __('Fim das inscrições on-line', 'sciflow-wp'), array('type' => 'datetime-local', 'class' => '', 'help' => __('Bloqueia a compra do produto de inscrição (requer WooCommerce).', 'sciflow-wp')));
            ?>
        </table>
        <?php
    }

    private function step_inscricao($s)
    {
        $woo = class_exists('WooCommerce');
        $products = array();
        if ($woo && function_exists('wc_get_products')) {
            $products = wc_get_products(array('status' => 'publish', 'limit' => 200, 'orderby' => 'title', 'order' => 'ASC'));
        }
        $product_select = function ($key, $label, $help) use ($s, $products) {
            $current = array_filter(array_map('absint', explode(',', (string) ($s[$key] ?? ''))));
            ?>
            <tr>
                <th scope="row"><label for="sf-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                <td>
                    <select id="sf-<?php echo esc_attr($key); ?>" multiple size="5" style="min-width:320px;" data-target="sf-hidden-<?php echo esc_attr($key); ?>" class="sf-multi">
                        <?php foreach ($products as $p) :
                            $options = array($p);
                            if ($p->is_type('variable')) {
                                foreach ($p->get_children() as $child_id) {
                                    $child = wc_get_product($child_id);
                                    if ($child) {
                                        $options[] = $child;
                                    }
                                }
                            }
                            foreach ($options as $opt) : ?>
                                <option value="<?php echo esc_attr($opt->get_id()); ?>" <?php selected(in_array($opt->get_id(), $current, true)); ?>>
                                    <?php echo esc_html(($opt->is_type('variation') ? '— ' : '') . wp_strip_all_tags($opt->get_formatted_name())); ?>
                                </option>
                            <?php endforeach;
                        endforeach; ?>
                    </select>
                    <input type="hidden" id="sf-hidden-<?php echo esc_attr($key); ?>" name="sciflow_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(implode(',', $current)); ?>">
                    <p class="description"><?php echo esc_html($help); ?> <?php esc_html_e('Ctrl/⌘ + clique para selecionar vários.', 'sciflow-wp'); ?></p>
                </td>
            </tr>
            <?php
        };
        ?>
        <h2><?php esc_html_e('Inscrição', 'sciflow-wp'); ?></h2>
        <input type="hidden" name="sciflow_settings[_sections][]" value="inscricao">
        <?php if (!$woo) : ?>
            <div class="notice notice-info inline"><p>
                <?php esc_html_e('O WooCommerce não está ativo. Sem ele, qualquer usuário logado pode submeter trabalhos e não há venda de inscrições.', 'sciflow-wp'); ?>
                <a href="<?php echo esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')); ?>"><?php esc_html_e('Instalar WooCommerce', 'sciflow-wp'); ?></a>
            </p></div>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><?php esc_html_e('Exigir inscrição paga', 'sciflow-wp'); ?></th>
                    <td><label><input type="checkbox" name="sciflow_settings[require_paid_registration]" value="1" <?php checked('1', (string) $s['require_paid_registration']); ?>> <?php esc_html_e('Exigir (só funciona com WooCommerce ativo)', 'sciflow-wp'); ?></label></td></tr>
            </table>
        <?php else : ?>
            <p class="lead"><?php esc_html_e('Escolha os produtos do WooCommerce que liberam cada perfil quando o pedido é concluído.', 'sciflow-wp'); ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" target="_blank"><?php esc_html_e('Criar produto', 'sciflow-wp'); ?></a></p>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><?php esc_html_e('Exigir inscrição paga', 'sciflow-wp'); ?></th>
                    <td><label><input type="checkbox" name="sciflow_settings[require_paid_registration]" value="1" <?php checked('1', (string) $s['require_paid_registration']); ?>> <?php esc_html_e('Somente inscritos com pedido concluído podem submeter resumos.', 'sciflow-wp'); ?></label></td></tr>
                <?php
                $product_select('woo_product_ids', __('Produto de inscrição', 'sciflow-wp'), __('Libera o perfil Inscrito.', 'sciflow-wp'));
                $product_select('woo_speaker_product_ids', __('Produto de palestrante', 'sciflow-wp'), __('Opcional. Libera o envio de palestras.', 'sciflow-wp'));
                $product_select('woo_tecnico_epagri_product_ids', __('Produto de técnico', 'sciflow-wp'), __('Opcional. Inscrição especial de técnicos.', 'sciflow-wp'));
                ?>
            </table>
            <script>
            document.querySelectorAll('.sf-multi').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    document.getElementById(sel.dataset.target).value = Array.prototype.map.call(sel.selectedOptions, function (o) { return o.value; }).join(',');
                });
            });
            </script>
        <?php endif; ?>
        <?php
    }

    private function step_paginas($s)
    {
        $pages = get_pages(array('post_status' => 'publish', 'sort_column' => 'post_title'));
        $ids = (array) ($s['page_ids'] ?? array());
        $theme = current_theme_supports('sciflow');
        ?>
        <h2><?php esc_html_e('Páginas do site', 'sciflow-wp'); ?></h2>
        <p class="lead">
            <?php echo $theme
                ? esc_html__('O tema SciFlow está ativo: as páginas serão criadas com os modelos do tema.', 'sciflow-wp')
                : esc_html__('O tema ativo não é o tema SciFlow: as páginas serão criadas com shortcodes. Algumas páginas só existem com o tema SciFlow.', 'sciflow-wp'); ?>
        </p>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e('Página', 'sciflow-wp'); ?></th><th><?php esc_html_e('Situação', 'sciflow-wp'); ?></th><th><?php esc_html_e('Usar página existente', 'sciflow-wp'); ?></th><th><?php esc_html_e('Criar', 'sciflow-wp'); ?></th></tr></thead>
            <tbody>
            <?php foreach (SciFlow_Pages::definitions() as $role => $def) :
                $resolved = SciFlow_Pages::get_id($role);
                $creatable = 'plugin' === $def['provider'] || '' !== $def['shortcode'] || $theme;
                ?>
                <tr class="sf-page-row">
                    <td><strong class="<?php echo $def['required'] ? 'sf-req' : ''; ?>"><?php echo esc_html($def['title']); ?></strong><br><span class="description"><?php echo esc_html($def['description']); ?></span></td>
                    <td><?php if ($resolved) : ?>
                            <span class="sf-ok">✔ <a href="<?php echo esc_url(get_permalink($resolved)); ?>" target="_blank"><?php echo esc_html(get_the_title($resolved)); ?></a></span>
                        <?php elseif (!$creatable) : ?>
                            <span class="sf-info"><?php esc_html_e('Requer o tema SciFlow', 'sciflow-wp'); ?></span>
                        <?php else : ?>
                            <span class="<?php echo $def['required'] ? 'sf-bad' : 'sf-info'; ?>"><?php echo $def['required'] ? esc_html__('Ausente (obrigatória)', 'sciflow-wp') : esc_html__('Ausente (opcional)', 'sciflow-wp'); ?></span>
                        <?php endif; ?></td>
                    <td><select name="sciflow_settings[page_ids][<?php echo esc_attr($role); ?>]" style="max-width:220px;">
                            <option value="0"><?php esc_html_e('— Automático —', 'sciflow-wp'); ?></option>
                            <?php foreach ($pages as $page) : ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected((int) ($ids[$role] ?? 0), $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    <td><?php if (!$resolved && $creatable) : ?>
                            <input type="checkbox" name="sciflow_settings[create_pages][]" value="<?php echo esc_attr($role); ?>" checked aria-label="<?php echo esc_attr(sprintf(__('Criar %s', 'sciflow-wp'), $def['title'])); ?>">
                        <?php else : ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php esc_html_e('Páginas existentes nunca são alteradas. Depois, monte os menus em Aparência → Menus.', 'sciflow-wp'); ?></p>
        <?php
    }

    private function step_emails($s)
    {
        if ('' === (string) $s['email_from_name']) {
            $s['email_from_name'] = (string) ($s['organizer_name'] ?: get_bloginfo('name'));
        }
        if ('' === (string) $s['email_from_address']) {
            $s['email_from_address'] = (string) get_option('admin_email');
        }
        if ('' === trim(wp_strip_all_tags((string) $s['lgpd_consent_text']))) {
            $s['lgpd_consent_text'] = sprintf('Declaro que li e concordo com o tratamento dos meus dados pessoais por %s, exclusivamente para a organização do evento, conforme a Lei nº 13.709/2018 (LGPD).', $s['organizer_name'] ?: get_bloginfo('name'));
        }
        ?>
        <h2><?php esc_html_e('E-mails e LGPD', 'sciflow-wp'); ?></h2>
        <table class="form-table" role="presentation">
            <?php
            $this->field_text($s, 'email_from_name', __('Nome do remetente', 'sciflow-wp'), array('required' => true));
            $this->field_text($s, 'email_from_address', __('E-mail do remetente', 'sciflow-wp'), array('type' => 'email', 'required' => true, 'help' => __('Use um endereço do domínio do site, autorizado no seu provedor SMTP (SPF/DKIM).', 'sciflow-wp')));
            foreach (SciFlow_Events::enabled() as $slug => $event) {
                $this->field_text($s, $slug . '_editor_email', sprintf(__('Editores de %s (e-mails extras)', 'sciflow-wp'), $event['label']), array('class' => 'large-text', 'help' => __('Opcional. Recebem aviso de novas submissões além dos usuários com papel de editor. Separe por vírgula.', 'sciflow-wp')));
            }
            ?>
            <tr>
                <th scope="row"><label for="sf-lgpd" class="sf-req"><?php esc_html_e('Texto de consentimento LGPD', 'sciflow-wp'); ?></label></th>
                <td><textarea id="sf-lgpd" name="sciflow_settings[lgpd_consent_text]" rows="4" class="large-text" required><?php echo esc_textarea((string) $s['lgpd_consent_text']); ?></textarea>
                    <p class="description"><?php esc_html_e('Exibido no aceite obrigatório da submissão, do pôster e do checkout. Revise com o responsável jurídico.', 'sciflow-wp'); ?></p></td>
            </tr>
        </table>
        <p class="description"><?php esc_html_e('Depois de concluir, faça um envio de teste pelo seu plugin SMTP: o SciFlow não confirma a entrega dos e-mails.', 'sciflow-wp'); ?></p>
        <?php
    }

    private function step_certificados($s)
    {
        if ('' === trim((string) $s['anais_title'])) {
            $labels = wp_list_pluck(SciFlow_Events::enabled(), 'label');
            $s['anais_title'] = 'Anais – ' . implode(' / ', $labels) . ' ' . wp_date('Y');
        }
        ?>
        <h2><?php esc_html_e('Certificados e Anais', 'sciflow-wp'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Download de certificados', 'sciflow-wp'); ?></th>
                <td><fieldset>
                    <label><input type="radio" name="sciflow_settings[certificate_access]" value="logged_in" <?php checked('logged_in', $s['certificate_access']); ?>> <?php esc_html_e('Somente logado, apenas o próprio certificado (recomendado)', 'sciflow-wp'); ?></label><br>
                    <label><input type="radio" name="sciflow_settings[certificate_access]" value="email" <?php checked('email', $s['certificate_access']); ?>> <?php esc_html_e('Qualquer visitante informando um e-mail', 'sciflow-wp'); ?></label>
                </fieldset>
                <p class="description"><?php esc_html_e('Os PDFs são enviados para wp-content/uploads/certificados/ com o nome <e-mail>.pdf.', 'sciflow-wp'); ?></p></td>
            </tr>
            <?php $this->field_text($s, 'anais_title', __('Título dos Anais', 'sciflow-wp'), array('required' => true, 'class' => 'large-text')); ?>
            <tr>
                <th scope="row"><label for="sf-anais-header"><?php esc_html_e('Cabeçalho das páginas dos Anais', 'sciflow-wp'); ?></label></th>
                <td><textarea id="sf-anais-header" name="sciflow_settings[anais_header]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Nome completo do evento<br>Datas e local', 'sciflow-wp'); ?>"><?php echo esc_textarea((string) $s['anais_header']); ?></textarea>
                    <p class="description"><?php esc_html_e('Até 3 linhas, separadas por <br>. Vazio: nomes completos dos eventos.', 'sciflow-wp'); ?></p></td>
            </tr>
        </table>
        <?php
    }

    private function step_revisao($s)
    {
        $checks = SciFlow_Diagnostics::run();
        $summary = SciFlow_Diagnostics::summary($checks);
        $ready = SciFlow_Diagnostics::is_ready($checks);
        $events = SciFlow_Events::enabled();
        ?>
        <h2><?php esc_html_e('Revisão', 'sciflow-wp'); ?></h2>
        <table class="widefat striped" style="margin-bottom:16px;">
            <tbody>
                <tr><th><?php esc_html_e('Eventos', 'sciflow-wp'); ?></th><td><?php echo esc_html(implode(' · ', wp_list_pluck($events, 'full_label'))); ?></td></tr>
                <tr><th><?php esc_html_e('Submissões até', 'sciflow-wp'); ?></th><td><?php echo esc_html($s['article_submission_deadline'] ? wp_date('d/m/Y H:i', strtotime($s['article_submission_deadline'])) : '—'); ?></td></tr>
                <tr><th><?php esc_html_e('Inscrição paga', 'sciflow-wp'); ?></th><td><?php echo esc_html('1' === (string) $s['require_paid_registration'] && class_exists('WooCommerce') ? sprintf(__('Exigida (produto %s)', 'sciflow-wp'), $s['woo_product_ids'] ?: '—') : __('Não exigida', 'sciflow-wp')); ?></td></tr>
                <tr><th><?php esc_html_e('Remetente', 'sciflow-wp'); ?></th><td><?php echo esc_html(SciFlow_Email::get_from_name() . ' <' . SciFlow_Email::get_from_address() . '>'); ?></td></tr>
                <tr><th><?php esc_html_e('Páginas', 'sciflow-wp'); ?></th><td><?php
                    $found = 0; $total = 0;
                    foreach (SciFlow_Pages::definitions() as $role => $def) { $total++; if (SciFlow_Pages::exists($role)) { $found++; } }
                    echo esc_html(sprintf(__('%1$d de %2$d configuradas', 'sciflow-wp'), $found, $total)); ?></td></tr>
            </tbody>
        </table>
        <p><strong><?php echo $ready ? esc_html__('Nenhum item bloqueante.', 'sciflow-wp') : esc_html__('Ainda há itens bloqueantes:', 'sciflow-wp'); ?></strong>
            <?php echo esc_html(sprintf(__('%1$d bloqueante(s), %2$d alerta(s), %3$d informativo(s).', 'sciflow-wp'), $summary['error'], $summary['warning'], $summary['info'])); ?></p>
        <ul style="list-style:disc;padding-left:20px;">
            <?php foreach ($checks as $c) :
                if (!in_array($c['status'], array('error', 'warning'), true)) { continue; } ?>
                <li class="<?php echo 'error' === $c['status'] ? 'sf-bad' : ''; ?>"><strong><?php echo esc_html($c['label']); ?>:</strong> <?php echo esc_html($c['message']); ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="description"><?php esc_html_e('Você pode concluir agora e resolver o restante depois pela tela Diagnóstico.', 'sciflow-wp'); ?></p>
        <?php
    }
}

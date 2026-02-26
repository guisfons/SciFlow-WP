<?php
/**
 * Central loader – registers all hooks, filters, and shortcodes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Loader
{

    /**
     * Module instances.
     */
    private $enfrute_cpt;
    private $senco_cpt;
    private $meta;
    private $status_manager;
    private $submission;
    private $review;
    private $editorial;
    private $ranking;
    private $email;
    private $shortcodes;
    private $ajax;
    private $admin;
    private $poster_upload;
    private $payment;
    private $woocommerce;
    private $access;
    private $redirection;

    /**
     * Instantiate all modules and register hooks.
     */
    public function run()
    {
        // Load text domain.
        load_plugin_textdomain('sciflow-wp', false, dirname(SCIFLOW_BASENAME) . '/languages');

        // Post types.
        $this->enfrute_cpt = new SciFlow_Enfrute_CPT();
        $this->senco_cpt = new SciFlow_Senco_CPT();

        // Self-repair roles if missing capabilities.
        $senco_editor = get_role('sciflow_senco_editor');
        if ($senco_editor && !$senco_editor->has_cap('manage_sciflow')) {
            $roles = new SciFlow_Roles();
            $roles->add_roles();
            $roles->add_caps();
        }

        // Meta fields.
        $this->meta = new SciFlow_Meta();

        // Workflow.
        $this->status_manager = new SciFlow_Status_Manager();
        $this->email = new SciFlow_Email();
        $this->submission = new SciFlow_Submission($this->status_manager, $this->email, $this->woocommerce);
        $this->review = new SciFlow_Review($this->status_manager, $this->email);
        $this->editorial = new SciFlow_Editorial($this->status_manager, $this->email);

        // Ranking.
        $this->ranking = new SciFlow_Ranking();

        // Poster upload.
        $this->poster_upload = new SciFlow_Poster_Upload($this->status_manager, $this->email);

        // Payment.
        $this->payment = new SciFlow_PayGo_Gateway();

        // Frontend.
        $this->shortcodes = new SciFlow_Shortcodes(
            $this->submission,
            $this->review,
            $this->editorial,
            $this->ranking,
            $this->poster_upload,
            $this->payment,
            $this->woocommerce
        );
        $this->ajax = new SciFlow_Ajax_Handler(
            $this->submission,
            $this->review,
            $this->editorial,
            $this->poster_upload,
            $this->payment,
            $this->woocommerce
        );

        // Admin.
        if (is_admin()) {
            $this->admin = new SciFlow_Admin($this->payment);
            $this->access = new SciFlow_Access();
        }

        if (class_exists('WooCommerce')) {
            $this->woocommerce = new SciFlow_WooCommerce();
        }

        // Redirection logic.
        $this->redirection = new SciFlow_Redirection($this->woocommerce);

        // Enqueue assets.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));

        // Register REST routes for payment webhook.
        add_action('rest_api_init', array($this->payment, 'register_routes'));

        // Cron for confirmation deadline.
        add_action('sciflow_check_confirmation_deadlines', array($this->ranking, 'check_deadlines'));
        if (!wp_next_scheduled('sciflow_check_confirmation_deadlines')) {
            wp_schedule_event(time(), 'hourly', 'sciflow_check_confirmation_deadlines');
        }
    }

    /**
     * Enqueue public-facing CSS and JS.
     */
    public function enqueue_public_assets()
    {
        wp_enqueue_style(
            'sciflow-public',
            SCIFLOW_URL . 'public/css/sciflow-public.css',
            array(),
            SCIFLOW_VERSION
        );

        wp_enqueue_script(
            'sciflow-public',
            SCIFLOW_URL . 'public/js/sciflow-public.js',
            array('jquery'),
            SCIFLOW_VERSION,
            true
        );

        wp_localize_script('sciflow-public', 'sciflow_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sciflow_nonce'),
            'strings' => array(
                'char_count' => __('Caracteres: %d / %d', 'sciflow-wp'),
                'char_over' => __('Limite de caracteres excedido!', 'sciflow-wp'),
                'submitting' => __('Enviando...', 'sciflow-wp'),
                'upload_error' => __('Erro no upload. Verifique o arquivo.', 'sciflow-wp'),
                'confirm_submit' => __('Confirma a submissão do trabalho?', 'sciflow-wp'),
            ),
        ));
    }
}

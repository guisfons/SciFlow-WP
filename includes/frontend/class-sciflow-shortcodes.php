<?php
/**
 * Frontend shortcodes for all SciFlow pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

#[AllowDynamicProperties]
class SciFlow_Shortcodes
{
    private $woocommerce;
    private $submission;
    private $review;
    private $editorial;
    private $ranking;
    private $poster_upload;
    private $payment;

    public function __construct(
        SciFlow_Submission $submission,
        SciFlow_Review $review,
        SciFlow_Editorial $editorial,
        SciFlow_Ranking $ranking,
        SciFlow_Poster_Upload $poster_upload,
        SciFlow_Sicredi_Pix $payment,
        SciFlow_WooCommerce $woocommerce = null
    ) {
        $this->submission = $submission;
        $this->review = $review;
        $this->editorial = $editorial;
        $this->ranking = $ranking;
        $this->poster_upload = $poster_upload;
        $this->payment = $payment;
        $this->woocommerce = $woocommerce;

        add_shortcode('sciflow_submission_form', array($this, 'submission_form'));
        add_shortcode('sciflow_author_dashboard', array($this, 'author_dashboard'));
        add_shortcode('sciflow_reviewer_panel', array($this, 'reviewer_panel'));
        add_shortcode('sciflow_editor_panel', array($this, 'editor_panel'));
        add_shortcode('sciflow_ranking', array($this, 'ranking_page'));
        add_shortcode('sciflow_poster_upload', array($this, 'poster_upload_form'));
    }

    /**
     * [sciflow_submission_form]
     */
    public function submission_form($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="sciflow-notice sciflow-notice--warning">'
                . __('Você precisa estar logado para submeter um trabalho.', 'sciflow-wp')
                . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">'
                . __('Fazer login', 'sciflow-wp') . '</a></div>';
        }

        $user_id = get_current_user_id();
        if ($this->woocommerce && !$this->woocommerce->has_paid_registration($user_id)) {
            $registration_page = get_pages(array('meta_key' => '_wp_page_template', 'meta_value' => 'page-templates/template-home-inscription.php'));
            $registration_url = !empty($registration_page) ? get_permalink($registration_page[0]->ID) : home_url('/inscricao');

            return '<div class="sciflow-notice sciflow-notice--error">'
                . __('Você precisa ter uma inscrição paga para submeter um trabalho.', 'sciflow-wp')
                . ' <a href="' . esc_url($registration_url) . '">'
                . __('Ir para página de inscrição', 'sciflow-wp') . '</a></div>';
        }

        ob_start();
        include SCIFLOW_PATH . 'public/templates/submission-form.php';
        return ob_get_clean();
    }

    /**
     * [sciflow_author_dashboard]
     */
    public function author_dashboard($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="sciflow-notice sciflow-notice--warning">'
                . __('Faça login para acessar seu painel.', 'sciflow-wp')
                . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">'
                . __('Fazer login', 'sciflow-wp') . '</a></div>';
        }

        $submissions = $this->submission->get_user_submissions();
        $status_manager = new SciFlow_Status_Manager();

        ob_start();
        include SCIFLOW_PATH . 'public/templates/author-dashboard.php';
        return ob_get_clean();
    }

    /**
     * [sciflow_reviewer_panel]
     */
    public function reviewer_panel($atts)
    {
        if (!is_user_logged_in() || !current_user_can('sciflow_review')) {
            return '<div class="sciflow-notice sciflow-notice--error">'
                . __('Acesso restrito a revisores.', 'sciflow-wp') . '</div>';
        }

        $articles = $this->review->get_reviewer_articles();
        $status_manager = new SciFlow_Status_Manager();

        ob_start();
        include SCIFLOW_PATH . 'public/templates/reviewer-form.php';
        return ob_get_clean();
    }

    /**
     * [sciflow_editor_panel]
     */
    public function editor_panel($atts)
    {
        if (!is_user_logged_in() || !current_user_can('manage_sciflow')) {
            return '<div class="sciflow-notice sciflow-notice--error">'
                . __('Acesso restrito a editores.', 'sciflow-wp') . '</div>';
        }

        $editorial = $this->editorial;
        $status_manager = new SciFlow_Status_Manager();

        ob_start();
        include SCIFLOW_PATH . 'public/templates/editorial-panel.php';
        return ob_get_clean();
    }

    /**
     * [sciflow_ranking event="enfrute|senco|geral"]
     */
    public function ranking_page($atts)
    {
        $atts = shortcode_atts(array(
            'event' => 'all',
        ), $atts, 'sciflow_ranking');

        $ranking = $this->ranking;
        $status_manager = new SciFlow_Status_Manager();
        $event = sanitize_text_field($atts['event']);

        ob_start();
        include SCIFLOW_PATH . 'public/templates/ranking-page.php';
        return ob_get_clean();
    }

    /**
     * [sciflow_poster_upload]
     */
    public function poster_upload_form($atts)
    {
        if (!is_user_logged_in()) {
            return '<div class="sciflow-notice sciflow-notice--warning">'
                . __('Faça login para enviar seu pôster.', 'sciflow-wp')
                . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">'
                . __('Fazer login', 'sciflow-wp') . '</a></div>';
        }

        $submissions = $this->submission->get_user_submissions();
        $status_manager = new SciFlow_Status_Manager();

        // Filter to only approved works.
        $approved = array_filter($submissions, function ($post) {
            $status = get_post_meta($post->ID, '_sciflow_status', true);
            return in_array($status, array('aprovado', 'poster_enviado'), true);
        });

        ob_start();
        include SCIFLOW_PATH . 'public/templates/poster-upload.php';
        return ob_get_clean();
    }
}

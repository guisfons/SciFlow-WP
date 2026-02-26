<?php
/**
 * Registers all post meta fields used by SciFlow.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Meta
{

    /**
     * Post types that share the same meta fields.
     */
    private $post_types = array('enfrute_trabalhos', 'senco_trabalhos');

    public function __construct()
    {
        add_action('init', array($this, 'register_meta'));
    }

    /**
     * Register all meta keys for both CPTs.
     */
    public function register_meta()
    {
        $fields = $this->get_fields();

        foreach ($this->post_types as $pt) {
            foreach ($fields as $key => $args) {
                register_post_meta($pt, $key, $args);
            }
        }
    }

    /**
     * Meta field definitions.
     */
    private function get_fields()
    {
        return array(
            '_sciflow_status' => array(
                'type' => 'string',
                'description' => 'Workflow status',
                'single' => true,
                'default' => 'rascunho',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_event' => array(
                'type' => 'string',
                'description' => 'Event: enfrute or senco',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_presenting_author' => array(
                'type' => 'string',
                'description' => 'Quem vai apresentar o trabalho (\'main\' ou o indice dp coautor)',
                'single' => true,
                'default' => 'main',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_cultura' => array(
                'type' => 'string',
                'description' => 'Cultura selecionada',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_knowledge_area' => array(
                'type' => 'string',
                'description' => 'Área de Conhecimento selecionada',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_author_id' => array(
                'type' => 'integer',
                'description' => 'WP user ID of main author',
                'single' => true,
                'sanitize_callback' => 'absint',
                'show_in_rest' => false,
            ),
            '_sciflow_main_author_instituicao' => array(
                'type' => 'string',
                'description' => 'Instituição do autor principal',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_main_author_cpf' => array(
                'type' => 'string',
                'description' => 'CPF do autor principal',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_main_author_email' => array(
                'type' => 'string',
                'description' => 'Email do autor principal',
                'single' => true,
                'sanitize_callback' => 'sanitize_email',
                'show_in_rest' => false,
            ),
            '_sciflow_main_author_telefone' => array(
                'type' => 'string',
                'description' => 'Telefone do autor principal',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_coauthors' => array(
                'type' => 'array',
                'description' => 'Co-authors (name, email, institution)',
                'single' => true,
                'default' => array(),
                'show_in_rest' => false,
            ),
            '_sciflow_keywords' => array(
                'type' => 'array',
                'description' => '3-5 keywords',
                'single' => true,
                'default' => array(),
                'show_in_rest' => false,
            ),
            '_sciflow_language' => array(
                'type' => 'string',
                'description' => 'Language: pt, en, es',
                'single' => true,
                'default' => 'pt',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_reviewer_id' => array(
                'type' => 'integer',
                'description' => 'Assigned reviewer user ID',
                'single' => true,
                'default' => 0,
                'sanitize_callback' => 'absint',
                'show_in_rest' => false,
            ),
            '_sciflow_scores' => array(
                'type' => 'object',
                'description' => 'Review scores: originalidade, objetividade, organizacao, metodologia, aderencia',
                'single' => true,
                'default' => array(),
                'show_in_rest' => false,
            ),
            '_sciflow_reviewer_decision' => array(
                'type' => 'string',
                'description' => 'Reviewer decision: approved, approved_with_considerations, rejected',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_reviewer_notes' => array(
                'type' => 'string',
                'description' => 'Reviewer observations',
                'single' => true,
                'sanitize_callback' => 'wp_kses_post',
                'show_in_rest' => false,
            ),
            '_sciflow_editorial_notes' => array(
                'type' => 'string',
                'description' => 'Editor notes to author',
                'single' => true,
                'sanitize_callback' => 'wp_kses_post',
                'show_in_rest' => false,
            ),
            '_sciflow_decision' => array(
                'type' => 'string',
                'description' => 'Editorial final decision',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_payment_status' => array(
                'type' => 'string',
                'description' => 'Payment status: pending, confirmed',
                'single' => true,
                'default' => 'pending',
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_payment_id' => array(
                'type' => 'string',
                'description' => 'External payment reference (txid)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_poster_id' => array(
                'type' => 'integer',
                'description' => 'Attachment ID of uploaded poster PDF',
                'single' => true,
                'default' => 0,
                'sanitize_callback' => 'absint',
                'show_in_rest' => false,
            ),
            '_sciflow_ranking_score' => array(
                'type' => 'number',
                'description' => 'Calculated weighted average score',
                'single' => true,
                'default' => 0,
                'show_in_rest' => false,
            ),
            '_sciflow_presentation_confirmed' => array(
                'type' => 'boolean',
                'description' => 'Whether author confirmed presentation',
                'single' => true,
                'default' => false,
                'show_in_rest' => false,
            ),
            '_sciflow_confirmation_deadline' => array(
                'type' => 'string',
                'description' => 'Deadline for presentation confirmation (ISO 8601)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'show_in_rest' => false,
            ),
            '_sciflow_selected_for_presentation' => array(
                'type' => 'boolean',
                'description' => 'Whether the work was selected via ranking',
                'single' => true,
                'default' => false,
                'show_in_rest' => false,
            ),
        );
    }
}

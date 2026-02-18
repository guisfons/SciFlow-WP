<?php
/**
 * Registers the Senco CPT (senco_trabalhos).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Senco_CPT
{

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        $labels = array(
            'name' => __('Trabalhos Senco', 'sciflow-wp'),
            'singular_name' => __('Trabalho Senco', 'sciflow-wp'),
            'add_new' => __('Adicionar Novo', 'sciflow-wp'),
            'add_new_item' => __('Adicionar Novo Trabalho', 'sciflow-wp'),
            'edit_item' => __('Editar Trabalho', 'sciflow-wp'),
            'new_item' => __('Novo Trabalho', 'sciflow-wp'),
            'view_item' => __('Ver Trabalho', 'sciflow-wp'),
            'search_items' => __('Buscar Trabalhos', 'sciflow-wp'),
            'not_found' => __('Nenhum trabalho encontrado', 'sciflow-wp'),
            'not_found_in_trash' => __('Nenhum trabalho na lixeira', 'sciflow-wp'),
            'all_items' => __('Todos os Trabalhos', 'sciflow-wp'),
            'menu_name' => __('Senco', 'sciflow-wp'),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 26,
            'menu_icon' => 'dashicons-media-text',
            'capability_type' => 'senco_trabalho',
            'map_meta_cap' => true,
            'supports' => array('title', 'editor', 'author'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
        );

        register_post_type('senco_trabalhos', $args);
    }
}

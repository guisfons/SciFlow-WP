<?php
/**
 * Registers the Palestra CPT (sciflow_palestra).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Palestra_CPT
{

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        $labels = array(
            'name' => __('Palestras', 'sciflow-wp'),
            'singular_name' => __('Palestra', 'sciflow-wp'),
            'add_new' => __('Adicionar Nova', 'sciflow-wp'),
            'add_new_item' => __('Adicionar Nova Palestra', 'sciflow-wp'),
            'edit_item' => __('Editar Palestra', 'sciflow-wp'),
            'new_item' => __('Nova Palestra', 'sciflow-wp'),
            'view_item' => __('Ver Palestra', 'sciflow-wp'),
            'search_items' => __('Buscar Palestras', 'sciflow-wp'),
            'not_found' => __('Nenhuma palestra encontrada', 'sciflow-wp'),
            'not_found_in_trash' => __('Nenhuma palestra na lixeira', 'sciflow-wp'),
            'all_items' => __('Todas as Palestras', 'sciflow-wp'),
            'menu_name' => __('Palestras SciFlow', 'sciflow-wp'),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 26,
            'menu_icon' => 'dashicons-megaphone',
            'capability_type' => 'sciflow_palestra',
            'map_meta_cap' => true,
            'supports' => array('title', 'editor', 'author'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
        );

        register_post_type('sciflow_palestra', $args);
    }
}

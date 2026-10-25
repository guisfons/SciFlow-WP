<?php
/**
 * Registers the Enfrute CPT (enfrute_trabalhos).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Enfrute_CPT
{

    public function __construct()
    {
        add_action('init', array($this, 'register'));
    }

    public function register()
    {
        $event_label = class_exists('SciFlow_Events') ? SciFlow_Events::label('enfrute') : 'Enfrute';
        $labels = array(
            /* translators: %s: event name */
            'name' => sprintf(__('Trabalhos %s', 'sciflow-wp'), $event_label),
            /* translators: %s: event name */
            'singular_name' => sprintf(__('Trabalho %s', 'sciflow-wp'), $event_label),
            'add_new' => __('Adicionar Novo', 'sciflow-wp'),
            'add_new_item' => __('Adicionar Novo Trabalho', 'sciflow-wp'),
            'edit_item' => __('Editar Trabalho', 'sciflow-wp'),
            'new_item' => __('Novo Trabalho', 'sciflow-wp'),
            'view_item' => __('Ver Trabalho', 'sciflow-wp'),
            'search_items' => __('Buscar Trabalhos', 'sciflow-wp'),
            'not_found' => __('Nenhum trabalho encontrado', 'sciflow-wp'),
            'not_found_in_trash' => __('Nenhum trabalho na lixeira', 'sciflow-wp'),
            'all_items' => __('Todos os Trabalhos', 'sciflow-wp'),
            'menu_name' => $event_label,
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-media-document',
            'capability_type' => 'enfrute_trabalho',
            'map_meta_cap' => true,
            'supports' => array('title', 'editor', 'author'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
        );

        register_post_type('enfrute_trabalhos', $args);
    }
}

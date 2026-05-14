<?php
if (!defined('ABSPATH')) exit;

class SciFlow_Dashboard_Injector {
    public function __construct() {
        add_action('pre_get_posts', array($this, 'inject_palestras_into_author_dashboard'));
    }

    public function inject_palestras_into_author_dashboard($query) {
        if (!is_admin() && $query->is_main_query() === false) {
            $post_types = $query->get('post_type');
            if (is_array($post_types) && in_array('enfrute_trabalhos', $post_types) && in_array('semco_trabalhos', $post_types)) {
                $post_types[] = 'sciflow_palestra';
                $query->set('post_type', $post_types);
            }
        }
    }
}
new SciFlow_Dashboard_Injector();

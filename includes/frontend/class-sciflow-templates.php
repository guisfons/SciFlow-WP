<?php
/**
 * Handles page template registration and loading from the plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Templates
{
    /**
     * Templates map.
     */
    protected $templates = array();

    public function __construct()
    {
        $this->templates = array(
            'template-poster-upload.php' => 'SciFlow - Enviar Pôster',
        );

        // Add templates to the page template dropdown.
        add_filter('theme_page_templates', array($this, 'add_templates_to_dropdown'));

        // Load the template from the plugin.
        add_filter('template_include', array($this, 'load_plugin_template'));
    }

    /**
     * Adds our templates to the page template dropdown.
     */
    public function add_templates_to_dropdown($templates)
    {
        foreach ($this->templates as $file => $name) {
            $templates[$file] = $name;
        }
        return $templates;
    }

    /**
     * Loads the template file from the plugin if selected.
     */
    public function load_plugin_template($template)
    {
        global $post;

        if (!$post) {
            return $template;
        }

        $template_name = get_post_meta($post->ID, '_wp_page_template', true);

        if (isset($this->templates[$template_name])) {
            $file = SCIFLOW_PATH . 'public/templates/' . $template_name;
            if (file_exists($file)) {
                return $file;
            }
        }

        return $template;
    }
}

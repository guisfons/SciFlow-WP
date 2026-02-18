<?php
/**
 * User roles and capabilities for SciFlow.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Roles
{

    /**
     * Add custom roles.
     */
    public function add_roles()
    {
        // Inscrito — can submit and edit own articles.
        add_role('sciflow_inscrito', __('Inscrito (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Revisor — can read and review assigned articles.
        add_role('sciflow_revisor', __('Revisor (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Editor — full editorial control over articles.
        add_role('sciflow_editor', __('Editor (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));
    }

    /**
     * Assign capabilities to each role.
     */
    public function add_caps()
    {
        // ---------- Inscrito ----------
        $inscrito = get_role('sciflow_inscrito');
        if ($inscrito) {
            // Own Enfrute posts.
            $inscrito->add_cap('edit_enfrute_trabalhos');
            $inscrito->add_cap('edit_published_enfrute_trabalhos');
            $inscrito->add_cap('delete_enfrute_trabalhos');
            // Own Senco posts.
            $inscrito->add_cap('edit_senco_trabalhos');
            $inscrito->add_cap('edit_published_senco_trabalhos');
            $inscrito->add_cap('delete_senco_trabalhos');
            // Upload.
            $inscrito->add_cap('upload_files');
        }

        // ---------- Revisor ----------
        $revisor = get_role('sciflow_revisor');
        if ($revisor) {
            $revisor->add_cap('read_enfrute_trabalhos');
            $revisor->add_cap('read_private_enfrute_trabalhos');
            $revisor->add_cap('read_senco_trabalhos');
            $revisor->add_cap('read_private_senco_trabalhos');
            $revisor->add_cap('sciflow_review');
        }

        // ---------- Editor ----------
        $editor = get_role('sciflow_editor');
        if ($editor) {
            // All Enfrute caps.
            foreach ($this->get_post_type_caps('enfrute_trabalho') as $cap) {
                $editor->add_cap($cap);
            }
            // All Senco caps.
            foreach ($this->get_post_type_caps('senco_trabalho') as $cap) {
                $editor->add_cap($cap);
            }
            $editor->add_cap('sciflow_review');
            $editor->add_cap('assign_sciflow_reviewers');
            $editor->add_cap('manage_sciflow');
            $editor->add_cap('upload_files');
        }

        // ---------- Administrator ----------
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($this->get_post_type_caps('enfrute_trabalho') as $cap) {
                $admin->add_cap($cap);
            }
            foreach ($this->get_post_type_caps('senco_trabalho') as $cap) {
                $admin->add_cap($cap);
            }
            $admin->add_cap('sciflow_review');
            $admin->add_cap('assign_sciflow_reviewers');
            $admin->add_cap('manage_sciflow');
        }
    }

    /**
     * Remove custom roles.
     */
    public function remove_roles()
    {
        remove_role('sciflow_inscrito');
        remove_role('sciflow_revisor');
        remove_role('sciflow_editor');
    }

    /**
     * Return full set of WP post-type capabilities for a given singular base.
     */
    private function get_post_type_caps($singular)
    {
        $plural = $singular . 's';
        return array(
            "edit_{$plural}",
            "edit_others_{$plural}",
            "edit_published_{$plural}",
            "publish_{$plural}",
            "read_private_{$plural}",
            "delete_{$plural}",
            "delete_others_{$plural}",
            "delete_published_{$plural}",
        );
    }
}

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

        // Palestrante — can submit talks
        add_role('sciflow_speaker', __('Palestrante (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
            'upload_files' => true,
        ));

        // Revisor — can read and review assigned articles.
        add_role('sciflow_revisor', __('Revisor (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Editor — full editorial control over articles.
        add_role('sciflow_editor', __('Editor (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // --- NEW ROLES ---

        // Semco Editor
        add_role('sciflow_semco_editor', __('Editor Semco (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Semco Revisor
        add_role('sciflow_semco_revisor', __('Revisor Semco (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Enfrute Editor
        add_role('sciflow_enfrute_editor', __('Editor Enfrute (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Enfrute Revisor
        add_role('sciflow_enfrute_revisor', __('Revisor Enfrute (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Técnico Epagri — inscrição especial; pode acumular revisor e/ou palestrante.
        add_role('sciflow_tecnico_epagri', __('Técnico Epagri (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
        ));

        // Gestor Técnico — pode gerenciar WooCommerce e Técnicos SciFlow.
        add_role('sciflow_tecnico_admin', __('Gestor Técnico (SciFlow)', 'sciflow-wp'), array(
            'read' => true,
            'manage_woocommerce' => true,
            'view_woocommerce_reports' => true,
            'edit_shop_orders' => true,
            'read_shop_order' => true,
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
            // Own Semco posts.
            $inscrito->add_cap('edit_semco_trabalhos');
            $inscrito->add_cap('edit_published_semco_trabalhos');
            $inscrito->add_cap('delete_semco_trabalhos');
            // Upload.
            $inscrito->add_cap('upload_files');
        }

        // ---------- Palestrante ----------
        $speaker = get_role('sciflow_speaker');
        if ($speaker) {
            foreach ($this->get_post_type_caps('sciflow_palestra') as $cap) {
                $speaker->add_cap($cap);
            }
        }

        // ---------- Revisor ----------
        $revisor = get_role('sciflow_revisor');
        if ($revisor) {
            $revisor->add_cap('read_enfrute_trabalhos');
            $revisor->add_cap('read_private_enfrute_trabalhos');
            $revisor->add_cap('read_semco_trabalhos');
            $revisor->add_cap('read_private_semco_trabalhos');
            $revisor->add_cap('sciflow_review');
            
            // Allow reviewers to also submit/edit their own articles
            $revisor->add_cap('edit_enfrute_trabalhos');
            $revisor->add_cap('edit_published_enfrute_trabalhos');
            $revisor->add_cap('delete_enfrute_trabalhos');
            $revisor->add_cap('edit_semco_trabalhos');
            $revisor->add_cap('edit_published_semco_trabalhos');
            $revisor->add_cap('delete_semco_trabalhos');
            $revisor->add_cap('upload_files');
        }

        // ---------- Editor ----------
        $editor = get_role('sciflow_editor');
        if ($editor) {
            // All Enfrute caps.
            foreach ($this->get_post_type_caps('enfrute_trabalho') as $cap) {
                $editor->add_cap($cap);
            }
            // All Semco caps.
            foreach ($this->get_post_type_caps('semco_trabalho') as $cap) {
                $editor->add_cap($cap);
            }
            // All Palestra caps.
            foreach ($this->get_post_type_caps('sciflow_palestra') as $cap) {
                $editor->add_cap($cap);
            }
            $editor->add_cap('sciflow_review');
            $editor->add_cap('assign_sciflow_reviewers');
            $editor->add_cap('manage_sciflow');
            $editor->add_cap('manage_sciflow_tecnicos');
            $editor->add_cap('upload_files');
        }

        // ---------- Gestor Técnico SciFlow ----------
        $gestor_tecnico = get_role('sciflow_tecnico_admin');
        if ($gestor_tecnico) {
            $gestor_tecnico->add_cap('manage_sciflow_tecnicos');
            $gestor_tecnico->add_cap('read');
            $gestor_tecnico->add_cap('upload_files');
            
            // Users management (needed for technical roles panel)
            $gestor_tecnico->add_cap('list_users');
            $gestor_tecnico->add_cap('edit_users');
            $gestor_tecnico->add_cap('promote_users');

            // WooCommerce full access (Shop Manager level)
            $gestor_tecnico->add_cap('manage_woocommerce');
            $gestor_tecnico->add_cap('view_woocommerce_reports');
            
            // Products
            $gestor_tecnico->add_cap('edit_products');
            $gestor_tecnico->add_cap('edit_others_products');
            $gestor_tecnico->add_cap('publish_products');
            $gestor_tecnico->add_cap('read_private_products');
            $gestor_tecnico->add_cap('delete_products');
            $gestor_tecnico->add_cap('delete_private_products');
            $gestor_tecnico->add_cap('delete_published_products');
            $gestor_tecnico->add_cap('delete_others_products');
            $gestor_tecnico->add_cap('edit_product_terms');
            $gestor_tecnico->add_cap('assign_product_terms');

            // Orders
            $gestor_tecnico->add_cap('edit_shop_orders');
            $gestor_tecnico->add_cap('read_shop_order');
            $gestor_tecnico->add_cap('edit_others_shop_orders');
            $gestor_tecnico->add_cap('publish_shop_orders');
            $gestor_tecnico->add_cap('read_private_shop_orders');
            $gestor_tecnico->add_cap('delete_shop_orders');
            $gestor_tecnico->add_cap('delete_private_shop_orders');
            $gestor_tecnico->add_cap('delete_published_shop_orders');
            $gestor_tecnico->add_cap('delete_others_shop_orders');
            $gestor_tecnico->add_cap('edit_shop_order_terms');
            $gestor_tecnico->add_cap('assign_shop_order_terms');

            // Coupons
            $gestor_tecnico->add_cap('edit_shop_coupons');
            $gestor_tecnico->add_cap('edit_others_shop_coupons');
            $gestor_tecnico->add_cap('publish_shop_coupons');
            $gestor_tecnico->add_cap('read_private_shop_coupons');
            $gestor_tecnico->add_cap('delete_shop_coupons');
            $gestor_tecnico->add_cap('delete_private_shop_coupons');
            $gestor_tecnico->add_cap('delete_published_shop_coupons');
            $gestor_tecnico->add_cap('delete_others_shop_coupons');
            $gestor_tecnico->add_cap('edit_shop_coupon_terms');
            $gestor_tecnico->add_cap('assign_shop_coupon_terms');
        }

        // ---------- Semco Editor ----------
        $semco_editor = get_role('sciflow_semco_editor');
        if ($semco_editor) {
            foreach ($this->get_post_type_caps('semco_trabalho') as $cap) {
                $semco_editor->add_cap($cap);
            }
            $semco_editor->add_cap('sciflow_review');
            $semco_editor->add_cap('assign_sciflow_reviewers');
            $semco_editor->add_cap('manage_sciflow'); // Needed for some dashboard checks
            $semco_editor->add_cap('manage_sciflow_tecnicos');
            $semco_editor->add_cap('upload_files');
        }

        // ---------- Semco Revisor ----------
        $semco_revisor = get_role('sciflow_semco_revisor');
        if ($semco_revisor) {
            $semco_revisor->add_cap('read_semco_trabalhos');
            $semco_revisor->add_cap('read_private_semco_trabalhos');
            $semco_revisor->add_cap('sciflow_review');

            // Authoring caps for their event
            $semco_revisor->add_cap('edit_semco_trabalhos');
            $semco_revisor->add_cap('edit_published_semco_trabalhos');
            $semco_revisor->add_cap('delete_semco_trabalhos');
            $semco_revisor->add_cap('upload_files');
        }

        // ---------- Enfrute Editor ----------
        $enfrute_editor = get_role('sciflow_enfrute_editor');
        if ($enfrute_editor) {
            foreach ($this->get_post_type_caps('enfrute_trabalho') as $cap) {
                $enfrute_editor->add_cap($cap);
            }
            $enfrute_editor->add_cap('sciflow_review');
            $enfrute_editor->add_cap('assign_sciflow_reviewers');
            $enfrute_editor->add_cap('manage_sciflow');
            $enfrute_editor->add_cap('manage_sciflow_tecnicos');
            $enfrute_editor->add_cap('upload_files');
        }

        // ---------- Enfrute Revisor ----------
        $enfrute_revisor = get_role('sciflow_enfrute_revisor');
        if ($enfrute_revisor) {
            $enfrute_revisor->add_cap('read_enfrute_trabalhos');
            $enfrute_revisor->add_cap('read_private_enfrute_trabalhos');
            $enfrute_revisor->add_cap('sciflow_review');

            // Authoring caps for their event
            $enfrute_revisor->add_cap('edit_enfrute_trabalhos');
            $enfrute_revisor->add_cap('edit_published_enfrute_trabalhos');
            $enfrute_revisor->add_cap('delete_enfrute_trabalhos');
            $enfrute_revisor->add_cap('upload_files');
        }

        // ---------- Técnico Epagri ----------
        $tecnico = get_role('sciflow_tecnico_epagri');
        if ($tecnico) {
            // Mesmas caps de inscrito: pode submeter e editar seus próprios trabalhos.
            $tecnico->add_cap('edit_enfrute_trabalhos');
            $tecnico->add_cap('edit_published_enfrute_trabalhos');
            $tecnico->add_cap('delete_enfrute_trabalhos');
            $tecnico->add_cap('edit_semco_trabalhos');
            $tecnico->add_cap('edit_published_semco_trabalhos');
            $tecnico->add_cap('delete_semco_trabalhos');
            $tecnico->add_cap('upload_files');
        }

        // ---------- Administrator ----------
        $admin = get_role('administrator');
        if ($admin) {
            foreach ($this->get_post_type_caps('enfrute_trabalho') as $cap) {
                $admin->add_cap($cap);
            }
            foreach ($this->get_post_type_caps('semco_trabalho') as $cap) {
                $admin->add_cap($cap);
            }
            foreach ($this->get_post_type_caps('sciflow_palestra') as $cap) {
                $admin->add_cap($cap);
            }
            $admin->add_cap('sciflow_review');
            $admin->add_cap('assign_sciflow_reviewers');
            $admin->add_cap('manage_sciflow');
            $admin->add_cap('manage_sciflow_tecnicos');
        }
    }

    /**
     * Remove custom roles.
     */
    public function remove_roles()
    {
        remove_role('sciflow_inscrito');
        remove_role('sciflow_speaker');
        remove_role('sciflow_revisor');
        remove_role('sciflow_editor');
        remove_role('sciflow_semco_editor');
        remove_role('sciflow_semco_revisor');
        remove_role('sciflow_enfrute_editor');
        remove_role('sciflow_enfrute_revisor');
        remove_role('sciflow_tecnico_epagri');
        remove_role('sciflow_tecnico_admin');
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
    /**
     * Refresh roles and capabilities.
     * Use this if new roles or caps are added but don't show up in DB.
     */
    public function refresh_roles() {
        $this->remove_roles();
        $this->add_roles();
        $this->add_caps();
    }
}

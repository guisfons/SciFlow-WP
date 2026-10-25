<?php
/**
 * Page registry.
 *
 * Replaces hard-coded slugs (/meus-artigos, /editor/dashboard/ ...) with
 * pages that are resolved — in this order — from:
 *  1. the page explicitly selected in SciFlow → Configurações → Páginas;
 *  2. a page created by SciFlow (meta `_sciflow_page_role`);
 *  3. a published page using the matching page template;
 *  4. the legacy URL setting, when one exists;
 *  5. the legacy slug, as a last resort (keeps old installs working).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Pages
{
    const ROLE_META = '_sciflow_page_role';

    /**
     * Request cache of resolved IDs (role => ID). Only positive results are
     * cached, so pages created later in the same request are found.
     *
     * @var array<string,int>
     */
    private static $resolved = array();

    /**
     * Clear the resolution cache.
     */
    public static function flush()
    {
        self::$resolved = array();
    }

    /**
     * Page roles.
     *
     * - template:   page template stored in `_wp_page_template` when the
     *               page is created (theme templates require a theme that
     *               declares `add_theme_support('sciflow')`).
     * - provider:   'theme' or 'plugin' (who ships the template).
     * - shortcode:  content used when the theme does not provide the template.
     * - legacy:     slug used by previous versions.
     * - legacy_url: old URL setting key, if any.
     * - required:   whether the readiness check requires this page.
     */
    public static function definitions()
    {
        return array(
            'registration_home' => array(
                'title' => __('Inscrição', 'sciflow-wp'),
                'template' => 'page-templates/template-home-inscription.php',
                'provider' => 'theme',
                'shortcode' => '',
                'legacy' => '/inscricao',
                'required' => false,
                'description' => __('Página inicial de inscrição (requer o tema SciFlow e WooCommerce).', 'sciflow-wp'),
            ),
            'submission' => array(
                'title' => __('Submissão de Trabalho', 'sciflow-wp'),
                'template' => 'page-templates/template-submission.php',
                'provider' => 'theme',
                'shortcode' => '[sciflow_submission_form]',
                'legacy' => '/submissao',
                'required' => true,
                'description' => __('Formulário de submissão de resumos.', 'sciflow-wp'),
            ),
            'author_dashboard' => array(
                'title' => __('Meus Trabalhos', 'sciflow-wp'),
                'template' => 'page-templates/template-submissions-list.php',
                'provider' => 'theme',
                'shortcode' => '[sciflow_author_dashboard]',
                'legacy' => '/meus-artigos',
                'legacy_url' => 'dashboard_url',
                'required' => true,
                'description' => __('Painel do autor com a lista dos trabalhos submetidos.', 'sciflow-wp'),
            ),
            'article_detail' => array(
                'title' => __('Detalhes do Trabalho', 'sciflow-wp'),
                'template' => 'page-templates/template-article-detail.php',
                'provider' => 'theme',
                'shortcode' => '',
                'legacy' => '/avaliar-artigo',
                'required' => false,
                'description' => __('Detalhe do trabalho para autor, revisor e editor (requer o tema SciFlow).', 'sciflow-wp'),
            ),
            'editor_dashboard' => array(
                'title' => __('Painel do Editor', 'sciflow-wp'),
                'template' => 'page-templates/template-editor-dashboard.php',
                'provider' => 'theme',
                'shortcode' => '[sciflow_editor_panel]',
                'legacy' => '/editor/dashboard/',
                'legacy_url' => 'editor_dashboard_url',
                'required' => true,
                'description' => __('Painel editorial.', 'sciflow-wp'),
            ),
            'reviewer_dashboard' => array(
                'title' => __('Painel do Revisor', 'sciflow-wp'),
                'template' => 'page-templates/template-reviewer-dashboard.php',
                'provider' => 'theme',
                'shortcode' => '[sciflow_reviewer_panel]',
                'legacy' => '/revisor/dashboard/',
                'legacy_url' => 'reviewer_dashboard_url',
                'required' => true,
                'description' => __('Painel dos revisores.', 'sciflow-wp'),
            ),
            'published_articles' => array(
                'title' => __('Trabalhos Publicados', 'sciflow-wp'),
                'template' => 'page-templates/template-published-articles.php',
                'provider' => 'theme',
                'shortcode' => '',
                'legacy' => '/trabalhos-publicados',
                'required' => false,
                'description' => __('Lista de trabalhos aprovados para a comissão (requer o tema SciFlow).', 'sciflow-wp'),
            ),
            'poster_upload' => array(
                'title' => __('Enviar Pôster', 'sciflow-wp'),
                'template' => 'template-poster-upload.php',
                'provider' => 'plugin',
                'shortcode' => '',
                'legacy' => '/enviar-poster',
                'required' => true,
                'description' => __('Envio do pôster em PDF pelos autores aprovados.', 'sciflow-wp'),
            ),
            'confirm_presentation' => array(
                'title' => __('Confirmar Apresentação', 'sciflow-wp'),
                'template' => 'sciflow-confirm-presentation',
                'provider' => 'plugin',
                'shortcode' => '',
                'legacy' => '/confirmar-apresentacao/',
                'legacy_url' => 'confirm_presentation_page_url',
                'required' => false,
                'description' => __('Destino do link de confirmação enviado por e-mail aos autores selecionados.', 'sciflow-wp'),
            ),
            'speaker' => array(
                'title' => __('Envio de Palestra', 'sciflow-wp'),
                'template' => 'page-templates/template-speaker.php',
                'provider' => 'theme',
                'shortcode' => '[sciflow_speaker_form]',
                'legacy' => '/palestrante',
                'required' => false,
                'description' => __('Envio do arquivo de palestra pelos palestrantes.', 'sciflow-wp'),
            ),
            'public_posters' => array(
                'title' => __('Pôsteres', 'sciflow-wp'),
                'template' => 'page-templates/template-public-posters.php',
                'provider' => 'theme',
                'shortcode' => '[sciflow_public_posters]',
                'legacy' => '/posteres',
                'required' => false,
                'description' => __('Galeria pública dos pôsteres aprovados.', 'sciflow-wp'),
            ),
            'certificates' => array(
                'title' => __('Certificados', 'sciflow-wp'),
                'template' => 'page-templates/template-certificados.php',
                'provider' => 'theme',
                'shortcode' => '[sciflow_certificados]',
                'legacy' => '/certificados',
                'required' => false,
                'description' => __('Download de certificados.', 'sciflow-wp'),
            ),
            'third_party' => array(
                'title' => __('Inscrição de Terceiros', 'sciflow-wp'),
                'template' => 'page-templates/template-terceiros.php',
                'provider' => 'theme',
                'shortcode' => '',
                'legacy' => '/inscricao-de-terceiros/',
                'required' => false,
                'description' => __('Inscrição feita por um "Inscritor de Terceiros" com cupom (requer o tema SciFlow e WooCommerce).', 'sciflow-wp'),
            ),
        );
    }

    /**
     * Resolve the page ID for a role (0 when not found).
     */
    public static function get_id($role)
    {
        if (isset(self::$resolved[$role])) {
            return self::$resolved[$role];
        }

        $defs = self::definitions();
        if (!isset($defs[$role])) {
            return 0;
        }

        $page_id = 0;
        $configured = SciFlow_Settings::get('page_ids', array());
        if (!empty($configured[$role]) && self::is_usable_page((int) $configured[$role])) {
            $page_id = (int) $configured[$role];
        }

        if (!$page_id) {
            $page_id = self::find_by_meta(self::ROLE_META, $role);
        }

        if (!$page_id && !empty($defs[$role]['template'])) {
            $page_id = self::find_by_meta('_wp_page_template', $defs[$role]['template']);
        }

        if ($page_id) {
            self::$resolved[$role] = $page_id;
        }
        return $page_id;
    }

    /**
     * URL for a role. Never returns an empty string.
     */
    public static function url($role)
    {
        $page_id = self::get_id($role);
        if ($page_id) {
            $url = get_permalink($page_id);
            if ($url) {
                return $url;
            }
        }

        $defs = self::definitions();
        if (!empty($defs[$role]['legacy_url'])) {
            $legacy_url = (string) SciFlow_Settings::get($defs[$role]['legacy_url'], '');
            if ('' !== $legacy_url) {
                return $legacy_url;
            }
        }

        return home_url(isset($defs[$role]) ? $defs[$role]['legacy'] : '/');
    }

    /**
     * Whether the role resolves to a real published page.
     */
    public static function exists($role)
    {
        return self::get_id($role) > 0;
    }

    /**
     * Create the pages that are missing. Idempotent: existing pages are never
     * modified or duplicated; created pages are marked with `_sciflow_page_role`.
     *
     * @param string[]|null $roles Roles to create (null = all missing).
     * @return array<string,int> Created page IDs keyed by role.
     */
    public static function create_missing($roles = null)
    {
        $created = array();
        $theme_supported = current_theme_supports('sciflow');

        foreach (self::definitions() as $role => $def) {
            if (null !== $roles && !in_array($role, (array) $roles, true)) {
                continue;
            }
            if (self::get_id($role)) {
                continue;
            }

            $uses_theme_template = 'theme' === $def['provider'];
            if ($uses_theme_template && !$theme_supported && '' === $def['shortcode']) {
                // Nothing useful can be rendered without the theme template.
                continue;
            }

            $content = '';
            if ($uses_theme_template && !$theme_supported) {
                $content = $def['shortcode'];
            }

            $page_id = wp_insert_post(array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => $def['title'],
                'post_content' => $content,
                'comment_status' => 'closed',
            ), true);

            if (is_wp_error($page_id) || !$page_id) {
                continue;
            }

            update_post_meta($page_id, self::ROLE_META, $role);
            if (!$uses_theme_template || $theme_supported) {
                update_post_meta($page_id, '_wp_page_template', $def['template']);
            }

            $created[$role] = (int) $page_id;
            self::$resolved[$role] = (int) $page_id;
        }

        if ($created) {
            $ids = (array) SciFlow_Settings::get('page_ids', array());
            SciFlow_Settings::update(array('page_ids' => array_merge($ids, $created)));
        }

        return $created;
    }

    /**
     * Find the first published page with a given meta value.
     */
    private static function find_by_meta($key, $value)
    {
        $ids = get_posts(array(
            'post_type'        => 'page',
            'post_status'      => 'publish',
            'meta_key'         => $key,
            'meta_value'       => $value,
            'fields'           => 'ids',
            'numberposts'      => 1,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'suppress_filters' => true,
        ));
        return $ids ? (int) $ids[0] : 0;
    }

    /**
     * Whether a page ID points to a published page.
     */
    private static function is_usable_page($page_id)
    {
        return $page_id > 0 && 'page' === get_post_type($page_id) && 'publish' === get_post_status($page_id);
    }
}

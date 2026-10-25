<?php
/**
 * Central access to the `sciflow_settings` option.
 *
 * Every module reads configuration through this class so defaults, secret
 * overrides (wp-config constants / environment variables) and sanitization
 * live in a single place.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Settings
{
    const OPTION = 'sciflow_settings';

    /**
     * Secrets that may be provided by a constant or environment variable
     * instead of the database. Map: setting key => constant / env name.
     */
    const SECRET_OVERRIDES = array(
        'paygo_integration_key' => 'SCIFLOW_PAYGO_INTEGRATION_KEY',
        'paygo_token'           => 'SCIFLOW_PAYGO_TOKEN',
        'paygo_webhook_secret'  => 'SCIFLOW_PAYGO_WEBHOOK_SECRET',
    );

    /**
     * Keys whose values must never be printed back in HTML.
     */
    const SECRET_KEYS = array('paygo_integration_key', 'paygo_token', 'paygo_webhook_secret');

    /**
     * Request-level cache.
     *
     * @var array|null
     */
    private static $cache = null;

    /**
     * Default values for every known setting.
     */
    public static function defaults()
    {
        return array(
            // Eventos.
            'event_enfrute_enabled'    => '1',
            'event_enfrute_label'      => 'Evento A',
            'event_enfrute_full_label' => '',
            'event_semco_enabled'      => '1',
            'event_semco_label'        => 'Evento B',
            'event_semco_full_label'   => '',
            'organizer_name'           => '',
            'tecnico_label'            => 'Técnico Conveniado',

            // Editores (destinatários adicionais).
            'enfrute_editor_email' => '',
            'semco_editor_email'   => '',

            // Páginas (IDs) — resolvidas por SciFlow_Pages.
            'page_ids' => array(),

            // URLs legadas (mantidas por compatibilidade; páginas têm prioridade).
            'dashboard_url'                 => '',
            'editor_dashboard_url'          => '',
            'reviewer_dashboard_url'        => '',
            'confirm_presentation_page_url' => '',
            'poster_template_url'           => '',

            'lgpd_consent_text' => '',

            // Inscrição / WooCommerce.
            'require_paid_registration'       => '1',
            'woo_product_ids'                 => '',
            'woo_speaker_product_ids'         => '',
            'woo_tecnico_epagri_product_ids'  => '',

            // Pagamento PayGo (legado, opcional).
            'paygo_integration_key' => '',
            'paygo_token'           => '',
            'paygo_pix_key'         => '',
            'paygo_ambiente'        => 'sandbox',
            'paygo_webhook_secret'  => '',
            'submission_price'      => 50.0,

            // Prazos.
            'article_submission_deadline' => '',
            'corrections_deadline'        => '',
            'poster_submission_deadline'  => '',
            'registration_deadline'       => '',

            // Ranking.
            'ranking_weights' => array(
                'originalidade' => 1,
                'objetividade'  => 1,
                'organizacao'   => 1,
                'metodologia'   => 1,
                'aderencia'     => 1,
            ),
            'excluded_ranking_emails' => '',

            // E-mail.
            'email_from_name'               => '',
            'email_from_address'            => '',
            'forgotten_article_email_text'  => '',
            'forgotten_poster_email_text'   => '',
            'unsubmitted_poster_email_text' => '',
            'ranking_email_text'            => '',
            'selected_date_email_text'      => '',
            'selected_date_btn_text'        => '',

            // Certificados.
            'certificate_access' => 'logged_in',
            'certificate_rate_limit' => 20,

            // Anais.
            'anais_title'             => '',
            'anais_header'            => '',
            'anais_pretextual'        => 'custom',
            'anais_catalog_html'      => '',
            'anais_presentation_html' => '',
            'event_enfrute_edition'   => '',
            'event_semco_edition'     => '',

            // Identidade visual.
            'brand_primary'       => '#1f6f78',
            'brand_primary_dark'  => '',
            'brand_secondary'     => '#c27c0e',
            'brand_text'          => '#1d2327',
            'brand_background'    => '#ffffff',
            'brand_heading_font'  => 'system-sans',
            'brand_body_font'     => 'system-sans',
            'brand_radius'        => 8,
            'brand_logo_id'       => 0,
            'brand_logo_ref'      => '',
            'brand_logo_height'   => 56,
            'anais_font'          => 'arial',
            'anais_cover_image_id'    => 0,
            'anais_title_image_id'    => 0,
            'anais_partners_image_id' => 0,

            // Uploads.
            'poster_max_mb'  => 5,
            'speaker_max_mb' => 50,

            // Ciclo de vida.
            'delete_data_on_uninstall' => '0',
        );
    }

    /**
     * All settings merged with defaults (secrets not resolved).
     */
    public static function all()
    {
        if (null === self::$cache) {
            $stored = get_option(self::OPTION, array());
            if (!is_array($stored)) {
                $stored = array();
            }
            self::$cache = array_merge(self::defaults(), $stored);
        }
        return self::$cache;
    }

    /**
     * Get one setting. Secrets are resolved from constants/env first.
     *
     * @param string $key
     * @param mixed  $default Used when the key has no default and is not stored.
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (isset(self::SECRET_OVERRIDES[$key])) {
            $external = self::external_secret($key);
            if ('' !== $external) {
                return $external;
            }
        }

        $all = self::all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        return $default;
    }

    /**
     * Whether a secret is supplied outside the database.
     */
    public static function is_externally_defined($key)
    {
        return '' !== self::external_secret($key);
    }

    /**
     * Read a secret from a constant or an environment variable.
     */
    private static function external_secret($key)
    {
        if (!isset(self::SECRET_OVERRIDES[$key])) {
            return '';
        }
        $name = self::SECRET_OVERRIDES[$key];
        if (defined($name) && is_string(constant($name)) && '' !== constant($name)) {
            return constant($name);
        }
        $env = getenv($name);
        return (is_string($env) && '' !== $env) ? $env : '';
    }

    /**
     * Flush the request cache (after updates).
     */
    public static function flush()
    {
        self::$cache = null;
    }

    /**
     * Update a subset of keys, keeping the others.
     */
    public static function update(array $values)
    {
        $stored = get_option(self::OPTION, array());
        if (!is_array($stored)) {
            $stored = array();
        }
        update_option(self::OPTION, array_merge($stored, $values));
        self::flush();
        if (class_exists('SciFlow_Branding')) {
            SciFlow_Branding::flush();
        }
    }

    /**
     * Sanitize a settings submission.
     *
     * Values not present in the submission are preserved, so partial forms
     * never wipe unrelated settings. Secret fields left blank keep their
     * current value; a dedicated "clear" checkbox removes them.
     *
     * @param mixed $input Raw input from options.php.
     * @return array
     */
    public static function sanitize($input)
    {
        $current = get_option(self::OPTION, array());
        if (!is_array($current)) {
            $current = array();
        }
        if (!is_array($input)) {
            return $current;
        }

        $clean = $current;

        $text_keys = array(
            'event_enfrute_label', 'event_enfrute_full_label', 'event_semco_label', 'event_semco_full_label',
            'organizer_name', 'tecnico_label', 'event_enfrute_edition', 'event_semco_edition', 'paygo_pix_key', 'selected_date_btn_text', 'email_from_name', 'anais_title',
            'article_submission_deadline', 'corrections_deadline', 'poster_submission_deadline', 'registration_deadline',
        );
        foreach ($text_keys as $key) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = sanitize_text_field(wp_unslash($input[$key]));
            }
        }

        foreach (array('article_submission_deadline', 'corrections_deadline', 'poster_submission_deadline', 'registration_deadline') as $key) {
            if (!empty($clean[$key]) && false === strtotime($clean[$key])) {
                add_settings_error(self::OPTION, 'invalid_' . $key, sprintf(
                    /* translators: %s: field key */
                    __('Data inválida em "%s". O valor foi descartado.', 'sciflow-wp'),
                    $key
                ));
                $clean[$key] = '';
            }
        }

        $html_keys = array(
            'lgpd_consent_text', 'forgotten_article_email_text', 'forgotten_poster_email_text',
            'unsubmitted_poster_email_text', 'ranking_email_text', 'selected_date_email_text', 'anais_header',
            'anais_catalog_html', 'anais_presentation_html',
        );
        foreach ($html_keys as $key) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = wp_kses_post(wp_unslash($input[$key]));
            }
        }

        $url_keys = array('dashboard_url', 'editor_dashboard_url', 'reviewer_dashboard_url', 'poster_template_url', 'confirm_presentation_page_url');
        foreach ($url_keys as $key) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = esc_url_raw(wp_unslash($input[$key]));
            }
        }

        $bool_keys = array('event_enfrute_enabled', 'event_semco_enabled', 'require_paid_registration', 'delete_data_on_uninstall');
        // Checkboxes are only evaluated when their form section was rendered.
        if (!empty($input['_sections']) && is_array($input['_sections'])) {
            $sections = array_map('sanitize_key', $input['_sections']);
            $bool_sections = array(
                'event_enfrute_enabled'     => 'eventos',
                'event_semco_enabled'       => 'eventos',
                'require_paid_registration' => 'inscricao',
                'delete_data_on_uninstall'  => 'avancado',
            );
            foreach ($bool_keys as $key) {
                if (in_array($bool_sections[$key], $sections, true)) {
                    $clean[$key] = empty($input[$key]) ? '0' : '1';
                }
            }
        }

        foreach (array('enfrute_editor_email', 'semco_editor_email') as $key) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = self::sanitize_email_list(wp_unslash($input[$key]));
            }
        }

        if (array_key_exists('email_from_address', $input)) {
            $email = sanitize_email(wp_unslash($input['email_from_address']));
            if ('' !== trim((string) $input['email_from_address']) && !is_email($email)) {
                add_settings_error(self::OPTION, 'invalid_from', __('E-mail de remetente inválido. O valor foi descartado.', 'sciflow-wp'));
                $email = '';
            }
            $clean['email_from_address'] = $email;
        }

        foreach (array('woo_product_ids', 'woo_speaker_product_ids', 'woo_tecnico_epagri_product_ids') as $key) {
            if (array_key_exists($key, $input)) {
                $ids = array_filter(array_map('absint', explode(',', (string) wp_unslash($input[$key]))));
                $clean[$key] = implode(',', array_unique($ids));
            }
        }

        if (array_key_exists('paygo_ambiente', $input)) {
            $clean['paygo_ambiente'] = in_array($input['paygo_ambiente'], array('sandbox', 'producao'), true) ? $input['paygo_ambiente'] : 'sandbox';
        }

        if (array_key_exists('submission_price', $input)) {
            $clean['submission_price'] = max(0, (float) str_replace(',', '.', (string) $input['submission_price']));
        }

        if (array_key_exists('anais_pretextual', $input)) {
            $clean['anais_pretextual'] = in_array($input['anais_pretextual'], array('custom', 'legacy'), true) ? $input['anais_pretextual'] : 'custom';
        }

        if (array_key_exists('certificate_access', $input)) {
            $clean['certificate_access'] = in_array($input['certificate_access'], array('logged_in', 'email'), true) ? $input['certificate_access'] : 'logged_in';
        }

        foreach (array('brand_primary', 'brand_primary_dark', 'brand_secondary', 'brand_text', 'brand_background') as $key) {
            if (array_key_exists($key, $input)) {
                $raw = trim((string) wp_unslash($input[$key]));
                $color = sanitize_hex_color($raw);
                if ('' !== $raw && !$color) {
                    add_settings_error(self::OPTION, 'invalid_' . $key, sprintf(__('Cor inválida em "%s" (use o formato #RRGGBB).', 'sciflow-wp'), $key));
                    continue;
                }
                $clean[$key] = $color ? strtolower($color) : ('brand_primary_dark' === $key ? '' : self::defaults()[$key]);
            }
        }
        $fonts = class_exists('SciFlow_Branding') ? array_keys(SciFlow_Branding::fonts()) : array();
        foreach (array('brand_heading_font', 'brand_body_font', 'anais_font') as $key) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = in_array($input[$key], $fonts, true) ? $input[$key] : self::defaults()[$key];
            }
        }
        foreach (array('brand_logo_id', 'anais_cover_image_id', 'anais_title_image_id', 'anais_partners_image_id') as $key) {
            if (array_key_exists($key, $input)) {
                $id = absint($input[$key]);
                $clean[$key] = ($id && wp_attachment_is_image($id)) ? $id : 0;
                if ('brand_logo_id' === $key && $clean[$key]) {
                    $clean['brand_logo_ref'] = '';
                }
            }
        }
        if (!empty($input['brand_logo_ref_clear'])) {
            $clean['brand_logo_ref'] = '';
        }
        if (class_exists('SciFlow_Branding')) {
            SciFlow_Branding::flush();
        }

        $int_keys = array(
            'brand_radius'           => array(0, 24),
            'brand_logo_height'      => array(24, 160),
            'certificate_rate_limit' => array(1, 1000),
            'poster_max_mb'          => array(1, 100),
            'speaker_max_mb'         => array(1, 200),
        );
        foreach ($int_keys as $key => $range) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = min($range[1], max($range[0], absint($input[$key])));
            }
        }

        if (isset($input['ranking_weights']) && is_array($input['ranking_weights'])) {
            foreach (array('originalidade', 'objetividade', 'organizacao', 'metodologia', 'aderencia') as $key) {
                $w = isset($input['ranking_weights'][$key]) ? str_replace(',', '.', (string) $input['ranking_weights'][$key]) : 1;
                $w = (float) $w;
                $clean['ranking_weights'][$key] = $w > 0 ? $w : 1;
            }
        }

        if (array_key_exists('excluded_ranking_emails', $input)) {
            $clean['excluded_ranking_emails'] = sanitize_textarea_field(wp_unslash($input['excluded_ranking_emails']));
        }

        if (isset($input['page_ids']) && is_array($input['page_ids'])) {
            $pages = array();
            foreach (SciFlow_Pages::definitions() as $role => $def) {
                if (!isset($input['page_ids'][$role])) {
                    continue;
                }
                $page_id = absint($input['page_ids'][$role]);
                if ($page_id && 'page' === get_post_type($page_id)) {
                    $pages[$role] = $page_id;
                }
            }
            $clean['page_ids'] = $pages;
        }

        // Secrets: blank keeps the current value; "clear" removes it.
        foreach (self::SECRET_KEYS as $key) {
            if (!empty($input[$key . '_clear'])) {
                $clean[$key] = '';
                continue;
            }
            if (isset($input[$key]) && '' !== trim((string) $input[$key])) {
                $clean[$key] = sanitize_text_field(wp_unslash($input[$key]));
            }
        }

        self::flush();
        return $clean;
    }

    /**
     * Sanitize a comma-separated list of e-mails.
     */
    public static function sanitize_email_list($string)
    {
        $clean = array();
        foreach (explode(',', (string) $string) as $email) {
            $email = sanitize_email(trim($email));
            if ($email && is_email($email)) {
                $clean[] = $email;
            }
        }
        return implode(', ', array_unique($clean));
    }

    /**
     * Parse a comma-separated list of product IDs.
     *
     * @return int[]
     */
    public static function get_id_list($key)
    {
        return array_values(array_filter(array_map('absint', explode(',', (string) self::get($key, '')))));
    }

    /**
     * Mask a secret for display ("••••1234").
     */
    public static function mask($value)
    {
        $value = (string) $value;
        if ('' === $value) {
            return '';
        }
        $tail = strlen($value) > 8 ? substr($value, -4) : '';
        return str_repeat('•', 8) . $tail;
    }
}

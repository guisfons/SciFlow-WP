<?php
/**
 * Installation, upgrades and data migrations.
 *
 * Every routine here is idempotent: running activation or an upgrade twice
 * never duplicates pages, roles, options or cron events, and never deletes
 * user content.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Installer
{
    const VERSION_OPTION = 'sciflow_version';
    const MIGRATIONS_OPTION = 'sciflow_migrations';

    /**
     * Cron hooks owned by the plugin.
     */
    const CRON_HOOKS = array(
        'sciflow_check_confirmation_deadlines',
        'sciflow_check_corrections_deadlines',
        'sciflow_check_poster_deadlines',
    );

    /**
     * Activation entry point (handles multisite network activation).
     *
     * @param bool $network_wide
     */
    public static function activate($network_wide = false)
    {
        $fresh = '' === get_option(self::VERSION_OPTION, '');
        if (is_multisite() && $network_wide) {
            foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $site_id) {
                switch_to_blog($site_id);
                self::install_site();
                restore_current_blog();
            }
            return;
        }
        self::install_site();

        // First activation on this site: open the setup wizard.
        if ($fresh) {
            SciFlow_Setup_Wizard::request_redirect();
        } elseif ('' === get_option(SciFlow_Setup_Wizard::STATUS_OPTION, '')) {
            // Existing installations keep working; the wizard stays available.
            update_option(SciFlow_Setup_Wizard::STATUS_OPTION, 'legacy', false);
        }
    }

    /**
     * Install or repair the plugin on the current site.
     */
    public static function install_site()
    {
        $previous = get_option(self::VERSION_OPTION, '');

        $roles = new SciFlow_Roles();
        $roles->add_roles();
        if ('' === $previous) {
            // Fresh install: grant the full default capability set.
            $roles->add_caps();
        } else {
            $roles->ensure_core_caps();
        }

        (new SciFlow_Enfrute_CPT())->register();
        (new SciFlow_Semco_CPT())->register();
        (new SciFlow_Palestra_CPT())->register();
        flush_rewrite_rules();

        self::schedule_events();
        self::protect_storage();
        self::run_migrations($previous);

        if ('' === $previous) {
            add_option('sciflow_installed_at', time(), '', false);
            update_option('sciflow_show_onboarding', '1', false);
        }

        update_option(self::VERSION_OPTION, SCIFLOW_VERSION);
    }

    /**
     * Run on every load: apply pending upgrades when the code version changed.
     */
    public static function maybe_upgrade()
    {
        $stored = get_option(self::VERSION_OPTION, '');
        if ($stored === SCIFLOW_VERSION) {
            return;
        }

        // Avoid concurrent upgrades on busy sites.
        if (get_transient('sciflow_upgrading')) {
            return;
        }
        set_transient('sciflow_upgrading', 1, 5 * MINUTE_IN_SECONDS);

        self::install_site();

        delete_transient('sciflow_upgrading');
    }

    /**
     * Deactivation: clear scheduled events. Data is kept.
     */
    public static function deactivate()
    {
        foreach (self::CRON_HOOKS as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        flush_rewrite_rules();
    }

    /**
     * Schedule cron events once.
     */
    public static function schedule_events()
    {
        foreach (self::CRON_HOOKS as $hook) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + 60, 'hourly', $hook);
            }
        }
    }

    /**
     * Deny direct web access to directories that hold personal data.
     *
     * @return array<string,bool> Directory => protected.
     */
    public static function protect_storage()
    {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error'])) {
            return array();
        }
        $base = trailingslashit($uploads['basedir']);
        $result = array();
        foreach (array('certificados', 'sciflow-certificates', 'sciflow-private') as $dir) {
            $result[$dir] = SciFlow_Security::protect_directory($base . $dir);
        }
        return $result;
    }

    /**
     * Apply data migrations that have not run yet.
     *
     * @param string $previous Version stored before this run ('' on fresh install).
     */
    private static function run_migrations($previous)
    {
        $done = get_option(self::MIGRATIONS_OPTION, array());
        if (!is_array($done)) {
            $done = array();
        }

        $migrations = array(
            '1.1.0-legacy-settings' => array(__CLASS__, 'migrate_110_legacy_settings'),
            '1.1.0-legacy-brand'    => array(__CLASS__, 'migrate_110_legacy_brand'),
        );

        foreach ($migrations as $id => $callback) {
            if (in_array($id, $done, true)) {
                continue;
            }
            call_user_func($callback, $previous);
            $done[] = $id;
        }

        update_option(self::MIGRATIONS_OPTION, $done, false);
    }

    /**
     * 1.1.0: installs that existed before the settings screen became the
     * source of truth keep their original texts and behaviour. Only keys
     * that are not stored yet are written; nothing is overwritten.
     *
     * @param string $previous
     */
    public static function migrate_110_legacy_brand($previous)
    {
        if ('' === $previous) {
            return; // Fresh install: neutral identity.
        }
        $stored = get_option(SciFlow_Settings::OPTION, array());
        if (!is_array($stored)) {
            $stored = array();
        }
        // The look the site had before the identity became configurable.
        $legacy = array(
            'brand_primary'      => '#3cac34',
            'brand_primary_dark' => '#0d6e43',
            'brand_secondary'    => '#fbc02d',
            'brand_text'         => '#333333',
            'brand_background'   => '#ffffff',
            'brand_heading_font' => 'system-sans',
            'brand_body_font'    => 'system-sans',
            'brand_radius'       => 8,
            'brand_logo_ref'     => 'theme:assets/images/logo.webp',
        );
        $changed = false;
        foreach ($legacy as $key => $value) {
            if (!array_key_exists($key, $stored)) {
                $stored[$key] = $value;
                $changed = true;
            }
        }
        if ($changed) {
            update_option(SciFlow_Settings::OPTION, $stored);
            SciFlow_Settings::flush();
        }
    }

    public static function migrate_110_legacy_settings($previous)
    {
        if ('' === $previous) {
            return; // Fresh install: generic defaults apply.
        }

        $stored = get_option(SciFlow_Settings::OPTION, array());
        if (!is_array($stored)) {
            $stored = array();
        }

        $legacy = array(
            'event_enfrute_label'      => 'Enfrute',
            'event_enfrute_full_label' => 'Enfrute — Encontro Nacional sobre Fruticultura de Clima Temperado',
            'event_semco_label'        => 'Semco',
            'event_semco_full_label'   => 'Semco — Seminário Catarinense de Olericultura',
            'tecnico_label'            => 'Técnico Epagri',
            'event_enfrute_edition'    => 'XIX',
            'event_semco_edition'      => 'III',
            'anais_pretextual'         => 'legacy',
            'anais_title'              => 'Anais – XIX Enfrute / III Semco 2026',
            'anais_header'             => 'XIX Encontro Nacional sobre Fruticultura de Clima Temperado (XIX Enfrute)<br>III Seminário Catarinense de Olericultura (III Semco)<br>28, 29 e 30 de julho de 2026 - Parque da Maçã - Fraiburgo/SC',
            // Before 1.1.0 anyone could download a certificate by e-mail.
            'certificate_access'       => 'email',
        );

        $changed = false;
        foreach ($legacy as $key => $value) {
            if (!array_key_exists($key, $stored)) {
                $stored[$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            update_option(SciFlow_Settings::OPTION, $stored);
            SciFlow_Settings::flush();
        }
    }
}

<?php
/**
 * Shared look of the SciFlow admin screens: top bar with navigation and
 * readiness status, scoped stylesheet and accent colour from the brand.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Admin_UI
{
    public static function init()
    {
        add_filter('admin_body_class', array(__CLASS__, 'body_class'));
        add_action('in_admin_header', array(__CLASS__, 'top_bar'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
    }

    /**
     * Whether the current admin screen belongs to SciFlow.
     */
    public static function is_sciflow_screen()
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        return 0 === strpos($page, 'sciflow');
    }

    public static function body_class($classes)
    {
        return self::is_sciflow_screen() ? $classes . ' sciflow-admin' : $classes;
    }

    public static function enqueue()
    {
        if (!self::is_sciflow_screen()) {
            return;
        }
        wp_enqueue_style('sciflow-admin-ui', SCIFLOW_URL . 'admin/css/admin-ui.css', array(), SCIFLOW_VERSION);
        $b = SciFlow_Branding::get();
        $accent = $b['primary'];
        // Keep the admin accent readable on white.
        if (SciFlow_Branding::ratio($accent, '#ffffff') < 3) {
            $accent = SciFlow_Branding::mix($accent, '#000000', 0.45);
        }
        wp_add_inline_style('sciflow-admin-ui', sprintf(
            '.sciflow-admin{--sfa-accent:%1$s;--sfa-accent-dark:%2$s;--sfa-accent-soft:%3$s;--sfa-accent-contrast:%4$s;}',
            SciFlow_Branding::css_value($accent),
            SciFlow_Branding::css_value(SciFlow_Branding::mix($accent, '#000000', 0.2)),
            SciFlow_Branding::css_value(SciFlow_Branding::mix($accent, '#ffffff', 0.9)),
            SciFlow_Branding::css_value(SciFlow_Branding::contrast($accent))
        ));
    }

    /**
     * Top bar: brand, navigation between SciFlow screens, readiness chip.
     */
    public static function top_bar()
    {
        if (!self::is_sciflow_screen()) {
            return;
        }
        global $submenu;
        $current = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $items = isset($submenu['sciflow-settings']) ? $submenu['sciflow-settings'] : array();

        $ready = null;
        if (current_user_can('manage_options')) {
            $cached = get_transient('sciflow_admin_ready');
            if (false === $cached) {
                $checks = SciFlow_Diagnostics::run();
                $summary = SciFlow_Diagnostics::summary($checks);
                $cached = array('ready' => SciFlow_Diagnostics::is_ready($checks) ? 1 : 0, 'errors' => $summary['error'], 'warnings' => $summary['warning']);
                set_transient('sciflow_admin_ready', $cached, 5 * MINUTE_IN_SECONDS);
            }
            $ready = $cached;
        }

        $logo = SciFlow_Branding::get()['logo_url'];
        $events = wp_list_pluck(SciFlow_Events::enabled(), 'label');
        ?>
        <div class="sfa-topbar" role="navigation" aria-label="<?php esc_attr_e('SciFlow', 'sciflow-wp'); ?>">
            <div class="sfa-topbar-brand">
                <?php if ($logo) : ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="">
                <?php else : ?>
                    <span class="sfa-mark dashicons dashicons-welcome-learn-more" aria-hidden="true"></span>
                <?php endif; ?>
                <span>
                    <strong>SciFlow</strong>
                    <?php if ($events) : ?><small><?php echo esc_html(implode(' · ', $events)); ?></small><?php endif; ?>
                </span>
            </div>
            <nav class="sfa-topbar-nav">
                <?php foreach ($items as $item) :
                    if (!current_user_can($item[1])) {
                        continue;
                    }
                    $slug = $item[2];
                    $label = wp_strip_all_tags(preg_replace('/^[^\pL\d]+/u', '', $item[0]));
                    ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . $slug)); ?>" class="<?php echo $slug === $current ? 'is-active' : ''; ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if (null !== $ready) : ?>
                <a class="sfa-status <?php echo $ready['ready'] ? 'is-ok' : 'is-error'; ?>" href="<?php echo esc_url(admin_url('admin.php?page=sciflow-diagnostics')); ?>">
                    <?php if ($ready['ready']) : ?>
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e('Pronto para uso', 'sciflow-wp'); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span><?php echo esc_html(sprintf(_n('%d pendência', '%d pendências', $ready['errors'], 'sciflow-wp'), $ready['errors'])); ?>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Clear the cached readiness (after settings changes).
     */
    public static function flush_status()
    {
        delete_transient('sciflow_admin_ready');
    }
}

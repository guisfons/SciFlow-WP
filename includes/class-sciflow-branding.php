<?php
/**
 * Visual identity (brand) tokens.
 *
 * Single source of truth for colors, typography, logo and images used by the
 * plugin's front-end, e-mails, certificates and Anais, and by the SciFlow
 * theme. Everything derives from a few settings; shades and contrast colors
 * are computed so any brand color stays readable.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Branding
{
    /** @var array|null */
    private static $cache = null;

    /**
     * Font choices: key => [label, CSS stack, Google Fonts family or ''].
     * Google fonts are only requested when selected (external request; see docs).
     */
    public static function fonts()
    {
        return array(
            'system-sans'  => array(__('Padrão do sistema (sem serifa)', 'sciflow-wp'), '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif', ''),
            'system-serif' => array(__('Padrão do sistema (serifada)', 'sciflow-wp'), 'Georgia, "Times New Roman", Times, serif', ''),
            'arial'        => array('Arial / Helvetica', 'Arial, "Liberation Sans", Helvetica, sans-serif', ''),
            'montserrat'   => array('Montserrat (Google Fonts)', '"Montserrat", Arial, sans-serif', 'Montserrat:wght@400;600;700;800'),
            'inter'        => array('Inter (Google Fonts)', '"Inter", Arial, sans-serif', 'Inter:wght@400;600;700'),
            'open-sans'    => array('Open Sans (Google Fonts)', '"Open Sans", Arial, sans-serif', 'Open+Sans:wght@400;600;700'),
            'lato'         => array('Lato (Google Fonts)', '"Lato", Arial, sans-serif', 'Lato:wght@400;700;900'),
            'source-sans'  => array('Source Sans 3 (Google Fonts)', '"Source Sans 3", Arial, sans-serif', 'Source+Sans+3:wght@400;600;700'),
            'merriweather' => array('Merriweather (Google Fonts)', '"Merriweather", Georgia, serif', 'Merriweather:wght@400;700'),
        );
    }

    /**
     * Resolved brand values (settings + derived shades).
     *
     * @return array<string,string>
     */
    public static function get()
    {
        if (null !== self::$cache) {
            return self::$cache;
        }
        $hex = function ($key, $fallback) {
            $v = sanitize_hex_color((string) SciFlow_Settings::get($key, ''));
            return $v ? strtolower($v) : $fallback;
        };
        $primary = $hex('brand_primary', '#1f6f78');
        $secondary = $hex('brand_secondary', '#c27c0e');
        $text = $hex('brand_text', '#1d2327');
        $bg = $hex('brand_background', '#ffffff');
        $fonts = self::fonts();
        $hfont = (string) SciFlow_Settings::get('brand_heading_font', 'system-sans');
        $bfont = (string) SciFlow_Settings::get('brand_body_font', 'system-sans');
        $hfont = isset($fonts[$hfont]) ? $hfont : 'system-sans';
        $bfont = isset($fonts[$bfont]) ? $bfont : 'system-sans';

        $primary_dark = $hex('brand_primary_dark', '') ?: self::mix($primary, '#000000', 0.25);

        self::$cache = array(
            'primary'           => $primary,
            'primary_dark'      => $primary_dark,
            'primary_light'     => self::mix($primary, '#ffffff', 0.88),
            'primary_contrast'  => self::contrast($primary),
            'primary_rgb'       => implode(', ', self::rgb($primary)),
            'secondary'         => $secondary,
            'secondary_dark'    => self::mix($secondary, '#000000', 0.2),
            'secondary_contrast' => self::contrast($secondary),
            'secondary_rgb'     => implode(', ', self::rgb($secondary)),
            'text'              => $text,
            'muted'             => self::mix($text, $bg, 0.45),
            'border'            => self::mix($text, $bg, 0.85),
            'background'        => $bg,
            'surface'           => self::mix($primary, $bg, 0.96),
            'heading_font_key'  => $hfont,
            'body_font_key'     => $bfont,
            'heading_font'      => $fonts[$hfont][1],
            'body_font'         => $fonts[$bfont][1],
            'radius'            => min(24, max(0, (int) SciFlow_Settings::get('brand_radius', 8))) . 'px',
            'logo_url'          => self::logo_url(),
            'logo_height'       => min(160, max(24, (int) SciFlow_Settings::get('brand_logo_height', 56))) . 'px',
        );
        return self::$cache;
    }

    /**
     * Register the brand stylesheet (CSS variables + optional web font).
     * Themes can depend on the 'sciflow-brand' handle.
     */
    public static function init()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 5);
        add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue'), 5);
    }

    public static function enqueue()
    {
        $fonts = self::google_fonts_url();
        $deps = array();
        if ($fonts) {
            wp_register_style('sciflow-brand-fonts', $fonts, array(), null);
            $deps[] = 'sciflow-brand-fonts';
        }
        wp_register_style('sciflow-brand', false, $deps, SCIFLOW_VERSION);
        wp_enqueue_style('sciflow-brand');
        wp_add_inline_style('sciflow-brand', self::css_variables());
    }

    /**
     * Flush the request cache (after saving).
     */
    public static function flush()
    {
        self::$cache = null;
    }

    /**
     * Logo URL: media library attachment, or a bundled theme asset reference
     * ("theme:path/to/file") kept for installs migrated from 1.0.x.
     */
    public static function logo_url()
    {
        $id = (int) SciFlow_Settings::get('brand_logo_id', 0);
        if ($id) {
            $url = wp_get_attachment_image_url($id, 'full');
            if ($url) {
                return $url;
            }
        }
        $ref = (string) SciFlow_Settings::get('brand_logo_ref', '');
        if (0 === strpos($ref, 'theme:')) {
            $rel = ltrim(substr($ref, 6), '/');
            if (file_exists(trailingslashit(get_template_directory()) . $rel)) {
                return trailingslashit(get_template_directory_uri()) . $rel;
            }
        }
        return '';
    }

    /**
     * URL of a configured image setting (attachment ID), or ''.
     */
    public static function image_url($key)
    {
        $id = (int) SciFlow_Settings::get($key, 0);
        return $id ? (string) wp_get_attachment_image_url($id, 'full') : '';
    }

    /**
     * Google Fonts stylesheet URL for the selected fonts ('' when none).
     */
    public static function google_fonts_url()
    {
        $b = self::get();
        $fonts = self::fonts();
        $families = array();
        foreach (array_unique(array($b['heading_font_key'], $b['body_font_key'])) as $key) {
            if ('' !== $fonts[$key][2]) {
                $families[] = 'family=' . $fonts[$key][2];
            }
        }
        return $families ? 'https://fonts.googleapis.com/css2?' . implode('&', $families) . '&display=swap' : '';
    }

    /**
     * CSS custom properties for the front-end (plugin + theme).
     */
    public static function css_variables()
    {
        $b = self::get();
        $vars = array(
            '--brand-primary'            => $b['primary'],
            '--brand-primary-dark'       => $b['primary_dark'],
            '--brand-primary-light'      => $b['primary_light'],
            '--brand-primary-contrast'   => $b['primary_contrast'],
            '--brand-primary-rgb'        => $b['primary_rgb'],
            '--brand-secondary'          => $b['secondary'],
            '--brand-secondary-dark'     => $b['secondary_dark'],
            '--brand-secondary-contrast' => $b['secondary_contrast'],
            '--brand-text'               => $b['text'],
            '--brand-muted'              => $b['muted'],
            '--brand-border'             => $b['border'],
            '--brand-background'         => $b['background'],
            '--brand-surface'            => $b['surface'],
            '--brand-heading-font'       => $b['heading_font'],
            '--brand-body-font'          => $b['body_font'],
            '--brand-radius'             => $b['radius'],
            '--brand-logo-height'        => $b['logo_height'],
            // Plugin component aliases.
            '--sciflow-primary'          => $b['primary'],
            '--sciflow-primary-light'    => self::mix($b['primary'], '#ffffff', 0.3),
            '--sciflow-primary-dark'     => $b['primary_dark'],
            '--sciflow-secondary'        => $b['secondary'],
            '--sciflow-font'             => $b['body_font'],
            '--sciflow-radius'           => $b['radius'],
        );
        $css = ':root{';
        foreach ($vars as $k => $v) {
            $css .= $k . ':' . self::css_value($v) . ';';
        }
        return $css . '}';
    }

    /**
     * Values for inline-styled e-mails (no CSS variables in mail clients).
     */
    public static function email()
    {
        $b = self::get();
        return array(
            'primary'   => $b['primary'],
            'primary2'  => self::mix($b['primary'], '#ffffff', 0.25),
            'contrast'  => $b['primary_contrast'],
            'surface'   => self::mix($b['primary'], '#ffffff', 0.94),
            'text'      => $b['text'],
            'muted'     => $b['muted'],
            'font'      => $b['body_font'],
            'logo_url'  => $b['logo_url'],
        );
    }

    /**
     * Make a value safe inside a CSS declaration (keeps quotes for font
     * names; removes characters that could close the rule or the tag).
     */
    public static function css_value($value)
    {
        return trim(preg_replace('/[<>{};\\\\]/', '', (string) $value));
    }

    /**
     * Mix two hex colors. $weight is the share of $b (0..1).
     */
    public static function mix($a, $b, $weight)
    {
        $ra = self::rgb($a);
        $rb = self::rgb($b);
        $out = array();
        for ($i = 0; $i < 3; $i++) {
            $out[] = (int) round($ra[$i] * (1 - $weight) + $rb[$i] * $weight);
        }
        return sprintf('#%02x%02x%02x', $out[0], $out[1], $out[2]);
    }

    /**
     * Black or white, whichever reads better on $hex (WCAG relative luminance).
     */
    public static function contrast($hex)
    {
        list($r, $g, $b) = array_map(function ($c) {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }, self::rgb($hex));
        $l = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        return ($l > 0.42) ? '#111111' : '#ffffff';
    }

    /**
     * WCAG contrast ratio between two colors.
     */
    public static function ratio($a, $b)
    {
        $lum = function ($hex) {
            list($r, $g, $bb) = array_map(function ($c) {
                $c /= 255;
                return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
            }, self::rgb($hex));
            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $bb;
        };
        $l1 = $lum($a);
        $l2 = $lum($b);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /**
     * @return int[] [r, g, b]
     */
    public static function rgb($hex)
    {
        $hex = ltrim((string) $hex, '#');
        if (3 === strlen($hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return array(0, 0, 0);
        }
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }
}

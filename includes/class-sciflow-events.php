<?php
/**
 * Event registry.
 *
 * SciFlow manages two event "slots". Their internal identifiers
 * (`enfrute`, `semco`) and post types (`enfrute_trabalhos`, `semco_trabalhos`)
 * are kept for compatibility with existing data; everything visible to users
 * (names, full titles, availability) comes from the settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Events
{
    /**
     * Fixed slot definitions: slug => post type.
     */
    const SLOTS = array(
        'enfrute' => 'enfrute_trabalhos',
        'semco'   => 'semco_trabalhos',
    );

    /**
     * All events (enabled or not), with labels resolved from settings.
     *
     * @return array<string, array{slug:string, post_type:string, label:string, full_label:string, enabled:bool}>
     */
    public static function all()
    {
        $events = array();
        foreach (self::SLOTS as $slug => $post_type) {
            $label = trim((string) SciFlow_Settings::get('event_' . $slug . '_label', ''));
            if ('' === $label) {
                $label = ucfirst($slug);
            }
            $full = trim((string) SciFlow_Settings::get('event_' . $slug . '_full_label', ''));
            $events[$slug] = array(
                'slug'       => $slug,
                'post_type'  => $post_type,
                'label'      => $label,
                'full_label' => '' !== $full ? $full : $label,
                'enabled'    => '1' === (string) SciFlow_Settings::get('event_' . $slug . '_enabled', '1'),
            );
        }

        /**
         * Filter the event registry (labels only; slugs and post types are fixed).
         *
         * @param array $events
         */
        return apply_filters('sciflow_events', $events);
    }

    /**
     * Enabled events only.
     */
    public static function enabled()
    {
        return array_filter(self::all(), function ($event) {
            return $event['enabled'];
        });
    }

    /**
     * Whether a slug is a known event.
     */
    public static function exists($slug)
    {
        return isset(self::SLOTS[$slug]);
    }

    /**
     * Short label for an event slug.
     */
    public static function label($slug)
    {
        $all = self::all();
        return isset($all[$slug]) ? $all[$slug]['label'] : (string) $slug;
    }

    /**
     * Full label for an event slug (used in e-mails and certificates).
     */
    public static function full_label($slug)
    {
        $all = self::all();
        return isset($all[$slug]) ? $all[$slug]['full_label'] : (string) $slug;
    }

    /**
     * Submission post types.
     *
     * @return string[]
     */
    public static function post_types()
    {
        return array_values(self::SLOTS);
    }
}

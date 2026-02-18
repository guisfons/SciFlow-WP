<?php
/**
 * Ranking calculations and selection logic.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Ranking
{

    /**
     * Get ranked articles for a specific event.
     *
     * @param string $event 'enfrute' or 'senco'.
     * @return array Sorted posts with ranking data.
     */
    public function get_event_ranking($event)
    {
        $post_type = SciFlow_Status_Manager::get_post_type_for_event($event);
        if (!$post_type) {
            return array();
        }

        return $this->query_ranked($post_type);
    }

    /**
     * Get general ranking (both events combined).
     */
    public function get_general_ranking()
    {
        $enfrute = $this->query_ranked('enfrute_trabalhos');
        $senco = $this->query_ranked('senco_trabalhos');
        $all = array_merge($enfrute, $senco);

        // Sort descending by score.
        usort($all, function ($a, $b) {
            $sa = (float) get_post_meta($a->ID, '_sciflow_ranking_score', true);
            $sb = (float) get_post_meta($b->ID, '_sciflow_ranking_score', true);
            return $sb <=> $sa;
        });

        return $all;
    }

    /**
     * Select top works for presentation.
     *
     * @param int $per_event  Number of top works per event (default 6).
     * @param int $general    Number of top works in general ranking (default 3).
     */
    public function select_top_works($per_event = 6, $general = 3)
    {
        $selected = array();

        // Per event.
        foreach (array('enfrute', 'senco') as $event) {
            $ranking = $this->get_event_ranking($event);
            $count = 0;

            foreach ($ranking as $post) {
                if ($count >= $per_event)
                    break;

                $already_selected = get_post_meta($post->ID, '_sciflow_selected_for_presentation', true);
                if ($already_selected) {
                    $selected[] = $post->ID;
                    $count++;
                    continue;
                }

                $confirmed = get_post_meta($post->ID, '_sciflow_presentation_confirmed', true);
                $status = get_post_meta($post->ID, '_sciflow_status', true);

                // Skip rejected confirmations (timed out).
                if ($status === 'reprovado')
                    continue;

                update_post_meta($post->ID, '_sciflow_selected_for_presentation', true);
                $selected[] = $post->ID;
                $count++;
            }
        }

        // General top (excluding already selected).
        $general_ranking = $this->get_general_ranking();
        $general_count = 0;

        foreach ($general_ranking as $post) {
            if ($general_count >= $general)
                break;
            if (in_array($post->ID, $selected, true))
                continue;

            update_post_meta($post->ID, '_sciflow_selected_for_presentation', true);
            $selected[] = $post->ID;
            $general_count++;
        }

        return $selected;
    }

    /**
     * Set confirmation deadline and notify authors.
     */
    public function notify_selected_authors($post_ids)
    {
        $email = new SciFlow_Email();
        $deadline = gmdate('Y-m-d\TH:i:s\Z', strtotime('+3 days'));

        foreach ($post_ids as $post_id) {
            $status = get_post_meta($post_id, '_sciflow_status', true);
            if ($status !== 'aprovado' && $status !== 'poster_enviado') {
                continue;
            }

            update_post_meta($post_id, '_sciflow_confirmation_deadline', $deadline);
            update_post_meta($post_id, '_sciflow_status', 'aguardando_confirmacao');

            $email->send_confirmation_needed($post_id);
        }
    }

    /**
     * Confirm presentation for an article.
     */
    public function confirm_presentation($post_id)
    {
        $user_id = get_current_user_id();
        $author = (int) get_post_meta($post_id, '_sciflow_author_id', true);

        if ($user_id !== $author) {
            return new WP_Error('unauthorized', __('Apenas o autor pode confirmar.', 'sciflow-wp'));
        }

        $status = get_post_meta($post_id, '_sciflow_status', true);
        if ($status !== 'aguardando_confirmacao') {
            return new WP_Error('invalid_status', __('Confirmação não solicitada.', 'sciflow-wp'));
        }

        update_post_meta($post_id, '_sciflow_presentation_confirmed', true);
        update_post_meta($post_id, '_sciflow_status', 'confirmado');

        return true;
    }

    /**
     * WP-Cron callback: check deadlines and escalate.
     */
    public function check_deadlines()
    {
        $now = current_time('timestamp', true);

        foreach (array('enfrute_trabalhos', 'senco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type' => $pt,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => '_sciflow_status',
                        'value' => 'aguardando_confirmacao',
                    ),
                ),
            ));

            foreach ($query->posts as $post) {
                $deadline = get_post_meta($post->ID, '_sciflow_confirmation_deadline', true);
                if (!$deadline)
                    continue;

                $deadline_ts = strtotime($deadline);
                if ($deadline_ts && $now > $deadline_ts) {
                    // Author didn't confirm in time — deselect.
                    update_post_meta($post->ID, '_sciflow_selected_for_presentation', false);
                    update_post_meta($post->ID, '_sciflow_status', 'aprovado'); // Back to approved.

                    // Find next candidate.
                    $event = SciFlow_Status_Manager::get_event_from_post_type($pt);
                    $this->escalate_to_next($event, $post->ID);
                }
            }
        }
    }

    /**
     * Escalate selection to the next ranked article.
     */
    private function escalate_to_next($event, $excluded_post_id)
    {
        $ranking = $this->get_event_ranking($event);
        $email = new SciFlow_Email();

        foreach ($ranking as $post) {
            if ($post->ID === $excluded_post_id)
                continue;

            $selected = get_post_meta($post->ID, '_sciflow_selected_for_presentation', true);
            $confirmed = get_post_meta($post->ID, '_sciflow_presentation_confirmed', true);
            $status = get_post_meta($post->ID, '_sciflow_status', true);

            // Skip already selected/confirmed.
            if ($selected || $confirmed)
                continue;

            // Must be approved.
            if ($status !== 'aprovado' && $status !== 'poster_enviado')
                continue;

            // Select this one.
            $deadline = gmdate('Y-m-d\TH:i:s\Z', strtotime('+3 days'));
            update_post_meta($post->ID, '_sciflow_selected_for_presentation', true);
            update_post_meta($post->ID, '_sciflow_confirmation_deadline', $deadline);
            update_post_meta($post->ID, '_sciflow_status', 'aguardando_confirmacao');

            $email->send_confirmation_needed($post->ID);
            break;
        }
    }

    /**
     * Query ranked articles of a post type (approved/poster_enviado/confirmado only).
     */
    private function query_ranked($post_type)
    {
        $query = new WP_Query(array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_sciflow_ranking_score',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'DECIMAL(10,4)',
                ),
                array(
                    'key' => '_sciflow_status',
                    'value' => array('aprovado', 'poster_enviado', 'aguardando_confirmacao', 'confirmado'),
                    'compare' => 'IN',
                ),
            ),
            'orderby' => 'meta_value_num',
            'meta_key' => '_sciflow_ranking_score',
            'order' => 'DESC',
        ));

        return $query->posts;
    }
}

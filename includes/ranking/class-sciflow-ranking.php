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
     * @param string $event 'enfrute' or 'semco'.
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
        $semco = $this->query_ranked('semco_trabalhos');
        $all = array_merge($enfrute, $semco);

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
        foreach (array('enfrute', 'semco') as $event) {
            $ranking = $this->get_event_ranking($event);
            $count = 0;

            foreach ($ranking as $post) {
                if ($count >= $per_event)
                    break;

                if (!empty($post->is_excluded_by_email)) {
                    continue;
                }

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
            if (!empty($post->is_excluded_by_email)) {
                continue;
            }
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
            if (!in_array($status, array('aprovado', 'poster_enviado', 'poster_em_correcao', 'poster_reenviado', 'poster_aprovado', 'poster_reprovado', 'apto_publicacao'), true)) {
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

        foreach (array('enfrute_trabalhos', 'semco_trabalhos') as $pt) {
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
            if (!in_array($status, array('aprovado', 'poster_enviado', 'poster_em_correcao', 'poster_reenviado', 'poster_aprovado', 'poster_reprovado', 'apto_publicacao'), true))
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
                    'value' => array('aprovado', 'poster_enviado', 'poster_em_correcao', 'poster_reenviado', 'poster_aprovado', 'poster_reprovado', 'apto_publicacao', 'aguardando_confirmacao', 'confirmado'),
                    'compare' => 'IN',
                ),
            ),
            // Ordering is handled in PHP for tie-breaking
        ));

        $settings = get_option('sciflow_settings', array());
        
        // Find committee users (administrators, editors)
        $committee_users = get_users(array(
            'role__in' => array('administrator', 'sciflow_editor', 'sciflow_enfrute_editor', 'sciflow_semco_editor'),
            'fields' => 'all'
        ));
        
        $committee_emails = array();
        foreach ($committee_users as $u) {
            if (!empty($u->user_email)) {
                $committee_emails[] = strtolower(trim($u->user_email));
            }
        }

        $excluded_emails_str = $settings['excluded_ranking_emails'] ?? '';
        $excluded_emails = array();
        if (!empty($excluded_emails_str)) {
            $lines = explode("\n", $excluded_emails_str);
            foreach ($lines as $line) {
                $trimmed = strtolower(trim($line));
                if (!empty($trimmed)) {
                    $excluded_emails[] = $trimmed;
                }
            }
        }

        $filtered_posts = array();

        foreach ($query->posts as $post) {
            $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
            $author = get_userdata($author_id);
            $author_email = $author ? strtolower(trim($author->user_email)) : '';
            
            $main_email = strtolower(trim(get_post_meta($post->ID, '_sciflow_main_author_email', true)));
            $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
            
            $is_committee = false;
            
            if (in_array($author_email, $committee_emails, true) || in_array($main_email, $committee_emails, true)) {
                $is_committee = true;
            }
            
            if (!$is_committee && is_array($coauthors)) {
                foreach ($coauthors as $co) {
                    if (isset($co['email']) && in_array(strtolower(trim($co['email'])), $committee_emails, true)) {
                        $is_committee = true;
                        break;
                    }
                }
            }
            
            if ($is_committee) {
                continue;
            }
            
            $is_excluded = false;
            
            if (in_array($author_email, $excluded_emails, true) || in_array($main_email, $excluded_emails, true)) {
                $is_excluded = true;
            }
            
            if (!$is_excluded && is_array($coauthors)) {
                foreach ($coauthors as $co) {
                    if (isset($co['email'])) {
                        $co_email = strtolower(trim($co['email']));
                        if (in_array($co_email, $excluded_emails, true)) {
                            $is_excluded = true;
                            break;
                        }
                    }
                }
            }
            
            if ($is_excluded) {
                $post->is_excluded_by_email = true;
            }
            
            $filtered_posts[] = $post;
        }

        // Sort posts by total score, then by weighted criteria if tied
        $weights = $settings['ranking_weights'] ?? array();
        arsort($weights); // Sort weights descending to know which criteria to prioritize in ties
        
        usort($filtered_posts, function($a, $b) use ($weights) {
            $sa = (float) get_post_meta($a->ID, '_sciflow_ranking_score', true);
            $sb = (float) get_post_meta($b->ID, '_sciflow_ranking_score', true);
            
            if ($sa !== $sb) {
                return $sb <=> $sa; // descending
            }
            
            // Tie-breaking
            $scores_a = get_post_meta($a->ID, '_sciflow_scores', true) ?: array();
            $scores_b = get_post_meta($b->ID, '_sciflow_scores', true) ?: array();
            
            foreach ($weights as $key => $weight) {
                $wa = (float) ($scores_a[$key] ?? 0) * (float) $weight;
                $wb = (float) ($scores_b[$key] ?? 0) * (float) $weight;
                
                if ($wa !== $wb) {
                    return $wb <=> $wa; // descending
                }
            }
            
            return 0; // Still tied
        });

        return $filtered_posts;
    }
}

<?php
/**
 * Handles the review workflow (reviewer actions).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Review
{

    private $status_manager;
    private $email;

    public function __construct(SciFlow_Status_Manager $status_manager, SciFlow_Email $email)
    {
        $this->status_manager = $status_manager;
        $this->email = $email;
    }

    /**
     * Submit a review for an article.
     *
     * @param int   $post_id  The article post ID.
     * @param array $data     Review data (scores, decision, notes).
     * @return true|WP_Error
     */
    public function submit_review($post_id, $data)
    {
        $user_id = get_current_user_id();
        $reviewer = (int) get_post_meta($post_id, '_sciflow_reviewer_id', true);

        if ($user_id !== $reviewer) {
            return new WP_Error('unauthorized', __('Você não é o revisor designado.', 'sciflow-wp'));
        }

        $current = $this->status_manager->get_status($post_id);
        if (!in_array($current, array('em_avaliacao', 'em_correcao', 'submetido'), true)) {
            return new WP_Error('invalid_status', __('O trabalho não está em fase de avaliação.', 'sciflow-wp'));
        }

        // Validate and save scores.
        $scores = $this->validate_scores($data['scores'] ?? array());
        if (is_wp_error($scores)) {
            return $scores;
        }

        // Validate decision.
        $decision = sanitize_text_field($data['decision'] ?? '');
        if (!in_array($decision, array('approved', 'approved_with_considerations', 'rejected'), true)) {
            return new WP_Error('invalid_decision', __('Decisão do revisor inválida.', 'sciflow-wp'));
        }

        $notes = wp_kses_post($data['notes'] ?? '');

        // Save review data.
        update_post_meta($post_id, '_sciflow_scores', $scores);
        update_post_meta($post_id, '_sciflow_reviewer_decision', $decision);
        update_post_meta($post_id, '_sciflow_reviewer_notes', $notes);

        // Add to history.
        $editorial = new SciFlow_Editorial($this->status_manager, $this->email);
        $editorial->add_message($post_id, 'revisor', $notes);

        // Calculate and store ranking score.
        $ranking_score = $this->calculate_average($scores);
        update_post_meta($post_id, '_sciflow_ranking_score', $ranking_score);

        // Transition to aguardando_decisao if in evaluation or newly resubmitted phase.
        if (in_array($current, array('em_avaliacao', 'submetido'), true)) {
            $result = $this->status_manager->transition($post_id, 'aguardando_decisao');

            if (!is_wp_error($result)) {
                $this->email->send_review_complete($post_id);
            }
        } else {
            $result = true;
        }

        return $result;
    }

    /**
     * Get articles assigned to a reviewer.
     */
    public function get_reviewer_articles($reviewer_id = null)
    {
        if (!$reviewer_id) {
            $reviewer_id = get_current_user_id();
        }

        $posts = array();
        foreach (array('enfrute_trabalhos', 'senco_trabalhos') as $pt) {
            $query = new WP_Query(array(
                'post_type' => $pt,
                'posts_per_page' => -1,
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => '_sciflow_reviewer_id',
                        'value' => $reviewer_id,
                        'type' => 'NUMERIC',
                    ),
                ),
            ));
            $posts = array_merge($posts, $query->posts);
        }

        return $posts;
    }

    /**
     * Validate scores array.
     */
    private function validate_scores($raw)
    {
        $criteria = array('originalidade', 'objetividade', 'organizacao', 'metodologia', 'aderencia');
        $scores = array();

        foreach ($criteria as $key) {
            if (!isset($raw[$key])) {
                return new WP_Error('missing_score', sprintf(__('Nota obrigatória: %s', 'sciflow-wp'), $key));
            }

            $val = floatval($raw[$key]);
            if ($val < 0 || $val > 10) {
                return new WP_Error('invalid_score', sprintf(__('Nota fora do intervalo (0-10): %s', 'sciflow-wp'), $key));
            }

            $scores[$key] = round($val, 2);
        }

        return $scores;
    }

    /**
     * Calculate weighted average of 5 criteria (equal weights by default).
     */
    private function calculate_average($scores)
    {
        $settings = get_option('sciflow_settings', array());
        $weights = $settings['ranking_weights'] ?? array();

        $criteria = array('originalidade', 'objetividade', 'organizacao', 'metodologia', 'aderencia');
        $total_weight = 0;
        $weighted_sum = 0;

        foreach ($criteria as $key) {
            $weight = floatval($weights[$key] ?? 1);
            $weighted_sum += $scores[$key] * $weight;
            $total_weight += $weight;
        }

        return $total_weight > 0 ? round($weighted_sum / $total_weight, 4) : 0;
    }
}

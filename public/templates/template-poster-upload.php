<?php
/**
 * Template Name: SciFlow - Enviar Pôster
 * 
 * Template for poster upload page.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<main id="primary" class="site-main sciflow-template-page">
    <div class="container pb-5 pt-5">
        <?php
        while (have_posts()) :
            the_post();
            
            // Replicate shortcode logic for poster upload
            $submission = new SciFlow_Submission(new SciFlow_Status_Manager(), new SciFlow_Email(), null);
            $submissions = $submission->get_user_submissions();
            $status_manager = new SciFlow_Status_Manager();

            // Filter to only approved works.
            $approved = array_filter($submissions, function ($post) {
                $status = get_post_meta($post->ID, '_sciflow_status', true);
                return in_array($status, array('aprovado', 'poster_enviado', 'poster_em_correcao'), true);
            });

            if (!is_user_logged_in()) {
                echo '<div class="sciflow-notice sciflow-notice--warning">'
                    . __('Faça login para enviar seu pôster.', 'sciflow-wp')
                    . ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">'
                    . __('Fazer login', 'sciflow-wp') . '</a></div>';
            } else {
                include SCIFLOW_PATH . 'public/templates/poster-upload.php';
            }

        endwhile;
        ?>
    </div>
</main>

<?php
get_footer();

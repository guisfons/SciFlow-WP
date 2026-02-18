<?php
/**
 * Ranking page template.
 *
 * Available vars:
 *   $ranking        - SciFlow_Ranking
 *   $status_manager - SciFlow_Status_Manager
 *   $event          - string (enfrute, senco, or all)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="sciflow-ranking-page">
    <h2 class="sciflow-dashboard__title"><?php esc_html_e( 'Ranking dos Trabalhos', 'sciflow-wp' ); ?></h2>

    <!-- Ranking tabs -->
    <div class="sciflow-tabs sciflow-ranking-tabs">
        <button class="sciflow-tab <?php echo $event === 'enfrute' || $event === 'all' ? 'sciflow-tab--active' : ''; ?>"
                data-ranking="enfrute">
            Enfrute
        </button>
        <button class="sciflow-tab <?php echo $event === 'senco' ? 'sciflow-tab--active' : ''; ?>"
                data-ranking="senco">
            Senco
        </button>
        <button class="sciflow-tab <?php echo $event === 'geral' ? 'sciflow-tab--active' : ''; ?>"
                data-ranking="geral">
            <?php esc_html_e( 'Geral', 'sciflow-wp' ); ?>
        </button>
    </div>

    <!-- Enfrute ranking -->
    <div class="sciflow-ranking-content" id="ranking-enfrute"
         style="<?php echo ( $event !== 'enfrute' && $event !== 'all' ) ? 'display:none;' : ''; ?>">
        <h3>Ranking Enfrute</h3>
        <?php
        $enfrute_ranking = $ranking->get_event_ranking( 'enfrute' );
        sciflow_render_ranking_table( $enfrute_ranking, $status_manager );
        ?>
    </div>

    <!-- Senco ranking -->
    <div class="sciflow-ranking-content" id="ranking-senco"
         style="<?php echo $event !== 'senco' ? 'display:none;' : ''; ?>">
        <h3>Ranking Senco</h3>
        <?php
        $senco_ranking = $ranking->get_event_ranking( 'senco' );
        sciflow_render_ranking_table( $senco_ranking, $status_manager );
        ?>
    </div>

    <!-- General ranking -->
    <div class="sciflow-ranking-content" id="ranking-geral"
         style="<?php echo $event !== 'geral' ? 'display:none;' : ''; ?>">
        <h3><?php esc_html_e( 'Ranking Geral', 'sciflow-wp' ); ?></h3>
        <?php
        $geral_ranking = $ranking->get_general_ranking();
        sciflow_render_ranking_table( $geral_ranking, $status_manager, true );
        ?>
    </div>
</div>

<?php
/**
 * Helper: render a ranking table.
 */
function sciflow_render_ranking_table( $posts, $status_manager, $show_event = false ) {
    if ( empty( $posts ) ) {
        echo '<div class="sciflow-empty"><p>' . esc_html__( 'Nenhum trabalho ranqueado ainda.', 'sciflow-wp' ) . '</p></div>';
        return;
    }
    ?>
    <table class="sciflow-table sciflow-ranking-table">
        <thead>
            <tr>
                <th>#</th>
                <th><?php esc_html_e( 'Título', 'sciflow-wp' ); ?></th>
                <?php if ( $show_event ) : ?>
                    <th><?php esc_html_e( 'Evento', 'sciflow-wp' ); ?></th>
                <?php endif; ?>
                <th><?php esc_html_e( 'Nota', 'sciflow-wp' ); ?></th>
                <th><?php esc_html_e( 'Status', 'sciflow-wp' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $pos = 1; foreach ( $posts as $post ) :
                $score  = get_post_meta( $post->ID, '_sciflow_ranking_score', true );
                $status = get_post_meta( $post->ID, '_sciflow_status', true );
                $event  = get_post_meta( $post->ID, '_sciflow_event', true );
                $selected = get_post_meta( $post->ID, '_sciflow_selected_for_presentation', true );
            ?>
                <tr class="<?php echo $selected ? 'sciflow-ranking-row--selected' : ''; ?>">
                    <td class="sciflow-ranking__pos">
                        <?php echo $pos;
                        if ( $pos <= 3 ) echo ' 🏆';
                        ?>
                    </td>
                    <td><?php echo esc_html( $post->post_title ); ?></td>
                    <?php if ( $show_event ) : ?>
                        <td><?php echo esc_html( ucfirst( $event ) ); ?></td>
                    <?php endif; ?>
                    <td class="sciflow-ranking__score">
                        <?php echo number_format( $score, 2, ',', '' ); ?>
                    </td>
                    <td>
                        <span class="sciflow-badge sciflow-badge--<?php echo esc_attr( $status ); ?>">
                            <?php echo esc_html( $status_manager->get_status_label( $status ) ); ?>
                        </span>
                    </td>
                </tr>
            <?php $pos++; endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>

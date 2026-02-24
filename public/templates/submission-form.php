<?php
/**
 * Submission form template.
 *
 * Available vars: (from shortcode callback)
 *   - Current user is logged in.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
$post_id = isset($_GET['edit_id']) ? absint($_GET['edit_id']) : 0;
$edit_post = $post_id ? get_post($post_id) : null;

// Security check: only author can edit
if ($edit_post && (int)$edit_post->post_author !== (int)$current_user->ID) {
    echo '<div class="sciflow-notice sciflow-notice--error">Você não tem permissão para editar este trabalho.</div>';
    return;
}

// Status check: only allowed statuses can be edited
if ($edit_post) {
    $status = get_post_meta($post_id, '_sciflow_status', true);
    $allowed_edit_statuses = array('rascunho', 'em_correcao', 'aprovado_com_consideracoes', 'reprovado');
    if (!in_array($status, $allowed_edit_statuses, true)) {
        echo '<div class="sciflow-notice sciflow-notice--warning">Este trabalho já foi submetido e ainda não foi liberado pelo editor para correções.</div>';
        return;
    }
}

$event = $edit_post ? get_post_meta($post_id, '_sciflow_event', true) : '';
$language = $edit_post ? get_post_meta($post_id, '_sciflow_language', true) : 'pt';
$title = $edit_post ? $edit_post->post_title : '';
$content = $edit_post ? $edit_post->post_content : '';
$keywords = $edit_post ? get_post_meta($post_id, '_sciflow_keywords', true) : array();
if (!is_array($keywords)) $keywords = array();
$coauthors = $edit_post ? get_post_meta($post_id, '_sciflow_coauthors', true) : array();
if (!is_array($coauthors)) $coauthors = array();
?>

<div class="sciflow-form-wrapper" id="sciflow-submission-form">
    <h2 class="sciflow-form__title"><?php esc_html_e( 'Submeter Trabalho', 'sciflow-wp' ); ?></h2>

    <?php 
    if ($edit_post) {
        $reviewer_notes = get_post_meta($post_id, '_sciflow_reviewer_notes', true);
        $editorial_history = get_post_meta($post_id, '_sciflow_editorial_message', true);

        if (!empty($reviewer_notes) || !empty($editorial_history)) {
            echo '<div class="alert alert-warning mb-4 rounded-4 shadow-sm border-0 border-start border-4 border-warning">';
            echo '<h5 class="alert-heading fw-bold mb-3"><i class="bi bi-chat-right-text me-2"></i>Considerações e Histórico da Avaliação</h5>';
            
            if (!empty($reviewer_notes)) {
                echo '<div class="mb-3">';
                echo '<h6 class="fw-bold text-dark mb-2">Parecer da Comissão Científica:</h6>';
                echo '<div class="p-3 bg-white rounded border border-warning-subtle text-dark small" style="white-space: pre-wrap;">' . wp_kses_post($reviewer_notes) . '</div>';
                echo '</div>';
            }

            if (!empty($editorial_history)) {
                echo '<div>';
                echo '<h6 class="fw-bold text-dark mb-2">Histórico de Considerações:</h6>';
                echo '<div class="p-3 bg-white rounded border border-warning-subtle text-dark small" style="white-space: pre-wrap;">' . wp_kses_post($editorial_history) . '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        }
    }
    ?>

    <div class="sciflow-notice sciflow-notice--info" id="sciflow-form-messages" style="display:none;"></div>

    <form id="sciflow-submit-form" class="sciflow-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="sciflow_submit">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sciflow_nonce' ) ); ?>">
        <input type="hidden" name="post_id" id="sciflow_post_id" value="<?php echo esc_attr($post_id); ?>">


        <!-- Event selection -->
        <div class="sciflow-field">
            <label for="sciflow-event" class="sciflow-field__label">
                <?php esc_html_e( 'Evento *', 'sciflow-wp' ); ?>
            </label>
            <select id="sciflow-event" name="event" required class="sciflow-field__select">
                <option value=""><?php esc_html_e( 'Selecione o evento', 'sciflow-wp' ); ?></option>
                <option value="enfrute" <?php selected($event, 'enfrute'); ?>>Enfrute — Congresso Nacional</option>
                <option value="senco" <?php selected($event, 'senco'); ?>>Senco — Seminário Catarinense de Olericultura</option>
            </select>
        </div>

        <!-- Language -->
        <div class="sciflow-field">
            <label for="sciflow-language" class="sciflow-field__label">
                <?php esc_html_e( 'Idioma *', 'sciflow-wp' ); ?>
            </label>
            <select id="sciflow-language" name="language" required class="sciflow-field__select">
                <option value="pt" <?php selected($language, 'pt'); ?>><?php esc_html_e( 'Português', 'sciflow-wp' ); ?></option>
                <option value="en" <?php selected($language, 'en'); ?>><?php esc_html_e( 'Inglês', 'sciflow-wp' ); ?></option>
                <option value="es" <?php selected($language, 'es'); ?>><?php esc_html_e( 'Espanhol', 'sciflow-wp' ); ?></option>
            </select>
        </div>

        <!-- Title -->
        <div class="sciflow-field">
            <label for="sciflow-title" class="sciflow-field__label">
                <?php esc_html_e( 'Título do Trabalho *', 'sciflow-wp' ); ?>
            </label>
            <input type="text" id="sciflow-title" name="title" required
                   class="sciflow-field__input"
                   value="<?php echo esc_attr($title); ?>"
                   placeholder="<?php esc_attr_e( 'Título do trabalho científico', 'sciflow-wp' ); ?>">
        </div>

        <!-- Main Author (read-only) -->
        <div class="sciflow-field">
            <label class="sciflow-field__label">
                <?php esc_html_e( 'Autor Principal', 'sciflow-wp' ); ?>
            </label>
            <input type="text" value="<?php echo esc_attr( $current_user->display_name ); ?>"
                   class="sciflow-field__input" readonly disabled>
            <input type="hidden" name="authors_text" id="sciflow-authors-text" value="<?php echo esc_attr( $current_user->display_name ); ?>">
        </div>

        <!-- Co-authors (dynamic) -->
        <div class="sciflow-field">
            <label class="sciflow-field__label">
                <?php esc_html_e( 'Coautores (até 5)', 'sciflow-wp' ); ?>
            </label>
            <div id="sciflow-coauthors-list">
                <?php foreach ($coauthors as $index => $coauthor) : ?>
                    <div class="sciflow-coauthor-item" data-index="<?php echo $index; ?>">
                        <input type="text" name="coauthors[<?php echo $index; ?>][name]" value="<?php echo esc_attr($coauthor['name'] ?? ''); ?>" placeholder="Nome" required>
                        <input type="email" name="coauthors[<?php echo $index; ?>][email]" value="<?php echo esc_attr($coauthor['email'] ?? ''); ?>" placeholder="E-mail" required>
                        <input type="text" name="coauthors[<?php echo $index; ?>][institution]" value="<?php echo esc_attr($coauthor['institution'] ?? ''); ?>" placeholder="Instituição" required>
                        <button type="button" class="sciflow-remove-coauthor">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="sciflow-add-coauthor" class="sciflow-btn sciflow-btn--secondary sciflow-btn--sm">
                + <?php esc_html_e( 'Adicionar Coautor', 'sciflow-wp' ); ?>
            </button>
            <p class="sciflow-field__help"><?php esc_html_e( 'Máximo de 6 autores por resumo (incluindo autor principal).', 'sciflow-wp' ); ?></p>
        </div>

        <!-- Content (WYSIWYG) -->
        <div class="sciflow-field">
            <label for="sciflow-content" class="sciflow-field__label">
                <?php esc_html_e( 'Resumo do Trabalho *', 'sciflow-wp' ); ?>
            </label>
            <?php
            wp_editor( $content, 'sciflow_content', array(
                'textarea_name' => 'content',
                'textarea_rows' => 12,
                'media_buttons' => false,
                'quicktags'     => false,
                'tinymce'       => array(
                    'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
                    'toolbar2' => '',
                ),
            ) );
            ?>
            <div class="sciflow-char-counter" id="sciflow-char-counter">
                <span id="sciflow-char-count">0</span> / 4000
                <span class="sciflow-char-counter__label"><?php esc_html_e( 'caracteres (mín. 3000, máx. 4000)', 'sciflow-wp' ); ?></span>
            </div>
        </div>

        <!-- Keywords -->
        <div class="sciflow-field">
            <label class="sciflow-field__label">
                <?php esc_html_e( 'Palavras-chave * (3 a 5)', 'sciflow-wp' ); ?>
            </label>
            <?php for ( $i = 0; $i < 5; $i++ ) : 
                $kw_val = isset($keywords[$i]) ? $keywords[$i] : '';
            ?>
                <input type="text"
                       name="keywords[]"
                       class="sciflow-field__input sciflow-keyword-input"
                       value="<?php echo esc_attr($kw_val); ?>"
                       placeholder="<?php printf( esc_attr__( 'Palavra-chave %d', 'sciflow-wp' ), $i + 1 ); ?>"
                       <?php echo $i < 3 ? 'required' : ''; ?>>
            <?php endfor; ?>
        </div>


        <!-- Submit -->
        <?php 
        $sciflow_status = $edit_post ? get_post_meta($post_id, '_sciflow_status', true) : '';
        $hide_draft = ($sciflow_status === 'rascunho') ? 'style="display:none;"' : '';
        ?>
        <div class="sciflow-field sciflow-field--actions">
            <button type="button" class="sciflow-btn sciflow-btn--secondary" id="sciflow-draft-btn" <?php echo $hide_draft; ?>>
                <?php esc_html_e( 'Salvar como Rascunho', 'sciflow-wp' ); ?>
            </button>
            <button type="submit" class="sciflow-btn sciflow-btn--primary" id="sciflow-submit-btn">
                <?php esc_html_e( 'Submeter Trabalho', 'sciflow-wp' ); ?>
            </button>
        </div>
    </form>
</div>

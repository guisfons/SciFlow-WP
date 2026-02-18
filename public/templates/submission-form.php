<?php
/**
 * Submission form template.
 *
 * Available vars: (from shortcode callback)
 *   - Current user is logged in.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$current_user = wp_get_current_user();
?>

<div class="sciflow-form-wrapper" id="sciflow-submission-form">
    <h2 class="sciflow-form__title"><?php esc_html_e( 'Submeter Trabalho', 'sciflow-wp' ); ?></h2>

    <div class="sciflow-notice sciflow-notice--info" id="sciflow-form-messages" style="display:none;"></div>

    <form id="sciflow-submit-form" class="sciflow-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="sciflow_submit">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sciflow_nonce' ) ); ?>">

        <!-- Event selection -->
        <div class="sciflow-field">
            <label for="sciflow-event" class="sciflow-field__label">
                <?php esc_html_e( 'Evento *', 'sciflow-wp' ); ?>
            </label>
            <select id="sciflow-event" name="event" required class="sciflow-field__select">
                <option value=""><?php esc_html_e( 'Selecione o evento', 'sciflow-wp' ); ?></option>
                <option value="enfrute">Enfrute — Congresso Nacional</option>
                <option value="senco">Senco — Seminário Catarinense de Olericultura</option>
            </select>
        </div>

        <!-- Language -->
        <div class="sciflow-field">
            <label for="sciflow-language" class="sciflow-field__label">
                <?php esc_html_e( 'Idioma *', 'sciflow-wp' ); ?>
            </label>
            <select id="sciflow-language" name="language" required class="sciflow-field__select">
                <option value="pt"><?php esc_html_e( 'Português', 'sciflow-wp' ); ?></option>
                <option value="en"><?php esc_html_e( 'Inglês', 'sciflow-wp' ); ?></option>
                <option value="es"><?php esc_html_e( 'Espanhol', 'sciflow-wp' ); ?></option>
            </select>
        </div>

        <!-- Title -->
        <div class="sciflow-field">
            <label for="sciflow-title" class="sciflow-field__label">
                <?php esc_html_e( 'Título do Trabalho *', 'sciflow-wp' ); ?>
            </label>
            <input type="text" id="sciflow-title" name="title" required
                   class="sciflow-field__input"
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
            <div id="sciflow-coauthors-list"></div>
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
            wp_editor( '', 'sciflow_content', array(
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
            <?php for ( $i = 0; $i < 5; $i++ ) : ?>
                <input type="text"
                       name="keywords[]"
                       class="sciflow-field__input sciflow-keyword-input"
                       placeholder="<?php printf( esc_attr__( 'Palavra-chave %d', 'sciflow-wp' ), $i + 1 ); ?>"
                       <?php echo $i < 3 ? 'required' : ''; ?>>
            <?php endfor; ?>
        </div>

        <!-- Submit -->
        <div class="sciflow-field sciflow-field--actions">
            <button type="submit" class="sciflow-btn sciflow-btn--primary" id="sciflow-submit-btn">
                <?php esc_html_e( 'Submeter Trabalho', 'sciflow-wp' ); ?>
            </button>
        </div>
    </form>
</div>

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

        <!-- Cultura -->
        <div class="sciflow-field">
            <label class="sciflow-field__label">
                <?php esc_html_e( 'Cultura *', 'sciflow-wp' ); ?>
            </label>
            <?php $cultura = $edit_post ? get_post_meta($post_id, '_sciflow_cultura', true) : ''; ?>
            <select name="cultura" required class="sciflow-field__select">
                <option value=""><?php esc_html_e('Selecione uma cultura...', 'sciflow-wp'); ?></option>
                <optgroup label="Frutas de clima temperado">
                    <option value="Figo" <?php selected($cultura, 'Figo'); ?>>Figo</option>
                    <option value="Frutas de caroço" <?php selected($cultura, 'Frutas de caroço'); ?>>Frutas de caroço</option>
                    <option value="Goiaba/Caqui" <?php selected($cultura, 'Goiaba/Caqui'); ?>>Goiaba/Caqui</option>
                    <option value="Maçã/Pera" <?php selected($cultura, 'Maçã/Pera'); ?>>Maçã/Pera</option>
                    <option value="Pequenas frutas" <?php selected($cultura, 'Pequenas frutas'); ?>>Pequenas frutas</option>
                    <option value="Frutas nativas" <?php selected($cultura, 'Frutas nativas'); ?>>Frutas nativas</option>
                    <option value="Uva" <?php selected($cultura, 'Uva'); ?>>Uva</option>
                    <option value="Outras (Frutas)" <?php selected($cultura, 'Outras (Frutas)'); ?>>Outras</option>
                </optgroup>
                <optgroup label="Olerícolas">
                    <option value="Alho" <?php selected($cultura, 'Alho'); ?>>Alho</option>
                    <option value="Cebola" <?php selected($cultura, 'Cebola'); ?>>Cebola</option>
                    <option value="Tomate" <?php selected($cultura, 'Tomate'); ?>>Tomate</option>
                    <option value="Morango" <?php selected($cultura, 'Morango'); ?>>Morango</option>
                    <option value="Aipim/mandioca" <?php selected($cultura, 'Aipim/mandioca'); ?>>Aipim/mandioca</option>
                    <option value="Cenoura" <?php selected($cultura, 'Cenoura'); ?>>Cenoura</option>
                    <option value="Pimentão" <?php selected($cultura, 'Pimentão'); ?>>Pimentão</option>
                    <option value="Folhosas" <?php selected($cultura, 'Folhosas'); ?>>Folhosas</option>
                    <option value="Outras (Olerícolas)" <?php selected($cultura, 'Outras (Olerícolas)'); ?>>Outras</option>
                </optgroup>
            </select>
        </div>

        <!-- Área de Conhecimento -->
        <div class="sciflow-field">
            <label class="sciflow-field__label">
                <?php esc_html_e( 'Área de Conhecimento *', 'sciflow-wp' ); ?>
            </label>
            <?php $knowledge_area = $edit_post ? get_post_meta($post_id, '_sciflow_knowledge_area', true) : ''; ?>
            <select name="knowledge_area" required class="sciflow-field__select">
                <option value=""><?php esc_html_e('Selecione uma área...', 'sciflow-wp'); ?></option>
                <option value="Biotecnologia/Genética e Melhoramento" <?php selected($knowledge_area, 'Biotecnologia/Genética e Melhoramento'); ?>>Biotecnologia/Genética e Melhoramento</option>
                <option value="Botânica e Fisiologia" <?php selected($knowledge_area, 'Botânica e Fisiologia'); ?>>Botânica e Fisiologia</option>
                <option value="Colheita e Pós-Colheita" <?php selected($knowledge_area, 'Colheita e Pós-Colheita'); ?>>Colheita e Pós-Colheita</option>
                <option value="Fitossanidade" <?php selected($knowledge_area, 'Fitossanidade'); ?>>Fitossanidade</option>
                <option value="Economia/Estatística" <?php selected($knowledge_area, 'Economia/Estatística'); ?>>Economia/Estatística</option>
                <option value="Fitotecnia" <?php selected($knowledge_area, 'Fitotecnia'); ?>>Fitotecnia</option>
                <option value="Irrigação" <?php selected($knowledge_area, 'Irrigação'); ?>>Irrigação</option>
                <option value="Processamento (Química e Bioquímica)" <?php selected($knowledge_area, 'Processamento (Química e Bioquímica)'); ?>>Processamento (Química e Bioquímica)</option>
                <option value="Propagação" <?php selected($knowledge_area, 'Propagação'); ?>>Propagação</option>
                <option value="Sementes" <?php selected($knowledge_area, 'Sementes'); ?>>Sementes</option>
                <option value="Solos e Nutrição de Plantas" <?php selected($knowledge_area, 'Solos e Nutrição de Plantas'); ?>>Solos e Nutrição de Plantas</option>
                <option value="Outros" <?php selected($knowledge_area, 'Outros'); ?>>Outros</option>
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

        <!-- Main Author -->
        <div class="sciflow-field">
            <label class="sciflow-field__label">
                <?php esc_html_e( 'Autor Principal', 'sciflow-wp' ); ?>
            </label>
            <input type="text" value="<?php echo esc_attr( $current_user->display_name ); ?>"
                   class="sciflow-field__input" style="margin-bottom: 10px;" readonly disabled>
            <input type="hidden" name="authors_text" id="sciflow-authors-text" value="<?php echo esc_attr( $current_user->display_name ); ?>">
            
            <?php 
            $main_author_instituicao = $edit_post ? get_post_meta($post_id, '_sciflow_main_author_instituicao', true) : '';
            $main_author_cpf = $edit_post ? get_post_meta($post_id, '_sciflow_main_author_cpf', true) : '';
            $main_author_email = $edit_post ? get_post_meta($post_id, '_sciflow_main_author_email', true) : $current_user->user_email;
            $main_author_telefone = $edit_post ? get_post_meta($post_id, '_sciflow_main_author_telefone', true) : '';
            ?>
            <div class="sciflow-author-details" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <input type="text" name="main_author_instituicao" value="<?php echo esc_attr($main_author_instituicao); ?>" placeholder="Instituição *" class="sciflow-field__input" required>
                <input type="text" name="main_author_cpf" value="<?php echo esc_attr($main_author_cpf); ?>" placeholder="CPF *" class="sciflow-field__input" required>
                <input type="email" name="main_author_email" value="<?php echo esc_attr($main_author_email); ?>" placeholder="E-mail *" class="sciflow-field__input" required>
                <input type="text" name="main_author_telefone" value="<?php echo esc_attr($main_author_telefone); ?>" placeholder="Telefone *" class="sciflow-field__input" required>
            </div>
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
                        <input type="text" name="coauthors[<?php echo $index; ?>][telefone]" value="<?php echo esc_attr($coauthor['telefone'] ?? ''); ?>" placeholder="Telefone" required>
                        <button type="button" class="sciflow-remove-coauthor">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="sciflow-add-coauthor" class="sciflow-btn sciflow-btn--secondary sciflow-btn--sm" style="margin-top: 10px;">
                + <?php esc_html_e( 'Adicionar Coautor', 'sciflow-wp' ); ?>
            </button>
            <p class="sciflow-field__help"><?php esc_html_e( 'Máximo de 6 autores por resumo (incluindo autor principal).', 'sciflow-wp' ); ?></p>
        </div>

        <!-- Presenting Author -->
        <div class="sciflow-field">
            <label class="sciflow-field__label">
                <?php esc_html_e( 'Autor Apresentador *', 'sciflow-wp' ); ?>
            </label>
            <?php 
                $presenting_author = $edit_post ? get_post_meta($post_id, '_sciflow_presenting_author', true) : '';
                if (empty($presenting_author)) {
                    $presenting_author = 'main';
                }
            ?>
            <select id="sciflow-presenting-author" name="presenting_author" required class="sciflow-field__select">
                <option value="main" <?php selected($presenting_author, 'main'); ?>><?php echo esc_html($current_user->display_name); ?> (Autor Principal)</option>
                <?php foreach ($coauthors as $index => $coauthor) : ?>
                    <?php if (!empty($coauthor['name'])) : ?>
                        <option value="<?php echo esc_attr($index); ?>" <?php selected($presenting_author, (string)$index); ?>><?php echo esc_html($coauthor['name']); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <p class="sciflow-field__help"><?php esc_html_e( 'Selecione qual autor será o responsável pela apresentação do trabalho.', 'sciflow-wp' ); ?></p>
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

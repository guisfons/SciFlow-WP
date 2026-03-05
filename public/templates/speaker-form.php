<?php
/**
 * Speaker submission form template.
 *
 * Available vars: (from shortcode callback)
 *   - Current user is logged in.
 *   - Current user has sciflow_speaker capability/role.
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
?>

<div class="sciflow-form-wrapper" id="sciflow-speaker-form-wrapper">
    <h2 class="sciflow-form__title">
        <?php esc_html_e('Submeter Palestra', 'sciflow-wp'); ?>
    </h2>

    <div class="sciflow-notice sciflow-notice--info" id="sciflow-form-messages" style="display:none;"></div>

    <form id="sciflow-speaker-form" class="sciflow-form">
        <input type="hidden" name="action" value="sciflow_submit_speaker_talk">
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('sciflow_speaker_nonce')); ?>">

        <!-- Event selection -->
        <div class="sciflow-field">
            <label for="sciflow-speaker-event" class="sciflow-field__label">
                <?php esc_html_e('Evento *', 'sciflow-wp'); ?>
            </label>
            <select id="sciflow-speaker-event" name="event" required class="sciflow-field__select">
                <option value="">
                    <?php esc_html_e('Selecione o evento', 'sciflow-wp'); ?>
                </option>
                <option value="enfrute">Enfrute — Congresso Nacional</option>
                <option value="semco">Semco — Seminário Catarinense de Olericultura</option>
            </select>
        </div>

        <!-- Title -->
        <div class="sciflow-field">
            <label for="sciflow-speaker-title" class="sciflow-field__label">
                <?php esc_html_e('Título da Palestra *', 'sciflow-wp'); ?>
            </label>
            <input type="text" id="sciflow-speaker-title" name="title" required class="sciflow-field__input">
        </div>

        <!-- Content (16k - 25k limit) -->
        <div class="sciflow-field">
            <label for="sciflow-speaker-content" class="sciflow-field__label">
                <?php esc_html_e('Resumo / Conteúdo (16.000 a 25.000 caracteres) *', 'sciflow-wp'); ?>
            </label>
            <textarea id="sciflow-speaker-content" name="content" required class="sciflow-field__textarea" rows="15"
                minlength="16000" maxlength="25000"></textarea>
            <div class="sciflow-field__help" id="speaker-char-count">Caracteres: 0 / 25000 (Mínimo: 16000)</div>
        </div>

        <!-- Terms -->
        <div class="sciflow-field">
            <label class="sciflow-field__checkbox-label">
                <input type="checkbox" name="terms" required value="1">
                <?php esc_html_e('Declaro que li e concordo com as normas do evento.', 'sciflow-wp'); ?>
            </label>
        </div>

        <div class="sciflow-form__actions mt-4">
            <button type="submit" class="sciflow-btn sciflow-btn--primary">
                <?php esc_html_e('Submeter Palestra', 'sciflow-wp'); ?>
            </button>
        </div>
    </form>
</div>
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
$content = '';
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
                <option value="enfrute">Enfrute — Encontro Nacional sobre Fruticultura de Clima Temperado</option>
                <option value="semco">Semco — Seminário Catarinense de Olericultura</option>
            </select>
        </div>

        <!-- Duration selection -->
        <div class="sciflow-field">
            <label for="sciflow-speaker-duration" class="sciflow-field__label">
                <?php esc_html_e('Duração da Palestra *', 'sciflow-wp'); ?>
            </label>
            <select id="sciflow-speaker-duration" name="duration" required class="sciflow-field__select">
                <option value="40" selected><?php esc_html_e('40 Minutos (16.000 a 25.000 caracteres)', 'sciflow-wp'); ?></option>
                <option value="20"><?php esc_html_e('20 Minutos (8.000 a 25.000 caracteres)', 'sciflow-wp'); ?></option>
            </select>
        </div>

        <!-- Title -->
        <div class="sciflow-field">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 5px;">
                <label for="sciflow-speaker-title" class="sciflow-field__label" style="margin-bottom: 0;">
                    <?php esc_html_e('Título da Palestra *', 'sciflow-wp'); ?>
                </label>
                <button type="button" id="sciflow-title-italic-btn" class="sciflow-btn-mini" title="<?php esc_attr_e('Aplicar Itálico', 'sciflow-wp'); ?>" style="padding: 2px 10px; font-style: italic; font-family: serif; font-weight: bold;">
                    I
                </button>
            </div>
            <input type="text" id="sciflow-speaker-title" name="title" required class="sciflow-field__input">
        </div>

        <!-- Content (16k - 25k limit) -->
        <div class="sciflow-field">
            <label for="sciflow-speaker-content" class="sciflow-field__label" id="sciflow-speaker-content-label">
                <?php esc_html_e('Resumo / Conteúdo (16.000 a 25.000 caracteres) *', 'sciflow-wp'); ?>
            </label>
            <?php
            wp_editor($content, 'sciflow_content', array(
                'textarea_name' => 'content',
                'textarea_rows' => 12,
                'media_buttons' => false,
                'quicktags' => false,
                'tinymce' => array(
                    'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
                    'toolbar2' => '',
                ),
            ));
            ?>
            <div class="sciflow-field__help" id="speaker-char-count">
                <?php esc_html_e('Caracteres:', 'sciflow-wp'); ?> <span id="sciflow-current-count">0</span> / 25.000 
                (<span id="sciflow-min-label"><?php esc_html_e('Mínimo:', 'sciflow-wp'); ?> 16.000</span>)
            </div>
        </div>

        <!-- References / Links -->
        <div class="sciflow-field">
            <label for="sciflow-speaker-references" class="sciflow-field__label">
                <?php esc_html_e('Referências (opcional)', 'sciflow-wp'); ?>
            </label>
            <textarea id="sciflow-speaker-references" name="references" class="sciflow-field__textarea" rows="6"
                placeholder="<?php esc_attr_e('Insira as referências bibliográficas ou links de referência aqui...', 'sciflow-wp'); ?>"></textarea>
            <p class="sciflow-field__help"><?php esc_html_e('Links e referências são permitidos neste campo.', 'sciflow-wp'); ?></p>
        </div>

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

<script>
(function() {
    // Italic helper for Title
    var titleInput = document.getElementById('sciflow-speaker-title');
    var italicBtn = document.getElementById('sciflow-title-italic-btn');
    
    if (titleInput && italicBtn) {
        italicBtn.addEventListener('click', function() {
            var start = titleInput.selectionStart;
            var end = titleInput.selectionEnd;
            var text = titleInput.value;
            var selectedText = text.substring(start, end);
            
            if (selectedText.length > 0) {
                var before = text.substring(0, start);
                var after = text.substring(end);
                
                // Toggle italic if already wrapped
                if (selectedText.startsWith('<i>') && selectedText.endsWith('</i>')) {
                    var newText = selectedText.substring(3, selectedText.length - 4);
                    titleInput.value = before + newText + after;
                    titleInput.setSelectionRange(start, start + newText.length);
                } else {
                    titleInput.value = before + '<i>' + selectedText + '</i>' + after;
                    titleInput.setSelectionRange(start, end + 7);
                }
                
                // Trigger input event (if needed for other listeners)
                titleInput.dispatchEvent(new Event('input'));
                titleInput.focus();
            } else {
                alert('<?php esc_html_e("Selecione o texto que deseja deixar em itálico primeiro.", "sciflow-wp"); ?>');
            }
        });
    }
    // Character counter and limit updates
    var durationSelect = document.getElementById('sciflow-speaker-duration');
    var minLabel = document.getElementById('sciflow-min-label');
    var countSpan = document.getElementById('sciflow-current-count');
    var mainLabel = document.getElementById('sciflow-speaker-content-label');

    function updateSpeakerLimits() {
        if (!durationSelect) return;
        
        var min = (durationSelect.value === '20') ? '8.000' : '16.000';
        if (minLabel) {
            minLabel.textContent = '<?php esc_html_e("Mínimo:", "sciflow-wp"); ?> ' + min;
        }
        
        if (mainLabel) {
            var rangeText = (durationSelect.value === '20') ? '8.000 a 25.000' : '16.000 a 25.000';
            mainLabel.innerHTML = '<?php esc_html_e("Resumo / Conteúdo", "sciflow-wp"); ?> (' + rangeText + ' <?php esc_html_e("caracteres", "sciflow-wp"); ?>) *';
        }
        
        if (typeof tinyMCE !== 'undefined') {
            checkTotal();
        }
    }

    function checkTotal() {
        if (typeof tinyMCE === 'undefined') return;
        var editor = tinyMCE.get('sciflow_content');
        if (editor && countSpan) {
            var content = editor.getContent({format: 'text'});
            var titleRaw = titleInput ? titleInput.value : '';
            var titleClean = titleRaw.replace(/<\/?[^>]+(>|$)/g, "");
            var total = content.length + titleClean.length;
            countSpan.textContent = total.toLocaleString();
            
            var min = (durationSelect.value === '20') ? 8000 : 16000;
            if (total < min || total > 25000) {
                countSpan.style.color = '#c0392b'; // Red
            } else {
                countSpan.style.color = '#27ae60'; // Green
            }
        }
    }

    if (durationSelect) {
        durationSelect.addEventListener('change', updateSpeakerLimits);
        // Initial run to be safe
        updateSpeakerLimits();
    }

    // Use the function in the interval if TinyMCE is present
    setInterval(function() {
        if (typeof tinyMCE !== 'undefined') {
            checkTotal();
        }
    }, 1000);
})();
</script>
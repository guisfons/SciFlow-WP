<?php
/**
 * "Identidade visual" fields (settings tab + setup wizard).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Branding_Admin
{
    /**
     * Enqueue color picker and media library on SciFlow admin screens.
     */
    public static function init()
    {
        add_action('admin_enqueue_scripts', function ($hook) {
            if (false === strpos((string) $hook, 'sciflow')) {
                return;
            }
            wp_enqueue_media();
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        });
    }

    /**
     * Render the identity fields.
     *
     * @param array $s       Settings (merged with defaults / old input).
     * @param bool  $compact Hide the Anais section (wizard shows it elsewhere).
     */
    public static function fields(array $s, $compact = false)
    {
        $fonts = SciFlow_Branding::fonts();
        $logo_url = '';
        if (!empty($s['brand_logo_id'])) {
            $logo_url = (string) wp_get_attachment_image_url((int) $s['brand_logo_id'], 'medium');
        } elseif (!empty($s['brand_logo_ref'])) {
            $logo_url = SciFlow_Branding::logo_url();
        }
        ?>
        <style>
            .sf-brand-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; align-items: start; }
            @media (max-width: 1100px) { .sf-brand-grid { grid-template-columns: 1fr; } }
            .sf-media { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .sf-media img { max-height: 56px; max-width: 200px; background: repeating-conic-gradient(#eee 0 25%, #fff 0 50%) 0 / 12px 12px; border: 1px solid #dcdcde; padding: 4px; }
            .sf-preview { position: sticky; top: 40px; border: 1px solid #dcdcde; border-radius: 8px; overflow: hidden; background: #fff; }
            .sf-preview-head { padding: 14px; text-align: center; }
            .sf-preview-head img { max-height: 40px; max-width: 160px; display: block; margin: 0 auto 6px; }
            .sf-preview-body { padding: 16px; }
            .sf-preview h3 { margin: 0 0 8px; font-size: 18px; }
            .sf-preview p { margin: 0 0 12px; font-size: 13px; }
            .sf-preview .sf-btn { display: inline-block; padding: 8px 14px; font-weight: 600; font-size: 13px; text-decoration: none; margin: 0 6px 6px 0; }
            .sf-contrast { font-size: 12px; margin-top: 8px; }
        </style>
        <div class="sf-brand-grid">
            <div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Logo', 'sciflow-wp'); ?></th>
                        <td>
                            <div class="sf-media" data-sf-media="brand_logo_id">
                                <img src="<?php echo esc_url($logo_url); ?>" alt="" <?php echo $logo_url ? '' : 'hidden'; ?>>
                                <input type="hidden" name="sciflow_settings[brand_logo_id]" value="<?php echo esc_attr((int) $s['brand_logo_id']); ?>">
                                <button type="button" class="button sf-media-pick"><?php esc_html_e('Escolher imagem', 'sciflow-wp'); ?></button>
                                <button type="button" class="button-link sf-media-clear" <?php echo $logo_url ? '' : 'hidden'; ?>><?php esc_html_e('Remover', 'sciflow-wp'); ?></button>
                                <?php if (!empty($s['brand_logo_ref']) && empty($s['brand_logo_id'])) : ?>
                                    <input type="hidden" name="sciflow_settings[brand_logo_ref_clear]" value="" class="sf-ref-clear">
                                <?php endif; ?>
                            </div>
                            <p class="description"><?php esc_html_e('PNG/SVG/WebP com fundo transparente. Usado no topo do site, nos e-mails, na capa e na folha de rosto dos Anais.', 'sciflow-wp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sf-logo-height"><?php esc_html_e('Altura do logo no site', 'sciflow-wp'); ?></label></th>
                        <td><input type="number" id="sf-logo-height" min="24" max="160" name="sciflow_settings[brand_logo_height]" value="<?php echo esc_attr((int) $s['brand_logo_height']); ?>" class="small-text"> px</td>
                    </tr>
                    <?php
                    $colors = array(
                        'brand_primary'      => array(__('Cor principal', 'sciflow-wp'), __('Botões, links, destaques e capa.', 'sciflow-wp')),
                        'brand_primary_dark' => array(__('Cor principal escura', 'sciflow-wp'), __('Opcional: botões ao passar o mouse e fundos fortes. Vazio = calculada.', 'sciflow-wp')),
                        'brand_secondary'    => array(__('Cor de destaque', 'sciflow-wp'), __('Selos, avisos e chamadas secundárias.', 'sciflow-wp')),
                        'brand_text'         => array(__('Cor do texto', 'sciflow-wp'), ''),
                        'brand_background'   => array(__('Cor de fundo', 'sciflow-wp'), ''),
                    );
                    foreach ($colors as $key => $info) : ?>
                        <tr>
                            <th scope="row"><label for="sf-<?php echo esc_attr($key); ?>"><?php echo esc_html($info[0]); ?></label></th>
                            <td><input type="text" id="sf-<?php echo esc_attr($key); ?>" class="sf-color" data-key="<?php echo esc_attr($key); ?>" name="sciflow_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $s[$key]); ?>">
                                <?php if ($info[1]) : ?><p class="description"><?php echo esc_html($info[1]); ?></p><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach (array('brand_heading_font' => __('Fonte dos títulos', 'sciflow-wp'), 'brand_body_font' => __('Fonte do texto', 'sciflow-wp')) as $key => $label) : ?>
                        <tr>
                            <th scope="row"><label for="sf-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td><select id="sf-<?php echo esc_attr($key); ?>" name="sciflow_settings[<?php echo esc_attr($key); ?>]" class="sf-font" data-key="<?php echo esc_attr($key); ?>">
                                <?php foreach ($fonts as $fk => $f) : ?>
                                    <option value="<?php echo esc_attr($fk); ?>" data-stack="<?php echo esc_attr($f[1]); ?>" <?php selected($s[$key], $fk); ?>><?php echo esc_html($f[0]); ?></option>
                                <?php endforeach; ?>
                            </select></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="2" style="padding-top:0;"><p class="description"><?php esc_html_e('Fontes do Google Fonts são baixadas dos servidores do Google pelo navegador do visitante (dado de navegação compartilhado com terceiro — mencione na política de privacidade).', 'sciflow-wp'); ?></p></td></tr>
                    <tr>
                        <th scope="row"><label for="sf-radius"><?php esc_html_e('Arredondamento dos cantos', 'sciflow-wp'); ?></label></th>
                        <td><input type="range" id="sf-radius" min="0" max="24" step="2" name="sciflow_settings[brand_radius]" value="<?php echo esc_attr((int) $s['brand_radius']); ?>" class="sf-radius"> <output><?php echo esc_html((int) $s['brand_radius']); ?></output> px</td>
                    </tr>
                </table>

                <?php if (!$compact) : ?>
                    <h3><?php esc_html_e('Anais (modo "Personalizadas")', 'sciflow-wp'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="sf-anais-font"><?php esc_html_e('Fonte do livro', 'sciflow-wp'); ?></label></th>
                            <td><select id="sf-anais-font" name="sciflow_settings[anais_font]">
                                <?php foreach ($fonts as $fk => $f) : ?>
                                    <option value="<?php echo esc_attr($fk); ?>" <?php selected($s['anais_font'], $fk); ?>><?php echo esc_html($f[0]); ?></option>
                                <?php endforeach; ?>
                            </select><p class="description"><?php esc_html_e('O modelo padrão usa Arial.', 'sciflow-wp'); ?></p></td>
                        </tr>
                        <?php foreach (array('anais_cover_image_id' => __('Capa (imagem A4)', 'sciflow-wp'), 'anais_title_image_id' => __('Folha de rosto (imagem A4)', 'sciflow-wp'), 'anais_partners_image_id' => __('Realização e patrocínio (imagem A4)', 'sciflow-wp')) as $key => $label) :
                            $url = !empty($s[$key]) ? (string) wp_get_attachment_image_url((int) $s[$key], 'medium') : ''; ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($label); ?></th>
                                <td><div class="sf-media" data-sf-media="<?php echo esc_attr($key); ?>">
                                    <img src="<?php echo esc_url($url); ?>" alt="" <?php echo $url ? '' : 'hidden'; ?>>
                                    <input type="hidden" name="sciflow_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((int) $s[$key]); ?>">
                                    <button type="button" class="button sf-media-pick"><?php esc_html_e('Escolher imagem', 'sciflow-wp'); ?></button>
                                    <button type="button" class="button-link sf-media-clear" <?php echo $url ? '' : 'hidden'; ?>><?php esc_html_e('Remover', 'sciflow-wp'); ?></button>
                                </div><p class="description"><?php esc_html_e('Página inteira, proporção A4 (2480 × 3508 px a 300 dpi). Vazio: página gerada com o logo e a cor principal.', 'sciflow-wp'); ?></p></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <aside class="sf-preview" aria-label="<?php esc_attr_e('Pré-visualização', 'sciflow-wp'); ?>">
                <div class="sf-preview-head" data-sf="head">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="" data-sf="logo" <?php echo $logo_url ? '' : 'hidden'; ?>>
                    <strong><?php echo esc_html(get_bloginfo('name')); ?></strong>
                    <div style="font-size:11px;opacity:.85;"><?php esc_html_e('Cabeçalho dos e-mails', 'sciflow-wp'); ?></div>
                </div>
                <div class="sf-preview-body" data-sf="body">
                    <h3 data-sf="heading"><?php esc_html_e('Submissão de trabalhos', 'sciflow-wp'); ?></h3>
                    <p><?php esc_html_e('Texto de exemplo com um', 'sciflow-wp'); ?> <a href="#" data-sf="link" onclick="return false;"><?php esc_html_e('link', 'sciflow-wp'); ?></a>.</p>
                    <a href="#" class="sf-btn" data-sf="btn" onclick="return false;"><?php esc_html_e('Enviar resumo', 'sciflow-wp'); ?></a>
                    <a href="#" class="sf-btn" data-sf="btn2" onclick="return false;"><?php esc_html_e('Destaque', 'sciflow-wp'); ?></a>
                    <div class="sf-contrast" data-sf="contrast"></div>
                </div>
            </aside>
        </div>
        <script>
        jQuery(function ($) {
            function hexRgb(h) { h = (h || '').replace('#', ''); if (h.length === 3) { h = h.replace(/(.)/g, '$1$1'); } var n = parseInt(h, 16); return isNaN(n) ? [0, 0, 0] : [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
            function lum(h) { return hexRgb(h).map(function (c) { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }).reduce(function (a, c, i) { return a + c * [0.2126, 0.7152, 0.0722][i]; }, 0); }
            function ratio(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
            function contrast(h) { return lum(h) > 0.42 ? '#111111' : '#ffffff'; }
            function val(k, d) { var v = $('.sf-color[data-key="' + k + '"]').val(); return /^#[0-9a-f]{3,6}$/i.test(v || '') ? v : d; }
            function refresh() {
                var p = val('brand_primary', '#1f6f78'), s = val('brand_secondary', '#c27c0e'), t = val('brand_text', '#1d2327'), bg = val('brand_background', '#ffffff');
                var r = $('.sf-radius').val() + 'px';
                var hf = $('.sf-font[data-key="brand_heading_font"] option:selected').data('stack'), bf = $('.sf-font[data-key="brand_body_font"] option:selected').data('stack');
                $('[data-sf="head"]').css({ background: p, color: contrast(p) });
                $('[data-sf="body"]').css({ background: bg, color: t, fontFamily: bf });
                $('[data-sf="heading"]').css({ fontFamily: hf, color: t });
                $('[data-sf="link"]').css({ color: p });
                $('[data-sf="btn"]').css({ background: p, color: contrast(p), borderRadius: r });
                $('[data-sf="btn2"]').css({ background: s, color: contrast(s), borderRadius: r });
                $('.sf-radius').next('output').text($('.sf-radius').val());
                var msgs = [];
                if (ratio(t, bg) < 4.5) { msgs.push('<?php echo esc_js(__('Texto x fundo com contraste baixo (mínimo 4,5:1).', 'sciflow-wp')); ?>'); }
                if (ratio(p, bg) < 3) { msgs.push('<?php echo esc_js(__('Cor principal pouco visível sobre o fundo (mínimo 3:1 para links e botões).', 'sciflow-wp')); ?>'); }
                $('[data-sf="contrast"]').html(msgs.length ? '⚠ ' + msgs.join('<br>⚠ ') : '✔ <?php echo esc_js(__('Contraste adequado', 'sciflow-wp')); ?>').css('color', msgs.length ? '#b32d2e' : '#008a20');
            }
            $('.sf-color').wpColorPicker({ change: function () { setTimeout(refresh, 0); }, clear: function () { setTimeout(refresh, 0); } });
            $('.sf-font, .sf-radius').on('input change', refresh);
            $('.sf-media').each(function () {
                var box = $(this), frame;
                box.on('click', '.sf-media-pick', function () {
                    frame = frame || wp.media({ title: '<?php echo esc_js(__('Escolher imagem', 'sciflow-wp')); ?>', library: { type: 'image' }, multiple: false });
                    frame.off('select').on('select', function () {
                        var a = frame.state().get('selection').first().toJSON();
                        var url = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
                        box.find('input[type=hidden]').first().val(a.id);
                        box.find('img').attr('src', url).prop('hidden', false);
                        box.find('.sf-media-clear').prop('hidden', false);
                        if (box.data('sfMedia') === 'brand_logo_id') { $('[data-sf="logo"]').attr('src', url).prop('hidden', false); }
                    });
                    frame.open();
                });
                box.on('click', '.sf-media-clear', function () {
                    box.find('input[type=hidden]').first().val('0');
                    box.find('.sf-ref-clear').val('1');
                    box.find('img').prop('hidden', true);
                    $(this).prop('hidden', true);
                    if (box.data('sfMedia') === 'brand_logo_id') { $('[data-sf="logo"]').prop('hidden', true); }
                });
            });
            refresh();
        });
        </script>
        <?php
    }
}

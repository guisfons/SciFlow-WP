<?php
/**
 * Additional settings tabs (included inside the settings <form>).
 *
 * @var array $settings Settings merged with defaults.
 */

if (!defined('ABSPATH')) {
    exit;
}

$sf_events = SciFlow_Events::all();
$sf_pages = get_pages(array('post_status' => 'publish', 'sort_column' => 'post_title'));
$sf_page_ids = (array) ($settings['page_ids'] ?? array());
?>
<input type="hidden" name="sciflow_settings[_sections][]" value="eventos">
<input type="hidden" name="sciflow_settings[_sections][]" value="inscricao">
<input type="hidden" name="sciflow_settings[_sections][]" value="avancado">

<div id="tab-eventos" class="sciflow-tab-content" style="display:none;">
    <h2><?php esc_html_e('Eventos', 'sciflow-wp'); ?></h2>
    <p class="description"><?php esc_html_e('O SciFlow gerencia até dois eventos simultâneos. Os identificadores internos (enfrute / semco) são fixos para preservar os dados existentes; nomes e disponibilidade são configuráveis.', 'sciflow-wp'); ?></p>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="sf-organizer"><?php esc_html_e('Organização responsável', 'sciflow-wp'); ?></label></th>
            <td><input type="text" id="sf-organizer" name="sciflow_settings[organizer_name]" value="<?php echo esc_attr($settings['organizer_name']); ?>" class="regular-text">
                <p class="description"><?php esc_html_e('Usado como remetente dos e-mails e nos Anais, quando não houver outro valor.', 'sciflow-wp'); ?></p></td>
        </tr>
        <?php foreach ($sf_events as $slug => $event) : ?>
            <tr>
                <th scope="row"><?php echo esc_html(sprintf(__('Evento "%s"', 'sciflow-wp'), $slug)); ?></th>
                <td>
                    <fieldset>
                        <label><input type="checkbox" name="sciflow_settings[event_<?php echo esc_attr($slug); ?>_enabled]" value="1" <?php checked($event['enabled']); ?>>
                            <?php esc_html_e('Aceitar novas submissões', 'sciflow-wp'); ?></label><br><br>
                        <label><?php esc_html_e('Nome curto', 'sciflow-wp'); ?><br>
                            <input type="text" name="sciflow_settings[event_<?php echo esc_attr($slug); ?>_label]" value="<?php echo esc_attr($settings['event_' . $slug . '_label']); ?>" class="regular-text" required></label><br>
                        <label><?php esc_html_e('Nome completo (e-mails, certificados)', 'sciflow-wp'); ?><br>
                            <input type="text" name="sciflow_settings[event_<?php echo esc_attr($slug); ?>_full_label]" value="<?php echo esc_attr($settings['event_' . $slug . '_full_label']); ?>" class="large-text"></label><br>
                        <label><?php esc_html_e('Edição (ex.: XIX) — usada nos Anais', 'sciflow-wp'); ?><br>
                            <input type="text" name="sciflow_settings[event_<?php echo esc_attr($slug); ?>_edition]" value="<?php echo esc_attr($settings['event_' . $slug . '_edition']); ?>" class="small-text"></label>
                    </fieldset>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <th scope="row"><label for="sf-tecnico"><?php esc_html_e('Nome do papel "Técnico"', 'sciflow-wp'); ?></label></th>
            <td><input type="text" id="sf-tecnico" name="sciflow_settings[tecnico_label]" value="<?php echo esc_attr($settings['tecnico_label']); ?>" class="regular-text">
                <p class="description"><?php esc_html_e('Inscrição especial de técnicos de instituição parceira, que podem ser promovidos a revisor ou palestrante.', 'sciflow-wp'); ?></p></td>
        </tr>
    </table>
</div>

<div id="tab-identidade" class="sciflow-tab-content" style="display:none;">
    <h2><?php esc_html_e('Identidade visual', 'sciflow-wp'); ?></h2>
    <p class="description"><?php esc_html_e('Cores, fontes e logo aplicados ao site (tema SciFlow), aos formulários, aos e-mails, aos certificados e aos Anais. Também editável em Aparência → Personalizar, com o tema SciFlow ativo.', 'sciflow-wp'); ?></p>
    <?php SciFlow_Branding_Admin::fields($settings); ?>
</div>

<div id="tab-paginas" class="sciflow-tab-content" style="display:none;">
    <h2><?php esc_html_e('Páginas', 'sciflow-wp'); ?></h2>
    <p class="description">
        <?php esc_html_e('Selecione a página de cada função. "Automático" usa a página criada pelo SciFlow ou a primeira com o modelo correspondente. Para criar as páginas que faltam, use SciFlow → Diagnóstico.', 'sciflow-wp'); ?>
    </p>
    <table class="form-table" role="presentation">
        <?php foreach (SciFlow_Pages::definitions() as $role => $def) :
            $resolved = SciFlow_Pages::get_id($role); ?>
            <tr>
                <th scope="row"><label for="sf-page-<?php echo esc_attr($role); ?>"><?php echo esc_html($def['title']); ?></label></th>
                <td>
                    <select id="sf-page-<?php echo esc_attr($role); ?>" name="sciflow_settings[page_ids][<?php echo esc_attr($role); ?>]">
                        <option value="0"><?php esc_html_e('— Automático —', 'sciflow-wp'); ?></option>
                        <?php foreach ($sf_pages as $page) : ?>
                            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected((int) ($sf_page_ids[$role] ?? 0), $page->ID); ?>><?php echo esc_html($page->post_title . ' (#' . $page->ID . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html($def['description']); ?>
                        <?php if ($resolved) : ?>
                            — <a href="<?php echo esc_url(get_permalink($resolved)); ?>" target="_blank" rel="noopener"><?php esc_html_e('em uso', 'sciflow-wp'); ?>: <?php echo esc_html(get_the_title($resolved)); ?></a>
                        <?php else : ?>
                            — <strong><?php esc_html_e('não encontrada', 'sciflow-wp'); ?></strong>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<div id="tab-inscricao" class="sciflow-tab-content" style="display:none;">
    <h2><?php esc_html_e('Inscrição e submissão', 'sciflow-wp'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Exigir inscrição paga', 'sciflow-wp'); ?></th>
            <td><label><input type="checkbox" name="sciflow_settings[require_paid_registration]" value="1" <?php checked('1', (string) $settings['require_paid_registration']); ?>>
                <?php esc_html_e('Somente usuários com pedido WooCommerce concluído/processando do produto de inscrição podem submeter trabalhos.', 'sciflow-wp'); ?></label>
                <p class="description"><?php esc_html_e('Editores, revisores, palestrantes e técnicos são isentos. Sem WooCommerce ou sem produto configurado, a exigência não se aplica. Os IDs dos produtos ficam na aba Geral.', 'sciflow-wp'); ?></p></td>
        </tr>
        <tr>
            <th scope="row"><label for="sf-poster-mb"><?php esc_html_e('Tamanho máximo do pôster (MB)', 'sciflow-wp'); ?></label></th>
            <td><input type="number" min="1" max="100" id="sf-poster-mb" name="sciflow_settings[poster_max_mb]" value="<?php echo esc_attr($settings['poster_max_mb']); ?>" class="small-text">
                <p class="description"><?php echo esc_html(sprintf(__('Limite do servidor (upload_max_filesize): %s.', 'sciflow-wp'), size_format(wp_max_upload_size()))); ?></p></td>
        </tr>
        <tr>
            <th scope="row"><label for="sf-speaker-mb"><?php esc_html_e('Tamanho máximo da palestra (MB)', 'sciflow-wp'); ?></label></th>
            <td><input type="number" min="1" max="200" id="sf-speaker-mb" name="sciflow_settings[speaker_max_mb]" value="<?php echo esc_attr($settings['speaker_max_mb']); ?>" class="small-text"></td>
        </tr>
    </table>
</div>

<div id="tab-email" class="sciflow-tab-content" style="display:none;">
    <h2><?php esc_html_e('Remetente dos e-mails', 'sciflow-wp'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="sf-from-name"><?php esc_html_e('Nome do remetente', 'sciflow-wp'); ?></label></th>
            <td><input type="text" id="sf-from-name" name="sciflow_settings[email_from_name]" value="<?php echo esc_attr($settings['email_from_name']); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="sf-from-email"><?php esc_html_e('E-mail do remetente', 'sciflow-wp'); ?></label></th>
            <td><input type="email" id="sf-from-email" name="sciflow_settings[email_from_address]" value="<?php echo esc_attr($settings['email_from_address']); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                <p class="description"><?php esc_html_e('Use um endereço do mesmo domínio do site e autorizado no SPF/DKIM do provedor SMTP, para evitar que os e-mails caiam no spam.', 'sciflow-wp'); ?></p></td>
        </tr>
    </table>
</div>

<div id="tab-certificados" class="sciflow-tab-content" style="display:none;">
    <h2><?php esc_html_e('Certificados', 'sciflow-wp'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Acesso ao download', 'sciflow-wp'); ?></th>
            <td><fieldset>
                <label><input type="radio" name="sciflow_settings[certificate_access]" value="logged_in" <?php checked('logged_in', $settings['certificate_access']); ?>>
                    <?php esc_html_e('Somente logado, e apenas o certificado do próprio e-mail (recomendado)', 'sciflow-wp'); ?></label><br>
                <label><input type="radio" name="sciflow_settings[certificate_access]" value="email" <?php checked('email', $settings['certificate_access']); ?>>
                    <?php esc_html_e('Qualquer visitante informando um e-mail (comportamento antigo — permite baixar certificados de terceiros)', 'sciflow-wp'); ?></label>
            </fieldset>
            <p class="description"><?php esc_html_e('Os PDFs ficam em wp-content/uploads/certificados/<email>.pdf e o acesso direto à pasta é bloqueado.', 'sciflow-wp'); ?></p></td>
        </tr>
        <tr>
            <th scope="row"><label for="sf-cert-rl"><?php esc_html_e('Tentativas por IP (10 min)', 'sciflow-wp'); ?></label></th>
            <td><input type="number" min="1" max="1000" id="sf-cert-rl" name="sciflow_settings[certificate_rate_limit]" value="<?php echo esc_attr($settings['certificate_rate_limit']); ?>" class="small-text"></td>
        </tr>
    </table>

    <h2><?php esc_html_e('Anais', 'sciflow-wp'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="sf-anais-title"><?php esc_html_e('Título', 'sciflow-wp'); ?></label></th>
            <td><input type="text" id="sf-anais-title" name="sciflow_settings[anais_title]" value="<?php echo esc_attr($settings['anais_title']); ?>" class="large-text" placeholder="Anais – ..."></td>
        </tr>
        <tr>
            <th scope="row"><label for="sf-anais-header"><?php esc_html_e('Cabeçalho das páginas', 'sciflow-wp'); ?></label></th>
            <td><textarea id="sf-anais-header" name="sciflow_settings[anais_header]" rows="3" class="large-text"><?php echo esc_textarea($settings['anais_header']); ?></textarea>
                <p class="description"><?php esc_html_e('Aceita <br>, <strong> e <em>. Vazio: nomes completos dos eventos.', 'sciflow-wp'); ?></p></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Páginas pré-textuais', 'sciflow-wp'); ?></th>
            <td><fieldset>
                <label><input type="radio" name="sciflow_settings[anais_pretextual]" value="custom" <?php checked('custom', $settings['anais_pretextual']); ?>> <?php esc_html_e('Personalizadas (campos abaixo)', 'sciflow-wp'); ?></label><br>
                <label><input type="radio" name="sciflow_settings[anais_pretextual]" value="legacy" <?php checked('legacy', $settings['anais_pretextual']); ?>> <?php esc_html_e('Modelo original (XIX Enfrute / III Semco 2026)', 'sciflow-wp'); ?></label>
            </fieldset></td>
        </tr>
        <tr>
            <th scope="row"><label for="sf-anais-catalog"><?php esc_html_e('Ficha catalográfica', 'sciflow-wp'); ?></label></th>
            <td><textarea id="sf-anais-catalog" name="sciflow_settings[anais_catalog_html]" rows="6" class="large-text"><?php echo esc_textarea($settings['anais_catalog_html']); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="sf-anais-presentation"><?php esc_html_e('Apresentação', 'sciflow-wp'); ?></label></th>
            <td><textarea id="sf-anais-presentation" name="sciflow_settings[anais_presentation_html]" rows="6" class="large-text"><?php echo esc_textarea($settings['anais_presentation_html']); ?></textarea></td>
        </tr>
    </table>
</div>

<div id="tab-avancado" class="sciflow-tab-content" style="display:none;">
    <h2><?php esc_html_e('Avançado', 'sciflow-wp'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Ao excluir o plugin', 'sciflow-wp'); ?></th>
            <td><label><input type="checkbox" name="sciflow_settings[delete_data_on_uninstall]" value="1" <?php checked('1', (string) $settings['delete_data_on_uninstall']); ?>>
                <?php esc_html_e('Remover configurações, papéis e tarefas agendadas do SciFlow.', 'sciflow-wp'); ?></label>
                <p class="description"><?php esc_html_e('Trabalhos, palestras, pôsteres e usuários nunca são apagados automaticamente. Desmarcado (padrão), nada é removido.', 'sciflow-wp'); ?></p></td>
        </tr>
    </table>
</div>

<?php
/**
 * Import and Export functionality for SciFlow works.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Import_Export
{
    public function __construct()
    {
        add_action('admin_post_sciflow_export_works', array($this, 'handle_export'));
        add_action('admin_post_sciflow_export_csv', array($this, 'handle_export_csv'));
        add_action('admin_post_sciflow_export_approved_csv', array($this, 'handle_export_approved_csv'));
        add_action('admin_post_sciflow_export_poster_aprovado_csv', array($this, 'handle_export_poster_aprovado_csv'));
        add_action('admin_post_sciflow_export_authors_clean', array($this, 'handle_export_authors_clean'));
        add_action('admin_post_sciflow_export_apresentacao_oral_csv', array($this, 'handle_export_apresentacao_oral_csv'));
        add_action('admin_post_sciflow_import_works', array($this, 'handle_import'));
    }

    /**
     * Render the admin page UI.
     */
    public function render_page()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Exportar / Importar Trabalhos', 'sciflow-wp'); ?></h1>

            <?php
            if (isset($_GET['sciflow_imported'])) {
                $count = absint($_GET['sciflow_imported']);
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('Sucesso! Foram importados/atualizados %d trabalhos.', 'sciflow-wp'), $count) . '</p></div>';
            }
            if (isset($_GET['sciflow_import_error'])) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Erro ao importar o arquivo. Verifique se é um JSON válido.', 'sciflow-wp') . '</p></div>';
            }
            ?>

            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e('Exportar', 'sciflow-wp'); ?></h2>
                <p><?php esc_html_e('Baixe todos os trabalhos (Enfrute e Semco) em um arquivo JSON. Os arquivos de pôster não são incluídos no arquivo exportado.', 'sciflow-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_export_works">
                    <?php wp_nonce_field('sciflow_export_works'); ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Baixar JSON', 'sciflow-wp'); ?></button>
                </form>
                <hr style="margin: 20px 0;">
                <p><?php esc_html_e('Baixe uma planilha CSV com os dados principais dos resumos (Enfrute e Semco) para Excel.', 'sciflow-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_export_csv">
                    <?php wp_nonce_field('sciflow_export_csv'); ?>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Baixar Planilha CSV', 'sciflow-wp'); ?></button>
                </form>
                <hr style="margin: 20px 0;">
                <h3 style="margin-top: 0; color: #1d2327; font-size: 1.15em;">✅ <?php esc_html_e('Exportar Artigos Aprovados (planilha direta)', 'sciflow-wp'); ?></h3>
                <p><?php esc_html_e('Baixe uma planilha com Nome do autor, Nome do trabalho e E-mail de todos os artigos aprovados — independente de o pôster ter sido enviado ou não. Inclui os status: Aprovado, Pôster Enviado, Pôster em Correção, Pôster Reenviado, Pôster Aprovado, Pôster Reprovado e Aprovado / Concluído.', 'sciflow-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_export_approved_csv">
                    <?php wp_nonce_field('sciflow_export_approved_csv'); ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Baixar Planilha — Aprovados', 'sciflow-wp'); ?></button>
                </form>
                <hr style="margin: 20px 0;">
                <p><?php esc_html_e('Baixe uma planilha CSV com os dados dos autores cujo pôster foi aprovado (status "Pôster Aprovado"), incluindo coautores.', 'sciflow-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_export_poster_aprovado_csv">
                    <?php wp_nonce_field('sciflow_export_poster_aprovado_csv'); ?>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Baixar CSV – Pôsteres Aprovados', 'sciflow-wp'); ?></button>
                </form>
                <hr style="margin: 20px 0;">
                <h3 style="margin-top: 0; color: #1d2327; font-size: 1.15em;">📄 <?php esc_html_e('Exportar Autores e Trabalhos (Sem Coautores)', 'sciflow-wp'); ?></h3>
                <p><?php esc_html_e('Baixe uma planilha com Nome do autor, Nome do trabalho e E-mail, gerando apenas 1 linha por trabalho e sem incluir dados de coautores.', 'sciflow-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_export_authors_clean">
                    <?php wp_nonce_field('sciflow_export_authors_clean'); ?>
                    
                    <p style="margin-bottom: 8px;">
                        <label for="export_clean_event"><strong><?php esc_html_e('Evento:', 'sciflow-wp'); ?></strong></label><br>
                        <select name="export_event" id="export_clean_event" style="width: 100%; max-width: 320px;">
                            <option value="all"><?php esc_html_e('Todos os Eventos (Enfrute e Semco)', 'sciflow-wp'); ?></option>
                            <option value="enfrute"><?php esc_html_e('Apenas Enfrute', 'sciflow-wp'); ?></option>
                            <option value="semco"><?php esc_html_e('Apenas Semco', 'sciflow-wp'); ?></option>
                        </select>
                    </p>

                    <p style="margin-bottom: 8px;">
                        <label for="export_clean_status"><strong><?php esc_html_e('Status do Trabalho:', 'sciflow-wp'); ?></strong></label><br>
                        <select name="export_status" id="export_clean_status" style="width: 100%; max-width: 320px;">
                            <option value="all"><?php esc_html_e('Todos os Status', 'sciflow-wp'); ?></option>
                            <option value="todos_aprovados"><?php esc_html_e('Todos os Aprovados (independente do pôster)', 'sciflow-wp'); ?></option>
                            <option value="poster_aprovado"><?php esc_html_e('Pôster Aprovado / Concluído', 'sciflow-wp'); ?></option>
                            <?php
                            if (!class_exists('SciFlow_Status_Manager')) {
                                require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-status-manager.php';
                            }
                            $sm_render = new SciFlow_Status_Manager();
                            foreach ($sm_render->get_statuses() as $s_k => $s_l) {
                                if ($s_k === 'poster_aprovado') continue;
                                echo '<option value="' . esc_attr($s_k) . '">' . esc_html($s_l) . '</option>';
                            }
                            ?>
                        </select>
                    </p>

                    <p style="margin-bottom: 8px;">
                        <label for="export_clean_format"><strong><?php esc_html_e('Formato do Arquivo:', 'sciflow-wp'); ?></strong></label><br>
                        <select name="export_format" id="export_clean_format" style="width: 100%; max-width: 320px;">
                            <option value="csv"><?php esc_html_e('CSV (Ponto e vírgula com UTF-8 BOM, ideal para Excel)', 'sciflow-wp'); ?></option>
                            <option value="xls"><?php esc_html_e('Excel (.xls)', 'sciflow-wp'); ?></option>
                        </select>
                    </p>

                    <p style="margin-top: 10px; margin-bottom: 15px;">
                        <label>
                            <input type="checkbox" name="unique_emails" value="1">
                            <?php esc_html_e('Apenas e-mails únicos (remover duplicados caso o autor tenha múltiplos trabalhos)', 'sciflow-wp'); ?>
                        </label>
                    </p>

                    <button type="submit" class="button button-primary"><?php esc_html_e('Baixar Planilha (Sem Coautores)', 'sciflow-wp'); ?></button>
                </form>
                <hr style="margin: 20px 0;">
                <h3 style="margin-top: 0; color: #2271b1; font-size: 1.15em;">🎤 <?php esc_html_e('Exportar Apresentação Oral — Dados dos Anais', 'sciflow-wp'); ?></h3>
                <p><?php esc_html_e('Exporta somente os trabalhos selecionados para apresentação oral (status "Aguardando Confirmação" e "Confirmado"), com todos os campos que aparecem nos anais: título, evento, área, linha de autores com afiliações, coautores individuais, corpo do resumo, palavras-chave e agradecimentos.', 'sciflow-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_export_apresentacao_oral_csv">
                    <?php wp_nonce_field('sciflow_export_apresentacao_oral_csv'); ?>
                    <button type="submit" class="button" style="background:#2271b1;color:#fff;border-color:#2271b1;">
                        <?php esc_html_e('Baixar CSV — Apresentação Oral', 'sciflow-wp'); ?>
                    </button>
                </form>
            </div>

            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2><?php esc_html_e('Importar', 'sciflow-wp'); ?></h2>
                <p><?php esc_html_e('Selecione o arquivo JSON baixado anteriormente para importar para este ambiente. Trabalhos com o mesmo título serão substituídos. Autores que não existem serão criados (apenas com e-mail e papel de inscrito).', 'sciflow-wp'); ?></p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sciflow_import_works">
                    <?php wp_nonce_field('sciflow_import_works'); ?>
                    <p><input type="file" name="import_file" accept=".json" required></p>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Importar JSON', 'sciflow-wp'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle the export request.
     */
        /**
     * Handle CSV export.
     */
    public function handle_export_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sciflow_export_csv');

        $query = new WP_Query(array(
            'post_type'      => array('enfrute_trabalhos', 'semco_trabalhos'),
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=resumos_export_' . date('Y-m-d') . '.csv');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Nome do trabalho', 'Nome do autor', 'Status Atual', 'Evento', 'Instituição do Autor Principal', 'CPF', 'E-mail', 'Telefone'));

        if (!class_exists('SciFlow_Status_Manager')) {
            require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-status-manager.php';
        }
        $sm = new SciFlow_Status_Manager();

        foreach ($query->posts as $post) {
            $title = $post->post_title;
            $author = get_post_meta($post->ID, '_sciflow_main_author_name', true);
            
            // If the author name isn't directly stored, try to get it from the user
            if (empty($author)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
                if ($author_id) {
                    $user = get_userdata($author_id);
                    if ($user) $author = $user->display_name;
                }
            }
            
            $status_label = $sm->get_status_label($sm->get_status($post->ID));
            $event = ($post->post_type === 'enfrute_trabalhos') ? 'Enfrute' : 'Semco';
            $inst = get_post_meta($post->ID, '_sciflow_main_author_instituicao', true);
            $cpf = get_post_meta($post->ID, '_sciflow_main_author_cpf', true);
            $email = get_post_meta($post->ID, '_sciflow_main_author_email', true);
            $phone = get_post_meta($post->ID, '_sciflow_main_author_telefone', true);

            fputcsv($output, array($title, $author, $status_label, $event, $inst, $cpf, $email, $phone));
        }

        fclose($output);
        exit;
    }

    /**
     * Export approved articles to CSV — one row per article, no co-authors.
     *
     * An article is considered approved if its _sciflow_status is any of:
     *   aprovado, poster_enviado, poster_em_correcao, poster_reenviado,
     *   poster_aprovado, poster_reprovado, apto_publicacao.
     *
     * The poster submission status is NOT a criterion — an approved article
     * with no poster submitted is still exported.
     */
    public function handle_export_approved_csv()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para exportar dados.', 'sciflow-wp'));
        }
        check_admin_referer('sciflow_export_approved_csv');

        if (!class_exists('SciFlow_Status_Manager')) {
            require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-status-manager.php';
        }
        $sm = new SciFlow_Status_Manager();

        // All statuses that indicate an article has been approved,
        // regardless of whether a poster has been submitted yet.
        $approved_statuses = array(
            'aprovado',
            'poster_enviado',
            'poster_em_correcao',
            'poster_reenviado',
            'poster_aprovado',
            'poster_reprovado',
            'apto_publicacao',
        );

        $query = new WP_Query(array(
            'post_type'      => array('enfrute_trabalhos', 'semco_trabalhos'),
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
            // Pre-filter at query level: only fetch posts whose _sciflow_status
            // is in the approved set, saving memory on large datasets.
            'meta_query'     => array(
                array(
                    'key'     => '_sciflow_status',
                    'value'   => $approved_statuses,
                    'compare' => 'IN',
                ),
            ),
        ));

        $filename = 'aprovados_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel / Google Sheets

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Nome do autor', 'Nome do trabalho', 'E-mail'), ';');

        foreach ($query->posts as $post) {
            // Double-check status in PHP (meta_query already filtered, but defensive)
            $current_status = $sm->get_status($post->ID);
            if (!in_array($current_status, $approved_statuses, true)) {
                continue;
            }

            // Author name
            $author_name = get_post_meta($post->ID, '_sciflow_main_author_name', true);
            if (empty($author_name)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true) ?: $post->post_author;
                if ($author_id) {
                    $user = get_userdata($author_id);
                    if ($user) {
                        $author_name = $user->display_name;
                    }
                }
            }
            $author_name = trim(html_entity_decode((string) $author_name, ENT_QUOTES, 'UTF-8'));

            // Work title
            $work_title = trim(html_entity_decode((string) $post->post_title, ENT_QUOTES, 'UTF-8'));

            // Author email
            $email = get_post_meta($post->ID, '_sciflow_main_author_email', true);
            if (empty($email)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true) ?: $post->post_author;
                if ($author_id) {
                    $user = get_userdata($author_id);
                    if ($user) {
                        $email = $user->user_email;
                    }
                }
            }
            $email = strtolower(trim((string) $email));

            fputcsv($output, array($author_name, $work_title, $email), ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Handle CSV export for works with 'poster_aprovado' status.
     * Includes main author data and co-authors on separate rows.
     */
    public function handle_export_poster_aprovado_csv()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sciflow_export_poster_aprovado_csv');

        if (!class_exists('SciFlow_Status_Manager')) {
            require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-status-manager.php';
        }
        $sm = new SciFlow_Status_Manager();

        // Status que indicam que o pôster foi aprovado (inclui apto_publicacao pois vem após poster_aprovado)
        $poster_aprovado_statuses = array('poster_aprovado', 'apto_publicacao');

        $query = new WP_Query(array(
            'post_type'      => array('enfrute_trabalhos', 'semco_trabalhos'),
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=poster_aprovado_' . date('Y-m-d') . '.csv');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel

        $output = fopen('php://output', 'w');
        fputcsv($output, array(
            'Título do Trabalho',
            'Evento',
            'Tipo de Autor',
            'Nome',
            'CPF',
            'E-mail',
            'Telefone',
            'Instituição',
            'URL do Pôster',
        ));

        foreach ($query->posts as $post) {
            // Filtra apenas trabalhos com pôster aprovado
            $current_status = $sm->get_status($post->ID);
            if (!in_array($current_status, $poster_aprovado_statuses, true)) {
                continue;
            }

            $event     = ($post->post_type === 'enfrute_trabalhos') ? 'Enfrute' : 'Semco';
            $title     = $post->post_title;

            // Poster file URL
            $poster_id  = get_post_meta($post->ID, '_sciflow_poster_id', true);
            $poster_url = $poster_id ? wp_get_attachment_url($poster_id) : '';

            // ── Autor principal ──────────────────────────────────────────────
            $main_name  = get_post_meta($post->ID, '_sciflow_main_author_name', true);
            if (empty($main_name)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
                if ($author_id) {
                    $user = get_userdata($author_id);
                    if ($user) $main_name = $user->display_name;
                }
            }
            $main_cpf   = get_post_meta($post->ID, '_sciflow_main_author_cpf', true);
            $main_email = get_post_meta($post->ID, '_sciflow_main_author_email', true);
            $main_phone = get_post_meta($post->ID, '_sciflow_main_author_telefone', true);
            $main_inst  = get_post_meta($post->ID, '_sciflow_main_author_instituicao', true);

            fputcsv($output, array(
                $title,
                $event,
                'Autor Principal',
                $main_name,
                $main_cpf,
                $main_email,
                $main_phone,
                $main_inst,
                $poster_url,
            ));

            // ── Coautores ────────────────────────────────────────────────────
            $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
            if (!is_array($coauthors)) $coauthors = array();

            foreach ($coauthors as $co) {
                if (empty($co) || !is_array($co)) continue;
                $co_name  = isset($co['name'])         ? $co['name']         : '';
                $co_cpf   = isset($co['cpf'])          ? $co['cpf']          : '';
                $co_email = isset($co['email'])        ? $co['email']        : '';
                $co_phone = isset($co['telefone'])     ? $co['telefone']     : (isset($co['phone']) ? $co['phone'] : '');
                $co_inst  = isset($co['instituicao'])  ? $co['instituicao']  : (isset($co['institution']) ? $co['institution'] : '');

                if (empty($co_name) && empty($co_email)) continue;

                fputcsv($output, array(
                    $title,
                    $event,
                    'Coautor',
                    $co_name,
                    $co_cpf,
                    $co_email,
                    $co_phone,
                    $co_inst,
                    $poster_url,
                ));
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Export authors, work titles and emails cleanly without co-authors.
     */
    public function handle_export_authors_clean()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para exportar dados.', 'sciflow-wp'));
        }
        check_admin_referer('sciflow_export_authors_clean');

        $event_filter  = isset($_POST['export_event']) ? sanitize_text_field($_POST['export_event']) : 'all';
        $status_filter = isset($_POST['export_status']) ? sanitize_text_field($_POST['export_status']) : 'all';
        $format        = isset($_POST['export_format']) && $_POST['export_format'] === 'xls' ? 'xls' : 'csv';
        $unique_emails = !empty($_POST['unique_emails']);

        $post_types = array('enfrute_trabalhos', 'semco_trabalhos');
        if ($event_filter === 'enfrute') {
            $post_types = array('enfrute_trabalhos');
        } elseif ($event_filter === 'semco') {
            $post_types = array('semco_trabalhos');
        }

        $query_args = array(
            'post_type'      => $post_types,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $query = new WP_Query($query_args);

        if (!class_exists('SciFlow_Status_Manager')) {
            require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-status-manager.php';
        }
        $sm = new SciFlow_Status_Manager();

        // DEBUG TEMPORÁRIO: mostra diagnóstico se ?sciflow_debug=1 estiver na URL
        if (!empty($_GET['sciflow_debug'])) {
            $debug = array(
                'total_posts_found' => count($query->posts),
                'status_filter'     => $status_filter,
                'event_filter'      => $event_filter,
                'post_types'        => $post_types,
                'posts'             => array(),
            );
            foreach ($query->posts as $p) {
                $debug['posts'][] = array(
                    'ID'             => $p->ID,
                    'title'          => $p->post_title,
                    'wp_status'      => $p->post_status,
                    'sciflow_status' => $sm->get_status($p->ID),
                );
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $filename = 'autores_trabalhos_' . date('Y-m-d_His') . '.' . $format;

        if ($format === 'xls') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1">';
            echo '<tr><th>' . esc_html__('Nome do autor', 'sciflow-wp') . '</th><th>' . esc_html__('Nome do trabalho', 'sciflow-wp') . '</th><th>' . esc_html__('E-mail', 'sciflow-wp') . '</th></tr>';
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel / Google Sheets
            $output = fopen('php://output', 'w');
            fputcsv($output, array('Nome do autor', 'Nome do trabalho', 'E-mail'), ';');
        }

        $seen_emails = array();

        // DEBUG: loga info no PHP error log para diagnóstico
        error_log('[SciFlow Export Debug] status_filter=' . $status_filter . ' | event_filter=' . $event_filter . ' | total_posts=' . count($query->posts));
        foreach ($query->posts as $post) {
            error_log('[SciFlow Export Debug] Post ID=' . $post->ID . ' | wp_status=' . $post->post_status . ' | sciflow_status=' . $sm->get_status($post->ID) . ' | title=' . $post->post_title);
        }

        // All statuses that indicate an article has been approved (regardless of poster submission state).
        $approved_statuses = array(
            'aprovado',
            'poster_enviado',
            'poster_em_correcao',
            'poster_reenviado',
            'poster_aprovado',
            'poster_reprovado',
            'apto_publicacao',
        );

        foreach ($query->posts as $post) {
            $current_status = $sm->get_status($post->ID);

            // Filter status if requested
            if ($status_filter === 'todos_aprovados') {
                // Export all articles that have been approved, regardless of poster submission status.
                if (!in_array($current_status, $approved_statuses, true)) {
                    continue;
                }
            } elseif ($status_filter === 'poster_aprovado') {
                if (!in_array($current_status, array('poster_aprovado', 'apto_publicacao'), true)) {
                    continue;
                }
            } elseif ($status_filter !== 'all') {
                if ($current_status !== $status_filter) {
                    continue;
                }
            }

            // Author Name
            $author_name = get_post_meta($post->ID, '_sciflow_main_author_name', true);
            if (empty($author_name)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true) ?: $post->post_author;
                if ($author_id) {
                    $user = get_userdata($author_id);
                    if ($user) {
                        $author_name = $user->display_name;
                    }
                }
            }
            $author_name = trim(html_entity_decode((string)$author_name, ENT_QUOTES, 'UTF-8'));

            // Work Title
            $work_title = trim(html_entity_decode((string)$post->post_title, ENT_QUOTES, 'UTF-8'));

            // Author Email
            $email = get_post_meta($post->ID, '_sciflow_main_author_email', true);
            if (empty($email)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true) ?: $post->post_author;
                if ($author_id) {
                    $user = get_userdata($author_id);
                    if ($user) {
                        $email = $user->user_email;
                    }
                }
            }
            $email = strtolower(trim((string)$email));

            // Skip duplicate emails if requested
            if ($unique_emails && !empty($email)) {
                if (isset($seen_emails[$email])) {
                    continue;
                }
                $seen_emails[$email] = true;
            }

            if ($format === 'xls') {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($work_title, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '</tr>';
            } else {
                fputcsv($output, array($author_name, $work_title, $email), ';');
            }
        }

        if ($format === 'xls') {
            echo '</table></body></html>';
        } else {
            fclose($output);
        }

        exit;
    }

    /**
     * Exporta somente os trabalhos de apresentação oral (aguardando_confirmacao / confirmado)
     * com todos os campos presentes nos anais: título, evento, área de conhecimento,
     * linha de autores formatada (com superscripts de afiliação), afiliações, coautores
     * individualmente, corpo do resumo (texto puro), palavras-chave e agradecimentos.
     */
    public function handle_export_apresentacao_oral_csv()
    {
        if (!current_user_can('manage_options') && !current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para exportar dados.', 'sciflow-wp'));
        }
        check_admin_referer('sciflow_export_apresentacao_oral_csv');

        if (!class_exists('SciFlow_Status_Manager')) {
            require_once SCIFLOW_PATH . 'includes/workflow/class-sciflow-status-manager.php';
        }
        if (!class_exists('SciFlow_Anais')) {
            require_once SCIFLOW_PATH . 'includes/admin/class-sciflow-anais.php';
        }

        $oral_statuses = array('aguardando_confirmacao', 'confirmado');

        $query = new WP_Query(array(
            'post_type'      => array('enfrute_trabalhos', 'semco_trabalhos'),
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_sciflow_status',
                    'value'   => $oral_statuses,
                    'compare' => 'IN',
                ),
            ),
            'meta_key'  => '_sciflow_knowledge_area',
            'orderby'   => array('meta_value' => 'ASC', 'title' => 'ASC'),
        ));

        $filename = 'apresentacao_oral_anais_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel

        $output = fopen('php://output', 'w');

        // Cabeçalho: campos dos anais + dados de contato do autor principal
        fputcsv($output, array(
            'Evento',
            'Status',
            'Área de Conhecimento',
            'Título',
            'Linha de Autores (formato anais)',
            'Afiliações (formato anais)',
            'Autor Principal — Nome',
            'Autor Principal — Instituição',
            'Autor Principal — E-mail',
            'Coautores (nome | instituição | e-mail)',
            'Resumo (texto puro)',
            'Palavras-chave',
            'Agradecimentos',
        ), ';');

        $sm = new SciFlow_Status_Manager();

        foreach ($query->posts as $post) {
            $current_status = $sm->get_status($post->ID);
            if (!in_array($current_status, $oral_statuses, true)) {
                continue;
            }

            // Evento
            $event = ($post->post_type === 'enfrute_trabalhos') ? 'XIX Enfrute' : 'III Semco';

            // Status legível
            $status_label = $sm->get_status_label($current_status);

            // Área de conhecimento
            $area = get_post_meta($post->ID, '_sciflow_knowledge_area', true) ?: '';

            // Título (texto puro)
            $title = html_entity_decode(strip_tags($post->post_title), ENT_QUOTES, 'UTF-8');

            // Autor principal
            $main_name  = get_post_meta($post->ID, '_sciflow_main_author_name', true);
            if (empty($main_name)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true) ?: $post->post_author;
                $user      = $author_id ? get_userdata($author_id) : null;
                if ($user) $main_name = $user->display_name;
            }
            $main_name  = trim(html_entity_decode((string) $main_name, ENT_QUOTES, 'UTF-8'));
            $main_inst  = trim((string) get_post_meta($post->ID, '_sciflow_main_author_instituicao', true));
            $main_email = trim((string) get_post_meta($post->ID, '_sciflow_main_author_email', true));
            if (empty($main_email)) {
                $author_id = get_post_meta($post->ID, '_sciflow_author_id', true) ?: $post->post_author;
                $user      = $author_id ? get_userdata($author_id) : null;
                if ($user) $main_email = $user->user_email;
            }

            // Coautores
            $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
            if (!is_array($coauthors)) $coauthors = array();

            // Linha de autores e afiliações no formato dos anais (usa método estático da classe Anais)
            $ad = SciFlow_Anais::build_author_affiliations($main_name, $main_inst, $coauthors);
            $authors_line = strip_tags($ad['authors_line']);
            $affils_line  = strip_tags($ad['affiliations_line']);

            // Coautores — formato "Nome | Instituição | E-mail" separados por " / "
            $co_parts = array();
            foreach ($coauthors as $co) {
                if (empty($co) || !is_array($co)) continue;
                $co_name  = trim($co['name']         ?? '');
                $co_inst  = trim($co['instituicao']  ?? ($co['institution'] ?? ''));
                $co_email = trim($co['email']        ?? '');
                if (empty($co_name)) continue;
                $co_parts[] = implode(' | ', array_filter(array($co_name, $co_inst, $co_email)));
            }
            $coauthors_cell = implode(' / ', $co_parts);

            // Corpo do resumo — texto puro, sem HTML
            $body = html_entity_decode(strip_tags($post->post_content), ENT_QUOTES, 'UTF-8');
            $body = preg_replace('/\s+/', ' ', trim($body));

            // Palavras-chave
            $keywords = get_post_meta($post->ID, '_sciflow_keywords', true);
            if (is_array($keywords)) {
                $keywords = implode('; ', array_filter(array_map('trim', $keywords)));
            } else {
                $keywords = trim((string) $keywords);
            }

            // Agradecimentos
            $ack = trim(strip_tags((string) get_post_meta($post->ID, '_sciflow_acknowledgement', true)));

            fputcsv($output, array(
                $event,
                $status_label,
                $area,
                $title,
                $authors_line,
                $affils_line,
                $main_name,
                $main_inst,
                $main_email,
                $coauthors_cell,
                $body,
                $keywords,
                $ack,
            ), ';');
        }

        fclose($output);
        exit;
    }

    public function handle_export()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sciflow_export_works');

        $query = new WP_Query(array(
            'post_type'      => array('enfrute_trabalhos', 'semco_trabalhos'),
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ));

        $export_data = array();

        foreach ($query->posts as $post) {
            $meta = get_post_meta($post->ID);
            
            // Clean up poster data from export
            unset($meta['_sciflow_poster_id']);
            unset($meta['_sciflow_poster_file']);
            unset($meta['_sciflow_poster_file_url']);

            // Resolve author email for reconciliation
            $author_email = '';
            $author_id = get_post_meta($post->ID, '_sciflow_author_id', true);
            if ($author_id) {
                $user = get_userdata($author_id);
                if ($user) {
                    $author_email = $user->user_email;
                }
            }
            if (empty($author_email)) {
                $author_email = get_post_meta($post->ID, '_sciflow_main_author_email', true);
            }

            // Flatten meta arrays
            $flat_meta = array();
            foreach ($meta as $k => $v) {
                if (is_array($v) && isset($v[0])) {
                    $flat_meta[$k] = maybe_unserialize($v[0]);
                } else {
                    $flat_meta[$k] = $v;
                }
            }

            $export_data[] = array(
                'post_type'    => $post->post_type,
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_status'  => $post->post_status,
                'post_date'    => $post->post_date,
                'author_email' => $author_email,
                'meta'         => $flat_meta,
            );
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=sciflow_export_' . date('Y-m-d') . '.json');
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Handle the import request.
     */
    public function handle_import()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('sciflow_import_works');

        if (empty($_FILES['import_file']['tmp_name'])) {
            wp_redirect(add_query_arg('sciflow_import_error', '1', wp_get_referer() ?: admin_url('admin.php?page=sciflow-import-export')));
            exit;
        }

        $json_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $data = json_decode($json_content, true);

        if (!is_array($data)) {
            wp_redirect(add_query_arg('sciflow_import_error', '1', wp_get_referer() ?: admin_url('admin.php?page=sciflow-import-export')));
            exit;
        }

        $imported_count = 0;

        foreach ($data as $item) {
            if (empty($item['post_title']) || empty($item['post_type'])) {
                continue;
            }

            // 1. Resolve author
            $author_id = get_current_user_id();
            if (!empty($item['author_email'])) {
                $user = get_user_by('email', $item['author_email']);
                if ($user) {
                    $author_id = $user->ID;
                } else {
                    // Create new user
                    $random_password = wp_generate_password(12, false);
                    $new_user_id = wp_create_user($item['author_email'], $random_password, $item['author_email']);
                    if (!is_wp_error($new_user_id)) {
                        $new_user = new WP_User($new_user_id);
                        $new_user->set_role('sciflow_inscrito');
                        $author_id = $new_user_id;
                    }
                }
            }

            // 2. Check if post exists (by title and type)
            $existing_post = get_page_by_title($item['post_title'], OBJECT, $item['post_type']);
            
            $post_data = array(
                'post_title'   => $item['post_title'],
                'post_content' => $item['post_content'],
                'post_status'  => $item['post_status'],
                'post_type'    => $item['post_type'],
                'post_date'    => $item['post_date'],
                'post_author'  => $author_id,
            );

            if ($existing_post) {
                $post_data['ID'] = $existing_post->ID;
                $post_id = wp_update_post($post_data);
            } else {
                $post_id = wp_insert_post($post_data);
            }

            if (is_wp_error($post_id) || $post_id == 0) {
                continue;
            }

            // 3. Update meta
            if (!empty($item['meta']) && is_array($item['meta'])) {
                foreach ($item['meta'] as $meta_key => $meta_value) {
                    // Make sure author ID matches the resolved one
                    if ($meta_key === '_sciflow_author_id') {
                        $meta_value = $author_id;
                    }
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }

            $imported_count++;
        }

        wp_redirect(add_query_arg('sciflow_imported', $imported_count, wp_get_referer() ?: admin_url('admin.php?page=sciflow-import-export')));
        exit;
    }
}

<?php
/**
 * Geração dos Anais – XIX Enfrute / III Semco 2026.
 *
 * Gera uma página HTML formatada para impressão como PDF.
 * Layout idêntico ao modelo `.doc` aprovado.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Anais
{
    private static $approved_statuses = array(
        'aprovado',
        'aprovado_com_consideracoes',
        'apto_publicacao',
        'poster_enviado',
        'poster_aprovado',
        'poster_em_correcao',
        'poster_reenviado',
        'poster_reprovado',
        'aguardando_confirmacao', // Selecionados para apresentação oral
        'confirmado',             // Confirmados para apresentação oral
    );

    public function render_page()
    {
        if (!current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }

        $volume_filter = sanitize_key($_GET['anais_volume'] ?? 'resumos');
        $merge_available = self::find_binary(array('chromium-browser', 'chromium', 'google-chrome', 'google-chrome-stable'))
            && self::find_binary(array('libreoffice', 'soffice')) && self::find_binary(array('gs'));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Anais', 'sciflow-wp'); ?> <span style="font-weight:400;color:#646970;font-size:15px;">— <?php echo esc_html($this->title()); ?></span></h1>
            <p class="description" style="max-width:760px;">
                <?php esc_html_e('A pré-visualização é paginada pelo navegador a partir do layout real (padrão CSS Paged Media): nenhum texto é cortado, cada trabalho começa em página nova, o cabeçalho e a numeração seguem o modelo aprovado e o sumário aponta para a página efetiva de cada trabalho. Aguarde a mensagem "N páginas" na barra superior e use "Imprimir / Salvar como PDF".', 'sciflow-wp'); ?>
            </p>

            <div class="card" style="max-width:760px;padding:20px;margin-top:20px;">
                <h2 style="margin-top:0;"><?php esc_html_e('Gerar Anais', 'sciflow-wp'); ?></h2>
                <form method="get" target="_blank" action="">
                    <input type="hidden" name="page" value="sciflow-anais">
                    <input type="hidden" name="anais_preview" value="1">
                    <table class="form-table" style="margin-bottom:0;">
                        <tr>
                            <th scope="row"><label for="anais_volume"><?php esc_html_e('Volume', 'sciflow-wp'); ?></label></th>
                            <td>
                                <select id="anais_volume" name="anais_volume">
                                    <option value="resumos" <?php selected($volume_filter, 'resumos'); ?>><?php esc_html_e('Vol. II – Resumos (todos os eventos)', 'sciflow-wp'); ?></option>
                                    <option value="resumos_enfrute" <?php selected($volume_filter, 'resumos_enfrute'); ?>><?php echo esc_html(sprintf(__('Vol. II – Resumos: somente %s', 'sciflow-wp'), $this->ev('enfrute'))); ?></option>
                                    <option value="resumos_semco" <?php selected($volume_filter, 'resumos_semco'); ?>><?php echo esc_html(sprintf(__('Vol. II – Resumos: somente %s', 'sciflow-wp'), $this->ev('semco'))); ?></option>
                                    <option value="palestras_resumos" <?php selected($volume_filter, 'palestras_resumos'); ?>><?php esc_html_e('Vol. I – Palestras (formato padronizado)', 'sciflow-wp'); ?></option>
                                    <option value="palestras" <?php selected($volume_filter, 'palestras'); disabled(!$merge_available); ?>><?php esc_html_e('Vol. I – Palestras: arquivos originais mesclados (PDF no servidor)', 'sciflow-wp'); ?></option>
                                </select>
                                <?php if (!$merge_available) : ?>
                                    <p class="description"><?php esc_html_e('O PDF com os arquivos originais exige LibreOffice, Ghostscript e Chromium/Chrome no servidor (ver Diagnóstico).', 'sciflow-wp'); ?></p>
                                <?php else : ?>
                                    <p class="description"><?php esc_html_e('"Arquivos originais" mantém a formatação de cada palestrante; o formato padronizado aplica o padrão do livro a todas.', 'sciflow-wp'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:16px;">
                        <button type="submit" class="button button-primary button-large"><?php esc_html_e('Abrir pré-visualização', 'sciflow-wp'); ?></button>
                    </p>
                </form>
            </div>

            <?php $this->render_stats(); ?>
        </div>
        <?php
    }

    private function render_stats()
    {
        $enfrute_posts = $this->get_approved_works('enfrute');
        $semco_posts   = $this->get_approved_works('semco');
        $enfrute_count = count($enfrute_posts);
        $semco_count   = count($semco_posts);
        $all_posts     = array_merge($enfrute_posts, $semco_posts);

        echo '<div class="card" style="max-width:680px;padding:20px;margin-top:20px;">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Resumo dos Dados', 'sciflow-wp') . '</h2>';
        echo '<table class="widefat striped" style="max-width:420px;margin-bottom:16px;">';
        echo '<thead><tr><th>' . esc_html__('Evento', 'sciflow-wp') . '</th><th>' . esc_html__('Resumos aprovados', 'sciflow-wp') . '</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td><strong>' . esc_html($this->ev('enfrute')) . '</strong></td><td>' . intval($enfrute_count) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html($this->ev('semco')) . '</strong></td><td>' . intval($semco_count) . '</td></tr>';
        echo '<tr><td><strong>' . esc_html__('Total', 'sciflow-wp') . '</strong></td><td><strong>' . ($enfrute_count + $semco_count) . '</strong></td></tr>';
        echo '</tbody></table>';

        $status_counts = array();
        foreach ($all_posts as $post) {
            $s = get_post_meta($post->ID, '_sciflow_status', true) ?: 'rascunho';
            $status_counts[$s] = ($status_counts[$s] ?? 0) + 1;
        }
        arsort($status_counts);
        $sm = new SciFlow_Status_Manager();
        echo '<details style="cursor:pointer;"><summary style="font-weight:bold;">' . esc_html__('Ver detalhamento por status', 'sciflow-wp') . '</summary>';
        echo '<table class="widefat striped" style="max-width:420px;margin-top:8px;">';
        echo '<thead><tr><th>' . esc_html__('Status', 'sciflow-wp') . '</th><th>' . esc_html__('Qtd', 'sciflow-wp') . '</th></tr></thead><tbody>';
        foreach ($status_counts as $status => $count) {
            echo '<tr><td>' . esc_html($sm->get_status_label($status)) . '</td><td>' . intval($count) . '</td></tr>';
        }
        echo '</tbody></table></details>';
        echo '</div>';
    }

    /**
     * Collect and sanitize everything the renderer needs.
     *
     * @param string $volume
     * @return array{items: array, sections: array, toc: array, reviewers: array}
     */
    public function collect($volume)
    {
        $is_palestras = in_array($volume, array('palestras', 'palestras_resumos'), true);
        $sections = array();
        $toc = array();
        $items = array();

        if ($is_palestras) {
            $by_event = array('enfrute' => array(), 'semco' => array());
            foreach ($this->get_lectures() as $p) {
                $ev = strtolower((string) get_post_meta($p->ID, '_sciflow_event', true));
                $by_event['semco' === $ev ? 'semco' : 'enfrute'][] = $p;
            }
            $section = array('title' => 'Palestras', 'subtitle' => $this->title(false), 'items' => array());
            foreach ($by_event as $ev => $posts) {
                if (!$posts) {
                    continue;
                }
                $toc[] = array('type' => 'area', 'text' => 'Palestras – ' . $this->ev($ev));
                foreach ($posts as $p) {
                    $item = $this->prepare_item($p, true);
                    $section['items'][] = $item;
                    $items[] = $item;
                    $toc[] = array('type' => 'post', 'id' => $p->ID, 'title_html' => $item['title_html']);
                }
            }
            $sections[] = $section;
        } else {
            $events = array();
            if (in_array($volume, array('resumos', 'resumos_enfrute'), true)) {
                $events[] = 'enfrute';
            }
            if (in_array($volume, array('resumos', 'resumos_semco'), true)) {
                $events[] = 'semco';
            }
            foreach ($events as $ev) {
                $posts = $this->get_approved_works($ev);
                if (!$posts) {
                    continue;
                }
                $toc[] = array('type' => 'event', 'text' => 'Resumos – ' . $this->ev($ev));
                foreach ($this->group_by_area($posts) as $area => $area_posts) {
                    $toc[] = array('type' => 'area', 'text' => $area);
                    $section = array('title' => 'Resumos – ' . $this->ev($ev), 'subtitle' => $area, 'items' => array());
                    foreach ($area_posts as $p) {
                        $item = $this->prepare_item($p, false);
                        $section['items'][] = $item;
                        $items[] = $item;
                        $toc[] = array('type' => 'post', 'id' => $p->ID, 'title_html' => $item['title_html']);
                    }
                    $sections[] = $section;
                }
            }
        }

        return array(
            'items'     => $items,
            'sections'  => $sections,
            'toc'       => $toc,
            'reviewers' => $is_palestras ? array() : $this->get_reviewers(),
        );
    }

    /**
     * All published lectures, ordered by title.
     *
     * @return WP_Post[]
     */
    public function get_lectures()
    {
        $query = new WP_Query(array(
            'post_type'      => 'sciflow_palestra',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        return $query->posts ?: array();
    }

    /**
     * Normalize one abstract or lecture for the renderer (all HTML sanitized).
     *
     * @param WP_Post $post
     * @param bool    $is_lecture
     * @return array
     */
    private function prepare_item($post, $is_lecture)
    {
        $main_name = (string) get_post_meta($post->ID, '_sciflow_main_author_name', true);
        if ($is_lecture && '' === trim($main_name)) {
            $u = get_userdata($post->post_author);
            $main_name = $u ? $u->display_name : '';
        }
        $coauthors = get_post_meta($post->ID, '_sciflow_coauthors', true);
        $ad = self::build_author_affiliations($main_name, (string) get_post_meta($post->ID, '_sciflow_main_author_instituicao', true), is_array($coauthors) ? $coauthors : array());

        $allowed = array('sup' => array(), 'sub' => array(), 'em' => array(), 'i' => array(), 'strong' => array(), 'b' => array(), 'br' => array(), 'a' => array('href' => array()));

        if ($is_lecture) {
            $attachment_id = (int) get_post_meta($post->ID, '_sciflow_attachment_id', true);
            $body = $attachment_id ? $this->extract_docx_content($attachment_id) : '';
            if ('' === trim(wp_strip_all_tags($body, true)) && false === strpos($body, '<img')) {
                $body = wp_kses_post($post->post_content);
            }
            // Images are embedded as data URIs; kses must keep that protocol.
            $body = wp_kses($body, wp_kses_allowed_html('post'), array_merge(wp_allowed_protocols(), array('data')));
        } else {
            $body = wp_kses_post($post->post_content);
            if (false === stripos($body, '<p')) {
                $body = wpautop($body);
            }
        }
        $body = $this->linkify_enfrute_urls($this->convert_scientific_notation($body));

        $ack = (string) get_post_meta($post->ID, '_sciflow_acknowledgement', true);
        $keywords = get_post_meta($post->ID, '_sciflow_keywords', true);
        if (!is_array($keywords)) {
            $keywords = $keywords ? array($keywords) : array();
        }
        $keywords = array_filter(array_map('trim', $keywords));

        $meta = '';
        if ($is_lecture) {
            $duration = (int) get_post_meta($post->ID, '_sciflow_duration', true);
            $meta = $duration ? sprintf('Duração: %d min', $duration) : '';
        }

        return array(
            'id'                => (int) $post->ID,
            'title_html'        => wp_kses($this->format_scientific_title($post->post_title), array('i' => array(), 'em' => array())),
            'authors_html'      => wp_kses($ad['authors_line'], array('sup' => array())),
            'affiliations_html' => wp_kses($ad['affiliations_line'], array('sup' => array())),
            'meta'              => $meta,
            'body_html'         => $body,
            'ack'               => '' !== trim($ack) ? wp_kses($this->convert_scientific_notation(esc_html($ack)), $allowed) : '',
            'keywords'          => $keywords ? wp_kses($this->convert_scientific_notation(esc_html(implode('; ', $keywords))), $allowed) : '',
        );
    }

    /**
     * Legacy "merged original files" lectures PDF: the speakers' own Word
     * files converted by LibreOffice, preceded by the standard preliminary
     * pages. Page numbers come from the measured PDFs; the preliminary part
     * is rendered until its page count is stable.
     *
     * @param WP_Post[] $palestra_posts
     */
    private function generate_server_pdf_palestras($palestra_posts)
    {
        $chrome = self::find_binary(array('chromium-browser', 'chromium', 'google-chrome', 'google-chrome-stable'));
        if (!$chrome || !self::find_binary(array('libreoffice', 'soffice')) || !self::find_binary(array('gs'))) {
            wp_die(esc_html__('Este modo exige LibreOffice, Ghostscript e Chromium/Chrome no servidor. Use a pré-visualização padronizada das palestras.', 'sciflow-wp'));
        }
        @set_time_limit(600);

        $temp_dir = untrailingslashit(SciFlow_Security::private_dir('anais-' . time() . '-' . wp_generate_password(8, false, false)));
        $soffice = self::find_binary(array('libreoffice', 'soffice'));

        // 1. Convert each lecture file and measure its pages.
        $data = $this->collect('palestras');
        $ordered = array();
        foreach ($data['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $ordered[] = $item['id'];
            }
        }
        $lecture_pdfs = array();
        $lecture_pages = array();
        foreach ($ordered as $post_id) {
            $file_path = get_attached_file((int) get_post_meta($post_id, '_sciflow_attachment_id', true));
            $pages = 0;
            if ($file_path && file_exists($file_path)) {
                shell_exec(sprintf('%s --headless --convert-to pdf %s --outdir %s 2>&1', escapeshellarg($soffice), escapeshellarg($file_path), escapeshellarg($temp_dir))); // phpcs:ignore
                $pdf_path = $temp_dir . '/' . pathinfo($file_path, PATHINFO_FILENAME) . '.pdf';
                if (file_exists($pdf_path)) {
                    $pages = max(1, $this->count_pdf_pages($pdf_path));
                    $lecture_pdfs[] = $pdf_path;
                }
            }
            $lecture_pages[$post_id] = $pages;
        }

        // 2. Render the preliminary part until its own length is stable.
        $prelim_pdf = $temp_dir . '/preliminar.pdf';
        $prelim_pages = 0;
        for ($pass = 0; $pass < 4; $pass++) {
            $fixed = array();
            $next = $prelim_pages; // physical index of the first lecture page (cover = 0)
            foreach ($ordered as $post_id) {
                $fixed[$post_id] = $next;
                $next += max(1, $lecture_pages[$post_id]);
            }
            ob_start();
            (new SciFlow_Anais_Renderer($this))->render('palestras', $data, $fixed, true);
            file_put_contents($temp_dir . '/preliminar.html', ob_get_clean());
            @unlink($prelim_pdf);
            shell_exec(sprintf('%s --headless=new --no-sandbox --disable-gpu --no-pdf-header-footer --allow-file-access-from-files --print-to-pdf=%s %s 2>&1', escapeshellarg($chrome), escapeshellarg($prelim_pdf), escapeshellarg('file://' . $temp_dir . '/preliminar.html'))); // phpcs:ignore
            $measured = file_exists($prelim_pdf) ? $this->count_pdf_pages($prelim_pdf) : 0;
            if (!$measured) {
                break;
            }
            if ($measured === $prelim_pages) {
                break;
            }
            $prelim_pages = $measured;
        }

        if (!file_exists($prelim_pdf)) {
            wp_die(esc_html__('Erro ao gerar as páginas preliminares. Verifique o Diagnóstico.', 'sciflow-wp'));
        }

        // 3. Merge.
        $final_pdf = $temp_dir . '/anais_palestras.pdf';
        $parts = array_merge(array($prelim_pdf), $lecture_pdfs);
        shell_exec(sprintf('gs -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -sOutputFile=%s %s 2>&1', escapeshellarg($final_pdf), implode(' ', array_map('escapeshellarg', $parts)))); // phpcs:ignore

        if (!file_exists($final_pdf)) {
            wp_die(esc_html__('Erro ao gerar o PDF consolidado. Verifique o Diagnóstico.', 'sciflow-wp'));
        }
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Anais_Palestras_arquivos_originais.pdf"');
        header('Content-Length: ' . filesize($final_pdf));
        readfile($final_pdf);
        SciFlow_Security::remove_tree($temp_dir);
        exit;
    }

    /**
     * First available executable among $names (or '').
     *
     * @param string[] $names
     */
    public static function find_binary(array $names)
    {
        if (!function_exists('shell_exec')) {
            return '';
        }
        foreach ($names as $name) {
            $path = trim((string) shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null')); // phpcs:ignore
            if ('' !== $path) {
                return $path;
            }
        }
        return '';
    }

    public function render_print_page()
    {
        $volume = sanitize_key($_GET['anais_volume'] ?? 'resumos');
        $volumes = array('resumos', 'resumos_enfrute', 'resumos_semco', 'palestras_resumos', 'palestras');
        if (!in_array($volume, $volumes, true)) {
            $volume = 'resumos';
        }

        if ('palestras' === $volume) {
            $this->generate_server_pdf_palestras($this->get_lectures());
            exit;
        }

        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        (new SciFlow_Anais_Renderer($this))->render($volume, $this->collect($volume));
        exit;
    }

    public function get_approved_works($event)
    {
        $post_type = SciFlow_Status_Manager::get_post_type_for_event($event);
        if (!$post_type) return array();

        $query = new WP_Query(array(
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_sciflow_status',
                    'value'   => self::$approved_statuses,
                    'compare' => 'IN',
                ),
            ),
            'meta_key'       => '_sciflow_knowledge_area',
            'orderby'        => array(
                'meta_value' => 'ASC',
                'title'      => 'ASC',
            ),
        ));
        return $query->posts ?: array();
    }

    /**
     * Agrupa resumos por área de conhecimento.
     */
    public function group_by_area($posts)
    {
        $grouped = array();
        foreach ($posts as $p) {
            $area = get_post_meta($p->ID, '_sciflow_knowledge_area', true);
            if ($p->post_type === 'sciflow_palestra') { $area = 'Palestras'; } else { $area = $area ? trim($area) : 'Outros'; }
            if (!isset($grouped[$area])) {
                $grouped[$area] = array();
            }
            $grouped[$area][] = $p;
        }
        ksort($grouped);
        return $grouped;
    }
    public function format_scientific_title($title)
    {
        $parts = preg_split('/(<\/?(?:i|em)[^>]*>)/i', $title, -1, PREG_SPLIT_DELIM_CAPTURE);
        $in_italic = false;
        foreach ($parts as &$part) {
            if (preg_match('/^<(i|em)[^>]*>$/i', $part)) {
                $in_italic = true;
            } elseif (preg_match('/^<\/(i|em)>/i', $part)) {
                $in_italic = false;
            } else {
                if (!$in_italic) {
                    $part = mb_strtoupper($part, 'UTF-8');
                }
            }
        }
        return implode('', $parts);
    }
    public function get_reviewers()
    {
        $users = get_users();
        $reviewers = array();
        foreach ($users as $u) {
            $is_reviewer = false;
            foreach ($u->roles as $r) {
                if (stripos($r, 'revisor') !== false) {
                    $is_reviewer = true; break;
                }
            }
            if ($is_reviewer) {
                $instit = get_user_meta($u->ID, '_sciflow_instituicao', true);
                $name = mb_convert_case(mb_strtolower($u->display_name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                $reviewers[] = trim($name . ($instit ? ' - ' . $instit : ''));
            }
        }
        $reviewers = array_unique($reviewers);
        sort($reviewers);
        if (empty($reviewers)) {
            $reviewers = array('Nenhum revisor cadastrado.');
        }
        return $reviewers;
    }

    public static function build_author_affiliations($main_name, $main_institution, $coauthors)
    {
        $affil_map   = array();
        $affil_index = array();

        $normalize = function ($s) {
            return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
        };

        $get_or_add = function ($institution) use (&$affil_map, &$affil_index, $normalize) {
            if (empty(trim($institution))) return array();
            $parts = array_map('trim', explode(';', $institution));
            $nums  = array();
            foreach ($parts as $part) {
                if ($part === '') continue;
                $key = $normalize($part);
                if (!isset($affil_map[$key])) {
                    $num              = count($affil_map) + 1;
                    $affil_map[$key]  = $part;
                    $affil_index[$key] = $num;
                }
                $nums[] = $affil_index[$key];
            }
            return $nums;
        };

        $authors_parts = array();
        // Item 11 – Normalizar nome do autor principal em Title Case
        $main_name_normalized = self::title_case_name($main_name);
        $main_nums   = $get_or_add($main_institution);
        $main_sup    = self::nums_to_superscript($main_nums);
        $authors_parts[] = trim($main_name_normalized) . $main_sup;

        foreach ($coauthors as $ca) {
            if (empty($ca['name'])) continue;
            // Item 11 – Normalizar nome do coautor em Title Case
            $ca_name_normalized = self::title_case_name($ca['name']);
            $ca_nums = $get_or_add($ca['institution'] ?? '');
            $ca_sup  = self::nums_to_superscript($ca_nums);
            $authors_parts[] = trim($ca_name_normalized) . $ca_sup;
        }

        $affil_parts = array();
        foreach ($affil_map as $key => $display) {
            $affil_parts[] = array('num' => $affil_index[$key], 'name' => $display);
        }
        usort($affil_parts, function ($a, $b) { return $a['num'] - $b['num']; });

        $affil_line_parts = array();
        foreach ($affil_parts as $a) {
            $affil_line_parts[] = self::num_to_superscript_char($a['num']) . trim($a['name']);
        }

        return array(
            'authors_line'      => implode('; ', $authors_parts),
            'affiliations_line' => implode('; ', $affil_line_parts),
        );
    }

    private static function nums_to_superscript($nums)
    {
        if (empty($nums)) return '';
        sort($nums);
        $parts = array_map(array('SciFlow_Anais', 'num_to_superscript_char'), $nums);
        return '<sup>' . implode(',', $parts) . '</sup>';
    }

    private static function num_to_superscript_char($n)
    {
        $map = array('¹','²','³','⁴','⁵','⁶','⁷','⁸','⁹','¹⁰','¹¹','¹²','¹³','¹⁴','¹⁵');
        $idx = intval($n) - 1;
        return ($idx >= 0 && $idx < count($map)) ? $map[$idx] : (string) $n;
    }

    /**
     * Item 11 – Normaliza nome de autor para Title Case,
     * preservando preposições/artigos em minúsculas.
     */
    private static function title_case_name($name)
    {
        if (empty(trim($name))) return $name;
        // Normaliza para lowercase e depois aplica Title Case
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        // Mantém preposições e artigos comuns em minúsculas (exceto no início)
        $lower_words = array('da', 'de', 'do', 'das', 'dos', 'e', 'a', 'o', 'as', 'os', 'na', 'no', 'nas', 'nos', 'em', 'di', 'del', 'van', 'von', 'der');
        $words = explode(' ', $name);
        for ($i = 1; $i < count($words); $i++) {
            $word_lower = mb_strtolower($words[$i], 'UTF-8');
            if (in_array($word_lower, $lower_words, true)) {
                $words[$i] = $word_lower;
            }
        }
        return implode(' ', $words);
    }

    /**
     * Item 12 – Converte notação científica com ^ para superscript HTML.
     * Ex: 10^3 → 10<sup>3</sup> | 10^-1 → 10<sup>-1</sup>
     * Aplica-se apenas a ^N fora de tags HTML.
     */
    public function convert_scientific_notation($text)
    {
        // Converte padrão ^(sinal opcional)(dígitos) para <sup>...</sup>
        // O negative lookbehind evita alterar dentro de atributos HTML
        // Only outside tags (attribute values never contain "^digit" in practice,
        // but tags are skipped explicitly anyway).
        $parts = preg_split('/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ('' === $part || '<' === $part[0]) {
                continue;
            }
            $parts[$i] = preg_replace('/\^\s?([-+−]?\d+)/u', '<sup>$1</sup>', $part);
        }
        return implode('', $parts);
    }

    /**
     * Item 1 – Torna URLs do domínio enfrute.com clicáveis no PDF.
     * Detecta variações: http/https, com/sem www, com caminho.
     * Não duplica links já existentes em atributos href.
     */
    public function linkify_enfrute_urls($text)
    {
        return preg_replace_callback(
            '/<a\b[^>]*>.*?<\/a>|((?:https?:\/\/)?(?:www\.)?enfrute\.com(?:\.br)?(?:\/[^\s<]*)?)/i',
            function ($m) {
                if (empty($m[1])) return $m[0];
                $url  = $m[1];
                $href = preg_match('/^https?:\/\//i', $url) ? $url : 'https://' . $url;
                return '<a href="' . esc_attr($href) . '" target="_blank">' . esc_html($url) . '</a>';
            },
            $text
        );
    }
    public function get_image_url($filename)
    {
        $path = SCIFLOW_PATH . 'admin/images/' . $filename;
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        return '';
    }

    /**
     * Extrai o conteúdo de um arquivo Word (DOCX) anexado como HTML.
     * Suporta .docx nativamente via ZipArchive + regex.
     * Para .doc, tenta conversão com LibreOffice (com cache em disco).
     *
     * @param  int    $attachment_id  ID do mídia do WordPress
     * @return string HTML com os parágrafos do documento, ou string vazia
     */
    public function extract_docx_content($attachment_id)
    {
        if (!$attachment_id) return '';

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) return '';

        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($ext === 'docx') {
            return $this->read_docx_as_html($file_path);
        }

        // Para .doc e outros formatos: conversão via LibreOffice com cache
        // Cache in the protected private directory (never next to the public upload).
        $cache_path = SciFlow_Security::private_dir('docconv-cache') . md5($file_path . '|' . filemtime($file_path)) . '.html';
        if (file_exists($cache_path) && filemtime($cache_path) >= filemtime($file_path)) {
            return file_get_contents($cache_path);
        }

        $temp_dir   = untrailingslashit(SciFlow_Security::private_dir('docconv-' . wp_generate_password(12, false, false)));

        $cmd = sprintf(
            'libreoffice --headless --convert-to html %s --outdir %s 2>&1',
            escapeshellarg($file_path),
            escapeshellarg($temp_dir)
        );
        shell_exec($cmd);

        $html_file = $temp_dir . '/' . pathinfo($file_path, PATHINFO_FILENAME) . '.html';
        $body_content = '';

        if (file_exists($html_file)) {
            $raw = file_get_contents($html_file);
            if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $raw, $m)) {
                // Uploaded documents are untrusted: strip scripts/handlers.
                $body_content = wp_kses_post($m[1]);
            }
            @unlink($html_file);
        }
        // Limpa arquivos de imagem gerados pela conversão
        SciFlow_Security::remove_tree($temp_dir);

        if (!empty($body_content)) {
            file_put_contents($cache_path, $body_content);
        }

        return $body_content;
    }

    /**
     * Lê um arquivo .docx (ZIP com word/document.xml) e retorna
     * os parágrafos como HTML sem dependências externas.
     *
     * @param  string $file_path  Caminho absoluto para o .docx
     * @return string HTML gerado
     */
    private function read_docx_as_html($file_path)
    {
        if (!class_exists('ZipArchive')) return '';

        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) return '';

        $xml      = $zip->getFromName('word/document.xml');
        $rels_xml = $zip->getFromName('word/_rels/document.xml.rels');

        // ── Mapa de relacionamentos: rId → caminho relativo ────────────────
        $rel_map = array();
        if ($rels_xml) {
            preg_match_all('/Id="([^"]+)"[^>]*Target="([^"]+)"/i', $rels_xml, $rm, PREG_SET_ORDER);
            foreach ($rm as $r) {
                $rel_map[$r[1]] = $r[2];
            }
        }

        // ── Extrai imagens como data URI base64 ────────────────────────
        $images_b64 = array();
        $img_exts   = array('png', 'jpg', 'jpeg', 'gif', 'bmp');
        foreach ($rel_map as $rid => $target) {
            $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            if (!in_array($ext, $img_exts, true)) continue;

            // Target é relativo a word/; pode começar com ../ subindo ao ZIP raiz
            if (strpos($target, '../') === 0) {
                $zip_path = ltrim(substr($target, 3), '/');
            } else {
                $zip_path = 'word/' . $target;
            }

            $img_data = $zip->getFromName($zip_path);
            if ($img_data !== false) {
                $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                $images_b64[$rid] = 'data:' . $mime . ';base64,' . base64_encode($img_data);
            }
        }

        $zip->close();

        if (!$xml) return '';

        // ── Percorre o corpo em ordem: parágrafos, tabelas e imagens ───────
        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$loaded) {
            return '';
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

        $body = $xp->query('/w:document/w:body')->item(0);
        if (!$body) {
            return '';
        }
        return $this->docx_blocks($body, $xp, $images_b64);
    }

    /**
     * Render the block-level children of a body/cell node.
     */
    private function docx_blocks(DOMNode $parent, DOMXPath $xp, array $images)
    {
        $html = '';
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ('p' === $node->localName) {
                $html .= $this->docx_paragraph($node, $xp, $images);
            } elseif ('tbl' === $node->localName) {
                $html .= '<table>';
                foreach ($xp->query('w:tr', $node) as $tr) {
                    $html .= '<tr>';
                    foreach ($xp->query('w:tc', $tr) as $tc) {
                        $span = $xp->query('w:tcPr/w:gridSpan/@w:val', $tc)->item(0);
                        $html .= '<td' . ($span ? ' colspan="' . (int) $span->nodeValue . '"' : '') . '>' . $this->docx_blocks($tc, $xp, $images) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';
            } elseif ('sdt' === $node->localName) {
                $content = $xp->query('w:sdtContent', $node)->item(0);
                if ($content) {
                    $html .= $this->docx_blocks($content, $xp, $images);
                }
            }
        }
        return $html;
    }

    /**
     * One paragraph: runs with bold/italic/sup/sub, breaks, tabs and images.
     */
    private function docx_paragraph(DOMElement $p, DOMXPath $xp, array $images)
    {
        $on = function ($run, $prop) use ($xp) {
            $el = $xp->query('w:rPr/w:' . $prop, $run)->item(0);
            if (!$el) {
                return false;
            }
            $val = $el->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
            return !in_array(strtolower($val), array('0', 'false', 'none'), true);
        };

        $inner = '';
        $imgs = '';
        foreach ($xp->query('.//w:r', $p) as $run) {
            foreach ($xp->query('.//a:blip/@r:embed | .//v:imagedata/@r:id', $run) as $rid) {
                if (isset($images[$rid->nodeValue])) {
                    $imgs .= '<img src="' . $images[$rid->nodeValue] . '" alt="">';
                }
            }
            $text = '';
            foreach ($xp->query('w:t | w:tab | w:br | w:cr', $run) as $part) {
                if ('t' === $part->localName) {
                    $text .= esc_html($part->textContent);
                } elseif ('tab' === $part->localName) {
                    $text .= ' ';
                } else {
                    $text .= '<br>';
                }
            }
            if ('' === $text) {
                continue;
            }
            $va = $xp->query('w:rPr/w:vertAlign/@w:val', $run)->item(0);
            if ($on($run, 'b')) {
                $text = '<strong>' . $text . '</strong>';
            }
            if ($on($run, 'i')) {
                $text = '<em>' . $text . '</em>';
            }
            if ($va && 'superscript' === $va->nodeValue) {
                $text = '<sup>' . $text . '</sup>';
            } elseif ($va && 'subscript' === $va->nodeValue) {
                $text = '<sub>' . $text . '</sub>';
            }
            $inner .= $text;
        }

        $out = '';
        if ('' !== $imgs) {
            $out .= '<p class="sf-figura">' . $imgs . '</p>';
        }
        $inner = trim($inner);
        if ('' === $inner) {
            return $out;
        }
        $style = $xp->query('w:pPr/w:pStyle/@w:val', $p)->item(0);
        if ($style && preg_match('/^(Heading|T[ií]tulo|Title)\s*(\d?)/iu', $style->nodeValue, $m)) {
            $level = $m[2] ? min(4, max(2, (int) $m[2] + 1)) : 2;
            return $out . '<h' . $level . '>' . $inner . '</h' . $level . '>';
        }
        $align = $xp->query('w:pPr/w:jc/@w:val', $p)->item(0);
        $center = $align && 'center' === $align->nodeValue ? ' style="text-align:center"' : '';
        return $out . '<p' . $center . '>' . $inner . '</p>';
    }

    /**
     * Book title (settings → legacy default).
     *
     * @param bool $with_prefix Keep the "Anais – " prefix.
     */
    public function title($with_prefix = true)
    {
        $title = trim((string) SciFlow_Settings::get('anais_title', ''));
        if ('' === $title) {
            $labels = array();
            foreach (SciFlow_Events::enabled() as $event) {
                $labels[] = $event['label'];
            }
            $title = 'Anais – ' . implode(' / ', $labels);
        }
        return $with_prefix ? $title : preg_replace('/^Anais\s*[–-]\s*/u', '', $title);
    }

    /**
     * Running header shown on every page (limited HTML).
     */
    public function header_html()
    {
        $header = (string) SciFlow_Settings::get('anais_header', '');
        if ('' === trim(wp_strip_all_tags($header))) {
            $parts = array();
            foreach (SciFlow_Events::enabled() as $event) {
                $parts[] = esc_html($event['full_label']);
            }
            return implode('<br>', $parts);
        }
        return wp_kses($header, array('br' => array(), 'strong' => array(), 'em' => array(), 'i' => array(), 'b' => array()));
    }

    /**
     * Event label used in the book (e.g. "XIX Enfrute").
     */
    public function ev($slug)
    {
        $edition = trim((string) SciFlow_Settings::get('event_' . $slug . '_edition', ''));
        return trim($edition . ' ' . SciFlow_Events::label($slug));
    }

    /**
     * The original pre-textual pages (cover, catalog card, presentation) are
     * specific to the 2026 Enfrute/Semco edition. They are used only when the
     * installation was migrated from that edition or explicitly selected.
     */
    public function use_legacy_pretextual()
    {
        return 'legacy' === (string) SciFlow_Settings::get('anais_pretextual', 'custom');
    }

    /**
     * Count PDF pages with Ghostscript, passing the path safely.
     *
     * @param string $pdf_path
     * @return int
     */
    private function count_pdf_pages($pdf_path)
    {
        // Escape for a PostScript string, then for the shell.
        $ps_path = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $pdf_path);
        $ps = sprintf('(%s) (r) file runpdfbegin pdfpagecount = quit', $ps_path);
        $cmd = 'gs -q -dNODISPLAY -dSAFER ' . escapeshellarg('--permit-file-read=' . $pdf_path) . ' -c ' . escapeshellarg($ps) . ' 2>/dev/null';
        $out = shell_exec($cmd); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
        return (int) trim((string) $out);
    }
}

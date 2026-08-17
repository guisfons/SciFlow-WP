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
    );

    public function render_page()
    {
        if (!current_user_can('manage_sciflow')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'sciflow-wp'));
        }

        $volume_filter = sanitize_text_field($_GET['anais_volume'] ?? 'resumos');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Anais – XIX Enfrute / III Semco 2026', 'sciflow-wp'); ?></h1>
            <p class="description">
                <?php esc_html_e('Gera a visualização formatada dos Anais para impressão como PDF. Clique em "Abrir Preview" e depois use Ctrl+P (ou ⌘+P) para salvar como PDF.', 'sciflow-wp'); ?>
            </p>

            <div class="card" style="max-width:680px;padding:20px;margin-top:20px;">
                <h2 style="margin-top:0;"><?php esc_html_e('Gerar Anais – Vol. II (Resumos)', 'sciflow-wp'); ?></h2>
                <form method="get" target="_blank" action="">
                    <input type="hidden" name="page" value="sciflow-anais">
                    <input type="hidden" name="anais_preview" value="1">
                    <table class="form-table" style="margin-bottom:0;">
                        <tr>
                            <th scope="row"><label for="anais_volume"><?php esc_html_e('Conteúdo', 'sciflow-wp'); ?></label></th>
                            <td>
                                <select id="anais_volume" name="anais_volume" class="regular-text">
                                    <option value="resumos" <?php selected($volume_filter, 'resumos'); ?>><?php esc_html_e('Resumos Vol. II (Completo)', 'sciflow-wp'); ?></option>
                                    <option value="resumos_enfrute" <?php selected($volume_filter, 'resumos_enfrute'); ?>><?php esc_html_e('Resumos: Somente XIX Enfrute', 'sciflow-wp'); ?></option>
                                    <option value="resumos_semco" <?php selected($volume_filter, 'resumos_semco'); ?>><?php esc_html_e('Resumos: Somente III Semco', 'sciflow-wp'); ?></option>
                                    <option value="palestras" <?php selected($volume_filter, 'palestras'); ?>><?php esc_html_e('Palestras Vol. I (Gerar PDF Consolidado)', 'sciflow-wp'); ?></option>
                                    <option value="palestras_resumos" <?php selected($volume_filter, 'palestras_resumos'); ?>><?php esc_html_e('Palestras Vol. I (Preview Completo – Resumos)', 'sciflow-wp'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:16px;">
                        <button type="submit" class="button button-primary button-large">
                            <span class="dashicons dashicons-printer" style="margin-top:3px;vertical-align:middle;"></span>
                            <?php esc_html_e('Abrir Preview para PDF', 'sciflow-wp'); ?>
                        </button>
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
        echo '<tr><td><strong>XIX Enfrute</strong></td><td>' . intval($enfrute_count) . '</td></tr>';
        echo '<tr><td><strong>III Semco</strong></td><td>' . intval($semco_count) . '</td></tr>';
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

        private function generate_server_pdf_palestras($palestra_posts)
    {
        $enfrute_posts = array();
        $semco_posts = array();
        foreach ($palestra_posts as $p) {
            $ev = strtolower(get_post_meta($p->ID, '_sciflow_event', true));
            if ($ev === 'semco') {
                $semco_posts[] = $p;
            } else {
                $enfrute_posts[] = $p;
            }
        }

        // Convert Word to PDF and count pages
        $pdfs_to_merge = array();
        $post_pages = array();
        
        $base_pages = 5;
        $reviewers = $this->get_reviewers();
        if (empty($reviewers)) { $reviewers = array('Nenhum revisor cadastrado.'); }
        $reviewer_chunks = array_chunk($reviewers, 70);
        $base_pages += count($reviewer_chunks);

        // We need to estimate Sumario pages. 
        // For palestras, we know exactly how many items.
        $total_items = count($enfrute_posts) + count($semco_posts);
        $sumario_rows = 0;
        if (!empty($enfrute_posts)) { $sumario_rows += 2 + 2 + count($enfrute_posts); }
        if (!empty($semco_posts)) { $sumario_rows += 2 + 2 + count($semco_posts); }
        $sumario_pages = ceil($sumario_rows / 38);
        if ($sumario_pages == 0) $sumario_pages = 1;
        $base_pages += $sumario_pages;

        $current_page_num = $base_pages;

        set_time_limit(300);
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/sciflow_palestras_' . time();
        mkdir($temp_dir);
        $debug_log = $temp_dir . '/debug.log';
        file_put_contents($debug_log, "Iniciando geração de palestras\n");

        $process_posts = function($posts) use (&$current_page_num, &$post_pages, &$pdfs_to_merge, $temp_dir, $debug_log) {
            $current_page_num++; // Separator page
            foreach ($posts as $p) {
                $post_pages[$p->ID] = $current_page_num; // This is where the Palestra starts!
                $attachment_id = get_post_meta($p->ID, '_sciflow_attachment_id', true);
                $file_path = get_attached_file($attachment_id);
                if ($file_path && file_exists($file_path)) {
                    // Convert to PDF
                    $cmd_convert = sprintf('libreoffice --headless --convert-to pdf %s --outdir %s 2>&1', escapeshellarg($file_path), escapeshellarg($temp_dir));
                    $out = shell_exec($cmd_convert);
                    file_put_contents($debug_log, "Libreoffice $file_path: $out\n", FILE_APPEND);
                    $pdf_name = pathinfo($file_path, PATHINFO_FILENAME) . '.pdf';
                    $pdf_path = $temp_dir . '/' . $pdf_name;
                    if (file_exists($pdf_path)) {
                        $pdfs_to_merge[] = $pdf_path;
                        // Count pages
                        $cmd_count = sprintf('gs -q -dNODISPLAY -c "(%s) (r) file runpdfbegin pdfpagecount = quit"', $pdf_path);
                        $pages = (int) trim(shell_exec($cmd_count));
                        if ($pages > 0) {
                            $current_page_num += $pages;
                        } else {
                            $current_page_num += 1;
                        }
                    } else {
                        $current_page_num += 1; // Fallback
                    }
                } else {
                    $current_page_num += 1; // Fallback
                }
            }
        };

        if (!empty($enfrute_posts)) { $process_posts($enfrute_posts); }
        if (!empty($semco_posts)) { $process_posts($semco_posts); }

        // Generate HTML for preliminary pages
        ob_start();
        // Since output_html expects to run in a web context, we need to inject the $post_pages logic into it.
        // Wait, output_html has its own calculation for $post_pages! We need to override it or let it use ours.
        // I will pass an extra parameter to output_html.
        $this->output_html($enfrute_posts, $semco_posts, $palestra_posts, 'palestras', $post_pages);
        $html = ob_get_clean();

        // Save HTML and generate PDF
        $html_file = $temp_dir . '/preliminar.html';
        $prelim_pdf = $temp_dir . '/preliminar.pdf';
        file_put_contents($html_file, $html);
        
        $cmd_chrome = sprintf('chromium-browser --headless --disable-gpu --no-sandbox --print-to-pdf=%s %s 2>&1', escapeshellarg($prelim_pdf), escapeshellarg($html_file));
        $out = shell_exec($cmd_chrome);
        file_put_contents($debug_log, "Chromium: $out\n", FILE_APPEND);

        // Merge PDFs
        $final_pdf = $temp_dir . '/anais_palestras.pdf';
        if (file_exists($prelim_pdf)) {
            array_unshift($pdfs_to_merge, $prelim_pdf);
        }
        
        $cmd_merge = sprintf('gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=%s %s 2>&1', escapeshellarg($final_pdf), implode(' ', array_map('escapeshellarg', $pdfs_to_merge)));
        $out = shell_exec($cmd_merge);
        file_put_contents($debug_log, "Ghostscript: $out\n", FILE_APPEND);

        // Serve PDF
        if (file_exists($final_pdf)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="Anais_Palestras.pdf"');
            header('Content-Length: ' . filesize($final_pdf));
            readfile($final_pdf);
        } else {
            wp_die('Erro ao gerar o PDF consolidado.');
        }

        // Cleanup
        // shell_exec('rm -rf ' . escapeshellarg($temp_dir)); // Comentado temporariamente para debug
        exit;
    }

    public function render_print_page()
    {
        $volume = sanitize_text_field($_GET['anais_volume'] ?? 'resumos');
        $enfrute_posts = array();
        $semco_posts   = array();
        $palestra_posts = array();

        if (in_array($volume, array('resumos', 'resumos_enfrute'), true)) {
            $enfrute_posts = $this->get_approved_works('enfrute');
        }
        if (in_array($volume, array('resumos', 'resumos_semco'), true)) {
            $semco_posts = $this->get_approved_works('semco');
        }
        if (in_array($volume, array('palestras', 'palestras_resumos'), true)) {
            $query = new WP_Query(array(
                'post_type'      => 'sciflow_palestra',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));
            $palestra_posts = $query->posts ?: array();
        }

        if ($volume === 'palestras') { $this->generate_server_pdf_palestras($palestra_posts); return; }
        if ($volume === 'palestras_resumos') { $this->output_html(array(), array(), $palestra_posts, 'palestras_resumos'); exit; }
        $this->output_html($enfrute_posts, $semco_posts, $palestra_posts, $volume);
        exit;
    }

    private function get_approved_works($event)
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
    private function group_by_area($posts)
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

        private function format_scientific_title($title)
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

        private function get_reviewers()
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
    private function convert_scientific_notation($text)
    {
        // Converte padrão ^(sinal opcional)(dígitos) para <sup>...</sup>
        // O negative lookbehind evita alterar dentro de atributos HTML
        return preg_replace('/(?<![="\'\w])\^([-+]?\d+)/', '<sup>$1</sup>', $text);
    }

    /**
     * Item 1 – Torna URLs do domínio enfrute.com clicáveis no PDF.
     * Detecta variações: http/https, com/sem www, com caminho.
     * Não duplica links já existentes em atributos href.
     */
    private function linkify_enfrute_urls($text)
    {
        return preg_replace_callback(
            '/(?<![="\'>])((https?:\/\/)?(?:www\.)?enfrute\.com(\/[^\s<>"\']*)?)/i',
            function ($m) {
                $url  = $m[1];
                $href = preg_match('/^https?:\/\//i', $url) ? $url : 'https://' . $url;
                return '<a href="' . esc_attr($href) . '" target="_blank">' . esc_html($url) . '</a>';
            },
            $text
        );
    }

    private function get_image_url($filename)
    {
        $path = SCIFLOW_PATH . 'admin/images/' . $filename;
        if (file_exists($path)) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        return '';
    }

    private function output_html($enfrute_posts, $semco_posts, $palestra_posts, $volume, $custom_post_pages = null)
    {
        $is_palestras         = in_array($volume, array('palestras', 'palestras_resumos'), true);
        $is_palestras_preview = ($volume === 'palestras_resumos');
        $total = $is_palestras ? count($palestra_posts) : (count($enfrute_posts) + count($semco_posts));

        $img_capa      = $is_palestras ? 'static_palestras_pg0.png' : 'static_pg0.png';
        $img_rosto     = $is_palestras ? 'static_palestras_pg1.png' : 'static_pg1.png';
        $img_parceiros = $is_palestras ? 'static_palestras_pg3.png' : 'static_pg3.png';
        $img_apres     = $is_palestras ? 'static_palestras_apres.png' : null;

        // ── PRE-CÁLCULOS (movidos para antes do HTML para que $total_pages
        //    esteja disponível na ficha catalográfica – Item 3) ─────────────
        if (!$is_palestras) {
            $base_pages = 5; // capa, rosto, ficha, parceiros, apresentação

            $reviewers = $this->get_reviewers();
            if (empty($reviewers)) { $reviewers = array('Nenhum revisor cadastrado.'); }
            $reviewer_chunks    = array_chunk($reviewers, 70);
            $num_reviewer_pages = count($reviewer_chunks);
            $base_pages += $num_reviewer_pages;

            // Monta estrutura do sumário e agrupa posts por área
            $sumario_lines   = array();
            $grouped_enfrute = array();
            $grouped_semco   = array();

            if (!empty($enfrute_posts)) {
                $sumario_lines[] = array('type' => 'event', 'text' => 'Resumos – XIX Enfrute');
                $grouped_enfrute = $this->group_by_area($enfrute_posts);
                foreach ($grouped_enfrute as $area => $posts) {
                    $sumario_lines[] = array('type' => 'area', 'text' => $area);
                    foreach ($posts as $p) {
                        $sumario_lines[] = array('type' => 'post', 'post' => $p);
                    }
                }
            }
            if (!empty($semco_posts)) {
                $sumario_lines[] = array('type' => 'event', 'text' => 'Resumos – III Semco');
                $grouped_semco = $this->group_by_area($semco_posts);
                foreach ($grouped_semco as $area => $posts) {
                    $sumario_lines[] = array('type' => 'area', 'text' => $area);
                    foreach ($posts as $p) {
                        $sumario_lines[] = array('type' => 'post', 'post' => $p);
                    }
                }
            }

            // Pagina o sumário
            $sumario_pages = array();
            $_cur_page     = array();
            $_rows         = 0;
            $max_rows      = 38;
            foreach ($sumario_lines as $line) {
                $row_cost = ($line['type'] === 'post') ? 1 : 2;
                if ($_rows + $row_cost > $max_rows && !empty($_cur_page)) {
                    $sumario_pages[] = $_cur_page;
                    $_cur_page = array();
                    $_rows     = 0;
                }
                $_cur_page[] = $line;
                $_rows += $row_cost;
            }
            if (!empty($_cur_page)) { $sumario_pages[] = $_cur_page; }

            $num_sumario_pages = count($sumario_pages);
            if ($num_sumario_pages === 0) $num_sumario_pages = 1;
            $base_pages += $num_sumario_pages;

            // Calcula números de página por artigo
            if ($custom_post_pages !== null) {
                $post_pages  = $custom_post_pages;
                $total_pages = !empty($post_pages) ? max($post_pages) : $base_pages;
            } else {
                $post_pages       = array();
                $current_page_num = $base_pages;

                if (!empty($enfrute_posts)) {
                    foreach ($grouped_enfrute as $area => $posts) {
                        $current_page_num++; // Separador
                        foreach ($posts as $p) {
                            $current_page_num++;
                            $post_pages[$p->ID] = $current_page_num;
                        }
                    }
                }
                if (!empty($semco_posts)) {
                    foreach ($grouped_semco as $area => $posts) {
                        $current_page_num++; // Separador
                        foreach ($posts as $p) {
                            $current_page_num++;
                            $post_pages[$p->ID] = $current_page_num;
                        }
                    }
                }
                // Item 3 – Total estimado de páginas do PDF final
                $total_pages = $current_page_num;
            }
        } else {
            // Palestras: inicialização de variáveis comuns
            $reviewers       = array();
            $reviewer_chunks = array();
            $grouped_enfrute = array();
            $grouped_semco   = array();

            if ($is_palestras_preview) {
                // ── Preview completo (HTML): calcula sumário e páginas ──────
                $palestra_enfrute = array();
                $palestra_semco   = array();
                foreach ($palestra_posts as $p) {
                    $ev = strtolower(get_post_meta($p->ID, '_sciflow_event', true));
                    if ($ev === 'semco') {
                        $palestra_semco[] = $p;
                    } else {
                        $palestra_enfrute[] = $p;
                    }
                }

                $base_pages = 5; // páginas 0-4: capa, rosto, ficha, parceiros, apresentação

                // Monta linhas do sumário
                $sumario_lines = array();
                if (!empty($palestra_enfrute)) {
                    $sumario_lines[] = array('type' => 'event', 'text' => 'Palestras – XIX Enfrute');
                    foreach ($palestra_enfrute as $p) {
                        $sumario_lines[] = array('type' => 'post', 'post' => $p);
                    }
                }
                if (!empty($palestra_semco)) {
                    $sumario_lines[] = array('type' => 'event', 'text' => 'Palestras – III Semco');
                    foreach ($palestra_semco as $p) {
                        $sumario_lines[] = array('type' => 'post', 'post' => $p);
                    }
                }

                // Pagina o sumário
                $sumario_pages = array();
                $_cur_page     = array();
                $_rows         = 0;
                $max_rows      = 38;
                foreach ($sumario_lines as $line) {
                    $row_cost = ($line['type'] === 'post') ? 1 : 2;
                    if ($_rows + $row_cost > $max_rows && !empty($_cur_page)) {
                        $sumario_pages[] = $_cur_page;
                        $_cur_page = array();
                        $_rows     = 0;
                    }
                    $_cur_page[] = $line;
                    $_rows += $row_cost;
                }
                if (!empty($_cur_page)) { $sumario_pages[] = $_cur_page; }
                $num_sumario_pages = count($sumario_pages);
                if ($num_sumario_pages === 0) $num_sumario_pages = 1;
                $base_pages += $num_sumario_pages;
                $base_pages += 1; // Separador (página 6 na numeração do usuário)

                // Calcula números de página por palestra
                if ($custom_post_pages !== null) {
                    $post_pages  = $custom_post_pages;
                    $total_pages = !empty($post_pages) ? max($post_pages) : $base_pages;
                } else {
                    $post_pages       = array();
                    $current_page_num = $base_pages;
                    foreach (array_merge($palestra_enfrute, $palestra_semco) as $p) {
                        $current_page_num++;
                        $post_pages[$p->ID] = $current_page_num;
                    }
                    $total_pages = $current_page_num;
                }
            } else {
                // ── Modo PDF: inicialização mínima (páginas pré-textuais apenas) ──
                $base_pages       = 0;
                $sumario_lines    = array();
                $sumario_pages    = array();
                $palestra_enfrute = array();
                $palestra_semco   = array();
                $post_pages       = ($custom_post_pages !== null) ? $custom_post_pages : array();
                $total_pages      = $total;
            }
        }
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Anais – XIX Enfrute / III Semco 2026 – <?php echo $is_palestras ? 'Vol. I Palestras' : 'Vol. II Resumos'; ?></title>
<?php $this->output_css(); ?>
</head>
<body class="anais-body">

<div class="noprint-bar">
  <span>⚠ Preview dos Anais – <?php echo $is_palestras_preview ? 'Vol. I (Palestras)' : 'Vol. II (Resumos)'; ?></span>
  <button onclick="window.print()" class="print-btn">🖨 Imprimir / Salvar como PDF</button>
  <span class="total-badge"><?php echo intval($total); ?> <?php echo $is_palestras_preview ? 'palestras' : 'resumos'; ?></span>
</div>

<!-- ═══════════════ CAPA ═══════════════ -->
<div class="page static-page">
    <img src="<?php echo esc_attr($this->get_image_url($img_capa)); ?>" alt="Capa" class="full-page-img">
</div>

<!-- ═══════════════ PÁGINA 2 (Folha de Rosto) ═══════════════ -->
<div class="page static-page">
    <img src="<?php echo esc_attr($this->get_image_url($img_rosto)); ?>" alt="Folha de Rosto" class="full-page-img">
</div>

<!-- ═══════════════ PÁGINA 3 (Ficha Catalográfica) ═══════════════ -->
<div class="page text-page pg-ficha">
    <p>Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina<br>
    Epagri/Estação Experimental de Caçador "José Oscar Kurtz"<br>
    Rua Abílio Franco, 1500, Bairro Bom Sucesso<br>
    89501-032, Caçador, SC<br>
    Fone: (49) 3561-6800<br>
    E-mail: <a href="mailto:eecd@epagri.sc.gov.br">eecd@epagri.sc.gov.br</a></p>

    <p style="margin-top:24px;">Editado pela DOPPIO DESIGN</p>

    <p style="margin-top:24px;">Edição: Julho 2026<br>
    Divulgação: <em>on-line</em></p>

    <p style="margin-top:24px;">Editoração: DOPPIO DESIGN<br>
    Revisão textual: Marcus Vinícius Kvitschal, Fernando Pereira Monteiro, André Amarildo Sezerino, Guilherme Mallmann e Marcelo Couto<br>
    Diagramação: DOPPIO DESIGN</p>

    <p style="margin-top:24px;">A responsabilidade do editor limita-se à adequação dos trabalhos às normas editoriais estabelecidas.</p>
    
    <p style="margin-top:16px;">O conteúdo dos resumos aqui publicados é de responsabilidade exclusiva dos respectivos autores.</p>
    
    <p style="margin-top:16px;">É permitida a reprodução parcial dos resumos desta edição desde que citada a fonte.</p>
    
    <div class="ficha-caixa">
        <p style="text-align:center; font-weight:bold; margin-bottom:12px;">Ficha Catalográfica</p>
        <p style="text-align:justify; margin-bottom:12px;">
        <!-- Item 2 – "DE" → "SOBRE" na ficha catalográfica -->
        <strong>ENCONTRO NACIONAL SOBRE FRUTICULTURA DE CLIMA TEMPERADO, 19., SEMINÁRIO CATARINENSE DE OLERICULTURA, 3.</strong>, 2026, Fraiburgo, SC. 
        <?php if ($is_palestras): ?>
            <strong>Anais de Palestras...</strong> Caçador, SC: Epagri, vol. I (Palestras), 2026. <?php echo intval($total); ?> palestras.
        <?php else: ?>
            <!-- Item 3 – Número real de páginas do PDF (estimativa) -->
            <strong>Anais de Resumos...</strong> Caçador, SC: Epagri, vol. II (Resumos), 2026. <?php echo intval($total_pages); ?> p.
        <?php endif; ?>
        </p>
        <p style="text-align:justify; margin-bottom:12px;">
        Fruticultura de Clima Temperado; Maçã; Uva; Pêssego; Pera; Ameixa, Nectarina; Goiaba; Caqui; Pequenas frutas; Frutas nativas; Olericultura; Tomate; Cebola; Alho; Morango; Mandioca; Cenoura; Pimentão, Folhosas; Lúpulo.
        </p>
        <p>ISSN 2175-1889</p>
    </div>
</div>

<!-- ═══════════════ PÁGINA 4 (Realização e Patrocínio) ═══════════════ -->
<div class="page static-page">
    <img src="<?php echo esc_attr($this->get_image_url($img_parceiros)); ?>" alt="Realização e Patrocínio" class="full-page-img">
</div>

<!-- ═══════════════ PÁGINA 5 (Apresentação) ═══════════════ -->
<?php if ($is_palestras && $img_apres): ?>
<div class="page static-page">
    <img src="<?php echo esc_attr($this->get_image_url($img_apres)); ?>" alt="Apresentação" class="full-page-img">
</div>
<?php else: ?>
<div class="page text-page pg-apresentacao">
    <p class="apres-titulo"><strong>APRESENTAÇÃO</strong></p>
    
    <!-- Item 2 – "DE" → "SOBRE" no texto da apresentação -->
    <p class="apres-texto">A Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina (Epagri), Associação dos Engenheiros Agrônomos de Caçador (AEAC), Universidade Alto Vale do Rio do Peixe (Uniarp) e Prefeitura de Fraiburgo realizaram em Fraiburgo-SC nos dias 28, 29 e 30 de julho de 2026 o XIX Encontro Nacional SOBRE Fruticultura de Clima Temperado – XIX Enfrute e III Seminário Catarinense de Olericultura – III Semco. O tema central do evento foi a valorização da ciência brasileira para a produção de frutas e de hortaliças, em busca de uma produção cada vez mais competitiva, aderente as boas práticas de produção. No XIX Enfrute e III Semco foram submetidos mais de 400 resumos científicos contendo informações científicas relacionadas à fruticultura e olericultura, tudo em primeira mão. Os trabalhos aprovados pelo comitê científico foram apresentados em formato de pôster digital no website dos eventos, e estão disponíveis publicamente para consulta a qualquer momento em <a href="https://enfrute.com" target="_blank">enfrute.com</a>. Nessa edição, foi criado o <strong>1º Prêmio HortiFruti Ciência</strong>, que tem como objetivo prestigiar, valorizar e premiar os melhores trabalhos científicos submetidos e apresentados. De todos os resumos submetidos ao XIX Enfrute e III Semco, 12 foram selecionados pelo comitê técnico-científico para apresentação oral pelos autores, sendo os três melhores premiados. Nesta obra estão apresentados os resumos de todos os trabalhos científicos aprovados nos dois eventos.</p>
    
    <p class="apres-texto">Desejamos a todos uma ótima leitura.</p>
    
    <div class="apres-assinatura">
        <p>Marcus Vinícius Kvitschal<br>
        Presidente da Comissão Organizadora do XIX Enfrute e III Semco<br>
        Julho de 2026</p>
    </div>
</div>
<?php endif; ?>

<?php if ($is_palestras && !$is_palestras_preview): ?>
<!-- ═══════════════ FIM DA SEÇÃO AUTOMÁTICA DE PALESTRAS (MODO PDF) ═══════════════ -->
<div class="page text-page centered-page">
    <h2 style="margin-top: 100px; color: #d32f2f;">Fim do Arquivo Introdutório (Volume I)</h2>
    <p style="margin-top: 30px; font-size: 16pt; color: #555;">
        Este arquivo contém as páginas pré-textuais do Vol. I.<br>
        As páginas das palestras em si devem ser unidas a este PDF manualmente a partir dos arquivos Word enviados pelos palestrantes.
    </p>
</div>
</body>
</html>
<?php
        return; // Interrompe para Palestras no modo PDF (geração de PDF consolidado)
endif;
?>

<?php if ($is_palestras_preview): ?>
<!-- ═══════════════ PÁGINA 5: SUMÁRIO (PALESTRAS) ═══════════════ -->
<?php
        foreach ($sumario_pages as $idx => $spage) {
            echo '<div class="page text-page pg-sumario">';
            if ($idx === 0) {
                echo '<p style="font-weight:bold; font-size:12pt; text-align:center; margin-bottom: 24px;">SUMÁRIO</p>';
            } else {
                echo '<p style="font-weight:bold; font-size:12pt; text-align:center; margin-bottom: 24px;">SUMÁRIO (Cont.)</p>';
            }
            echo '<div class="sumario-lista">';
            foreach ($spage as $line) {
                if ($line['type'] === 'event') {
                    echo '<p style="font-weight:bold; margin-top:16px;">' . esc_html($line['text']) . '</p>';
                } elseif ($line['type'] === 'post') {
                    $p  = $line['post'];
                    $pg = isset($post_pages[$p->ID]) ? $post_pages[$p->ID] : '';
                    echo '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-left:20px; font-size:10pt; line-height:1.2; margin-bottom:4px;">';
                    echo '<a href="#palestra-' . intval($p->ID) . '" style="flex:1; padding-right:10px; color:inherit; text-decoration:none;">'
                        . wp_kses($this->format_scientific_title($p->post_title), array('i' => array(), 'em' => array()))
                        . '</a>';
                    echo '<a href="#palestra-' . intval($p->ID) . '" style="white-space:nowrap; color:inherit; text-decoration:none;">' . $pg . '</a>';
                    echo '</div>';
                }
            }
            echo '</div></div>';
        }
?>

<!-- ═══════════════ PÁGINA 6: SEPARADOR (PALESTRAS) ═══════════════ -->
<div class="page separator-page">
    <div class="separator-content">
        <p class="sep-evento">Palestras</p>
        <p class="sep-area">XIX Enfrute / III Semco 2026</p>
    </div>
</div>

<!-- ═══════════════ PÁGINAS 7+: RESUMOS DE PALESTRAS ═══════════════ -->
<?php
        $all_palestras_ordered = array_merge($palestra_enfrute, $palestra_semco);
        foreach ($all_palestras_ordered as $palestra) {
            $pg = isset($post_pages[$palestra->ID]) ? $post_pages[$palestra->ID] : 0;
            $this->render_palestra_resumo($palestra, $pg);
        }
?>
<?php endif; ?>
<?php if ($is_palestras_preview): ?>
</body>
</html>
<?php return; // Fim do preview de palestras — impede a renderização do bloco de resumos
endif; ?>

<!-- ═══════════════ PÁGINA 6 (Revisores) ═══════════════ -->
<?php
        // Renderiza páginas de revisores usando $reviewer_chunks pré-calculado
        foreach ($reviewer_chunks as $idx => $chunk) {
            echo '<div class="page text-page pg-revisores">';
            if ($idx === 0) {
                echo '<p style="font-weight:bold; font-size:12pt; text-align:center; margin-bottom:30px;">Lista de revisores <em>ad hoc</em> dos resumos científicos</p>';
            } else {
                echo '<p style="font-weight:bold; font-size:12pt; text-align:center; margin-bottom:30px;">Lista de revisores <em>ad hoc</em> dos resumos científicos (Cont.)</p>';
            }
            echo '<div style="column-count: 2; column-gap: 40px; font-size: 11pt;">';
            foreach ($chunk as $r) {
                echo '<p style="margin-bottom: 5px;">' . esc_html($r) . '</p>';
            }
            echo '</div></div>';
        }

        // ── RENDER SUMÁRIO ──────────────────────────────────────────────────
        foreach ($sumario_pages as $idx => $spage) {
            echo '<div class="page text-page pg-sumario">';
            if ($idx === 0) {
                echo '<p style="font-weight:bold; font-size:12pt; text-align:center; margin-bottom: 24px;">SUMÁRIO</p>';
            } else {
                echo '<p style="font-weight:bold; font-size:12pt; text-align:center; margin-bottom: 24px;">SUMÁRIO (Cont.)</p>';
            }
            echo '<div class="sumario-lista">';
            foreach ($spage as $line) {
                if ($line['type'] === 'event') {
                    echo '<p style="font-weight:bold; margin-top:16px;">' . esc_html($line['text']) . '</p>';
                } elseif ($line['type'] === 'area') {
                    echo '<p style="margin-top:10px; font-weight:bold; text-transform:uppercase; font-size:11pt;">' . esc_html($line['text']) . '</p>';
                } elseif ($line['type'] === 'post') {
                    $p  = $line['post'];
                    $pg = isset($post_pages[$p->ID]) ? $post_pages[$p->ID] : '';
                    // Item 5 – Título completo sem truncamento
                    // Item 6 – Links clicáveis no sumário (id no artigo, href aqui)
                    // Item 4 – Apenas o número, sem "Pág."
                    echo '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-left:20px; font-size:10pt; line-height:1.2; margin-bottom:4px;">';
                    echo '<a href="#post-' . intval($p->ID) . '" style="flex:1; padding-right:10px; color:inherit; text-decoration:none;">'
                        . wp_kses($this->format_scientific_title($p->post_title), array('i' => array(), 'em' => array()))
                        . '</a>';
                    echo '<a href="#post-' . intval($p->ID) . '" style="white-space:nowrap; color:inherit; text-decoration:none;">' . $pg . '</a>';
                    echo '</div>';
                }
            }
            echo '</div></div>';
        }

        // ── PROCESSAMENTO: XIX ENFRUTE ──────────────────────────────────────
        if (!empty($enfrute_posts)) {
            foreach ($grouped_enfrute as $area => $posts_in_area) {
                echo '<div class="page separator-page">';
                echo '  <div class="separator-content">';
                echo '    <p class="sep-evento">Resumos – XIX Enfrute</p>';
                echo '    <p class="sep-area">' . esc_html($area) . '</p>';
                echo '  </div>';
                echo '</div>';

                foreach ($posts_in_area as $post) {
                    $pg = isset($post_pages[$post->ID]) ? $post_pages[$post->ID] : 0;
                    $this->render_resumo($post, $pg, 'XIX Enfrute', $area);
                }
            }
        }

        // ── PROCESSAMENTO: III SEMCO ────────────────────────────────────────
        if (!empty($semco_posts)) {
            foreach ($grouped_semco as $area => $posts_in_area) {
                echo '<div class="page separator-page">';
                echo '  <div class="separator-content">';
                echo '    <p class="sep-evento">Resumos – III Semco</p>';
                echo '    <p class="sep-area">' . esc_html($area) . '</p>';
                echo '  </div>';
                echo '</div>';

                foreach ($posts_in_area as $post) {
                    $pg = isset($post_pages[$post->ID]) ? $post_pages[$post->ID] : 0;
                    $this->render_resumo($post, $pg, 'III Semco', $area);
                }
            }
        }
?>
</body>
</html>
<?php
    }

    private function render_resumo($post, $num, $evento_label, $area_label)
    {
        $main_name    = get_post_meta($post->ID, '_sciflow_main_author_name', true);
        $main_instit  = get_post_meta($post->ID, '_sciflow_main_author_instituicao', true);
        $coauthors    = get_post_meta($post->ID, '_sciflow_coauthors', true);
        $keywords     = get_post_meta($post->ID, '_sciflow_keywords', true);
        $ack          = get_post_meta($post->ID, '_sciflow_acknowledgement', true);
        
        if (!is_array($coauthors)) $coauthors = array();
        if (!is_array($keywords)) $keywords = $keywords ? array($keywords) : array();
        $keywords = array_filter(array_map('trim', $keywords));

        $ad = self::build_author_affiliations($main_name, $main_instit, $coauthors);

        // Item 6 – id para link interno do sumário
        echo '<div class="page resumo-page" id="post-' . intval($post->ID) . '">';
        echo '  <div class="resumo-container">';
        
        // Item 7 – Cabeçalho mantém conteúdo existente e agrega local/data do evento
        $event_location = 'Fraiburgo, SC | 28, 29 e 30 de julho de 2026';
        echo '    <p class="resumo-header-ctx">' . esc_html($evento_label) . ' | <strong>' . esc_html($area_label) . '</strong> | ' . esc_html($event_location) . '</p>';

        // Título centralizado, uppercase, Arial/Calibri ou TNR 12pt Bold
        echo '    <h3 class="resumo-titulo">' . wp_kses($this->format_scientific_title($post->post_title), array('i' => array(), 'em' => array())) . '</h3>';

        // Autores (centralizados) – Item 11: Title Case já aplicado em build_author_affiliations
        echo '    <p class="resumo-autores">' . wp_kses($ad['authors_line'], array('sup' => array())) . '</p>';

        // Afiliações (centralizadas, menor)
        if (!empty($ad['affiliations_line'])) {
            echo '    <p class="resumo-afils">' . wp_kses($ad['affiliations_line'], array('sup' => array())) . '</p>';
        }

        // Item 9 – Removido o label "Resumo:" que aparecia antes do corpo do artigo

        // Corpo do resumo – Item 1 (linkify), Item 12 (notação científica)
        $content = wp_kses_post($post->post_content);
        $content = $this->convert_scientific_notation($content);
        $content = $this->linkify_enfrute_urls($content);
        echo '    <div class="resumo-corpo">' . $content . '</div>';

        // Agradecimentos – Item 1 (linkify), Item 12 (notação científica)
        if (!empty($ack)) {
            $ack_safe = esc_html($ack);
            $ack_safe = $this->convert_scientific_notation($ack_safe);
            $ack_safe = $this->linkify_enfrute_urls($ack_safe);
            echo '    <p class="resumo-ack"><strong>Agradecimentos:</strong> ' . $ack_safe . '</p>';
        }

        // Palavras-chave – Item 12 (notação científica)
        if (!empty($keywords)) {
            $kw_safe = esc_html(implode('; ', $keywords));
            $kw_safe = $this->convert_scientific_notation($kw_safe);
            echo '    <p class="resumo-kws"><strong>Palavras-chave:</strong> ' . $kw_safe . '.</p>';
        }

        echo '  </div>';

        // Item 10 – Rodapé fixo na parte inferior da página, sem a palavra "Página"
        echo '  <div class="resumo-footer">' . intval($num) . '</div>';

        echo '</div>';
    }

    /**
     * Renderiza uma página de resumo de palestra.
     * Campos disponíveis: título, evento, duração, autor WP, corpo do texto.
     * Cabeçalho: 'Palestras | [Evento] | Fraiburgo, SC | 28, 29 e 30 de julho de 2026'
     */
    private function render_palestra_resumo($post, $num)
    {
        $event_raw = strtolower(get_post_meta($post->ID, '_sciflow_event', true));
        if ($event_raw === 'enfrute') {
            $event_label = 'XIX Enfrute';
        } elseif ($event_raw === 'semco') {
            $event_label = 'III Semco';
        } else {
            $event_label = ucfirst($event_raw) ?: 'Palestra';
        }

        $duration       = get_post_meta($post->ID, '_sciflow_duration', true);
        $event_location = 'Fraiburgo, SC | 28, 29 e 30 de julho de 2026';

        // Autor: prefere _sciflow_main_author_name, cai para WP post_author
        $main_author_meta = get_post_meta($post->ID, '_sciflow_main_author_name', true);
        if (!empty(trim($main_author_meta))) {
            $author_name = self::title_case_name($main_author_meta);
        } else {
            $author_user = get_userdata($post->post_author);
            $author_name = $author_user ? self::title_case_name($author_user->display_name) : '';
        }

        $main_instit = get_post_meta($post->ID, '_sciflow_main_author_instituicao', true);
        $coauthors   = get_post_meta($post->ID, '_sciflow_coauthors', true);
        if (!is_array($coauthors)) $coauthors = array();

        // Monta linha de autores e afiliações (igual ao render_resumo)
        $ad = self::build_author_affiliations($author_name, $main_instit, $coauthors);

        // Conteúdo: tenta extrair o arquivo Word anexado; cai para post_content
        $attachment_id = get_post_meta($post->ID, '_sciflow_attachment_id', true);
        $content = '';
        if ($attachment_id) {
            $content = $this->extract_docx_content($attachment_id);
        }
        if (empty($content)) {
            $content = wp_kses_post($post->post_content);
        }
        $content = $this->convert_scientific_notation($content);
        $content = $this->linkify_enfrute_urls($content);

        // ID âncora: palestra-{ID} (para links do sumário)
        echo '<div class="page resumo-page palestra-page" id="palestra-' . intval($post->ID) . '">';
        echo '  <div class="resumo-container">';

        // Cabeçalho: mesmo formato dos resumos normais
        echo '    <p class="resumo-header-ctx">Palestras | <strong>' . esc_html($event_label) . '</strong> | ' . esc_html($event_location) . '</p>';

        // Título (uppercase, bold, centralizado)
        echo '    <h3 class="resumo-titulo">' . wp_kses($this->format_scientific_title($post->post_title), array('i' => array(), 'em' => array())) . '</h3>';

        // Autores
        if (!empty($ad['authors_line'])) {
            echo '    <p class="resumo-autores">' . wp_kses($ad['authors_line'], array('sup' => array())) . '</p>';
        } elseif (!empty($author_name)) {
            echo '    <p class="resumo-autores">' . esc_html($author_name) . '</p>';
        }

        // Afiliações
        if (!empty($ad['affiliations_line'])) {
            echo '    <p class="resumo-afils">' . wp_kses($ad['affiliations_line'], array('sup' => array())) . '</p>';
        }

        // Duração (se disponível)
        if (!empty($duration)) {
            echo '    <p class="resumo-afils" style="margin-top:4px;"><em>Duração: ' . esc_html($duration) . ' min</em></p>';
        }

        // Corpo do texto (extraído do Word ou post_content)
        if (!empty($content)) {
            echo '    <div class="resumo-corpo">' . $content . '</div>';
        }

        echo '  </div>';

        // Rodapé com número de página (Item 10 – mesmo padrão dos resumos)
        echo '  <div class="resumo-footer">' . intval($num) . '</div>';

        echo '</div>';
    }

    /**
     * Extrai o conteúdo de um arquivo Word (DOCX) anexado como HTML.
     * Suporta .docx nativamente via ZipArchive + regex.
     * Para .doc, tenta conversão com LibreOffice (com cache em disco).
     *
     * @param  int    $attachment_id  ID do mídia do WordPress
     * @return string HTML com os parágrafos do documento, ou string vazia
     */
    private function extract_docx_content($attachment_id)
    {
        if (!$attachment_id) return '';

        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) return '';

        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($ext === 'docx') {
            return $this->read_docx_as_html($file_path);
        }

        // Para .doc e outros formatos: conversão via LibreOffice com cache
        $cache_path = $file_path . '.html.cache';
        if (file_exists($cache_path) && filemtime($cache_path) >= filemtime($file_path)) {
            return file_get_contents($cache_path);
        }

        $upload_dir = wp_upload_dir();
        $temp_dir   = $upload_dir['basedir'] . '/sciflow_docconv_' . uniqid();
        @mkdir($temp_dir, 0755, true);

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
                $body_content = $m[1];
            }
            @unlink($html_file);
        }
        // Limpa arquivos de imagem gerados pela conversão
        array_map('unlink', glob($temp_dir . '/*'));
        @rmdir($temp_dir);

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

        // ── Processa parágrafos <w:p>...</w:p> ────────────────────────
        preg_match_all('/<w:p[ >\/].*?<\/w:p>/s', $xml, $para_matches);

        $html_parts = array();

        foreach ($para_matches[0] as $para_xml) {

            // ── IMAGENS (w:drawing / w:pict) ───────────────────────
            if (preg_match('/<w:drawing\b/', $para_xml) || preg_match('/<v:imagedata\b/', $para_xml)) {
                // a:blip r:embed (moderno DOCX)
                preg_match_all('/<a:blip[^>]+r:embed="([^"]+)"/i', $para_xml, $bm1);
                // v:imagedata r:id (legado)
                preg_match_all('/<v:imagedata[^>]+r:id="([^"]+)"/i', $para_xml, $bm2);
                $rids = array_unique(array_merge($bm1[1], $bm2[1]));

                $img_rendered = false;
                foreach ($rids as $rid) {
                    if (isset($images_b64[$rid])) {
                        $html_parts[] = '<p style="text-align:center; margin:8px 0;">';
                        $html_parts[] = '<img src="' . $images_b64[$rid] . '" style="max-width:100%; height:auto;">';
                        $html_parts[] = '</p>';
                        $img_rendered = true;
                    }
                }
                if ($img_rendered) continue; // parágrafo tratado como imagem
            }

            // ── TEXTO ────────────────────────────────────
            $is_heading = preg_match('/<w:pStyle[^>]*w:val="(?:Heading|T[iI]tulo|Title)[^"]*"/i', $para_xml);

            preg_match_all('/<w:r[ >].*?<\/w:r>/s', $para_xml, $run_matches);

            $para_html = '';
            foreach ($run_matches[0] as $run_xml) {
                $bold   = (bool) preg_match('/<w:b(?:\/| \/|[^a-zA-Z][^>]*\/?>)/', $run_xml);
                $italic = (bool) preg_match('/<w:i(?:\/| \/|[^a-zA-Z][^>]*\/?>)/', $run_xml);

                preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $run_xml, $t_matches);
                $run_text = implode('', $t_matches[1]);
                $run_text = html_entity_decode($run_text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                if (empty($run_text)) continue;

                $run_text = esc_html($run_text);
                if ($bold)   $run_text = '<strong>' . $run_text . '</strong>';
                if ($italic) $run_text = '<em>' . $run_text . '</em>';
                $para_html .= $run_text;
            }

            // Hyperlinks
            preg_match_all('/<w:hyperlink[^>]*>(.*?)<\/w:hyperlink>/s', $para_xml, $hl_matches);
            foreach ($hl_matches[1] as $hl_inner) {
                preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $hl_inner, $ht);
                $hl_text = html_entity_decode(implode('', $ht[1]), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                if (!empty($hl_text)) {
                    $para_html .= esc_html($hl_text);
                }
            }

            $para_html = trim($para_html);
            if (empty($para_html)) continue;

            $html_parts[] = $is_heading
                ? '<p><strong>' . $para_html . '</strong></p>'
                : '<p>' . $para_html . '</p>';
        }

        return implode('', $html_parts);
    }

    private function output_css()
    {
        ?>
<style>
/* ─── Base / Reset ─────────────────────────────────────────── */
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --pw: 210mm; 
  --ph: 297mm;
  --margin-interna: 20mm;
  --f-serif: 'Times New Roman', Times, serif;
  --f-sans: Arial, Helvetica, sans-serif;
  --c-text: #000;
}
html, body {
  font-family: var(--f-serif);
  font-size: 12pt;
  color: var(--c-text);
  line-height: 1.0;
}
/* Item 8 – Corrige superscript: reduz elevação para não invadir linha anterior */
sup { font-size: 70%; line-height: 0; position: relative; top: -0.3em; vertical-align: baseline; }

/* ─── Screen Preview (Admin) ───────────────────────────────── */
@media screen {
  body.anais-body { background: #d4d4d4; padding-top: 50px; }
  .page {
    width: var(--pw);
    min-height: var(--ph);
    margin: 20px auto;
    background: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,.2);
    position: relative;
    overflow: hidden;
  }
  .noprint-bar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
    background: #1d4ed8; color: #fff;
    font-family: var(--f-sans); font-size: 13px;
    display: flex; align-items: center; gap: 16px; padding: 10px 20px;
  }
  .noprint-bar span { flex: 1; }
  .print-btn {
    background: #fff; color: #1d4ed8; border: none;
    padding: 6px 16px; border-radius: 4px; font-weight: bold; cursor: pointer;
  }
  .total-badge {
    background: rgba(255,255,255,.2);
    padding: 3px 10px; border-radius: 20px;
  }
}

/* ─── Print Settings ───────────────────────────────────────── */
@media print {
  @page {
    size: A4 portrait;
    margin: 0;
  }
  /* Páginas de palestra podem ultrapassar uma página física;
     @page nomeado garante margens corretas em TODAS as páginas do overflow */
  @page palestra-content {
    size: A4 portrait;
    margin: 20mm;
  }
  body.anais-body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .noprint-bar { display: none; }
  .page {
    width: auto; min-height: 0; margin: 0; box-shadow: none;
    page-break-after: always; break-after: page;
    position: relative;
  }
  /* Item 10 – Rodapé fixo em print: garantir altura mínima para posicionamento */
  .resumo-page:not(.palestra-page) {
    min-height: var(--ph) !important;
  }
  .resumo-page:not(.palestra-page) { page-break-inside: avoid; break-inside: avoid; }

  /* Palestra: permite overflow para múltiplas páginas com margens corretas */
  .palestra-page {
    page: palestra-content;          /* margens geridas pelo @page nomeado */
    padding: 20mm;                   /* preview de tela; impresso o @page sobrescreve */
    min-height: 0 !important;
    page-break-before: always;
    page-break-inside: auto;
    break-inside: auto;
    display: block;                  /* evita flex que força altura mínima */
  }
  .palestra-page .resumo-container {
    flex: none;
    display: block;
  }
  .palestra-page .resumo-footer {
    margin-top: 20px;
    border-top: 1px solid #ccc;
    padding-top: 5px;
  }
}

/* ─── Formatação Geral de Texto (Páginas Internas) ─────────── */
.text-page {
  padding: var(--margin-interna);
}
.centered-page {
  text-align: center;
}

/* ─── Static Full Pages (Images) ───────────────────────────── */
.static-page {
  padding: 0;
  margin: 0;
  height: var(--ph);
}
.full-page-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* ─── Ficha Catalográfica (Página 3) ───────────────────────── */
.pg-ficha {
  font-size: 12pt;
  line-height: 1.4;
}
.ficha-caixa {
  margin-top: 40px;
  border: 1px solid #000;
  padding: 20px;
  width: 100%;
}

/* ─── Apresentação (Página 5) ──────────────────────────────── */
.pg-apresentacao { line-height: 1.6; }
.apres-titulo { font-size: 12pt; font-weight: bold; margin-bottom: 30px; }
.apres-texto { text-indent: 1.5cm; text-align: justify; margin-bottom: 15px; }
.apres-assinatura { margin-top: 40px; text-align: right; }

/* ─── Sumário (Página 7) ───────────────────────────────────── */
.sumario-lista { line-height: 1.8; }

/* ─── Separadores de Categoria (XIX Enfrute / III Semco) ───── */
.separator-page {
  display: flex; align-items: center; justify-content: center;
  height: var(--ph);
  text-align: center;
  font-family: var(--f-sans);
}
.sep-evento { font-size: 32pt; font-weight: bold; margin-bottom: 20px; }
.sep-area { font-size: 24pt; color: #555; }

/* ─── Resumos Individuais ──────────────────────────────────── */
/* Item 10 – Layout flex para posicionamento fixo do rodapé */
.resumo-page {
  padding: var(--margin-interna);
  min-height: var(--ph);
  display: flex;
  flex-direction: column;
}
.resumo-container {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.resumo-header-ctx {
  font-family: var(--f-sans);
  font-size: 10pt;
  color: #777;
  border-bottom: 1px solid #ccc;
  padding-bottom: 5px;
  margin-bottom: 20px;
  text-align: right;
}
.resumo-titulo {
  font-size: 12pt;
  font-weight: bold;
  text-align: center;
  margin-bottom: 15px;
  line-height: 1.15;
}
.resumo-autores {
  font-size: 12pt;
  font-weight: bold;
  text-align: center;
  margin-bottom: 10px;
}
.resumo-afils {
  font-size: 10pt;
  text-align: justify;
  margin-bottom: 20px;
  color: #000;
}
.resumo-corpo {
  font-size: 12pt;
  text-align: justify;
  text-indent: 0;
  line-height: 1.0;
  margin-bottom: 15px;
}
.resumo-corpo p {
  margin-bottom: 10px;
}
.resumo-ack {
  font-size: 11pt;
  text-align: justify;
  margin-bottom: 10px;
}
.resumo-kws {
  font-size: 11pt;
  margin-top: 15px;
}
/* Item 10 – Rodapé fixo na parte inferior via margin-top:auto (flex) */
/* Item 10 – Removida a palavra "Página" do rodapé (feito no PHP) */
.resumo-footer {
  margin-top: auto;
  padding-top: 10px;
  font-size: 10pt;
  color: #999;
  text-align: right;
  font-family: var(--f-sans);
}
</style>
        <?php
    }
}

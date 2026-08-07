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
                                    <option value="palestras" <?php selected($volume_filter, 'palestras'); ?>><?php esc_html_e('Palestras Vol. I (Páginas Iniciais)', 'sciflow-wp'); ?></option>
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
        if ($volume === 'palestras') {
            $query = new WP_Query(array(
                'post_type'      => 'sciflow_palestra',
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ));
            $palestra_posts = $query->posts ?: array();
        }

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
            $area = $area ? trim($area) : 'Outros';
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
        $main_nums   = $get_or_add($main_institution);
        $main_sup    = self::nums_to_superscript($main_nums);
        $authors_parts[] = trim($main_name) . $main_sup;

        foreach ($coauthors as $ca) {
            if (empty($ca['name'])) continue;
            $ca_nums = $get_or_add($ca['institution'] ?? '');
            $ca_sup  = self::nums_to_superscript($ca_nums);
            $authors_parts[] = trim($ca['name']) . $ca_sup;
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

    private function get_image_url($filename)
    {
        return plugins_url('admin/images/' . $filename, dirname(dirname(__DIR__)) . '/dummy.php');
    }

    private function output_html($enfrute_posts, $semco_posts, $palestra_posts, $volume)
    {
        $is_palestras = ($volume === 'palestras');
        $total = $is_palestras ? count($palestra_posts) : (count($enfrute_posts) + count($semco_posts));

        $img_capa = $is_palestras ? 'static_palestras_pg0.png' : 'static_pg0.png';
        $img_rosto = $is_palestras ? 'static_palestras_pg1.png' : 'static_pg1.png';
        $img_parceiros = $is_palestras ? 'static_palestras_pg3.png' : 'static_pg3.png';
        $img_apres = $is_palestras ? 'static_palestras_apres.png' : null;
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
  <span>⚠ Preview dos Anais – Vol. II (Resumos)</span>
  <button onclick="window.print()" class="print-btn">🖨 Imprimir / Salvar como PDF</button>
  <span class="total-badge"><?php echo intval($total); ?> resumos</span>
</div>

<!-- ═══════════════ CAPA ═══════════════ -->
<div class="page static-page">
    <img src="<?php echo esc_url($this->get_image_url($img_capa)); ?>" alt="Capa" class="full-page-img">
</div>

<!-- ═══════════════ PÁGINA 2 (Folha de Rosto) ═══════════════ -->
<div class="page static-page">
    <img src="<?php echo esc_url($this->get_image_url($img_rosto)); ?>" alt="Folha de Rosto" class="full-page-img">
</div>

<!-- ═══════════════ PÁGINA 3 (Ficha Catalográfica) ═══════════════ -->
<div class="page text-page pg-ficha">
    <p>Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina<br>
    Epagri/Estação Experimental de Caçador “José Oscar Kurtz”<br>
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
        <strong>ENCONTRO NACIONAL DE FRUTICULTURA DE CLIMA TEMPERADO, 19., SEMINÁRIO CATARINENSE DE OLERICULTURA, 3.</strong>, 2026, Fraiburgo, SC. 
        <?php if ($is_palestras): ?>
            <strong>Anais de Palestras...</strong> Caçador, SC: Epagri, vol. I (Palestras), 2026. <?php echo intval($total); ?> palestras.
        <?php else: ?>
            <strong>Anais de Resumos...</strong> Caçador, SC: Epagri, vol. II (Resumos), 2026. <?php echo intval($total); ?> resumos.
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
    <img src="<?php echo esc_url($this->get_image_url($img_parceiros)); ?>" alt="Realização e Patrocínio" class="full-page-img">
</div>

<!-- ═══════════════ PÁGINA 5 (Apresentação) ═══════════════ -->
<?php if ($is_palestras && $img_apres): ?>
<div class="page static-page">
    <img src="<?php echo esc_url($this->get_image_url($img_apres)); ?>" alt="Apresentação" class="full-page-img">
</div>
<?php else: ?>
<div class="page text-page pg-apresentacao">
    <p class="apres-titulo"><strong>APRESENTAÇÃO</strong></p>
    
    <p class="apres-texto">A Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina (Epagri), Associação dos Engenheiros Agrônomos de Caçador (AEAC), Universidade Alto Vale do Rio do Peixe (Uniarp) e Prefeitura de Fraiburgo realizaram em Fraiburgo-SC nos dias 28, 29 e 30 de julho de 2026 o XIX Encontro Nacional de Fruticultura de Clima Temperado – XIX Enfrute e III Seminário Catarinense de Olericultura – III Semco. O tema central do evento foi a valorização da ciência brasileira para a produção de frutas e de hortaliças, em busca de uma produção cada vez mais competitiva, aderente as boas práticas de produção. No XIX Enfrute e III Semco foram submetidos mais de 400 resumos científicos contendo informações científicas relacionadas à fruticultura e olericultura, tudo em primeira mão. Os trabalhos aprovados pelo comitê científico foram apresentados em formato de pôster digital no website dos eventos, e estão disponíveis publicamente para consulta a qualquer momento. Nessa edição, foi criado o <strong>1º Prêmio HortiFruti Ciência</strong>, que tem como objetivo prestigiar, valorizar e premiar os melhores trabalhos científicos submetidos e apresentados. De todos os resumos submetidos ao XIX Enfrute e III Semco, 12 foram selecionados pelo comitê técnico-científico para apresentação oral pelos autores, sendo os três melhores premiados. Nesta obra estão apresentados os resumos de todos os trabalhos científicos aprovados nos dois eventos.</p>
    
    <p class="apres-texto">Desejamos a todos uma ótima leitura.</p>
    
    <div class="apres-assinatura">
        <p>Marcus Vinícius Kvitschal<br>
        Presidente da Comissão Organizadora do XIX Enfrute e III Semco<br>
        Julho de 2026</p>
    </div>
</div>
<?php endif; ?>

<?php if ($is_palestras): ?>
<!-- ═══════════════ FIM DA SEÇÃO AUTOMÁTICA DE PALESTRAS ═══════════════ -->
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
        return; // Interrompe para Palestras (já que elas são Word soltos)
endif; 
?>

<!-- ═══════════════ PÁGINA 6 (Revisores) ═══════════════ -->
<?php
        $base_pages = 5;

        $reviewers = $this->get_reviewers();
        if (empty($reviewers)) {
            $reviewers = array('Nenhum revisor cadastrado.');
        }
        $reviewer_chunks = array_chunk($reviewers, 70);
        $num_reviewer_pages = count($reviewer_chunks);

        $base_pages += $num_reviewer_pages;

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

        // ── CHUNK SUMÁRIO ───────────────────────────────────────────────────
        $sumario_lines = array();
        $grouped_enfrute = array();
        $grouped_semco = array();

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

        $sumario_pages = array();
        $current_page = array();
        $rows = 0;
        $max_rows = 38; 

        foreach ($sumario_lines as $line) {
            $row_cost = ($line['type'] === 'post') ? 1 : 2;
            if ($rows + $row_cost > $max_rows && !empty($current_page)) {
                $sumario_pages[] = $current_page;
                $current_page = array();
                $rows = 0;
            }
            $current_page[] = $line;
            $rows += $row_cost;
        }
        if (!empty($current_page)) {
            $sumario_pages[] = $current_page;
        }

        $num_sumario_pages = count($sumario_pages);
        $base_pages += $num_sumario_pages;

        // Calcula números de página
        $post_pages = array();
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
                    $p = $line['post'];
                    $pg = isset($post_pages[$p->ID]) ? $post_pages[$p->ID] : '';
                    echo '<div style="display:flex; justify-content:space-between; margin-left:20px; font-size:10pt; line-height:1.2; margin-bottom:4px;">';
                    echo '<span style="flex:1; padding-right:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' . wp_kses($this->format_scientific_title($p->post_title), array('i'=>array(),'em'=>array())) . '</span>';
                    echo '<span>Pág. ' . $pg . '</span>';
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

        echo '<div class="page resumo-page">';
        echo '  <div class="resumo-container">';
        
        // Cabeçalho de contexto na página do resumo
        echo '    <p class="resumo-header-ctx">' . esc_html($evento_label) . ' | <strong>' . esc_html($area_label) . '</strong></p>';

        // Título centralizado, uppercase, Arial/Calibri ou TNR 12pt Bold
        echo '    <h3 class="resumo-titulo">' . wp_kses($this->format_scientific_title($post->post_title), array('i' => array(), 'em' => array())) . '</h3>';

        // Autores (centralizados)
        echo '    <p class="resumo-autores">' . wp_kses($ad['authors_line'], array('sup' => array())) . '</p>';

        // Afiliações (centralizadas, menor)
        if (!empty($ad['affiliations_line'])) {
            echo '    <p class="resumo-afils">' . wp_kses($ad['affiliations_line'], array('sup' => array())) . '</p>';
        }

        // Corpo do resumo
        echo '    <p style="font-size:12pt; font-weight:bold; margin-bottom:5px;">Resumo:</p>';
        echo '    <div class="resumo-corpo">' . wp_kses_post($post->post_content) . '</div>';

        // Agradecimentos
        if (!empty($ack)) {
            echo '    <p class="resumo-ack"><strong>Agradecimentos:</strong> ' . esc_html($ack) . '</p>';
        }

        // Palavras-chave
        if (!empty($keywords)) {
            echo '    <p class="resumo-kws"><strong>Palavras-chave:</strong> ' . esc_html(implode('; ', $keywords)) . '.</p>';
        }

        // Rodapé com o número do resumo
        echo '    <div class="resumo-footer">Página ' . intval($num) . '</div>';

        echo '  </div>';
        echo '</div>';
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
sup { font-size: 70%; line-height: 0; position: relative; top: -0.4em; }

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
  body.anais-body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .noprint-bar { display: none; }
  .page {
    width: auto; min-height: 0; margin: 0; box-shadow: none;
    page-break-after: always; break-after: page;
    position: relative;
  }
  .resumo-page { page-break-inside: avoid; break-inside: avoid; }
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
.resumo-page {
  padding: var(--margin-interna);
}
.resumo-container {
  /* Resumo único por página não deve quebrar */
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
.resumo-footer {
  margin-top: 30px;
  font-size: 10pt;
  color: #999;
  text-align: right;
  font-family: var(--f-sans);
}
</style>
        <?php
    }
}

<?php
/**
 * Geração dos Anais – XIX Enfrute / III Semco 2026.
 *
 * Gera uma página HTML formatada para impressão como PDF.
 * Critério de inclusão: todos os resumos com status de aprovação,
 * independentemente do status do pôster.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Anais
{
    /**
     * Statuses que indicam que o resumo foi aprovado.
     */
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

    /**
     * Render the admin control panel page.
     */
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
                                    <option value="resumos" <?php selected($volume_filter, 'resumos'); ?>><?php esc_html_e('Enfrute + Semco (completo)', 'sciflow-wp'); ?></option>
                                    <option value="resumos_enfrute" <?php selected($volume_filter, 'resumos_enfrute'); ?>><?php esc_html_e('Somente XIX Enfrute', 'sciflow-wp'); ?></option>
                                    <option value="resumos_semco" <?php selected($volume_filter, 'resumos_semco'); ?>><?php esc_html_e('Somente III Semco', 'sciflow-wp'); ?></option>
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

    /**
     * Render statistics card.
     */
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

    /**
     * Render the full printable HTML page (called when ?anais_preview=1).
     */
    public function render_print_page()
    {
        $volume = sanitize_text_field($_GET['anais_volume'] ?? 'resumos');

        $enfrute_posts = array();
        $semco_posts   = array();

        if (in_array($volume, array('resumos', 'resumos_enfrute'), true)) {
            $enfrute_posts = $this->get_approved_works('enfrute');
        }
        if (in_array($volume, array('resumos', 'resumos_semco'), true)) {
            $semco_posts = $this->get_approved_works('semco');
        }

        $this->output_html($enfrute_posts, $semco_posts);
        exit;
    }

    /**
     * Fetch all approved works for a given event, sorted by knowledge area then title.
     */
    private function get_approved_works($event)
    {
        $post_type = SciFlow_Status_Manager::get_post_type_for_event($event);
        if (!$post_type) {
            return array();
        }

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
     * Build author + affiliation superscript notation.
     *
     * Returns:
     *   'authors_line'      => "Autor Principal¹; Coautor A¹²"
     *   'affiliations_line' => "¹Instituição A; ²Instituição B"
     *
     * @param  string $main_name
     * @param  string $main_institution
     * @param  array  $coauthors  array of ['name'=>…, 'institution'=>…]
     * @return array
     */
    public static function build_author_affiliations($main_name, $main_institution, $coauthors)
    {
        $affil_map   = array(); // normalized => display
        $affil_index = array(); // normalized => number

        $normalize = function ($s) {
            return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
        };

        $get_or_add = function ($institution) use (&$affil_map, &$affil_index, $normalize) {
            if (empty(trim($institution))) {
                return array();
            }
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

        // Main author
        $main_nums   = $get_or_add($main_institution);
        $main_sup    = self::nums_to_superscript($main_nums);
        $authors_parts[] = trim($main_name) . $main_sup;

        // Co-authors
        foreach ($coauthors as $ca) {
            if (empty($ca['name'])) continue;
            $ca_nums = $get_or_add($ca['institution'] ?? '');
            $ca_sup  = self::nums_to_superscript($ca_nums);
            $authors_parts[] = trim($ca['name']) . $ca_sup;
        }

        // Build affiliations line
        $affil_parts = array();
        foreach ($affil_map as $key => $display) {
            $affil_parts[] = array('num' => $affil_index[$key], 'name' => $display);
        }
        usort($affil_parts, function ($a, $b) { return $a['num'] - $b['num']; });

        $affil_line_parts = array();
        foreach ($affil_parts as $a) {
            $affil_line_parts[] = self::num_to_superscript_char($a['num']) . $a['name'];
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
     * Output the complete printable HTML document.
     */
    private function output_html($enfrute_posts, $semco_posts)
    {
        $total = count($enfrute_posts) + count($semco_posts);
        ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Anais – XIX Enfrute / III Semco 2026 – Vol. II Resumos</title>
<?php $this->output_css(); ?>
</head>
<body class="anais-body">

<div class="noprint-bar">
  <span>⚠ Preview dos Anais – Vol. II (Resumos)</span>
  <button onclick="window.print()" class="print-btn">🖨 Imprimir / Salvar como PDF</button>
  <span class="total-badge"><?php echo intval($total); ?> resumos</span>
</div>

<!-- ═══════════════ CAPA ═══════════════ -->
<div class="page cover-page">
  <div class="cover-top">
    <p class="issn">ISSN 2175-1889</p>
    <p class="event-main">XIX Encontro Nacional de Fruticultura de Clima Temperado – XIX Enfrute</p>
    <p class="event-main">III Seminário Catarinense de Olericultura – III Semco</p>
    <p class="event-date">28, 29 e 30 de julho de 2026 — Fraiburgo, SC</p>
  </div>
  <div class="volume-box">
    <div class="volume-label">ANAIS</div>
    <div class="volume-sub">Vol. II – Resumos</div>
  </div>
  <div class="cover-bottom">
    <p><strong>Marcelo Couto</strong><br><em>(Organizador)</em></p>
    <p>Comitê Técnico Científico:<br>Marcelo Couto (Epagri/EECD); Guilherme Mallmann (Epagri/EECD)</p>
    <p class="publisher">Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina<br>Florianópolis — 2026</p>
  </div>
</div>

<!-- ═══════════════ VERSO DA CAPA ═══════════════ -->
<div class="page verso-page">
  <p>Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina<br>
  Epagri/Estação Experimental de Caçador "José Oscar Kurtz"<br>
  Rua Abílio Franco, 1500, Bairro Bom Sucesso<br>
  89501-032, Caçador, SC<br>
  Fone: (49) 3561-6800 | E-mail: eecd@epagri.sc.gov.br</p>

  <p>Editado pela DOPPIO DESIGN</p>
  <p>Edição: Julho 2026 | Divulgação: on-line</p>
  <p>Editoração: DOPPIO DESIGN<br>
  Revisão textual: Marcus Vinícius Kvitschal, Fernando Pereira Monteiro, André Amarildo Sezerino,
  Guilherme Mallmann e Marcelo Couto<br>
  Diagramação: DOPPIO DESIGN</p>
  <p>A responsabilidade do editor limita-se à adequação dos trabalhos às normas editoriais estabelecidas.</p>
  <p>O conteúdo dos resumos aqui publicados é de responsabilidade exclusiva dos respectivos autores.</p>
  <p>É permitida a reprodução parcial dos resumos desta edição desde que citada a fonte.</p>
  <div class="ficha">
    <p><strong>Ficha Catalográfica</strong></p>
    <p>ENCONTRO NACIONAL DE FRUTICULTURA DE CLIMA TEMPERADO, 19., SEMINÁRIO CATARINENSE DE
    OLERICULTURA, 3., 2026, Fraiburgo, SC. <em>Anais de Resumos...</em> Caçador, SC: Epagri,
    vol. II (Resumos), 2026. <?php echo intval($total); ?> resumos.</p>
    <p>Fruticultura de Clima Temperado; Maçã; Uva; Pêssego; Pera; Ameixa; Nectarina;
    Goiaba; Caqui; Pequenas frutas; Frutas nativas; Olericultura; Tomate; Cebola; Alho;
    Morango; Mandioca; Cenoura; Pimentão; Folhosas; Lúpulo.</p>
    <p>ISSN 2175-1889</p>
  </div>
</div>

<!-- ═══════════════ APRESENTAÇÃO ═══════════════ -->
<div class="page apresentacao-page">
  <h2 class="cap-title">APRESENTAÇÃO</h2>
  <p class="ind-text">A Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina (Epagri), Associação dos Engenheiros Agrônomos de Caçador (AEAC), Universidade Alto Vale do Rio do Peixe (Uniarp) e Prefeitura de Fraiburgo realizaram em Fraiburgo-SC nos dias 28, 29 e 30 de julho de 2026 o XIX Encontro Nacional de Fruticultura de Clima Temperado – XIX Enfrute e III Seminário Catarinense de Olericultura – III Semco. O tema central do evento foi a valorização da ciência brasileira para a produção de frutas e de hortaliças, em busca de uma produção cada vez mais competitiva, aderente as boas práticas de produção. No XIX Enfrute e III Semco foram submetidos mais de 400 resumos científicos contendo informações científicas relacionadas à fruticultura e olericultura, tudo em primeira mão. Os trabalhos aprovados pelo comitê científico foram apresentados em formato de pôster digital no website dos eventos, e estão disponíveis publicamente para consulta a qualquer momento. Nessa edição, foi criado o 1º Prêmio HortiFruti Ciência, que tem como objetivo prestigiar, valorizar e premiar os melhores trabalhos científicos submetidos e apresentados. De todos os resumos submetidos ao XIX Enfrute e III Semco, 12 foram selecionados pelo comitê técnico-científico para apresentação oral pelos autores, sendo os três melhores premiados. Nesta obra estão apresentados os resumos de todos os trabalhos científicos aprovados nos dois eventos.</p>
  <p class="ind-text">Desejamos a todos uma ótima leitura.</p>
  <p class="assinatura"><strong>Marcus Vinícius Kvitschal</strong><br>
  Presidente da Comissão Organizadora do XIX Enfrute e III Semco<br>
  Julho de 2026</p>
</div>

<!-- ═══════════════ SUMÁRIO ═══════════════ -->
<div class="page sumario-page">
  <h2 class="cap-title">SUMÁRIO</h2>
  <div class="toc">
<?php
        if (!empty($enfrute_posts)) {
            echo '<p class="toc-section">Resumos – XIX Enfrute</p>';
            $i = 1;
            foreach ($enfrute_posts as $post) {
                $area = get_post_meta($post->ID, '_sciflow_knowledge_area', true);
                echo '<div class="toc-entry">';
                echo '<span class="toc-n">' . $i . '.</span> ';
                echo '<span class="toc-t">' . wp_kses($post->post_title, array('i' => array(), 'em' => array())) . '</span>';
                if ($area) echo '<span class="toc-a"> (' . esc_html($area) . ')</span>';
                echo '</div>';
                $i++;
            }
        }
        if (!empty($semco_posts)) {
            echo '<p class="toc-section">Resumos – III Semco</p>';
            $i = 1;
            foreach ($semco_posts as $post) {
                $area = get_post_meta($post->ID, '_sciflow_knowledge_area', true);
                echo '<div class="toc-entry">';
                echo '<span class="toc-n">' . $i . '.</span> ';
                echo '<span class="toc-t">' . wp_kses($post->post_title, array('i' => array(), 'em' => array())) . '</span>';
                if ($area) echo '<span class="toc-a"> (' . esc_html($area) . ')</span>';
                echo '</div>';
                $i++;
            }
        }
?>
  </div>
</div>

<?php
        // ── Resumos XIX Enfrute ─────────────────────────────────────────────
        if (!empty($enfrute_posts)) {
            echo '<div class="page separator-page"><div class="separator-box">Resumos – XIX Enfrute</div></div>';
            $n = 1;
            foreach ($enfrute_posts as $post) {
                $this->render_resumo($post, $n++);
            }
        }

        // ── Resumos III Semco ───────────────────────────────────────────────
        if (!empty($semco_posts)) {
            echo '<div class="page separator-page"><div class="separator-box">Resumos – III Semco</div></div>';
            $n = 1;
            foreach ($semco_posts as $post) {
                $this->render_resumo($post, $n++);
            }
        }
?>

</body>
</html>
<?php
    }

    /**
     * Render a single abstract.
     */
    private function render_resumo($post, $num)
    {
        $main_name    = get_post_meta($post->ID, '_sciflow_main_author_name', true);
        $main_instit  = get_post_meta($post->ID, '_sciflow_main_author_instituicao', true);
        $coauthors    = get_post_meta($post->ID, '_sciflow_coauthors', true);
        $keywords     = get_post_meta($post->ID, '_sciflow_keywords', true);
        $ack          = get_post_meta($post->ID, '_sciflow_acknowledgement', true);
        $area         = get_post_meta($post->ID, '_sciflow_knowledge_area', true);
        $cultura      = get_post_meta($post->ID, '_sciflow_cultura', true);

        if (!is_array($coauthors)) $coauthors = array();
        if (!is_array($keywords)) $keywords = $keywords ? array($keywords) : array();
        $keywords = array_filter(array_map('trim', $keywords));

        $ad = self::build_author_affiliations($main_name, $main_instit, $coauthors);

        echo '<div class="page resumo-page">';
        echo '<div class="resumo">';

        // Tags de classificação
        echo '<div class="resumo-tags">';
        echo '<span class="tag-num">' . intval($num) . '</span>';
        if ($area)    echo '<span class="tag-badge">' . esc_html($area) . '</span>';
        if ($cultura) echo '<span class="tag-badge tag-cultura">' . esc_html($cultura) . '</span>';
        echo '</div>';

        // Título
        echo '<h3 class="resumo-titulo">' . wp_kses($post->post_title, array('i' => array(), 'em' => array())) . '</h3>';

        // Autores
        echo '<p class="resumo-autores">' . wp_kses($ad['authors_line'], array('sup' => array())) . '</p>';

        // Afiliações
        if (!empty($ad['affiliations_line'])) {
            echo '<p class="resumo-afils">' . wp_kses($ad['affiliations_line'], array('sup' => array())) . '</p>';
        }

        // Corpo do resumo
        echo '<div class="resumo-corpo">' . wp_kses_post($post->post_content) . '</div>';

        // Agradecimentos
        if (!empty($ack)) {
            echo '<p class="resumo-ack"><strong>Agradecimentos:</strong> ' . esc_html($ack) . '</p>';
        }

        // Palavras-chave
        if (!empty($keywords)) {
            echo '<p class="resumo-kws"><strong>Palavras-chave:</strong> ' . esc_html(implode('; ', $keywords)) . '.</p>';
        }

        echo '</div>'; // /resumo
        echo '</div>'; // /page
    }

    /**
     * Output all CSS for the printable page.
     */
    private function output_css()
    {
        ?>
<style>
/* ─── Reset & Base ─────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --pw:210mm;--ph:297mm;
  --mt:25mm;--mb:25mm;--ms:30mm;
  --f:  'Times New Roman','TimesNewRoman',Georgia,serif;
  --fs: 11pt;
  --lh: 1.55;
  --c0: #111;--c1:#444;--c2:#777;--cr:#bbb;
}
html,body{font-family:var(--f);font-size:var(--fs);line-height:var(--lh);color:var(--c0);}
sup{font-size:70%;line-height:0;position:relative;vertical-align:baseline;top:-.4em}

/* ─── Screen: page cards ───────────────────────────────────── */
@media screen{
  body.anais-body{background:#d4d4d4;padding-top:48px;}
  .page{
    width:var(--pw);min-height:var(--ph);
    margin:20px auto;
    padding:var(--mt) var(--ms) var(--mb);
    background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.2);
  }
  .noprint-bar{
    position:fixed;top:0;left:0;right:0;z-index:9999;
    background:#1d4ed8;color:#fff;
    font-family:system-ui,sans-serif;font-size:13px;
    display:flex;align-items:center;gap:16px;padding:8px 20px;
  }
  .noprint-bar span{flex:1;}
  .print-btn{
    background:#fff;color:#1d4ed8;border:none;
    padding:6px 16px;border-radius:4px;font-size:13px;
    font-weight:bold;cursor:pointer;
  }
  .total-badge{
    background:rgba(255,255,255,.2);
    padding:2px 10px;border-radius:20px;font-size:12px;
  }
}

/* ─── Print ────────────────────────────────────────────────── */
@media print{
  @page{size:A4 portrait;margin:var(--mt) var(--ms) var(--mb);}
  body.anais-body{background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .noprint-bar{display:none;}
  .page{width:auto;min-height:0;margin:0;padding:0;box-shadow:none;page-break-after:always;break-after:page;}
  .resumo-page{page-break-inside:avoid;break-inside:avoid;}
}

/* ─── Cover ────────────────────────────────────────────────── */
.cover-page{display:flex;flex-direction:column;justify-content:space-between;text-align:center;}
.cover-top .issn{font-size:9pt;color:var(--c2);margin-bottom:8px;}
.cover-top .event-main{font-size:12.5pt;font-weight:bold;line-height:1.4;margin-bottom:3px;}
.cover-top .event-date{font-size:10.5pt;color:var(--c1);margin-top:8px;}
.volume-box{margin:0 auto;border-top:3px solid var(--c0);border-bottom:3px solid var(--c0);padding:24px 0;width:75%;}
.volume-label{font-size:26pt;font-weight:bold;letter-spacing:.08em;}
.volume-sub{font-size:14pt;font-weight:normal;margin-top:4px;}
.cover-bottom p{font-size:10pt;margin-bottom:8px;}
.cover-bottom .publisher{margin-top:18px;font-size:10pt;border-top:1px solid var(--cr);padding-top:12px;}

/* ─── Verso da capa ────────────────────────────────────────── */
.verso-page{font-size:9.5pt;}
.verso-page p{margin-bottom:10px;}
.ficha{margin-top:28px;padding:14px;border:1px solid #ccc;font-size:9pt;}

/* ─── Apresentação ─────────────────────────────────────────── */
.cap-title{font-size:14pt;font-weight:bold;text-align:center;letter-spacing:.06em;margin-bottom:24px;}
.ind-text{text-indent:2em;text-align:justify;margin-bottom:12px;}
.assinatura{margin-top:28px;text-align:right;font-size:10.5pt;}

/* ─── Sumário ──────────────────────────────────────────────── */
.toc-section{font-weight:bold;font-size:11pt;border-bottom:1px solid var(--cr);padding-bottom:4px;margin:14px 0 6px;}
.toc-entry{font-size:9.5pt;margin-bottom:4px;line-height:1.4;padding-left:18px;text-indent:-18px;}
.toc-n{font-weight:bold;margin-right:4px;}
.toc-a{color:var(--c2);font-size:9pt;}

/* ─── Separador de seção ───────────────────────────────────── */
.separator-page{display:flex;align-items:center;justify-content:center;}
.separator-box{text-align:center;font-size:18pt;font-weight:bold;border-top:3px solid var(--c0);border-bottom:3px solid var(--c0);padding:24px 48px;width:75%;}

/* ─── Resumos ──────────────────────────────────────────────── */
.resumo-page{padding-top:8mm;}
.resumo{border-bottom:1px solid var(--cr);padding-bottom:16px;}

.resumo-tags{margin-bottom:7px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.tag-num{display:inline-block;background:var(--c0);color:#fff;font-size:8pt;font-weight:bold;padding:1px 7px;border-radius:2px;}
.tag-badge{display:inline-block;font-size:8pt;color:var(--c1);border:1px solid var(--cr);padding:0 6px;border-radius:2px;}
.tag-cultura{color:#5b3e00;border-color:#c9a86c;background:#fdf6e3;}

.resumo-titulo{font-size:11.5pt;font-weight:bold;text-transform:uppercase;text-align:justify;margin-bottom:6px;line-height:1.4;}
.resumo-autores{font-size:10.5pt;font-weight:bold;margin-bottom:2px;}
.resumo-afils{font-size:9pt;color:var(--c1);margin-bottom:10px;line-height:1.35;}
.resumo-corpo{font-size:10.5pt;text-align:justify;text-indent:1.5em;line-height:var(--lh);margin-bottom:8px;}
.resumo-corpo p{margin-bottom:6px;}
.resumo-ack{font-size:9.5pt;color:var(--c1);margin-bottom:4px;text-align:justify;}
.resumo-kws{font-size:9.5pt;margin-top:6px;line-height:1.4;}
</style>
        <?php
    }
}

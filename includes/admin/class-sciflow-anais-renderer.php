<?php
/**
 * Book renderer for the Anais (proceedings).
 *
 * One pipeline for every volume. The HTML is a continuous, semantic document;
 * pagination is done by Paged.js (CSS Paged Media polyfill) in the browser,
 * from the real layout — nothing is estimated by counting characters. After
 * layout, a small handler writes every page number (footer, summary, total
 * pages in the catalog card) from the physical position of the pages, so the
 * summary can never disagree with the footers.
 *
 * Standard (from the approved template "Modelo Livro de RESUMOS 2026" and
 * the 2024 book):
 *  - A4; front matter margins 2 cm; content pages 3.5 cm top / 2 cm others;
 *  - three-line running header on separators and abstracts;
 *  - folio bottom-right; the cover is page 0 (not printed), title page = 1;
 *  - sans-serif; title 12 pt bold uppercase centred; authors 12 pt bold
 *    centred; affiliations 10 pt centred; body 12 pt justified, single
 *    spacing, 6 pt after paragraphs.
 *
 * Static mode (no JavaScript) is used only for the legacy "merged original
 * files" lectures PDF, where page numbers come from the measured PDFs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Anais_Renderer
{
    /** @var SciFlow_Anais */
    private $anais;

    public function __construct(SciFlow_Anais $anais)
    {
        $this->anais = $anais;
    }

    /**
     * Output the full book document.
     *
     * @param string     $volume       resumos | resumos_enfrute | resumos_semco | palestras_resumos | palestras
     * @param array      $data         Prepared data (see SciFlow_Anais::collect()).
     * @param array|null $fixed_pages  post_id => page number (static mode).
     * @param bool       $static       No Paged.js (for headless printing).
     */
    public function render($volume, array $data, $fixed_pages = null, $static = false)
    {
        $is_palestras = in_array($volume, array('palestras', 'palestras_resumos'), true);
        $title = $this->anais->title() . ' – ' . ($is_palestras ? 'Vol. I (Palestras)' : 'Vol. II (Resumos)');

        echo "<!DOCTYPE html>\n<html lang=\"pt-BR\">\n<head>\n<meta charset=\"UTF-8\">\n";
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">' . "\n";
        echo '<title>' . esc_html($title) . "</title>\n";
        $fonts = SciFlow_Branding::fonts();
        $font_key = (string) SciFlow_Settings::get('anais_font', 'arial');
        $font_key = isset($fonts[$font_key]) ? $font_key : 'arial';
        if ('' !== $fonts[$font_key][2]) {
            echo '<link rel="stylesheet" href="' . esc_url('https://fonts.googleapis.com/css2?family=' . $fonts[$font_key][2] . '&display=swap') . '">' . "\n";
        }
        $brand = SciFlow_Branding::get();
        $this->styles($static, array(
            '--sf-fonte'           => $fonts[$font_key][1],
            '--sf-marca'           => $brand['primary'],
            '--sf-marca-contraste' => $brand['primary_contrast'],
        ));
        if (!$static) {
            $this->scripts($is_palestras, count($data['items']));
        }
        echo "</head>\n<body class=\"sf-book" . ($static ? ' sf-static' : '') . "\">\n";

        $this->front_matter($is_palestras, $volume);

        if (!$is_palestras) {
            $this->reviewers($data['reviewers']);
        }

        $this->summary($data['toc'], $fixed_pages);

        $header_done = false;
        foreach ($data['sections'] as $section) {
            echo '<section class="sf-separador">';
            if (!$header_done) {
                // Running element: must precede the first page that uses it.
                echo '<div class="sf-cabecalho">' . wp_kses_post($this->anais->header_html()) . '</div>';
                $header_done = true;
            }
            echo '<div class="sf-separador-texto">';
            echo '<p class="sf-sep-evento">' . esc_html($section['title']) . '</p>';
            if ('' !== $section['subtitle']) {
                echo '<p class="sf-sep-area">' . esc_html($section['subtitle']) . '</p>';
            }
            echo '</div></section>' . "\n";

            if ($static) {
                continue; // Static mode renders preliminary pages only.
            }

            foreach ($section['items'] as $item) {
                $this->article($item);
            }
        }

        echo "\n</body>\n</html>";
    }

    /**
     * One abstract / lecture.
     */
    private function article(array $item)
    {
        echo '<article class="sf-trabalho" id="post-' . (int) $item['id'] . '">';
        echo '<h1 class="sf-titulo">' . wp_kses($item['title_html'], array('i' => array(), 'em' => array())) . '</h1>';
        if ('' !== $item['authors_html']) {
            echo '<p class="sf-autores">' . wp_kses($item['authors_html'], array('sup' => array())) . '</p>';
        }
        if ('' !== $item['affiliations_html']) {
            echo '<p class="sf-afiliacoes">' . wp_kses($item['affiliations_html'], array('sup' => array())) . '</p>';
        }
        if ('' !== $item['meta']) {
            echo '<p class="sf-meta">' . esc_html($item['meta']) . '</p>';
        }
        echo '<div class="sf-corpo">' . $item['body_html'] . '</div>'; // Sanitized in SciFlow_Anais::collect().
        if ('' !== $item['ack']) {
            echo '<p class="sf-agradecimentos"><strong>Agradecimentos:</strong> ' . $item['ack'] . '</p>';
        }
        if ('' !== $item['keywords']) {
            echo '<p class="sf-palavras-chave"><strong>Palavras-chave:</strong> ' . $item['keywords'] . '.</p>';
        }
        echo "</article>\n";
    }

    /**
     * Summary. Page numbers are filled after layout (or from $fixed_pages).
     */
    private function summary(array $toc, $fixed_pages)
    {
        echo '<section class="sf-sumario"><h2 class="sf-pre-titulo">SUMÁRIO</h2>';
        foreach ($toc as $line) {
            if ('event' === $line['type']) {
                echo '<p class="sf-toc-evento">' . esc_html($line['text']) . '</p>';
            } elseif ('area' === $line['type']) {
                echo '<p class="sf-toc-area">' . esc_html($line['text']) . '</p>';
            } else {
                $id = (int) $line['id'];
                $fixed = (is_array($fixed_pages) && isset($fixed_pages[$id])) ? (int) $fixed_pages[$id] : null;
                echo '<p class="sf-toc-item"><a href="#post-' . $id . '">';
                echo '<span class="sf-toc-titulo">' . wp_kses($line['title_html'], array('i' => array(), 'em' => array())) . '</span>';
                echo '<span class="sf-toc-pontos" aria-hidden="true"></span>';
                echo '<span class="sf-toc-pg" data-target="post-' . $id . '"' . (null !== $fixed ? ' data-fixed="' . $fixed . '"' : '') . '>' . (null !== $fixed ? $fixed : '000') . '</span>';
                echo '</a></p>';
            }
        }
        echo "</section>\n";
    }

    /**
     * Reviewer list (flows over as many pages as needed).
     */
    private function reviewers(array $reviewers)
    {
        echo '<section class="sf-revisores"><h2 class="sf-pre-titulo">Lista de revisores <em>ad hoc</em> dos resumos científicos</h2><ul>';
        foreach ($reviewers as $r) {
            echo '<li>' . esc_html($r) . '</li>';
        }
        echo "</ul></section>\n";
    }

    /**
     * Cover, title page, catalog card, partners, presentation.
     */
    private function front_matter($is_palestras, $volume)
    {
        if ($this->anais->use_legacy_pretextual()) {
            $this->legacy_front_matter($is_palestras, $volume);
            return;
        }

        $title = $this->anais->title();
        $organizer = (string) SciFlow_Settings::get('organizer_name', '');
        $catalog = (string) SciFlow_Settings::get('anais_catalog_html', '');
        $presentation = (string) SciFlow_Settings::get('anais_presentation_html', '');
        $vol = $is_palestras ? 'Vol. I – Palestras' : 'Vol. II – Resumos';

        $brand = SciFlow_Branding::get();
        $image = function ($key, $alt) {
            $url = SciFlow_Branding::image_url($key);
            if ('' === $url) {
                return false;
            }
            echo '<section class="sf-imagem"><img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '"></section>';
            return true;
        };

        if (!$image('anais_cover_image_id', 'Capa')) {
            echo '<section class="sf-capa sf-capa-texto sf-capa-marca"><div>';
            if ($brand['logo_url']) {
                echo '<img class="sf-capa-logo" src="' . esc_url($brand['logo_url']) . '" alt="">';
            }
            echo '<p class="sf-capa-titulo">' . esc_html($title) . '</p><p class="sf-capa-vol">' . esc_html($vol) . '</p></div></section>';
        }
        if (!$image('anais_title_image_id', 'Folha de rosto')) {
            echo '<section class="sf-pre sf-rosto">';
            if ($brand['logo_url']) {
                echo '<p><img class="sf-rosto-logo" src="' . esc_url($brand['logo_url']) . '" alt=""></p>';
            }
            echo '<p>' . wp_kses_post($this->anais->header_html()) . '</p>';
            echo '<p class="sf-rosto-titulo">ANAIS<br>' . esc_html($vol) . '</p>';
            if ('' !== $organizer) {
                echo '<p>' . esc_html($organizer) . '</p>';
            }
            echo '</section>';
        }
        if ('' !== trim(wp_strip_all_tags($catalog))) {
            echo '<section class="sf-pre sf-ficha">' . wp_kses_post($catalog) . '</section>';
        }
        $image('anais_partners_image_id', 'Realização e Patrocínio');
        if ('' !== trim(wp_strip_all_tags($presentation))) {
            echo '<section class="sf-pre sf-apresentacao">' . wp_kses_post($presentation) . '</section>';
        }
    }

    /**
     * Front matter of the XIX Enfrute / III Semco 2026 edition (verbatim).
     */
    private function legacy_front_matter($is_palestras, $volume)
    {
        $img = $is_palestras
            ? array('capa' => 'static_palestras_pg0.png', 'rosto' => 'static_palestras_pg1.png', 'parceiros' => 'static_palestras_pg3.png', 'apres' => 'static_palestras_apres.png')
            : array('capa' => 'static_pg0.png', 'rosto' => 'static_pg1.png', 'parceiros' => 'static_pg3.png', 'apres' => null);

        $image_page = function ($file, $alt) {
            $src = $this->anais->get_image_url($file);
            if ('' === $src) {
                return;
            }
            echo '<section class="sf-imagem"><img src="' . esc_attr($src) . '" alt="' . esc_attr($alt) . '"></section>';
        };

        $image_page($img['capa'], 'Capa');
        $image_page($img['rosto'], 'Folha de rosto');

        $vol_line = $is_palestras
            ? '<strong>Anais de Palestras...</strong> Caçador, SC: Epagri, vol. I (Palestras), 2026.'
            : '<strong>Anais de Resumos...</strong> Caçador, SC: Epagri, vol. II (Resumos), 2026.';
        ?>
<section class="sf-pre sf-ficha">
    <p>Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina<br>
    Epagri/Estação Experimental de Caçador "José Oscar Kurtz"<br>
    Rua Abílio Franco, 1500, Bairro Bom Sucesso<br>
    89501-032, Caçador, SC<br>
    Fone: (49) 3561-6800<br>
    E-mail: eecd@epagri.sc.gov.br</p>
    <p>Editado pela DOPPIO DESIGN</p>
    <p>Edição: Julho 2026<br>Divulgação: <em>on-line</em></p>
    <p>Editoração: DOPPIO DESIGN<br>
    Revisão textual: Marcus Vinícius Kvitschal, Fernando Pereira Monteiro, André Amarildo Sezerino, Guilherme Mallmann e Marcelo Couto<br>
    Diagramação: DOPPIO DESIGN</p>
    <p class="sf-centro">A responsabilidade do editor limita-se à adequação dos trabalhos às normas editoriais estabelecidas.</p>
    <p class="sf-centro">O conteúdo dos resumos aqui publicados é de responsabilidade exclusiva dos respectivos autores.</p>
    <p class="sf-centro">É permitida a reprodução parcial dos resumos desta edição desde que citada a fonte.</p>
    <div class="sf-ficha-caixa">
        <p class="sf-centro"><strong>Ficha Catalográfica</strong></p>
        <p><strong>ENCONTRO NACIONAL SOBRE FRUTICULTURA DE CLIMA TEMPERADO, 19., SEMINÁRIO CATARINENSE DE OLERICULTURA, 3.</strong>, 2026, Fraiburgo, SC. <?php echo $vol_line; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup ?> <span class="sf-total-paginas">000</span> p.</p>
        <p>Fruticultura de Clima Temperado; Maçã; Uva; Pêssego; Pera; Ameixa, Nectarina; Goiaba; Caqui; Pequenas frutas; Frutas nativas; Olericultura; Tomate; Cebola; Alho; Morango; Mandioca; Cenoura; Pimentão, Folhosas; Lúpulo.</p>
        <p>ISSN 2175-1889</p>
    </div>
</section>
        <?php
        $image_page($img['parceiros'], 'Realização e Patrocínio');

        if ($img['apres']) {
            $image_page($img['apres'], 'Apresentação');
            return;
        }
        ?>
<section class="sf-pre sf-apresentacao">
    <h2 class="sf-pre-titulo">APRESENTAÇÃO</h2>
    <p class="sf-recuo">A Empresa de Pesquisa Agropecuária e Extensão Rural de Santa Catarina (Epagri), Associação dos Engenheiros Agrônomos de Caçador (AEAC), Universidade Alto Vale do Rio do Peixe (Uniarp) e Prefeitura de Fraiburgo realizaram em Fraiburgo-SC nos dias 28, 29 e 30 de julho de 2026 o XIX Encontro Nacional SOBRE Fruticultura de Clima Temperado – XIX Enfrute e III Seminário Catarinense de Olericultura – III Semco. O tema central do evento foi a valorização da ciência brasileira para a produção de frutas e de hortaliças, em busca de uma produção cada vez mais competitiva, aderente as boas práticas de produção. No XIX Enfrute e III Semco foram submetidos mais de 400 resumos científicos contendo informações científicas relacionadas à fruticultura e olericultura, tudo em primeira mão. Os trabalhos aprovados pelo comitê científico foram apresentados em formato de pôster digital no website dos eventos, e estão disponíveis publicamente para consulta a qualquer momento em <a href="https://enfrute.com">enfrute.com</a>. Nessa edição, foi criado o <strong>1º Prêmio HortiFruti Ciência</strong>, que tem como objetivo prestigiar, valorizar e premiar os melhores trabalhos científicos submetidos e apresentados. De todos os resumos submetidos ao XIX Enfrute e III Semco, 12 foram selecionados pelo comitê técnico-científico para apresentação oral pelos autores, sendo os três melhores premiados. Nesta obra estão apresentados os resumos de todos os trabalhos científicos aprovados nos dois eventos.</p>
    <p class="sf-recuo">Desejamos a todos uma ótima leitura.</p>
    <p class="sf-assinatura">Marcus Vinícius Kvitschal<br>Presidente da Comissão Organizadora do XIX Enfrute e III Semco<br>Julho de 2026</p>
</section>
        <?php
    }

    /**
     * Paged.js + numbering handler + preview toolbar.
     */
    private function scripts($is_palestras, $count)
    {
        $label = $is_palestras ? 'palestras' : 'resumos';
        ?>
<script>
window.PagedConfig = { auto: true };
</script>
<script src="<?php echo esc_url(SCIFLOW_URL . 'public/vendor/pagedjs/paged.polyfill.min.js?ver=0.4.3'); ?>"></script>
<script>
(function () {
  if (!window.Paged) { return; }
  var started = Date.now();

  function toolbar() {
    var bar = document.getElementById('sf-toolbar');
    if (bar) { return bar; }
    bar = document.createElement('div');
    bar.id = 'sf-toolbar';
    bar.innerHTML = '<strong><?php echo esc_js($this->anais->title()); ?></strong>' +
      '<span id="sf-status">Paginando…</span>' +
      '<button type="button" id="sf-print" disabled>Imprimir / Salvar como PDF</button>' +
      '<small>Na impressão: destino “Salvar como PDF”, escala 100%. Tamanho e margens já vêm definidos.</small>';
    document.documentElement.appendChild(bar);
    document.getElementById('sf-print').addEventListener('click', function () { window.print(); });
    return bar;
  }

  class SciFlowNumbering extends Paged.Handler {
    afterPageLayout(pageElement, page) {
      toolbar();
      var s = document.getElementById('sf-status');
      if (s) { s.textContent = 'Paginando… ' + (page.position + 1) + ' páginas'; }
    }
    afterRendered(pages) {
      var list = Array.prototype.slice.call(document.querySelectorAll('.pagedjs_page'));
      var issues = 0;
      list.forEach(function (pg, i) {
        // Physical position = page number (cover = 0, title page = 1).
        pg.setAttribute('data-sf-folio', i);
        var noFolio = pg.querySelector('.sf-imagem, .sf-capa') !== null || i === 0;
        if (!noFolio) {
          var f = document.createElement('div');
          f.className = 'sf-folio';
          f.textContent = String(i);
          pg.querySelector('.pagedjs_pagebox').appendChild(f);
        }
      });
      document.querySelectorAll('.sf-toc-pg').forEach(function (el) {
        if (el.hasAttribute('data-fixed')) { return; }
        var t = document.getElementById(el.getAttribute('data-target'));
        var pg = t ? t.closest('.pagedjs_page') : null;
        if (pg) { el.textContent = pg.getAttribute('data-sf-folio'); } else { el.textContent = '?'; issues++; }
      });
      var last = list.length - 1;
      document.querySelectorAll('.sf-total-paginas').forEach(function (el) { el.textContent = String(last); });
      toolbar();
      var s = document.getElementById('sf-status');
      if (s) {
        s.textContent = list.length + ' páginas (capa + ' + last + ' numeradas) · <?php echo (int) $count; ?> <?php echo esc_js($label); ?>' +
          ' · ' + Math.round((Date.now() - started) / 1000) + ' s' + (issues ? ' · ' + issues + ' referência(s) sem página' : '');
      }
      document.getElementById('sf-print').disabled = false;
      document.documentElement.setAttribute('data-sf-ready', '1');
    }
  }
  Paged.registerHandlers(SciFlowNumbering);
})();
</script>
        <?php
    }

    /**
     * The print stylesheet (CSS Paged Media).
     */
    private function styles($static, array $vars = array())
    {
        ?>
<style>
:root {
<?php foreach ($vars as $k => $v) { echo '  ' . esc_html($k) . ': ' . SciFlow_Branding::css_value($v) . ";\n"; } // phpcs:ignore WordPress.Security.EscapeOutput -- css_value() ?>
}
/* ── Página e tipografia (padrão do modelo aprovado) ───────────────── */
@page { size: A4; margin: 20mm; }
@page capa { margin: 0; }
@page imagem { margin: 0; }
@page conteudo {
  margin: 35mm 20mm 20mm 20mm;
  @top-center { content: element(cabecalho); vertical-align: bottom; padding-bottom: 8mm; }
}
:root {
  --sf-texto: #000;
}
html, body { margin: 0; padding: 0; }
body.sf-book { font-family: var(--sf-fonte); font-size: 12pt; line-height: 1.15; color: var(--sf-texto); }
.sf-book p { margin: 0 0 6pt; orphans: 2; widows: 2; }
.sf-book a { color: inherit; text-decoration: none; }
.sf-book sup, .sf-book sub { font-size: 70%; line-height: 0; }
.sf-centro { text-align: center; }

/* ── Páginas de imagem (capa, rosto, parceiros): sangradas, sem fólio ─ */
.sf-imagem, .sf-capa { page: capa; break-before: page; break-after: page; height: 296.5mm; overflow: hidden; }
.sf-imagem { page: imagem; }
.sf-imagem img { display: block; width: 210mm; height: 296.5mm; object-fit: cover; }
.sf-capa-texto { display: flex; align-items: center; justify-content: center; text-align: center; }
.sf-capa-titulo { font-size: 24pt; font-weight: bold; padding: 0 25mm; }
.sf-capa-vol { font-size: 16pt; }
.sf-capa-marca { background: var(--sf-marca); color: var(--sf-marca-contraste); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.sf-capa-logo { display: block; box-sizing: content-box; max-width: 70mm; max-height: 30mm; margin: 0 auto 20mm; padding: 8mm 10mm; background: #fff; border-radius: 4mm; }
.sf-rosto-logo { max-width: 50mm; max-height: 25mm; }

/* ── Pré-textuais ──────────────────────────────────────────────────── */
.sf-pre, .sf-revisores, .sf-sumario { break-before: page; }
.sf-pre-titulo { font-size: 12pt; font-weight: bold; text-align: center; margin: 0 0 18pt; }
.sf-rosto { text-align: center; }
.sf-rosto-titulo { font-size: 14pt; font-weight: bold; margin: 50mm 0 30mm !important; }
.sf-ficha { font-size: 11pt; }
.sf-ficha > p { margin-bottom: 12pt; }
.sf-ficha-caixa { border: 1px solid #000; padding: 10pt 12pt; margin: 18pt 15mm 0; font-size: 10pt; text-align: justify; }
.sf-apresentacao { text-align: justify; line-height: 1.5; }
.sf-recuo { text-indent: 1.25cm; }
.sf-assinatura { text-align: center; margin-top: 18pt !important; }

/* ── Revisores ─────────────────────────────────────────────────────── */
.sf-revisores ul { list-style: none; margin: 0; padding: 0; column-count: 2; column-gap: 10mm; font-size: 10pt; }
.sf-revisores li { margin: 0 0 3pt; break-inside: avoid; }

/* ── Sumário ───────────────────────────────────────────────────────── */
.sf-sumario { font-size: 10pt; }
.sf-toc-evento { font-weight: bold; font-size: 11pt; margin: 12pt 0 4pt !important; break-after: avoid; }
.sf-toc-area { font-weight: bold; text-transform: uppercase; margin: 8pt 0 3pt !important; break-after: avoid; }
.sf-toc-item { margin: 0 0 3pt 6mm !important; break-inside: avoid; }
.sf-toc-item a { display: flex; align-items: last baseline; gap: 1.5mm; }
.sf-toc-titulo { flex: 0 1 auto; max-width: calc(100% - 22mm); }
.sf-toc-pontos { flex: 1 1 10mm; min-width: 10mm; height: 0.9em; overflow: hidden; white-space: nowrap; }
.sf-toc-pontos::after { content: ". . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . ."; }
.sf-toc-pg { flex: 0 0 auto; min-width: 4ch; text-align: right; font-variant-numeric: tabular-nums; }

/* ── Cabeçalho corrente e fólio ───────────────────────────────────── */
.sf-cabecalho { position: running(cabecalho); font-size: 9pt; line-height: 1.3; text-align: center; }
.sf-folio { position: absolute; right: 20mm; bottom: 12.7mm; font-size: 10pt; font-family: var(--sf-fonte); }

/* ── Separadores ───────────────────────────────────────────────────── */
.sf-separador { page: conteudo; break-before: page; break-after: page; }
.sf-separador-texto { padding-top: 85mm; text-align: center; }
.sf-sep-evento { font-size: 20pt; font-weight: bold; margin-bottom: 8pt !important; }
.sf-sep-area { font-size: 14pt; }

/* ── Resumo / palestra ─────────────────────────────────────────────── */
.sf-trabalho { page: conteudo; break-before: page; }
.sf-titulo { font-size: 12pt; font-weight: bold; text-align: center; margin: 0 0 6pt; line-height: 1.15; break-after: avoid; }
.sf-autores { font-weight: bold; text-align: center; break-after: avoid; }
.sf-afiliacoes { font-size: 10pt; text-align: center; margin-bottom: 12pt !important; break-after: avoid; }
.sf-meta { font-size: 10pt; text-align: center; font-style: italic; }
.sf-corpo { text-align: justify; }
.sf-corpo p, .sf-corpo li { text-align: justify; }
.sf-corpo h1, .sf-corpo h2, .sf-corpo h3, .sf-corpo h4 { font-size: 12pt; font-weight: bold; margin: 9pt 0 6pt; break-after: avoid; }
.sf-corpo img { display: block; max-width: 100%; max-height: 170mm; height: auto; width: auto; margin: 6pt auto; break-inside: avoid; }
.sf-corpo table { border-collapse: collapse; width: 100%; font-size: 10pt; margin: 6pt 0; }
.sf-corpo td, .sf-corpo th { border: 1px solid #000; padding: 2pt 4pt; vertical-align: top; }
.sf-corpo tr { break-inside: avoid; }
/* Documentos convertidos trazem estilos próprios: o padrão do livro prevalece. */
.sf-corpo * { font-family: var(--sf-fonte) !important; color: var(--sf-texto) !important; line-height: 1.15 !important; }
.sf-corpo p, .sf-corpo span, .sf-corpo li, .sf-corpo td { font-size: inherit !important; }
.sf-agradecimentos, .sf-palavras-chave { font-size: 11pt; text-align: justify; }
.sf-palavras-chave { margin-top: 6pt !important; }

/* ── Pré-visualização na tela ─────────────────────────────────────── */
@media screen {
  html { background: #d4d6d2; }
  .pagedjs_pages { padding: 64px 0 40px; }
  .pagedjs_page { background: #fff; margin: 0 auto 16px; box-shadow: 0 2px 12px rgba(0,0,0,.25); }
  #sf-toolbar { position: fixed; top: 0; left: 0; right: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px;
    padding: 10px 20px; background: #1f3b2c; color: #fff; font: 13px/1.4 system-ui, sans-serif; }
  #sf-toolbar strong { font-size: 14px; }
  #sf-toolbar small { flex-basis: 100%; opacity: .8; }
  #sf-print { margin-left: auto; padding: 6px 14px; border: 0; border-radius: 4px; background: #fff; color: #1f3b2c; font-weight: 600; cursor: pointer; }
  #sf-print:disabled { opacity: .5; cursor: wait; }
}
@media print { #sf-toolbar { display: none !important; } }
<?php if ($static) : ?>
/* Modo estático (sem Paged.js): impressão nativa do Chrome. */
.sf-cabecalho { display: none; }
<?php endif; ?>
</style>
        <?php
    }
}

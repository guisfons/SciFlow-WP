<?php
/**
 * Generates deterministic sample data for the Anais (book of abstracts).
 * DISPOSABLE installs only:
 *   wp eval-file tests/fixtures/anais-sample-data.php [count_per_event]
 *
 * Abstracts follow the real submission rules: a single paragraph, title +
 * body + acknowledgement between 3,000 and 4,000 characters, 3–5 keywords,
 * up to 6 authors, italic species names and ^ exponents.
 */

if (!defined('ABSPATH')) {
    exit(1);
}

mt_srand(20260725);

$per_event = isset($args[0]) ? max(1, (int) $args[0]) : 40;

$words = explode(' ', 'avaliação produtividade cultivar porta-enxerto manejo irrigação adubação nitrogenada fruto colheita pós-colheita armazenamento atmosfera controlada qualidade firmeza sólidos solúveis acidez titulável coloração epiderme incidência doença fúngica controle biológico tratamento experimental delineamento blocos casualizados repetições plantas parcela safra região altitude temperatura acúmulo horas frio dormência brotação floração frutificação raleio poda condução sistema densidade plantio rendimento comercial calibre massa média produção estimada análise variância teste Tukey significância diferença estatística resultado indica tendência aumento redução efeito interação genótipo ambiente hortaliça folhosa estufa hidroponia substrato muda transplante germinação vigor sementes');
$species = array('<em>Malus domestica</em>', '<em>Vitis vinifera</em>', '<em>Prunus persica</em>', '<em>Pyrus communis</em>', '<em>Solanum lycopersicum</em>', '<em>Allium cepa</em>', '<em>Fragaria × ananassa</em>', '<em>Colletotrichum gloeosporioides</em>', '<em>Botrytis cinerea</em>', '<em>Diaporthe</em> sp.');
$areas = array('Fitotecnia', 'Fitossanidade', 'Pós-colheita', 'Melhoramento Genético', 'Fisiologia Vegetal', 'Solos e Nutrição de Plantas', 'Socioeconomia');
$institutions = array('Epagri/Estação Experimental de Caçador', 'Universidade Federal de Santa Catarina', 'Embrapa Uva e Vinho', 'Universidade do Estado de Santa Catarina', 'Universidade Alto Vale do Rio do Peixe', 'Instituto Federal Catarinense – Campus Videira', 'Universidade Federal do Rio Grande do Sul', 'Universidade Tecnológica Federal do Paraná – Campus Dois Vizinhos');
$first = array('Ana', 'Bruno', 'Carla', 'Diego', 'Eduarda', 'Felipe', 'Gabriela', 'Henrique', 'Isadora', 'João', 'Larissa', 'Marcos', 'Natália', 'Otávio', 'Paula', 'Rafael');
$last = array('da Silva', 'Pereira dos Santos', 'Kvitschal', 'de Souza Mallmann', 'Oliveira', 'Couto', 'Sezerino', 'Monteiro', 'Brighenti', 'Hawerroth', 'Petri', 'Kulkamp de Souza');

$pick = function ($list) {
    return $list[mt_rand(0, count($list) - 1)];
};
$sentence = function ($min, $max) use ($words, $species, $pick) {
    $n = mt_rand($min, $max);
    $out = array();
    for ($i = 0; $i < $n; $i++) {
        $out[] = $pick($words);
    }
    if (mt_rand(0, 3) === 0) {
        $out[mt_rand(0, $n - 1)] = $pick($species);
    }
    if (mt_rand(0, 6) === 0) {
        $out[] = mt_rand(1, 9) . ',' . mt_rand(0, 9) . ' x 10^' . mt_rand(2, 6) . ' UFC mL^-1';
    }
    $s = implode(' ', $out);
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1) . '.';
};
$name = function () use ($first, $last, $pick) {
    return $pick($first) . ' ' . $pick($last);
};

$author_id = (int) wp_insert_user(array('user_login' => 'anais_autor_' . wp_generate_password(4, false), 'user_email' => 'anais' . wp_generate_password(4, false) . '@example.test', 'user_pass' => wp_generate_password(20), 'role' => 'sciflow_inscrito'));

$created = 0;
foreach (array('enfrute', 'semco') as $event) {
    for ($i = 0; $i < $per_event; $i++) {
        $title = rtrim($sentence(8, 22), '.');
        if (mt_rand(0, 2) === 0) {
            $title .= ' em ' . $pick($species);
        }
        $target = mt_rand(3050, 3950);
        $ack = mt_rand(0, 1) ? 'À Fapesc e ao CNPq pelo apoio financeiro e às equipes de campo pela condução dos experimentos.' : '';
        $body = '';
        while (mb_strlen(wp_strip_all_tags($title . $body . $ack)) < $target) {
            $body .= ($body ? ' ' : '') . $sentence(10, 28);
        }
        $body = mb_substr($body, 0, max(10, $target - mb_strlen(wp_strip_all_tags($title . $ack))));
        $body = preg_replace('/\s+\S*$/u', '', $body) . '.';

        $coauthors = array();
        $n_co = mt_rand(0, 5);
        for ($c = 0; $c < $n_co; $c++) {
            $coauthors[] = array(
                'name' => $name(),
                'email' => 'co' . $c . '@example.test',
                'institution' => $pick($institutions) . (mt_rand(0, 4) === 0 ? '; ' . $pick($institutions) : ''),
                'telefone' => '',
            );
        }

        $pt = SciFlow_Status_Manager::get_post_type_for_event($event);
        $id = wp_insert_post(array(
            'post_type' => $pt,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => '<p>' . $body . '</p>',
            'post_author' => $author_id,
        ));
        update_post_meta($id, '_sciflow_author_id', $author_id);
        update_post_meta($id, '_sciflow_event', $event);
        update_post_meta($id, '_sciflow_status', $pick(array('aprovado', 'poster_aprovado', 'apto_publicacao', 'poster_enviado')));
        update_post_meta($id, '_sciflow_knowledge_area', $pick($areas));
        update_post_meta($id, '_sciflow_main_author_name', mb_strtoupper($name()));
        update_post_meta($id, '_sciflow_main_author_instituicao', $pick($institutions));
        update_post_meta($id, '_sciflow_coauthors', $coauthors);
        update_post_meta($id, '_sciflow_keywords', array_slice(array_map(function () use ($words, $pick) { return $pick($words); }, range(1, 5)), 0, mt_rand(3, 5)));
        update_post_meta($id, '_sciflow_acknowledgement', $ack);
        $created++;
    }
}

// Reviewers for the reviewer list pages.
for ($r = 0; $r < 85; $r++) {
    $uid = wp_insert_user(array('user_login' => 'rev_' . $r . '_' . wp_generate_password(4, false), 'user_email' => 'rev' . $r . wp_generate_password(4, false) . '@example.test', 'user_pass' => wp_generate_password(20), 'display_name' => $name(), 'role' => $r % 2 ? 'sciflow_enfrute_revisor' : 'sciflow_semco_revisor'));
    update_user_meta($uid, '_sciflow_instituicao', $pick($institutions));
}

echo "Resumos criados: {$created}\n";

<?php
/**
 * SciFlow integration tests.
 *
 * Run inside a DISPOSABLE WordPress install (never production):
 *   wp eval-file wp-content/plugins/<sciflow>/tests/integration/run-tests.php
 *
 * Creates its own users/posts. Exit code 1 when any assertion fails.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run with: wp eval-file run-tests.php\n");
    exit(1);
}

final class SciFlow_Test_Runner
{
    private $pass = 0;
    private $fail = 0;
    private $current = '';

    public function section($name)
    {
        $this->current = $name;
        echo "\n== {$name}\n";
    }

    public function ok($cond, $label)
    {
        if ($cond) {
            $this->pass++;
            echo "  PASS  {$label}\n";
        } else {
            $this->fail++;
            echo "  FAIL  {$label}\n";
        }
    }

    public function done()
    {
        echo "\nResult: {$this->pass} passed, {$this->fail} failed\n";
        exit($this->fail ? 1 : 0);
    }
}

/**
 * Call an AJAX handler and return the decoded JSON response.
 */
function sciflow_test_ajax($callback, array $post, $nonce_action = 'sciflow_nonce')
{
    $_POST = $post;
    $_REQUEST = $post;
    if ($nonce_action) {
        $_POST['nonce'] = $_REQUEST['nonce'] = wp_create_nonce($nonce_action);
    }
    add_filter('wp_doing_ajax', '__return_true');
    $handler = function () {
        return function () {
            throw new RuntimeException('wp_die');
        };
    };
    add_filter('wp_die_ajax_handler', $handler);
    ob_start();
    try {
        call_user_func($callback);
    } catch (RuntimeException $e) {
        // Expected: wp_send_json_* ends with wp_die().
    }
    $out = ob_get_clean();
    remove_filter('wp_die_ajax_handler', $handler);
    remove_filter('wp_doing_ajax', '__return_true');
    $_POST = $_REQUEST = array();
    return json_decode($out, true);
}

function sciflow_test_user($login, $role)
{
    $existing = get_user_by('login', $login);
    if ($existing) {
        return (int) $existing->ID;
    }
    return (int) wp_insert_user(array(
        'user_login' => $login,
        'user_email' => $login . '@example.test',
        'user_pass'  => wp_generate_password(20),
        'role'       => $role,
    ));
}

function sciflow_test_submission($author_id, $event = 'enfrute', $status = 'submetido')
{
    $pt = SciFlow_Status_Manager::get_post_type_for_event($event);
    $id = wp_insert_post(array('post_type' => $pt, 'post_status' => 'publish', 'post_title' => 'T ' . wp_generate_password(6, false), 'post_content' => 'x', 'post_author' => $author_id));
    update_post_meta($id, '_sciflow_author_id', $author_id);
    update_post_meta($id, '_sciflow_status', $status);
    update_post_meta($id, '_sciflow_event', $event);
    return (int) $id;
}

$t = new SciFlow_Test_Runner();

$admin_id   = sciflow_test_user('t_admin', 'administrator');
$author_id  = sciflow_test_user('t_author', 'sciflow_inscrito');
$other_id   = sciflow_test_user('t_other', 'sciflow_inscrito');
$semco_ed   = sciflow_test_user('t_semco_editor', 'sciflow_semco_editor');
$enf_ed     = sciflow_test_user('t_enfrute_editor', 'sciflow_enfrute_editor');
$enf_rev    = sciflow_test_user('t_enfrute_rev', 'sciflow_enfrute_revisor');
$gestor     = sciflow_test_user('t_gestor', 'sciflow_tecnico_admin');

/* ------------------------------------------------------------------ */
$t->section('Visualização de trabalhos (IDOR)');
$sub = sciflow_test_submission($author_id, 'enfrute', 'submetido');
$t->ok(!sciflow_can_view_submission($sub, 0), 'anônimo não vê trabalho sem revisor atribuído');
$t->ok(sciflow_can_view_submission($sub, $author_id), 'autor vê o próprio trabalho');
$t->ok(!sciflow_can_view_submission($sub, $other_id), 'outro inscrito não vê');
$t->ok(sciflow_can_view_submission($sub, $enf_ed), 'editor do evento vê');
$t->ok(!sciflow_can_view_submission($sub, $semco_ed), 'editor de OUTRO evento não vê');
$t->ok(sciflow_can_view_submission($sub, $enf_rev), 'revisor do evento vê trabalho publicado do evento');
$draft = sciflow_test_submission($author_id, 'enfrute', 'rascunho');
$t->ok(!sciflow_can_view_submission($draft, $enf_rev), 'revisor do evento não vê rascunho');

/* ------------------------------------------------------------------ */
$t->section('Autorização editorial por evento');
$sm = new SciFlow_Status_Manager();
$editorial = new SciFlow_Editorial($sm, new SciFlow_Email());
$sub2 = sciflow_test_submission($author_id, 'enfrute', 'submetido');
wp_set_current_user($semco_ed);
$r = $editorial->make_decision($sub2, 'approve', '');
$t->ok(is_wp_error($r) && 'unauthorized' === $r->get_error_code(), 'editor Semco NÃO decide trabalho Enfrute');
$t->ok('submetido' === $sm->get_status($sub2), 'status preservado após tentativa negada');
wp_set_current_user($enf_ed);
$r = $editorial->make_decision($sub2, 'approve', '');
$t->ok(true === $r && 'aprovado' === $sm->get_status($sub2), 'editor Enfrute decide trabalho Enfrute');
wp_set_current_user($author_id);
$r = $editorial->make_decision($sub2, 'reject', '');
$t->ok(is_wp_error($r), 'autor não toma decisão editorial');
wp_set_current_user($semco_ed);
$r = $editorial->assign_reviewer(sciflow_test_submission($author_id, 'enfrute'), $enf_rev);
$t->ok(is_wp_error($r), 'editor Semco não atribui revisor em trabalho Enfrute');

/* ------------------------------------------------------------------ */
$t->section('Endpoints AJAX');
$loader_ajax = new SciFlow_Ajax_Handler(
    new SciFlow_Submission($sm, new SciFlow_Email()),
    new SciFlow_Review($sm, new SciFlow_Email()),
    $editorial,
    new SciFlow_Poster_Upload($sm, new SciFlow_Email()),
    new SciFlow_PayGo_Gateway()
);
$sub3 = sciflow_test_submission($author_id, 'enfrute', 'aprovado');
wp_set_current_user($other_id);
$res = sciflow_test_ajax(array($loader_ajax, 'handle_create_payment'), array('post_id' => $sub3));
$t->ok(is_array($res) && false === $res['success'], 'create_payment em trabalho alheio é negado');
$res = sciflow_test_ajax(array($loader_ajax, 'handle_update_coauthors'), array('post_id' => $sub3, 'coauthors' => '[{"name":"X","email":"x@example.test"}]'));
$t->ok(is_array($res) && false === $res['success'], 'update_coauthors em trabalho alheio é negado');
wp_set_current_user($author_id);
$res = sciflow_test_ajax(array($loader_ajax, 'handle_update_coauthors'), array('post_id' => $sub3, 'coauthors' => '[{"name":"Coautor","email":"c@example.test"}]'));
$t->ok(is_array($res) && true === $res['success'], 'autor atualiza coautores do próprio trabalho aprovado');
$res = sciflow_test_ajax(array($loader_ajax, 'handle_poster_decision'), array('post_id' => $sub3, 'decision' => 'approve_poster'));
$t->ok(is_array($res) && false === $res['success'], 'autor não decide pôster');
wp_set_current_user(0);
$res = sciflow_test_ajax(array($loader_ajax, 'handle_submit'), array('event' => 'enfrute', 'title' => 'x'));
$t->ok(is_array($res) && false === $res['success'], 'anônimo não submete');
wp_set_current_user($author_id);
$res = sciflow_test_ajax(array($loader_ajax, 'handle_submit'), array('event' => 'enfrute', 'title' => 'x'), 'outra_acao');
$t->ok(is_array($res) && false === $res['success'], 'nonce inválido é rejeitado');

/* ------------------------------------------------------------------ */
$t->section('Webhook de pagamento');
$pay = sciflow_test_submission($author_id, 'enfrute', 'aguardando_pagamento');
update_post_meta($pay, '_sciflow_payment_status', 'pending');
update_post_meta($pay, '_sciflow_payment_id', 'TX-TEST-1');
$gw = new SciFlow_PayGo_Gateway();
$t->ok(false === $gw->handle_webhook(array('id' => 'TX-TEST-1', 'status' => 'PAID')), 'notificação forjada (sem confirmação na API) é recusada');
$t->ok('pending' === get_post_meta($pay, '_sciflow_payment_status', true), 'status de pagamento inalterado');
SciFlow_Settings::update(array('paygo_webhook_secret' => 'segredo-teste'));
$req = new WP_REST_Request('POST', '/sciflow/v1/payment-webhook');
$t->ok(is_wp_error($gw->webhook_permission($req)), 'webhook sem token é negado quando há segredo');
$req->set_param('token', 'segredo-teste');
$t->ok(true === $gw->webhook_permission($req), 'webhook com token correto é aceito');
SciFlow_Settings::update(array('paygo_webhook_secret' => ''));
$t->ok(in_array('submetido', (array) (new ReflectionMethod('SciFlow_Status_Manager', 'get_transitions'))->invoke($sm)['aguardando_pagamento'], true), 'transição aguardando_pagamento → submetido existe');

/* ------------------------------------------------------------------ */
$t->section('Escalada de privilégio (Gestor Técnico)');
wp_set_current_user($gestor);
$t->ok(!current_user_can('edit_user', $admin_id), 'gestor não edita administrador');
$t->ok(!current_user_can('promote_user', $admin_id), 'gestor não altera papel de administrador');
$t->ok(!current_user_can('delete_user', $admin_id), 'gestor não exclui administrador');
$t->ok(current_user_can('edit_user', $author_id), 'gestor edita usuário comum');
$editable = get_editable_roles();
$t->ok(!isset($editable['administrator']), 'papel administrator não é atribuível pelo gestor');
$req = new WP_REST_Request('POST', '/wp/v2/users/' . $gestor);
$req->set_param('roles', array('administrator'));
$t->ok(rest_do_request($req)->get_status() >= 400, 'REST: autopromoção a administrador falha');
wp_set_current_user($admin_id);
$t->ok(current_user_can('edit_user', $gestor) && isset(get_editable_roles()['administrator']), 'administrador mantém controle total');

/* ------------------------------------------------------------------ */
$t->section('Configurações');
$before = SciFlow_Settings::all();
update_option('sciflow_settings', array_merge(get_option('sciflow_settings', array()), array('paygo_token' => 'tok-antigo', 'ranking_email_text' => 'Texto X')));
SciFlow_Settings::flush();
$clean = SciFlow_Settings::sanitize(array(
    'paygo_token' => '',
    'woo_product_ids' => '12, abc, 12, 7',
    'article_submission_deadline' => 'nao-e-data',
    'email_from_address' => 'invalido',
    'event_enfrute_label' => '<b>Meu Evento</b>',
    'certificate_access' => 'qualquer',
));
$t->ok('tok-antigo' === $clean['paygo_token'], 'segredo em branco mantém o valor salvo');
$t->ok('Texto X' === $clean['ranking_email_text'], 'chave não enviada é preservada');
$t->ok('12,7' === $clean['woo_product_ids'], 'IDs de produto normalizados');
$t->ok('' === $clean['article_submission_deadline'], 'data inválida descartada');
$t->ok('' === $clean['email_from_address'], 'e-mail inválido descartado');
$t->ok('Meu Evento' === $clean['event_enfrute_label'], 'HTML removido do nome do evento');
$t->ok('logged_in' === $clean['certificate_access'], 'valor fora da lista volta ao padrão seguro');
$clean = SciFlow_Settings::sanitize(array('paygo_token_clear' => '1'));
$t->ok('' === $clean['paygo_token'], 'opção "remover" apaga o segredo');
define('SCIFLOW_PAYGO_INTEGRATION_KEY', 'from-constant');
$t->ok('from-constant' === SciFlow_Settings::get('paygo_integration_key') && SciFlow_Settings::is_externally_defined('paygo_integration_key'), 'segredo via constante tem prioridade');
$t->ok(false === strpos(SciFlow_Settings::mask('abcdefghijklmnop'), 'abcdefgh'), 'máscara não revela o segredo');

/* ------------------------------------------------------------------ */
$t->section('Páginas e idempotência');
$created1 = SciFlow_Pages::create_missing();
$count1 = count(get_posts(array('post_type' => 'page', 'numberposts' => -1, 'meta_key' => SciFlow_Pages::ROLE_META, 'fields' => 'ids')));
$created2 = SciFlow_Pages::create_missing();
$count2 = count(get_posts(array('post_type' => 'page', 'numberposts' => -1, 'meta_key' => SciFlow_Pages::ROLE_META, 'fields' => 'ids')));
$t->ok(empty($created2) && $count1 === $count2, 'segunda execução não duplica páginas (' . $count1 . ' páginas)');
$t->ok(SciFlow_Pages::exists('submission') && SciFlow_Pages::exists('author_dashboard'), 'páginas obrigatórias resolvidas');
$t->ok(0 === strpos(SciFlow_Pages::url('author_dashboard'), home_url()), 'URL usa o domínio atual');

SciFlow_Installer::install_site();
SciFlow_Installer::install_site();
$crons = 0;
foreach ((array) _get_cron_array() as $events) {
    foreach (SciFlow_Installer::CRON_HOOKS as $hook) {
        if (isset($events[$hook])) {
            $crons += count($events[$hook]);
        }
    }
}
$t->ok(3 === $crons, 'reinstalação mantém exatamente 3 eventos agendados');

$role = get_role('sciflow_tecnico_admin');
$role->remove_cap('manage_woocommerce');
update_option('sciflow_version', '1.0.6');
delete_transient('sciflow_upgrading');
SciFlow_Installer::maybe_upgrade();
$t->ok(!get_role('sciflow_tecnico_admin')->has_cap('manage_woocommerce'), 'atualização preserva permissões customizadas do Gestor');
$t->ok(SCIFLOW_VERSION === get_option('sciflow_version'), 'versão registrada após atualização');

/* ------------------------------------------------------------------ */
$t->section('Migração de instalação legada');
$saved = get_option('sciflow_settings');
update_option('sciflow_settings', array('woo_product_ids' => '5'));
update_option('sciflow_migrations', array());
update_option('sciflow_version', '1.0.6');
SciFlow_Settings::flush();
delete_transient('sciflow_upgrading');
SciFlow_Installer::maybe_upgrade();
$t->ok('Enfrute' === SciFlow_Events::label('enfrute') && 'Semco' === SciFlow_Events::label('semco'), 'nomes legados dos eventos semeados');
$t->ok('email' === SciFlow_Settings::get('certificate_access'), 'modo de certificado legado preservado');
$t->ok('5' === SciFlow_Settings::get('woo_product_ids'), 'valores existentes não sobrescritos');
update_option('sciflow_settings', array('event_enfrute_label' => 'Customizado'));
update_option('sciflow_migrations', array());
update_option('sciflow_version', '1.0.6');
SciFlow_Settings::flush();
delete_transient('sciflow_upgrading');
SciFlow_Installer::maybe_upgrade();
$t->ok('Customizado' === SciFlow_Events::label('enfrute'), 'migração não sobrescreve valor já definido');
update_option('sciflow_settings', $saved);
SciFlow_Settings::flush();

/* ------------------------------------------------------------------ */
$t->section('Exportação CSV');
$t->ok("'=HYPERLINK(\"x\")" === SciFlow_Security::csv_cell('=HYPERLINK("x")'), 'fórmula neutralizada');
$t->ok("'+1+1" === SciFlow_Security::csv_cell('+1+1'), 'prefixo + neutralizado');
$t->ok('-12.5' === SciFlow_Security::csv_cell('-12.5'), 'número negativo preservado');
$t->ok('Maçã' === SciFlow_Security::csv_cell('Maçã'), 'texto normal preservado');

/* ------------------------------------------------------------------ */
$t->section('Diagnóstico');
$checks = SciFlow_Diagnostics::run();
$ids = wp_list_pluck($checks, 'id');
$t->ok(in_array('php', $ids, true) && in_array('woocommerce', $ids, true), 'verificações executadas (' . count($checks) . ')');
SciFlow_Settings::update(array('paygo_integration_key' => 'k', 'paygo_token' => ''));
$paygo = wp_list_filter(SciFlow_Diagnostics::run(), array('id' => 'paygo'));
$paygo = reset($paygo);
$t->ok($paygo && 'error' === $paygo['status'], 'credencial PayGo incompleta é bloqueante');
SciFlow_Settings::update(array('paygo_integration_key' => ''));
$t->ok(!SciFlow_Diagnostics::is_ready(array(array('status' => 'error'))), 'is_ready() falso com erro bloqueante');

/* ------------------------------------------------------------------ */
$t->section('Armazenamento privado');
$uploads = wp_upload_dir();
$t->ok(file_exists($uploads['basedir'] . '/certificados/.htaccess'), 'certificados/ protegido');
$dir = SciFlow_Security::private_dir('teste-' . wp_generate_password(6, false));
file_put_contents($dir . 'a.txt', 'x');
$t->ok(SciFlow_Security::remove_tree($dir) && !is_dir($dir), 'remove_tree apaga diretório privado');
$t->ok(false === SciFlow_Security::remove_tree(ABSPATH . 'wp-includes'), 'remove_tree recusa caminho fora de uploads');


/* ------------------------------------------------------------------ */
$t->section('Certificados por tipo');
$cert_base = SciFlow_Certificates::base_dir();
$cert_mail = 'teste-cert-' . strtolower(wp_generate_password(6, false)) . '@exemplo.com';
$cert_files = array(
    $cert_base . $cert_mail . '.pdf',
    $cert_base . 'poster/' . strtoupper($cert_mail) . '.PDF',
    $cert_base . 'poster/' . $cert_mail . '-2.pdf',
    $cert_base . 'palestrante/' . $cert_mail . '.pdf',
    $cert_base . 'palestrante/outro-' . $cert_mail . '.pdf',
    $cert_base . 'Pasta Invalida/' . $cert_mail . '.pdf',
);
foreach ($cert_files as $f) {
    wp_mkdir_p(dirname($f));
    file_put_contents($f, '%PDF-1.4 teste');
}
$types = SciFlow_Certificates::types();
$t->ok(array_slice(array_keys($types), 0, 2) === array('participacao', 'poster') && isset($types['palestrante']) && !isset($types['Pasta Invalida']), 'tipos vêm das subpastas, na ordem do evento, ignorando nomes inválidos');
$found = SciFlow_Certificates::find_certificates($cert_mail);
$t->ok(4 === count($found), 'encontra os 4 certificados da pessoa em todos os tipos (' . count($found) . ')');
$t->ok(array('Participação', 'Pôster 1', 'Pôster 2', 'Palestrante') === wp_list_pluck($found, 'label'), 'rótulos numerados quando há mais de um do mesmo tipo');
$t->ok(!in_array('palestrante/outro-' . $cert_mail . '.pdf', wp_list_pluck($found, 'key'), true), 'arquivo de outro e-mail com o mesmo final não é incluído');
$t->ok($cert_files[0] === SciFlow_Certificates::find_certificate_file($cert_mail), 'find_certificate_file() continua devolvendo o de participação');
$t->ok(array() === SciFlow_Certificates::find_certificates('../../wp-config.php'), 'entrada que não é e-mail não encontra nada');
$t->ok(file_exists($cert_base . '.htaccess'), 'pasta raiz dos certificados (e subpastas) protegida');
$cert_user = wp_insert_user(array('user_login' => 'cert_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'user_email' => 'conta-' . $cert_mail));
update_user_meta($cert_user, 'billing_email', strtoupper($cert_mail));
wp_set_current_user($cert_user);
$t->ok(4 === count(SciFlow_Certificates::find_certificates(SciFlow_Certificates::current_user_emails())), 'usuário logado vê os certificados do e-mail de cobrança');
$html = SciFlow_Certificates::render_shortcode();
$t->ok(4 === substr_count($html, 'name="sciflow_cert_key"') && false !== strpos($html, 'Pôster 2'), 'página lista um botão por certificado');
$t->ok(false === strpos($html, $cert_base), 'caminho no servidor não aparece na página');
wp_set_current_user(0);
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user($cert_user);
foreach ($cert_files as $f) {
    @unlink($f);
}
foreach (array('poster', 'palestrante', 'Pasta Invalida') as $d) {
    @rmdir($cert_base . $d);
}


/* ------------------------------------------------------------------ */
$t->section('Anais');
$anais = new SciFlow_Anais();
$t->ok('6,3 x 10<sup>5</sup> UFC mL<sup>-1</sup>' === $anais->convert_scientific_notation('6,3 x 10^5 UFC mL^-1'), 'expoentes convertidos (10^5, mL^-1)');
$t->ok('<a href="x^1">y<sup>2</sup></a>' === $anais->convert_scientific_notation('<a href="x^1">y^2</a>'), 'atributos HTML não são alterados');
if (class_exists('ZipArchive')) {
    $docx = wp_tempnam('sciflow') . '.docx';
    $zip = new ZipArchive();
    $zip->open($docx, ZipArchive::CREATE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        . '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Introdução</w:t></w:r></w:p>'
        . '<w:p><w:r><w:t xml:space="preserve">Texto com </w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>Malus</w:t></w:r><w:r><w:rPr><w:b w:val="0"/></w:rPr><w:t xml:space="preserve"> e &lt;script&gt;</w:t></w:r></w:p>'
        . '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>B</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
        . '</w:body></w:document>');
    $zip->close();
    $m = new ReflectionMethod('SciFlow_Anais', 'read_docx_as_html');
    $m->setAccessible(true);
    $html = $m->invoke($anais, $docx);
    @unlink($docx);
    $t->ok(false !== strpos($html, '<h2>Introdução</h2>'), 'docx: título vira cabeçalho');
    $t->ok(false !== strpos($html, '<em>Malus</em>') && false === strpos($html, '<strong> e'), 'docx: itálico preservado e w:b val=0 ignorado');
    $t->ok(false !== strpos($html, '<table><tr><td><p>A</p></td><td><p>B</p></td></tr></table>'), 'docx: tabela preservada');
    $t->ok(false === strpos($html, '<script>'), 'docx: texto escapado');
}
$approved = sciflow_test_submission($author_id, 'semco', 'aprovado');
update_post_meta($approved, '_sciflow_knowledge_area', 'Fitotecnia');
$data = $anais->collect('resumos_semco');
$ids = array();
foreach ($data['sections'] as $sec) { foreach ($sec['items'] as $it) { $ids[] = $it['id']; } }
$t->ok(in_array($approved, $ids, true) && !in_array($sub, $ids, true), 'anais incluem apenas trabalhos aprovados do evento');
$toc_ids = array_values(wp_list_pluck(wp_list_filter($data['toc'], array('type' => 'post')), 'id'));
$t->ok($toc_ids === $ids, 'sumário e conteúdo têm a mesma ordem');


/* ------------------------------------------------------------------ */
$t->section('Assistente de configuração');
wp_set_current_user($admin_id);
update_option(SciFlow_Setup_Wizard::STATUS_OPTION, 'pending', false);
delete_option(SciFlow_Setup_Wizard::REDIRECT_OPTION);
do_action('after_switch_theme', 'twentytwentyfive', wp_get_theme('twentytwentyfive'));
$t->ok(!function_exists('inscricao_enfrute_on_activate') || (int) get_option(SciFlow_Setup_Wizard::REDIRECT_OPTION) > 0, 'ativar o tema SciFlow abre o assistente se a configuração está pendente');
update_option(SciFlow_Setup_Wizard::STATUS_OPTION, 'completed', false);
delete_option(SciFlow_Setup_Wizard::REDIRECT_OPTION);
sciflow_request_setup_wizard();
$t->ok(!get_option(SciFlow_Setup_Wizard::REDIRECT_OPTION), 'após concluir, o assistente não reabre sozinho');
delete_option(SciFlow_Setup_Wizard::STATUS_OPTION);
update_option('sciflow_version', '1.0.6');
SciFlow_Installer::activate(false);
$t->ok('legacy' === get_option(SciFlow_Setup_Wizard::STATUS_OPTION) && !get_option(SciFlow_Setup_Wizard::REDIRECT_OPTION), 'instalação existente (1.0.x) não é redirecionada');
$w = new SciFlow_Setup_Wizard();
$v = new ReflectionMethod('SciFlow_Setup_Wizard', 'validate');
$v->setAccessible(true);
$t->ok(count($v->invoke($w, 'evento', array('event_enfrute_enabled' => '1', 'event_enfrute_label' => 'X', 'event_enfrute_full_label' => 'X completo', 'organizer_name' => 'Org'))) === 0, 'passo Evento válido sem erros');
$t->ok(count($v->invoke($w, 'evento', array())) > 0, 'passo Evento vazio é recusado');
$t->ok(count($v->invoke($w, 'emails', array('email_from_address' => 'x', 'email_from_name' => 'N', 'lgpd_consent_text' => 'ok'))) === 1, 'remetente inválido é recusado');


/* ------------------------------------------------------------------ */
$t->section('Identidade visual');
SciFlow_Settings::update(array('brand_primary' => '#2b4c9b', 'brand_text' => '#1b2233', 'brand_heading_font' => 'montserrat'));
SciFlow_Branding::flush();
$b = SciFlow_Branding::get();
$t->ok('#2b4c9b' === $b['primary'] && '#ffffff' === $b['primary_contrast'], 'cor principal e contraste calculado (texto branco)');
SciFlow_Settings::update(array('brand_primary' => '#f2e85c'));
SciFlow_Branding::flush();
$t->ok('#111111' === SciFlow_Branding::get()['primary_contrast'], 'cor clara recebe texto escuro');
$t->ok(false !== strpos(SciFlow_Branding::css_variables(), '--bs-primary') || false !== strpos(SciFlow_Branding::css_variables(), '--brand-primary:#f2e85c'), 'variáveis CSS geradas');
$t->ok(false !== strpos(SciFlow_Branding::google_fonts_url(), 'Montserrat'), 'fonte do Google carregada só quando escolhida');
$clean = SciFlow_Settings::sanitize(array('brand_primary' => 'red;}</style>', 'brand_heading_font' => 'comic', 'brand_logo_id' => 999999));
$t->ok('#f2e85c' === $clean['brand_primary'] && 'system-sans' === $clean['brand_heading_font'] && 0 === $clean['brand_logo_id'], 'cor/fonte/logo inválidos são recusados');
$cv = SciFlow_Branding::css_value('"Open Sans", Arial;}</style>');
$t->ok(0 === strpos($cv, '"Open Sans", Arial') && false === strpbrk($cv, '<>{};'), 'valor CSS não fecha a regra nem a tag');
ob_start();
$m = new ReflectionMethod('SciFlow_Email', 'render_template'); $m->setAccessible(true);
$html = $m->invoke(new SciFlow_Email(), 'new-submission', array('titulo' => 'T', 'evento' => 'E', 'status' => 'S', 'link' => home_url('/'), 'site_name' => 'Site', 'message' => 'M'));
ob_end_clean();
$t->ok(false !== strpos($html, '#f2e85c') && false === strpos($html, '#2c5530'), 'e-mail usa a cor da identidade');
SciFlow_Settings::update(array('brand_primary' => '#1f6f78', 'brand_heading_font' => 'system-sans'));
SciFlow_Branding::flush();

$t->done();

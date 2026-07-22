<?php
/**
 * Public Posters Gallery Template.
 */

if (!defined('ABSPATH')) {
    exit;
}

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$search_query = isset($_GET['sciflow_search']) ? sanitize_text_field($_GET['sciflow_search']) : '';
$filter_evento = isset($_GET['sciflow_evento']) ? sanitize_text_field($_GET['sciflow_evento']) : '';
$filter_cultura = isset($_GET['sciflow_cultura']) ? sanitize_text_field($_GET['sciflow_cultura']) : '';
$filter_area = isset($_GET['sciflow_area']) ? sanitize_text_field($_GET['sciflow_area']) : '';

$meta_query = array(
    'relation' => 'AND',
    array(
        'key'     => '_sciflow_status',
        'value'   => array('aprovado', 'poster_enviado', 'poster_aprovado', 'apto_publicacao', 'confirmado', 'poster_reenviado'),
        'compare' => 'IN'
    )
);

if (!empty($filter_cultura)) {
    $meta_query[] = array(
        'key'   => '_sciflow_cultura',
        'value' => $filter_cultura
    );
}

if (!empty($filter_area)) {
    $meta_query[] = array(
        'key'   => '_sciflow_knowledge_area',
        'value' => $filter_area
    );
}

// Prepare search args if search query exists
$post_types = array('enfrute_trabalhos', 'semco_trabalhos');
if ($filter_evento === 'enfrute') {
    $post_types = array('enfrute_trabalhos');
} elseif ($filter_evento === 'semco') {
    $post_types = array('semco_trabalhos');
}

$args = array(
    'post_type'      => $post_types,
    'post_status'    => 'publish',
    'posts_per_page' => 20,
    'paged'          => $paged,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'meta_query'     => $meta_query
);

if (!empty($search_query)) {
    $args['s'] = $search_query;
}

$query = new WP_Query($args);
$status_manager = new SciFlow_Status_Manager();

?>

<div class="sciflow-public-posters py-5 bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="h2 fw-900 text-dark mb-1">Exibição Pública de Pôsteres do XIX ENFRUTE e III SEMCO</h2>
                <p class="text-muted mb-0">Confira a galeria de trabalhos aprovados para o evento.</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge bg-white text-dark border p-2 px-3 rounded-pill fw-bold">
                    <i class="bi bi-file-earmark-check me-1 text-success"></i>
                    <?php echo esc_html($query->found_posts); ?> Trabalhos
                </span>
            </div>
        </div>

        <form method="GET" class="row g-3 mb-4 sciflow-filters" id="sciflow-public-filters">
            <!-- Retain page ID or slug if shortcode is placed on a page -->
            <?php if (is_page()) { echo '<input type="hidden" name="page_id" value="' . get_the_ID() . '">'; } ?>
            
            <div class="col-12 col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted">🔍</span>
                    <input type="text" name="sciflow_search" value="<?php echo esc_attr($search_query); ?>"
                           class="form-control border-start-0 ps-0 fw-medium shadow-none"
                           placeholder="Buscar por Título...">
                </div>
            </div>
            <div class="col-12 col-md-2">
                <select name="sciflow_evento" class="form-select form-select-sm fw-medium text-secondary shadow-none">
                    <option value="">Todos Eventos</option>
                    <option value="enfrute" <?php selected($filter_evento, 'enfrute'); ?>>Enfrute</option>
                    <option value="semco" <?php selected($filter_evento, 'semco'); ?>>Semco</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="sciflow_cultura" class="form-select form-select-sm fw-medium text-secondary shadow-none">
                    <option value="">Todas as Culturas</option>
                    <optgroup label="Frutas de clima temperado">
                        <?php 
                        $frutas = array('Figo', 'Frutas de caroço', 'Goiaba/Caqui', 'Maçã/Pera', 'Pequenas frutas', 'Frutas nativas', 'Uva', 'Outras (Frutas)');
                        foreach ($frutas as $f) {
                            echo '<option value="' . esc_attr($f) . '" ' . selected($filter_cultura, $f, false) . '>' . esc_html($f) . '</option>';
                        }
                        ?>
                    </optgroup>
                    <optgroup label="Olerícolas">
                        <?php 
                        $olericolas = array('Alho', 'Cebola', 'Tomate', 'Morango', 'Aipim/mandioca', 'Cenoura', 'Pimentão', 'Folhosas', 'Outras (Olerícolas)');
                        foreach ($olericolas as $o) {
                            echo '<option value="' . esc_attr($o) . '" ' . selected($filter_cultura, $o, false) . '>' . esc_html($o) . '</option>';
                        }
                        ?>
                    </optgroup>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <select name="sciflow_area" class="form-select form-select-sm fw-medium text-secondary shadow-none">
                    <option value="">Todas as Áreas</option>
                    <?php 
                    $areas = array(
                        'Biotecnologia/Genética e Melhoramento', 'Botânica e Fisiologia', 'Colheita e Pós-Colheita',
                        'Fitossanidade', 'Economia/Estatística', 'Fitotecnia', 'Irrigação', 
                        'Processamento (Química e Bioquímica)', 'Propagação', 'Sementes', 
                        'Solos e Nutrição de Plantas', 'Outros'
                    );
                    foreach ($areas as $a) {
                        echo '<option value="' . esc_attr($a) . '" ' . selected($filter_area, $a, false) . '>' . esc_html($a) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Aplicar Filtro</button>
            </div>
        </form>

        <?php if ($query->have_posts()): ?>
            <div class="row g-4">
                <?php while ($query->have_posts()): $query->the_post(); 
                    $post_id = get_the_ID();
                    $status = get_post_meta($post_id, '_sciflow_status', true);
                    $event = get_post_meta($post_id, '_sciflow_event', true);
                    $cultura = get_post_meta($post_id, '_sciflow_cultura', true);
                    $area = get_post_meta($post_id, '_sciflow_knowledge_area', true);
                    $poster_id = get_post_meta($post_id, '_sciflow_poster_id', true);
                    
                    $author_id = get_post_meta($post_id, '_sciflow_author_id', true);
                    $main_user = get_userdata($author_id);
                    $main_author_name = $main_user ? $main_user->display_name : 'Autor Principal';
                    
                    $coauthors = get_post_meta($post_id, '_sciflow_coauthors', true);
                    $coauthors_names = array();
                    if (is_array($coauthors)) {
                        foreach ($coauthors as $c) {
                            if (!empty($c['name'])) $coauthors_names[] = $c['name'];
                        }
                    }
                    $all_authors_text = $main_author_name;
                    if (!empty($coauthors_names)) {
                        $all_authors_text .= ', ' . implode(', ', $coauthors_names);
                    }
                ?>
                    <div class="col-12 col-md-6 col-lg-4 d-flex">
                        <div class="card w-100 border-0 shadow-sm rounded-4 h-100">
                            <div class="card-body d-flex flex-column p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <span class="badge bg-light text-secondary border rounded-pill px-3 py-2 fw-medium" style="font-size: 11px;">
                                        ID: #<?php echo str_pad($status_manager::get_visual_id($post_id), 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                    <?php 
                                        $event_color_class = (strtolower($event) === 'enfrute') ? 'bg-primary text-primary border-primary-subtle' : 'bg-success text-success border-success-subtle';
                                    ?>
                                    <span class="badge <?php echo $event_color_class; ?> bg-opacity-10 border rounded-pill px-3 py-2" style="font-size: 11px;">
                                        <?php echo esc_html(ucfirst($event)); ?>
                                    </span>
                                </div>
                                <h5 class="card-title fw-bold text-dark lh-sm mb-3">
                                    <?php echo $status_manager::render_title(get_the_title()); ?>
                                </h5>
                                <div class="mb-3" style="font-size: 13px; color: #555;">
                                    <strong>Autores:</strong> <?php echo esc_html($all_authors_text); ?>
                                </div>
                                <div class="mt-auto">
                                    <hr class="border-light opacity-50 my-3">
                                    <div class="d-flex flex-column gap-2" style="font-size: 12px; color: #777;">
                                        <?php if ($cultura): ?>
                                            <div><strong>Cultura:</strong> <?php echo esc_html($cultura); ?></div>
                                        <?php endif; ?>
                                        <?php if ($area): ?>
                                            <div><strong>Área:</strong> <?php echo esc_html($area); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-4 pt-3 border-top d-flex gap-2 justify-content-center">
                                        <?php if ($poster_id): ?>
                                            <a href="<?php echo esc_url(wp_get_attachment_url($poster_id)); ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill fw-bold w-100">
                                                Visualizar Pôster
                                            </a>
                                        <?php else: ?>
                                            <span class="btn btn-outline-secondary btn-sm rounded-pill fw-bold w-100 disabled">
                                                Pôster Pendente
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($query->max_num_pages > 1): ?>
                <nav aria-label="Navegação de página" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php
                        $pages = paginate_links(array(
                            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                            'format' => '?paged=%#%',
                            'current' => max(1, get_query_var('paged')),
                            'total' => $query->max_num_pages,
                            'type' => 'array',
                            'prev_text' => '&laquo; Anterior',
                            'next_text' => 'Próxima &raquo;',
                        ));
                        if (is_array($pages)) {
                            foreach ($pages as $page) {
                                $page = str_replace('page-numbers', 'page-link', $page);
                                $li_class = 'page-item';
                                if (strpos($page, 'current') !== false) {
                                    $li_class .= ' active';
                                }
                                echo '<li class="' . $li_class . '">' . $page . '</li>';
                            }
                        }
                        ?>
                    </ul>
                </nav>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>

        <?php else: ?>
            <div class="text-center py-5">
                <p class="text-muted mb-0">Nenhum pôster ou trabalho aprovado encontrado para os filtros selecionados.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

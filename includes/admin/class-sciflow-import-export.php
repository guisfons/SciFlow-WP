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
        add_action('admin_post_sciflow_import_works', array($this, 'handle_import'));
    }

    /**
     * Render the admin page UI.
     */
    public function render_page()
    {
        if (!current_user_can('manage_options')) {
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

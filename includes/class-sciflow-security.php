<?php
/**
 * Security helpers and global hardening hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SciFlow_Security
{
    /**
     * Register global hooks. Must run on every request (front, admin, REST,
     * AJAX, cron) because capability checks happen in all of them.
     */
    public static function init()
    {
        add_filter('map_meta_cap', array(__CLASS__, 'protect_privileged_users'), 10, 4);
        add_filter('editable_roles', array(__CLASS__, 'filter_editable_roles'));
    }

    /**
     * Users without `manage_options` may not edit, delete or promote users
     * who hold `manage_options` (administrators). This closes the path where
     * a role with `edit_users`/`promote_users` (e.g. Gestor Técnico) could take
     * over an administrator account or promote itself through the REST API.
     *
     * @param string[] $caps
     * @param string   $cap
     * @param int      $user_id
     * @param array    $args
     * @return string[]
     */
    public static function protect_privileged_users($caps, $cap, $user_id, $args)
    {
        if (!in_array($cap, array('edit_user', 'delete_user', 'promote_user', 'remove_user'), true)) {
            return $caps;
        }
        if (empty($args[0])) {
            return $caps;
        }

        $target_id = (int) $args[0];
        if ($target_id === (int) $user_id && 'edit_user' === $cap) {
            return $caps; // Editing your own profile stays allowed.
        }

        if (user_can($user_id, 'manage_options')) {
            return $caps;
        }

        if (user_can($target_id, 'manage_options')) {
            $caps[] = 'do_not_allow';
        }

        return $caps;
    }

    /**
     * Roles that grant `manage_options`, `edit_users` or `promote_users` are
     * only assignable by administrators. Applies everywhere (wp-admin and REST).
     *
     * @param array $roles
     * @return array
     */
    public static function filter_editable_roles($roles)
    {
        if (current_user_can('manage_options')) {
            return $roles;
        }

        $sensitive = array('manage_options', 'edit_users', 'promote_users', 'create_users', 'delete_users', 'activate_plugins', 'install_plugins', 'edit_plugins', 'unfiltered_html');
        foreach ($roles as $key => $role) {
            $caps = isset($role['capabilities']) ? (array) $role['capabilities'] : array();
            foreach ($sensitive as $cap) {
                if (!empty($caps[$cap])) {
                    unset($roles[$key]);
                    break;
                }
            }
        }
        return $roles;
    }

    /**
     * Whether a post is a SciFlow submission.
     */
    public static function is_submission($post_id)
    {
        return in_array(get_post_type($post_id), SciFlow_Events::post_types(), true);
    }

    /**
     * Whether the current user may act editorially on a submission.
     *
     * Event editors only receive capabilities for their own event post type,
     * so checking `edit_others_{post_type}` scopes decisions per event.
     *
     * @param int $post_id
     * @param int $user_id Defaults to the current user.
     */
    public static function can_manage_submission($post_id, $user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if (!$user_id || !self::is_submission($post_id)) {
            return false;
        }
        if (!user_can($user_id, 'manage_sciflow')) {
            return false;
        }
        $pto = get_post_type_object(get_post_type($post_id));
        return $pto && user_can($user_id, $pto->cap->edit_others_posts);
    }

    /**
     * Whether the user is the submitting author of a submission.
     */
    public static function is_author($post_id, $user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0 || !self::is_submission($post_id)) {
            return false;
        }
        return (int) get_post_meta($post_id, '_sciflow_author_id', true) === $user_id;
    }

    /**
     * Whether the user is the assigned reviewer of a submission.
     */
    public static function is_assigned_reviewer($post_id, $user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if ($user_id <= 0 || !self::is_submission($post_id)) {
            return false;
        }
        return (int) get_post_meta($post_id, '_sciflow_reviewer_id', true) === $user_id;
    }

    /**
     * Neutralise spreadsheet formulas (CSV injection) in exported values.
     *
     * @param mixed $value
     * @return string
     */
    public static function csv_cell($value)
    {
        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }
        $value = (string) $value;
        if ('' !== $value && in_array($value[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
            // Keep plain negative numbers intact.
            if (!preg_match('/^-?\d+([.,]\d+)?$/', $value)) {
                $value = "'" . $value;
            }
        }
        return $value;
    }

    /**
     * Apply csv_cell() to every column of a row.
     *
     * @param array $row
     * @return array
     */
    public static function csv_row(array $row)
    {
        return array_map(array(__CLASS__, 'csv_cell'), $row);
    }

    /**
     * Create a directory and deny direct web access to it (Apache/LiteSpeed
     * via .htaccess, IIS via web.config). Nginx needs a server rule — see docs.
     *
     * @param string $dir Absolute path.
     * @return bool Whether the directory exists and is protected.
     */
    public static function protect_directory($dir)
    {
        $dir = trailingslashit($dir);
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return false;
        }

        $files = array(
            'index.php'  => "<?php\n// Silence is golden.\n",
            '.htaccess'  => "# SciFlow: acesso direto bloqueado\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n",
            'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
        );

        $ok = true;
        foreach ($files as $name => $content) {
            $path = $dir . $name;
            if (!file_exists($path)) {
                $ok = (false !== @file_put_contents($path, $content)) && $ok; // phpcs:ignore WordPress.WP.AlternativeFunctions
            }
        }
        return $ok && file_exists($dir . '.htaccess');
    }

    /**
     * Private working directory inside uploads (protected).
     *
     * @param string $sub Optional sub-directory.
     * @return string Absolute path with trailing slash.
     */
    public static function private_dir($sub = '')
    {
        $uploads = wp_upload_dir(null, false);
        $base = trailingslashit($uploads['basedir']) . 'sciflow-private/';
        self::protect_directory($base);
        if ('' !== $sub) {
            $base = trailingslashit($base . sanitize_file_name($sub));
            wp_mkdir_p($base);
        }
        return $base;
    }

    /**
     * Remove a directory tree without shell commands. Refuses paths outside
     * the uploads directory.
     */
    public static function remove_tree($dir)
    {
        $uploads = wp_upload_dir(null, false);
        $base = realpath($uploads['basedir']);
        $real = realpath($dir);
        if (!$base || !$real || 0 !== strpos($real, $base . DIRECTORY_SEPARATOR)) {
            return false;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        return @rmdir($real);
    }

    /**
     * Fixed-window rate limit keyed by action + client IP.
     *
     * @param string $action
     * @param int    $max     Max hits per window.
     * @param int    $window  Window in seconds.
     * @return bool True when the request is allowed.
     */
    public static function rate_limit($action, $max, $window = 600)
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $key = 'sciflow_rl_' . md5($action . '|' . $ip);
        $hits = (int) get_transient($key);
        if ($hits >= $max) {
            return false;
        }
        set_transient($key, $hits + 1, $window);
        return true;
    }

    /**
     * Validate an uploaded file array and return a normalized error.
     *
     * @param array    $file      $_FILES entry.
     * @param string[] $mimes     Allowed ext => mime map.
     * @param int      $max_bytes
     * @return true|WP_Error
     */
    public static function validate_upload($file, array $mimes, $max_bytes)
    {
        if (!is_array($file) || !isset($file['tmp_name'], $file['name'], $file['error'], $file['size'])) {
            return new WP_Error('invalid_upload', __('Nenhum arquivo enviado.', 'sciflow-wp'));
        }
        if (is_array($file['tmp_name'])) {
            return new WP_Error('invalid_upload', __('Envie apenas um arquivo.', 'sciflow-wp'));
        }
        if (UPLOAD_ERR_OK !== (int) $file['error']) {
            return new WP_Error('upload_error', sprintf(
                /* translators: %d: PHP upload error code */
                __('Erro no upload do arquivo (código %d).', 'sciflow-wp'),
                (int) $file['error']
            ));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', __('Upload inválido.', 'sciflow-wp'));
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $max_bytes) {
            return new WP_Error('file_too_large', sprintf(
                /* translators: %s: size in MB */
                __('O arquivo excede o limite de %s MB.', 'sciflow-wp'),
                number_format_i18n($max_bytes / MB_IN_BYTES)
            ));
        }

        $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $mimes);
        if (empty($check['ext']) || empty($check['type'])) {
            return new WP_Error('invalid_file', __('Tipo de arquivo não permitido.', 'sciflow-wp'));
        }

        return true;
    }
}

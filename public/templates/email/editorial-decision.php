<?php
/**
 * Email template: Editorial Decision → Author.
 */
if (!defined('ABSPATH'))
    exit;
$color = '#2c5530';
if (!empty($decision_label)) {
    if (strpos($decision_label, 'Reprovado') !== false)
        $color = '#c0392b';
    elseif (strpos($decision_label, 'Devolvido') !== false)
        $color = '#e67e22';
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
</head>

<body style="margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f4f7fa;">
    <div
        style="max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
        <div
            style="background:linear-gradient(135deg,<?php echo $color; ?>,<?php echo $color; ?>cc);padding:30px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;">
                <?php echo esc_html($site_name); ?>
            </h1>
            <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;">
                <?php esc_html_e('Decisão Editorial', 'sciflow-wp'); ?>
            </p>
        </div>
        <div style="padding:30px;">
            <p style="color:#333;font-size:15px;line-height:1.6;">
                <?php echo wp_kses_post($message); ?>
            </p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr>
                    <td style="padding:8px 12px;color:#666;font-weight:600;width:100px;">
                        <?php esc_html_e('Título', 'sciflow-wp'); ?>
                    </td>
                    <td style="padding:8px 12px;color:#333;">
                        <?php echo esc_html($titulo); ?>
                    </td>
                </tr>
                <tr style="background:#f9f9f9;">
                    <td style="padding:8px 12px;color:#666;font-weight:600;">
                        <?php esc_html_e('Evento', 'sciflow-wp'); ?>
                    </td>
                    <td style="padding:8px 12px;color:#333;">
                        <?php echo esc_html($evento); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 12px;color:#666;font-weight:600;">
                        <?php esc_html_e('Decisão', 'sciflow-wp'); ?>
                    </td>
                    <td style="padding:8px 12px;color:#333;font-weight:700;">
                        <?php echo esc_html($decision_label ?? ''); ?>
                    </td>
                </tr>
            </table>
            <div style="text-align:center;margin:30px 0 10px;">
                <a href="<?php echo esc_url($link); ?>"
                    style="display:inline-block;padding:12px 30px;background:<?php echo $color; ?>;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                    <?php esc_html_e('Acessar Meus Trabalhos', 'sciflow-wp'); ?>
                </a>
            </div>
        </div>
    </div>
</body>

</html>
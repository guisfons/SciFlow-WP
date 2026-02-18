<?php
/**
 * Email template: Review Complete → Editor.
 */
if (!defined('ABSPATH'))
    exit;
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
</head>

<body style="margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f4f7fa;">
    <div
        style="max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
        <div style="background:linear-gradient(135deg,#5a3d8a,#8b5fbf);padding:30px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;">
                <?php echo esc_html($site_name); ?>
            </h1>
            <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;">
                <?php esc_html_e('Revisão Concluída', 'sciflow-wp'); ?>
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
            </table>
            <div style="text-align:center;margin:30px 0 10px;">
                <a href="<?php echo esc_url($link); ?>"
                    style="display:inline-block;padding:12px 30px;background:#5a3d8a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                    <?php esc_html_e('Acessar Painel Editorial', 'sciflow-wp'); ?>
                </a>
            </div>
        </div>
    </div>
</body>

</html>
<?php
/**
 * Email template: Approved + Poster request.
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
        <div style="background:linear-gradient(135deg,#27ae60,#2ecc71);padding:30px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:22px;">🎉
                <?php esc_html_e('Trabalho Aprovado!', 'sciflow-wp'); ?>
            </h1>
        </div>
        <div style="padding:30px;">
            <p style="color:#333;font-size:15px;line-height:1.6;">
                <?php echo wp_kses_post($message); ?>
            </p>
            <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                <tr>
                    <td style="padding:8px 12px;color:#666;font-weight:600;">
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
            <div style="background:#e8f5e9;border-radius:8px;padding:20px;margin:20px 0;">
                <p style="margin:0;color:#2e7d32;font-size:14px;">
                    <strong>
                        <?php esc_html_e('Próximo passo:', 'sciflow-wp'); ?>
                    </strong>
                    <?php esc_html_e('Envie seu pôster em formato PDF pela plataforma.', 'sciflow-wp'); ?>
                </p>
            </div>
            <div style="text-align:center;margin:20px 0 10px;">
                <a href="<?php echo esc_url($link); ?>"
                    style="display:inline-block;padding:12px 30px;background:#27ae60;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                    <?php esc_html_e('Enviar Pôster', 'sciflow-wp'); ?>
                </a>
            </div>
        </div>
    </div>
</body>

</html>
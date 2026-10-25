<?php
/**
 * Email template: Reviewer Assigned.
 */
if (!defined('ABSPATH'))
    exit;
$brand = SciFlow_Branding::email();
$header_text = $brand['contrast'];
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
</head>

<body style="margin:0;padding:0;font-family:<?php echo esc_attr($brand['font']); ?>;background:#f4f7fa;">
    <div
        style="max-width:600px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
        <?php if ($brand['logo_url']) : ?><div style="background:#ffffff;padding:18px 30px;text-align:center;border-bottom:1px solid #eeeeee;"><img src="<?php echo esc_url($brand['logo_url']); ?>" alt="<?php echo esc_attr($site_name); ?>" style="display:inline-block;max-height:56px;max-width:240px;"></div><?php endif; ?>
        <div style="background:<?php echo esc_attr($brand['primary']); ?>;padding:30px;text-align:center;">
            <h1 style="color:<?php echo esc_attr($header_text); ?>;margin:0;font-size:22px;">
                <?php echo esc_html($site_name); ?>
            </h1>
            <p style="color:<?php echo esc_attr($header_text); ?>;opacity:.85;margin:8px 0 0;font-size:14px;">
                <?php esc_html_e('Trabalho Atribuído Para Revisão', 'sciflow-wp'); ?>
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
                        <?php echo SciFlow_Status_Manager::render_title($titulo); ?>
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
                    style="display:inline-block;padding:12px 30px;background:<?php echo esc_attr($brand['primary']); ?>;color:<?php echo esc_attr($brand['contrast']); ?>;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                    <?php esc_html_e('Avaliar Trabalho', 'sciflow-wp'); ?>
                </a>
            </div>
        </div>
        <div style="background:#f4f7fa;padding:15px;text-align:center;font-size:12px;color:#999;">
            <?php echo esc_html($site_name); ?>
        </div>
    </div>
</body>

</html>
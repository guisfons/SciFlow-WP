<?php
/**
 * Email template: Editorial Decision → Author.
 */
if (!defined('ABSPATH'))
    exit;

$is_poster_decision = in_array($decision_label ?? '', array('Pôster Aprovado', 'Pôster Reprovado', 'Pôster Necessita Correção'), true);

$color = '#2c5530'; // default green
if (!empty($decision_label)) {
    if (strpos($decision_label, 'Reprovado') !== false)
        $color = '#c0392b';
    elseif (strpos($decision_label, 'Devolvido') !== false || strpos($decision_label, 'Correção') !== false || strpos($decision_label, 'Alterações') !== false)
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
                <?php echo $is_poster_decision ? esc_html__('Decisão sobre o Pôster', 'sciflow-wp') : esc_html__('Decisão Editorial', 'sciflow-wp'); ?>
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
                        <?php echo $is_poster_decision ? esc_html__('Decisão (Pôster)', 'sciflow-wp') : esc_html__('Decisão', 'sciflow-wp'); ?>
                    </td>
                    <td style="padding:8px 12px;color:#333;font-weight:700;">
                        <?php echo esc_html($decision_label ?? ''); ?>
                    </td>
                </tr>
            </table>
            <?php if (!empty($notes)): ?>
            <div style="background:#fff8e1;border-left:4px solid #f0ad4e;border-radius:4px;padding:15px 20px;margin:20px 0;">
                <strong style="display:block;margin-bottom:6px;color:#856404;font-size:14px;">
                    <?php esc_html_e('Observações do Comitê:', 'sciflow-wp'); ?>
                </strong>
                <p style="margin:0;color:#555;font-size:14px;line-height:1.6;">
                    <?php echo wp_kses_post($notes); ?>
                </p>
            </div>
            <?php endif; ?>
            <div style="text-align:center;margin:30px 0 10px;">
                <a href="<?php echo esc_url($link); ?>"
                    style="display:inline-block;padding:12px 30px;background:<?php echo $color; ?>;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                    <?php esc_html_e('Acessar Meu Painel', 'sciflow-wp'); ?>
                </a>
            </div>
        </div>
    </div>
</body>

</html>
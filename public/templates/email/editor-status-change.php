<?php
/**
 * Email template for notifying the editor of a status change.
 * Variables available:
 * - $titulo: Article title
 * - $evento: Event name
 * - $status: New status label
 * - $old_status_label: Old status label
 * - $link: Direct link to the dashboard or article
 * - $site_name: Site name
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;">
    <h2 style="color:#2c5530;"><?php echo esc_html($site_name); ?></h2>
    <p>Olá Editor,</p>
    <p>O trabalho abaixo teve seu status alterado no sistema:</p>
    
    <div style="background-color:#f9f9f9;border-left:4px solid #2c5530;padding:15px;margin:20px 0;">
        <p style="margin:0 0 10px 0;"><strong>Trabalho:</strong> <br><?php echo esc_html($titulo); ?></p>
        <p style="margin:0 0 10px 0;"><strong>Evento:</strong> <br><?php echo esc_html($evento); ?></p>
        
        <?php if (!empty($old_status_label)): ?>
            <p style="margin:0 0 5px 0;"><strong>Status Anterior:</strong> <br><span style="color:#666;"><?php echo esc_html($old_status_label); ?></span></p>
        <?php endif; ?>
        
        <p style="margin:0;"><strong>Novo Status:</strong> <br><span style="color:#2c5530;font-weight:bold;"><?php echo esc_html($status); ?></span></p>
    </div>

    <?php if (!empty($message)): ?>
        <p><?php echo wp_kses_post($message); ?></p>
    <?php endif; ?>

    <p style="margin-top:30px;">
        <a href="<?php echo esc_url($link); ?>" style="display:inline-block;padding:12px 24px;background-color:#2c5530;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;">Acessar o Trabalho</a>
    </p>
    
    <p style="margin-top:40px;font-size:12px;color:#999;border-top:1px solid #ddd;padding-top:10px;">
        Este é um e-mail automático do sistema. Por favor, não responda.
    </p>
</div>

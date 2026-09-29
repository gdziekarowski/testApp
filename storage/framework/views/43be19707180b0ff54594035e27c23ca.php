<?php $__env->startSection('title', 'Instalacja'); ?>

<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('panel.nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <h2 style="font-size: 18px; margin-bottom: 16px; color: #111827;">Dane instalacji</h2>

    <table id="installation-details" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
        <tbody>
            <tr style="border-bottom: 1px solid #e5e7eb;"><th style="padding: 8px;">Client ID</th><td style="padding: 8px;"><?php echo e($license->client_id); ?></td></tr>
            <tr style="border-bottom: 1px solid #e5e7eb;"><th style="padding: 8px;">Application ID</th><td style="padding: 8px;"><?php echo e($license->application_id); ?></td></tr>
            <tr style="border-bottom: 1px solid #e5e7eb;"><th style="padding: 8px;">Panel</th><td style="padding: 8px;"><?php echo e($license->domain() ?? '—'); ?></td></tr>
            <tr style="border-bottom: 1px solid #e5e7eb;"><th style="padding: 8px;">Autoryzacja Admin API</th><td style="padding: 8px;"><?php echo e($license->authorization_type); ?></td></tr>
            <tr style="border-bottom: 1px solid #e5e7eb;"><th style="padding: 8px;">Licencja aktywna</th><td style="padding: 8px;"><?php echo e($license->active ? 'tak' : 'nie'); ?></td></tr>
            <tr style="border-bottom: 1px solid #e5e7eb;"><th style="padding: 8px;">Instalacja potwierdzona</th><td style="padding: 8px;"><?php echo e($license->installation_confirmed ? 'tak' : 'nie'); ?></td></tr>
            <tr style="border-bottom: 1px solid #e5e7eb;"><th style="padding: 8px;">Sklepy z instalacji</th><td style="padding: 8px;"><?php echo e(collect($shops)->map(fn (array $shop): string => $shop['id'].' '.$shop['name'])->implode(', ') ?: '—'); ?></td></tr>
            <tr><th style="padding: 8px;">Ważność linków</th><td style="padding: 8px;"><?php echo e($linkTtl); ?> min</td></tr>
        </tbody>
    </table>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/testowyprojekt/resources/views/panel/installation.blade.php ENDPATH**/ ?>
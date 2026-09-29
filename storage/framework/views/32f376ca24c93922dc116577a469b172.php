<?php $__env->startSection('title', 'Lista Sklepów'); ?>

<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('panel.nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; margin-bottom: 24px; border-radius: 4px;">
        <p style="margin: 0; color: #1e40af; font-size: 14px;">Uruchomiono dla klienta ID: <strong><?php echo e($clientId); ?></strong></p>
    </div>

    
    <form method="POST" action="<?php echo e(idosell_route('app.shops.fetch')); ?>" style="margin-bottom: 24px;">
        <button id="btn-fetch-shops" type="submit" style="background-color: #2563eb; color: #ffffff; border: none; padding: 10px 20px; font-size: 15px; font-weight: 600; border-radius: 6px; cursor: pointer;">
            Pokaż sklepy
        </button>
    </form>

    <?php if($error): ?>
        <div id="error-message" style="background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <strong>Wystąpił błąd:</strong> <?php echo e($error); ?>

        </div>
    <?php endif; ?>

    <?php if($shops !== null): ?>
        <div id="shops-list-container">
            <h2 style="font-size: 18px; margin-bottom: 16px; color: #111827;">Lista sklepów z panelu:</h2>
            <?php if(count($shops) > 0): ?>
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 12px; font-size: 13px; color: #6b7280; text-transform: uppercase;">ID</th>
                            <th style="padding: 12px; font-size: 13px; color: #6b7280; text-transform: uppercase;">Nazwa Sklepu</th>
                            <th style="padding: 12px; font-size: 13px; color: #6b7280; text-transform: uppercase;">Adres / Domena</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $shops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                <td style="padding: 12px; font-weight: 600;"><?php echo e($shop['id'] ?? '—'); ?></td>
                                <td style="padding: 12px;"><?php echo e($shop['name'] ?? '—'); ?></td>
                                <td style="padding: 12px; color: #4b5563;"><?php echo e($shop['domain'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #6b7280; font-style: italic;">Brak danych o sklepach do wyświetlenia.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /app/testowyprojekt/resources/views/welcome.blade.php ENDPATH**/ ?>
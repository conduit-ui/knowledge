<?php $__env->startSection('title', $entry->title . ' - Knowledge Base'); ?>

<?php $__env->startSection('content'); ?>
    <div class="content">
        <h1 style="margin-bottom: 20px;"><?php echo e($entry->title); ?></h1>

        <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #ecf0f1;">
            <?php if($entry->category): ?>
                <span class="badge badge-primary"><?php echo e($entry->category); ?></span>
            <?php endif; ?>
            <?php if($entry->module): ?>
                <span class="badge badge-secondary"><?php echo e($entry->module); ?></span>
            <?php endif; ?>
            <span class="badge badge-secondary"><?php echo e($entry->priority); ?></span>
            <span class="badge badge-success"><?php echo e($entry->confidence); ?>%</span>
            <span class="badge badge-secondary"><?php echo e($entry->status); ?></span>
        </div>

        <?php if($entry->tags && count($entry->tags) > 0): ?>
            <div style="margin-bottom: 20px;">
                <strong>Tags:</strong>
                <?php $__currentLoopData = $entry->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="badge badge-warning"><?php echo e($tag); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>

        <div style="margin: 30px 0; line-height: 1.8; white-space: pre-wrap;"><?php echo e($entry->content); ?></div>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ecf0f1; color: #7f8c8d; font-size: 0.9em;">
            <h3 style="margin-bottom: 15px; color: #2c3e50;">Metadata</h3>

            <?php if($entry->source): ?>
                <p><strong>Source:</strong> <?php echo e($entry->source); ?></p>
            <?php endif; ?>

            <?php if($entry->ticket): ?>
                <p><strong>Ticket:</strong> <?php echo e($entry->ticket); ?></p>
            <?php endif; ?>

            <?php if($entry->author): ?>
                <p><strong>Author:</strong> <?php echo e($entry->author); ?></p>
            <?php endif; ?>

            <?php if($entry->files && count($entry->files) > 0): ?>
                <p><strong>Files:</strong></p>
                <ul style="margin-left: 20px; margin-top: 5px;">
                    <?php $__currentLoopData = $entry->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($file); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            <?php endif; ?>

            <?php if($entry->repo): ?>
                <p><strong>Repository:</strong> <?php echo e($entry->repo); ?></p>
            <?php endif; ?>

            <?php if($entry->branch): ?>
                <p><strong>Branch:</strong> <?php echo e($entry->branch); ?></p>
            <?php endif; ?>

            <?php if($entry->commit): ?>
                <p><strong>Commit:</strong> <?php echo e($entry->commit); ?></p>
            <?php endif; ?>

            <p><strong>Usage Count:</strong> <?php echo e($entry->usage_count); ?></p>

            <?php if($entry->last_used): ?>
                <p><strong>Last Used:</strong> <?php echo e($entry->last_used->format('Y-m-d H:i:s')); ?></p>
            <?php endif; ?>

            <p><strong>Created:</strong> <?php echo e($entry->created_at->format('Y-m-d H:i:s')); ?></p>
            <p><strong>Updated:</strong> <?php echo e($entry->updated_at->format('Y-m-d H:i:s')); ?></p>
        </div>

        <div style="margin-top: 30px;">
            <a href="index.html" style="color: #3498db; text-decoration: none;">&larr; Back to all entries</a>
        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('site.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /private/tmp/knowledge-export-1765914823/resources/views/site/entry.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', 'Categories - Knowledge Base'); ?>

<?php $__env->startSection('content'); ?>
    <div class="content">
        <h2 style="margin-bottom: 20px;">Categories</h2>

        <?php if(count($categories) === 0): ?>
            <p style="color: #7f8c8d;">No categories found.</p>
        <?php else: ?>
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #ecf0f1;">
                    <h3 style="margin-bottom: 10px;"><?php echo e($category->name ?? 'Uncategorized'); ?></h3>
                    <p style="color: #7f8c8d; margin-bottom: 10px;"><?php echo e($category->count); ?> entries</p>

                    <ul style="margin-left: 20px;">
                        <?php $__currentLoopData = $category->entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li style="margin-bottom: 8px;">
                                <a href="entry-<?php echo e($entry->id); ?>.html" style="color: #2c3e50; text-decoration: none;">
                                    <?php echo e($entry->title); ?>

                                </a>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('site.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /private/tmp/knowledge-export-1765914823/resources/views/site/categories.blade.php ENDPATH**/ ?>
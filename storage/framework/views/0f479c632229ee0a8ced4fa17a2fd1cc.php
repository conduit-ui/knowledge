<?php $__env->startSection('title', 'Tags - Knowledge Base'); ?>

<?php $__env->startSection('content'); ?>
    <div class="content">
        <h2 style="margin-bottom: 20px;">Tags</h2>

        <?php if(count($tags) === 0): ?>
            <p style="color: #7f8c8d;">No tags found.</p>
        <?php else: ?>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 30px;">
                <?php $__currentLoopData = $tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="#tag-<?php echo e($tag->slug); ?>" style="text-decoration: none;">
                        <span class="badge badge-warning" style="font-size: 1em; cursor: pointer;">
                            <?php echo e($tag->name); ?> (<?php echo e($tag->count); ?>)
                        </span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <?php $__currentLoopData = $tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div id="tag-<?php echo e($tag->slug); ?>" style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #ecf0f1;">
                    <h3 style="margin-bottom: 10px;"><?php echo e($tag->name); ?></h3>
                    <p style="color: #7f8c8d; margin-bottom: 10px;"><?php echo e($tag->count); ?> entries</p>

                    <ul style="margin-left: 20px;">
                        <?php $__currentLoopData = $tag->entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
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

<?php echo $__env->make('site.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/jordanpartridge/packages/conduit-ui/knowledge/resources/views/site/tags.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', 'Knowledge Base - Home'); ?>

<?php $__env->startSection('content'); ?>
    <div class="search-box">
        <input type="text" id="search" placeholder="Search knowledge entries..." onkeyup="filterEntries()">
    </div>

    <div class="content">
        <h2 style="margin-bottom: 20px;">Knowledge Entries</h2>

        <?php if(count($entries) === 0): ?>
            <p style="color: #7f8c8d;">No entries found.</p>
        <?php else: ?>
            <div id="entries-list">
                <?php $__currentLoopData = $entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="entry-item" data-title="<?php echo e(strtolower($entry->title)); ?>" data-content="<?php echo e(strtolower($entry->content)); ?>" data-category="<?php echo e(strtolower($entry->category ?? '')); ?>" data-tags="<?php echo e(strtolower(implode(' ', $entry->tags ?? []))); ?>">
                        <h3 style="margin-bottom: 10px;">
                            <a href="entry-<?php echo e($entry->id); ?>.html" style="color: #2c3e50; text-decoration: none;">
                                <?php echo e($entry->title); ?>

                            </a>
                        </h3>

                        <div style="margin-bottom: 10px;">
                            <?php if($entry->category): ?>
                                <span class="badge badge-primary"><?php echo e($entry->category); ?></span>
                            <?php endif; ?>
                            <span class="badge badge-secondary"><?php echo e($entry->priority); ?></span>
                            <span class="badge badge-success"><?php echo e($entry->confidence); ?>%</span>
                        </div>

                        <?php if($entry->tags && count($entry->tags) > 0): ?>
                            <div style="margin-bottom: 10px;">
                                <?php $__currentLoopData = $entry->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="badge badge-warning"><?php echo e($tag); ?></span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php endif; ?>

                        <p style="color: #7f8c8d; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ecf0f1;">
                            <?php echo e(\Illuminate\Support\Str::limit($entry->content, 200)); ?>

                        </p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
function filterEntries() {
    const searchTerm = document.getElementById('search').value.toLowerCase();
    const entries = document.querySelectorAll('.entry-item');

    entries.forEach(entry => {
        const title = entry.getAttribute('data-title');
        const content = entry.getAttribute('data-content');
        const category = entry.getAttribute('data-category');
        const tags = entry.getAttribute('data-tags');

        const matches = title.includes(searchTerm) ||
                       content.includes(searchTerm) ||
                       category.includes(searchTerm) ||
                       tags.includes(searchTerm);

        entry.style.display = matches ? 'block' : 'none';
    });
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('site.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /private/tmp/knowledge-export-1765914823/resources/views/site/index.blade.php ENDPATH**/ ?>
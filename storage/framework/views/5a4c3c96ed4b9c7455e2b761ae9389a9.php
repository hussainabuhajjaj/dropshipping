<?php
    use Illuminate\Support\Facades\Storage;

    $title = $data['title'] ?? 'Your headline here';
    $description = $data['description'] ?? 'Add a compelling subtitle to boost clicks.';
    $badgeText = $data['badge_text'] ?? null;
    $badgeColor = $data['badge_color'] ?? 'primary';
    $ctaText = $data['cta_text'] ?? 'Shop now';
    $background = $data['background_color'] ?? '#ffffff';
    $textColor = $data['text_color'] ?? '#0f172a';
    $displayType = $data['display_type'] ?? 'hero';
    $imagePath = $data['image_path'] ?? null;
    $imageUrl = $imagePath ? Storage::url($imagePath) : null;

    $badgePalette = [
        'primary' => '#2563eb',
        'danger' => '#dc2626',
        'warning' => '#f59e0b',
        'success' => '#16a34a',
        'default' => '#334155',
    ];
    $badgeBg = $badgePalette[$badgeColor] ?? $badgePalette['default'];
?>

<div class="fi-section rounded-xl border border-slate-200 bg-white p-4">
    <div class="flex items-center justify-between text-xs text-slate-500">
        <span>Storefront Banner Preview</span>
        <span class="uppercase tracking-[0.18em]"><?php echo e(strtoupper($displayType)); ?></span>
    </div>

    <div
        class="mt-3 overflow-hidden rounded-lg border border-slate-200"
        style="background: <?php echo e($background); ?>; color: <?php echo e($textColor); ?>;"
    >
        <div class="grid gap-4 p-5 md:grid-cols-[1.2fr,0.8fr]">
            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($badgeText): ?>
                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-white"
                        style="background: <?php echo e($badgeBg); ?>;"
                    >
                        <?php echo e($badgeText); ?>

                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <h3 class="text-xl font-semibold leading-tight md:text-2xl" style="color: <?php echo e($textColor); ?>;">
                    <?php echo e($title); ?>

                </h3>
                <p class="text-sm leading-relaxed" style="color: <?php echo e($textColor); ?>; opacity: 0.85;">
                    <?php echo e($description); ?>

                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="#"
                        class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700"
                    >
                        <?php echo e($ctaText); ?>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="ml-2 h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12l-3.75 3.75M3 12h18" />
                        </svg>
                    </a>
                    <span class="text-xs text-slate-500">CTA link will use your target/URL.</span>
                </div>
            </div>

            <div class="relative min-h-[180px] overflow-hidden rounded-lg bg-slate-100">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($imageUrl): ?>
                    <img src="<?php echo e($imageUrl); ?>" alt="Banner image" class="h-full w-full object-cover" />
                <?php else: ?>
                    <div class="flex h-full w-full items-center justify-center text-sm text-slate-400">
                        Upload an image to preview
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php /**PATH E:\laragon\www\dropshipping\resources\views/filament/storefront-banners/preview.blade.php ENDPATH**/ ?>
<?php
declare(strict_types=1);

session_start();

$page_title = 'About | Party4U';
$active_page = 'about';

$members = [
    ['name' => 'Jeiven', 'role' => 'Project Leader', 'image' => 'jeiven'],
    ['name' => 'Faith', 'role' => 'Project Member', 'image' => 'faith'],
    ['name' => 'Leanne', 'role' => 'Project Member', 'image' => 'leanne'],
    ['name' => 'Yana', 'role' => 'Project Member', 'image' => 'yana'],
];

function memberImagePath(string $name): ?string
{
    $extensions = ['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG', 'WEBP', 'svg'];
    $directory = __DIR__ . '/assets/member_pic';
    $files = scandir($directory);

    if ($files === false) {
        return null;
    }

    foreach ($extensions as $extension) {
        $filename = "{$name}.{$extension}";

        if (in_array($filename, $files, true) && is_file($directory . '/' . $filename)) {
            return 'assets/member_pic/' . $filename;
        }
    }

    return null;
}

require __DIR__ . '/includes/faith_header.php';
?>
<main>
    <section class="cta-band">
        <div class="container py-5">
            <div class="row align-items-center g-4 py-lg-4">
                <div class="col-lg-8">
                    <span class="badge text-bg-light mb-3">About Party4U</span>
                    <h1 class="display-5 fw-bold">A simpler way to rent your next car.</h1>
                    <p class="lead mb-0">
                        Party4U helps customers browse available vehicles, choose
                        rental dates, and reserve a car through one clear online flow.
                    </p>
                </div>
                <div class="col-lg-auto ms-lg-auto">
                    <a class="btn btn-primary btn-lg" href="leanne_store.php">
                        Browse Available Cars
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-title mb-4">
            <h2 class="h1 fw-bold">Why choose Party4U?</h2>
            <p class="text-secondary mb-0">
                Our service is designed to make local car rental clear and convenient.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body p-4">
                        <div class="feature-icon mb-3">01</div>
                        <h3 class="h5">Up-to-date availability</h3>
                        <p class="text-secondary mb-0">
                            Browse vehicles currently listed as available by our fleet team.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body p-4">
                        <div class="feature-icon mb-3">02</div>
                        <h3 class="h5">Clear rental pricing</h3>
                        <p class="text-secondary mb-0">
                            Review the daily rate, rental period, and full total before paying.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card h-100">
                    <div class="card-body p-4">
                        <div class="feature-icon mb-3">03</div>
                        <h3 class="h5">Easy reservation flow</h3>
                        <p class="text-secondary mb-0">
                            Move from browsing to checkout and payment in a few simple steps.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white border-top border-bottom">
        <div class="container py-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <h2 class="h1 fw-bold">How renting works</h2>
                    <p class="text-secondary mb-0">
                        Pick the car that fits your plans and confirm your reservation online.
                    </p>
                </div>
                <div class="col-lg-7">
                    <div class="vstack gap-4">
                        <div class="d-flex gap-3">
                            <span class="step-number">1</span>
                            <div><h3 class="h5 mb-1">Choose a car</h3><p class="text-secondary mb-0">Browse the fleet and open a vehicle to see its details.</p></div>
                        </div>
                        <div class="d-flex gap-3">
                            <span class="step-number">2</span>
                            <div><h3 class="h5 mb-1">Select your dates</h3><p class="text-secondary mb-0">Choose pickup and return dates, then review your rental cart.</p></div>
                        </div>
                        <div class="d-flex gap-3">
                            <span class="step-number">3</span>
                            <div><h3 class="h5 mb-1">Checkout and reserve</h3><p class="text-secondary mb-0">Confirm your details, select a payment method, and finish the reservation.</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="section-title mb-4">
            <h2 class="h1 fw-bold">Meet the members</h2>
            <p class="text-secondary mb-0">
                The people behind the Party4U car rental system.
            </p>
        </div>

        <div class="row g-4">
            <?php foreach ($members as $member): ?>
                <?php $image_path = memberImagePath($member['image']); ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="card member-card h-100">
                        <?php if ($image_path !== null): ?>
                            <img
                                src="<?php echo e($image_path); ?>"
                                class="member-photo"
                                alt="<?php echo e($member['name']); ?> profile photo"
                            >
                        <?php else: ?>
                            <div class="member-photo member-photo-placeholder">
                                <?php echo e(substr($member['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card-body p-4 text-center">
                            <h3 class="h5 mb-1"><?php echo e($member['name']); ?></h3>
                            <p class="text-secondary mb-0"><?php echo e($member['role']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4">
            <div class="col-lg-7">
                <h2 class="h1 fw-bold">Contact Party4U</h2>
                <p class="text-secondary">
                    Need help choosing a vehicle or planning your rental? Contact our team.
                </p>
                <a class="btn btn-primary" href="leanne_store.php">Start Browsing</a>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="h5">Business information</h3>
                        <p class="mb-2"><strong>Address:</strong> Philippines</p>
                        <p class="mb-2"><strong>Phone:</strong> +63 09989720113</p>
                        <p class="mb-2"><strong>Email:</strong> info@party4u.com</p>
                        <p class="mb-0"><strong>Hours:</strong> Monday-Saturday, 8:00 AM-6:00 PM</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/faith_footer.php'; ?>

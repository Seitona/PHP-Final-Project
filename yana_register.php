<?php
session_start();

// Leanne: gamitin mo nalang yung shared $conn connection dito sa leanne_db.php.
require_once __DIR__ . '/includes/leanne_db.php';
require_once __DIR__ . '/includes/yana_mailer.php';
require_once __DIR__ . '/includes/yana_user_fields.php';

yana_ensure_user_contact_fields($conn);

$full_name = '';
$email = '';
$complete_address = '';
$contact_numbers = '';
$street_address = '';
$region_code = '';
$region_name = '';
$province_code = '';
$province_name = '';
$city_code = '';
$city_name = '';
$barangay_code = '';
$barangay_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $street_address = trim($_POST['street_address'] ?? '');
    $region_code = trim($_POST['region_code'] ?? '');
    $region_name = trim($_POST['region_name'] ?? '');
    $province_code = trim($_POST['province_code'] ?? '');
    $province_name = trim($_POST['province_name'] ?? '');
    $city_code = trim($_POST['city_code'] ?? '');
    $city_name = trim($_POST['city_name'] ?? '');
    $barangay_code = trim($_POST['barangay_code'] ?? '');
    $barangay_name = trim($_POST['barangay_name'] ?? '');
    $contact_numbers = trim($_POST['contact_numbers'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address_parts = array_filter([$street_address, $barangay_name, $city_name, $province_name, $region_name]);
    $complete_address = implode(', ', $address_parts);

    if ($full_name === '' || $email === '' || $street_address === '' || $region_code === '' || $region_name === '' || $city_code === '' || $city_name === '' || $barangay_code === '' || $barangay_name === '' || $contact_numbers === '' || $password === '' || $confirm_password === '') {
        $_SESSION['message'] = 'Please complete all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9+(),.\-\s]{7,120}$/', $contact_numbers)) {
        $_SESSION['message'] = 'Please enter a valid contact number.';
    } elseif (strlen($password) < 8) {
        $_SESSION['message'] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $_SESSION['message'] = 'Passwords do not match.';
    } else {
        $check_sql = 'SELECT user_id FROM users WHERE email = ? LIMIT 1';
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, 's', $email);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['message'] = 'This email is already registered.';
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_code = bin2hex(random_bytes(32));
            $role = 'customer';

            $sql = 'INSERT INTO users (full_name, email, complete_address, contact_numbers, password, role, is_verified, verification_code) VALUES (?, ?, ?, ?, ?, ?, 0, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssss', $full_name, $email, $complete_address, $contact_numbers, $hashed_password, $role, $verification_code);

            if (mysqli_stmt_execute($stmt)) {
                $verify_link = yana_site_url('yana_verify_email.php?code=' . urlencode($verification_code));
                $email_result = yana_send_verification_email($email, $full_name, $verify_link);
                $mail_config = yana_mail_config();

                if ($email_result['sent']) {
                    $_SESSION['message'] = 'Registration successful. Please check your email to verify your account before logging in.';
                    header('Location: yana_login.php');
                } else {
                    $_SESSION['message'] = 'Registration successful, but we could not send the verification email. ' . $email_result['error'];

                    if (!empty($mail_config['show_verification_link_on_failure'])) {
                        $_SESSION['verify_link'] = $verify_link;
                    }

                    header('Location: yana_register.php');
                }

                mysqli_stmt_close($stmt);
                exit;
            }

            $_SESSION['message'] = 'Registration failed. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

$message = $_SESSION['message'] ?? '';
$verify_link = $_SESSION['verify_link'] ?? '';
unset($_SESSION['message'], $_SESSION['verify_link']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Party4U</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="mb-3">
                    <a class="text-decoration-none" href="index.php">
                        ← Back to Party4U
                    </a>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h1 class="h3 text-center mb-4">Create Account</h1>
                        <?php if ($message !== ''): ?>
                            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($verify_link !== ''): ?>
                            <div class="alert alert-warning">For testing only: <a href="<?php echo htmlspecialchars($verify_link); ?>">Verify email now</a></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="street_address" class="form-label">House No., Street, Building</label>
                                <input type="text" class="form-control" id="street_address" name="street_address" value="<?php echo htmlspecialchars($street_address); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="region_code" class="form-label">Region</label>
                                <select class="form-select location-select" id="region_code" name="region_code" data-selected="<?php echo htmlspecialchars($region_code); ?>" required>
                                    <option value="">Select region</option>
                                </select>
                                <input type="hidden" id="region_name" name="region_name" value="<?php echo htmlspecialchars($region_name); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="province_code" class="form-label">Province</label>
                                <select class="form-select location-select" id="province_code" name="province_code" data-selected="<?php echo htmlspecialchars($province_code); ?>" disabled>
                                    <option value="">Select province</option>
                                </select>
                                <input type="hidden" id="province_name" name="province_name" value="<?php echo htmlspecialchars($province_name); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="city_code" class="form-label">City / Municipality</label>
                                <select class="form-select location-select" id="city_code" name="city_code" data-selected="<?php echo htmlspecialchars($city_code); ?>" required disabled>
                                    <option value="">Select city / municipality</option>
                                </select>
                                <input type="hidden" id="city_name" name="city_name" value="<?php echo htmlspecialchars($city_name); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="barangay_code" class="form-label">Barangay</label>
                                <select class="form-select location-select" id="barangay_code" name="barangay_code" data-selected="<?php echo htmlspecialchars($barangay_code); ?>" required disabled>
                                    <option value="">Select barangay</option>
                                </select>
                                <input type="hidden" id="barangay_name" name="barangay_name" value="<?php echo htmlspecialchars($barangay_name); ?>">
                                <input type="hidden" id="complete_address" name="complete_address" value="<?php echo htmlspecialchars($complete_address); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="contact_numbers" class="form-label">Contact Number(s)</label>
                                <input type="text" class="form-control" id="contact_numbers" name="contact_numbers" value="<?php echo htmlspecialchars($contact_numbers); ?>" maxlength="120" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Register</button>
                        </form>
                        <p class="text-center mt-3 mb-0">Already registered? <a href="yana_login.php">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>
        const locationApiUrl = 'yana_locations_api.php';
        const regionSelect = document.getElementById('region_code');
        const provinceSelect = document.getElementById('province_code');
        const citySelect = document.getElementById('city_code');
        const barangaySelect = document.getElementById('barangay_code');
        const streetInput = document.getElementById('street_address');
        const form = document.querySelector('form');
        const locationMessage = document.createElement('div');

        locationMessage.className = 'alert alert-warning d-none';
        regionSelect.closest('.mb-3').before(locationMessage);

        function setMessage(message) {
            locationMessage.textContent = message;
            locationMessage.classList.toggle('d-none', message === '');
        }

        function resetSelect(select, placeholder, disabled = true) {
            select.innerHTML = `<option value="">${placeholder}</option>`;
            select.disabled = disabled;
            const hidden = document.getElementById(select.name.replace('_code', '_name'));
            if (hidden) hidden.value = '';
        }

        function fillSelect(select, locations, placeholder) {
            resetSelect(select, placeholder, false);
            locations.forEach((location) => {
                const option = document.createElement('option');
                option.value = location.code;
                option.textContent = location.type ? `${location.name} (${location.type})` : location.name;
                option.dataset.name = location.name;
                select.appendChild(option);
            });

            if (select.dataset.selected) {
                select.value = select.dataset.selected;
                select.dataset.selected = '';
            }

            updateHiddenName(select);
        }

        async function loadLocations(params) {
            const url = new URL(locationApiUrl, window.location.href);
            Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
            const response = await fetch(url);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Unable to load locations.');
            }

            return data.locations || [];
        }

        function updateHiddenName(select) {
            const hidden = document.getElementById(select.name.replace('_code', '_name'));
            if (!hidden) return;

            const option = select.selectedOptions[0];
            hidden.value = option && option.value ? (option.dataset.name || option.textContent) : '';
            buildCompleteAddress();
        }

        function buildCompleteAddress() {
            const parts = [
                streetInput.value.trim(),
                document.getElementById('barangay_name').value,
                document.getElementById('city_name').value,
                document.getElementById('province_name').value,
                document.getElementById('region_name').value,
            ].filter(Boolean);

            document.getElementById('complete_address').value = parts.join(', ');
        }

        async function loadRegions() {
            try {
                setMessage('');
                fillSelect(regionSelect, await loadLocations({ action: 'regions' }), 'Select region');
                if (regionSelect.value) await loadProvinces();
            } catch (error) {
                setMessage(error.message);
            }
        }

        async function loadProvinces() {
            resetSelect(provinceSelect, 'Loading provinces...');
            resetSelect(citySelect, 'Select city / municipality');
            resetSelect(barangaySelect, 'Select barangay');
            updateHiddenName(regionSelect);

            if (!regionSelect.value) {
                resetSelect(provinceSelect, 'Select province');
                return;
            }

            try {
                setMessage('');
                const provinces = await loadLocations({ action: 'provinces', region: regionSelect.value });
                fillSelect(provinceSelect, provinces, provinces.length ? 'Select province' : 'No province for this region');

                if (provinces.length === 0) {
                    provinceSelect.disabled = true;
                    await loadCities();
                } else if (provinceSelect.value) {
                    await loadCities();
                }
            } catch (error) {
                resetSelect(provinceSelect, 'Select province');
                setMessage(error.message);
            }
        }

        async function loadCities() {
            resetSelect(citySelect, 'Loading cities / municipalities...');
            resetSelect(barangaySelect, 'Select barangay');
            updateHiddenName(provinceSelect);

            const params = { action: 'cities' };
            if (provinceSelect.value) {
                params.province = provinceSelect.value;
            } else if (regionSelect.value) {
                params.region = regionSelect.value;
            } else {
                resetSelect(citySelect, 'Select city / municipality');
                return;
            }

            try {
                setMessage('');
                fillSelect(citySelect, await loadLocations(params), 'Select city / municipality');
                if (citySelect.value) await loadBarangays();
            } catch (error) {
                resetSelect(citySelect, 'Select city / municipality');
                setMessage(error.message);
            }
        }

        async function loadBarangays() {
            resetSelect(barangaySelect, 'Loading barangays...');
            updateHiddenName(citySelect);

            if (!citySelect.value) {
                resetSelect(barangaySelect, 'Select barangay');
                return;
            }

            try {
                setMessage('');
                fillSelect(barangaySelect, await loadLocations({ action: 'barangays', city: citySelect.value }), 'Select barangay');
            } catch (error) {
                resetSelect(barangaySelect, 'Select barangay');
                setMessage(error.message);
            }
        }

        regionSelect.addEventListener('change', loadProvinces);
        provinceSelect.addEventListener('change', loadCities);
        citySelect.addEventListener('change', loadBarangays);
        barangaySelect.addEventListener('change', () => updateHiddenName(barangaySelect));
        streetInput.addEventListener('input', buildCompleteAddress);
        form.addEventListener('submit', buildCompleteAddress);
        loadRegions();
    </script>
</body>
</html>

CREATE DATABASE IF NOT EXISTS car_rental_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE car_rental_management;

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_code VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_verification_code (verification_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand VARCHAR(80) NOT NULL,
    model VARCHAR(80) NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    category ENUM(
        'Sedan',
        'SUV',
        'Hatchback',
        'Pickup',
        'Van',
        'Coupe',
        'Convertible'
    ) NOT NULL,
    transmission ENUM('Automatic', 'Manual') NOT NULL,
    fuel_type ENUM('Gasoline', 'Diesel', 'Hybrid', 'Electric') NOT NULL,
    seating_capacity TINYINT UNSIGNED NOT NULL,
    plate_number VARCHAR(30) NOT NULL,
    color VARCHAR(50) NOT NULL,
    daily_rate DECIMAL(10, 2) NOT NULL,
    description TEXT DEFAULT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    availability_status ENUM(
        'Available',
        'Reserved',
        'Rented',
        'Under Maintenance'
    ) NOT NULL DEFAULT 'Available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicles_plate_number (plate_number),
    KEY idx_vehicles_status (availability_status),
    KEY idx_vehicles_category (category),
    KEY idx_vehicles_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    rental_days INT UNSIGNED NOT NULL DEFAULT 1,
    daily_rate DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM(
        'Pending',
        'Reserved',
        'Rented',
        'Completed',
        'Cancelled'
    ) NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bookings_user_id (user_id),
    KEY idx_bookings_vehicle_id (vehicle_id),
    KEY idx_bookings_status (status),
    KEY idx_bookings_created_at (created_at),
    KEY idx_bookings_end_date (end_date),
    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_bookings_vehicle
        FOREIGN KEY (vehicle_id)
        REFERENCES vehicles (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details VARCHAR(1000) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_logs_user_id (user_id),
    KEY idx_audit_logs_action (action),
    KEY idx_audit_logs_created_at (created_at),
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(60) NOT NULL DEFAULT 'Cash',
    payment_status ENUM('Pending', 'Paid', 'Failed', 'Refunded')
        NOT NULL DEFAULT 'Pending',
    reference_number VARCHAR(120) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_reference_number (reference_number),
    KEY idx_payments_booking_id (booking_id),
    KEY idx_payments_status (payment_status),
    CONSTRAINT fk_payments_booking
        FOREIGN KEY (booking_id)
        REFERENCES bookings (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (
    user_id,
    full_name,
    email,
    password,
    role,
    is_verified,
    verification_code
) VALUES
(
    1,
    'System Admin',
    'admin@carrental.test',
    '$2y$10$hzoXKOv3XIHOiKoP7IBTSOES39hhdAmp7dYiBBnN6cHBqRhYAfJZe',
    'admin',
    1,
    NULL
),
(
    2,
    'Demo Customer',
    'customer@carrental.test',
    '$2y$10$hzoXKOv3XIHOiKoP7IBTSOES39hhdAmp7dYiBBnN6cHBqRhYAfJZe',
    'customer',
    1,
    NULL
);

INSERT IGNORE INTO vehicles (
    id,
    brand,
    model,
    year,
    category,
    transmission,
    fuel_type,
    seating_capacity,
    plate_number,
    color,
    daily_rate,
    description,
    image_path,
    availability_status,
    created_at,
    updated_at
) VALUES
(
    1,
    'Toyota',
    'Vios',
    2023,
    'Sedan',
    'Automatic',
    'Gasoline',
    5,
    'ABC-1234',
    'Silver',
    1800.00,
    'A reliable compact sedan for city drives and short trips.',
    'assets/cars/toyota-vios.jpg',
    'Available',
    DATE_SUB(NOW(), INTERVAL 8 DAY),
    NOW()
),
(
    2,
    'Mitsubishi',
    'Xpander',
    2022,
    'Van',
    'Automatic',
    'Gasoline',
    7,
    'MPV-2022',
    'White',
    2800.00,
    'A spacious seven-seater for family errands and weekend getaways.',
    'assets/cars/mitsubishi-xpander.jpg',
    'Available',
    DATE_SUB(NOW(), INTERVAL 7 DAY),
    NOW()
),
(
    3,
    'Ford',
    'Ranger',
    2021,
    'Pickup',
    'Manual',
    'Diesel',
    5,
    'TRK-7788',
    'Blue',
    3500.00,
    'A capable pickup for cargo, long routes, and tougher road conditions.',
    'assets/cars/ford-ranger.jpg',
    'Rented',
    DATE_SUB(NOW(), INTERVAL 6 DAY),
    NOW()
),
(
    4,
    'Honda',
    'Civic',
    2024,
    'Sedan',
    'Automatic',
    'Gasoline',
    5,
    'CVC-2401',
    'Black',
    2600.00,
    'A polished sedan with a comfortable cabin and responsive handling.',
    'assets/cars/honda-civic.jpg',
    'Available',
    DATE_SUB(NOW(), INTERVAL 5 DAY),
    NOW()
),
(
    5,
    'Hyundai',
    'Tucson',
    2023,
    'SUV',
    'Automatic',
    'Diesel',
    5,
    'SUV-5555',
    'Gray',
    3200.00,
    'A comfortable SUV for business travel, family trips, and extra luggage.',
    'assets/cars/hyundai-tucson.png',
    'Reserved',
    DATE_SUB(NOW(), INTERVAL 4 DAY),
    NOW()
),
(
    6,
    'Nissan',
    'Leaf',
    2022,
    'Hatchback',
    'Automatic',
    'Electric',
    5,
    'EV-1010',
    'Red',
    2400.00,
    'An electric hatchback for quiet, efficient daily travel.',
    'assets/cars/nissan-leaf.jpeg',
    'Under Maintenance',
    DATE_SUB(NOW(), INTERVAL 3 DAY),
    NOW()
);

INSERT IGNORE INTO bookings (
    id,
    user_id,
    vehicle_id,
    start_date,
    end_date,
    rental_days,
    daily_rate,
    total_amount,
    status,
    created_at,
    updated_at
) VALUES
(
    1,
    2,
    3,
    DATE_SUB(NOW(), INTERVAL 1 DAY),
    DATE_ADD(NOW(), INTERVAL 2 DAY),
    3,
    3500.00,
    10500.00,
    'Rented',
    DATE_SUB(NOW(), INTERVAL 2 DAY),
    NOW()
),
(
    2,
    2,
    5,
    DATE_ADD(NOW(), INTERVAL 1 DAY),
    DATE_ADD(NOW(), INTERVAL 4 DAY),
    3,
    3200.00,
    9600.00,
    'Reserved',
    DATE_SUB(NOW(), INTERVAL 1 DAY),
    NOW()
),
(
    3,
    2,
    1,
    DATE_SUB(NOW(), INTERVAL 9 DAY),
    DATE_SUB(NOW(), INTERVAL 7 DAY),
    2,
    1800.00,
    3600.00,
    'Completed',
    DATE_SUB(NOW(), INTERVAL 10 DAY),
    DATE_SUB(NOW(), INTERVAL 7 DAY)
),
(
    4,
    2,
    4,
    DATE_ADD(NOW(), INTERVAL 2 DAY),
    DATE_ADD(NOW(), INTERVAL 5 DAY),
    3,
    2600.00,
    7800.00,
    'Pending',
    NOW(),
    NOW()
);

INSERT IGNORE INTO audit_logs (
    id,
    user_id,
    action,
    details,
    ip_address,
    created_at
) VALUES
(
    1,
    1,
    'DATABASE_SEEDED',
    'Initial Party4U database records were imported.',
    '127.0.0.1',
    NOW()
);

UPDATE vehicles
SET image_path = CASE id
    WHEN 1 THEN 'assets/cars/toyota-vios.jpg'
    WHEN 2 THEN 'assets/cars/mitsubishi-xpander.jpg'
    WHEN 3 THEN 'assets/cars/ford-ranger.jpg'
    WHEN 4 THEN 'assets/cars/honda-civic.jpg'
    WHEN 5 THEN 'assets/cars/hyundai-tucson.png'
    WHEN 6 THEN 'assets/cars/nissan-leaf.jpeg'
    ELSE image_path
END
WHERE id IN (1, 2, 3, 4, 5, 6);

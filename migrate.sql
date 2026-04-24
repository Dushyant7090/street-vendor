-- Migration: Add missing tables/columns to existing street_vendor database
USE street_vendor;

CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL UNIQUE,
    name VARCHAR(100) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(15) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    business_name VARCHAR(150) DEFAULT NULL,
    business_location VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    id_proof_type VARCHAR(50) DEFAULT NULL,
    id_proof_number VARCHAR(100) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_name VARCHAR(100) NOT NULL UNIQUE,
    location VARCHAR(255) DEFAULT NULL,
    area_description TEXT,
    max_vendors INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT DEFAULT NULL,
    license_number VARCHAR(50) UNIQUE DEFAULT NULL,
    license_type VARCHAR(100) DEFAULT NULL,
    business_name VARCHAR(150) DEFAULT NULL,
    business_location VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(20) DEFAULT NULL,
    contact_email VARCHAR(150) DEFAULT NULL,
    applied_date DATE DEFAULT NULL,
    issue_date DATE DEFAULT NULL,
    expiry_date DATE DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT DEFAULT NULL,
    location_name VARCHAR(100) DEFAULT NULL,
    vendor_id INT DEFAULT NULL,
    spot_number VARCHAR(50) DEFAULT NULL,
    allocated_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    UNIQUE KEY unique_spot (zone_id, spot_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO zones (zone_name, location, area_description, max_vendors) VALUES
('Zone A - Market Road', 'Market Road', 'Main market area near city center', 15),
('Zone B - Station Area', 'Station Area', 'Near railway station and bus stand', 20),
('Zone C - College Road', 'College Road', 'Area near universities and colleges', 10),
('Zone D - Industrial Area', 'Industrial Area', 'Near factories and industrial zone', 12),
('Zone E - Residential Block', 'Residential Block', 'Residential neighborhood markets', 8);

INSERT IGNORE INTO vendors (user_id, phone, address, id_proof_type, id_proof_number) VALUES
(2, '9876543210', 'Sample Address, City', 'Aadhar Card', '1234-5678-9012');

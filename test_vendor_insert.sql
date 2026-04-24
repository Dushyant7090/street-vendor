-- ============================================
-- Test Vendor Account Insertion
-- ============================================

-- Insert test vendor user
-- Email: vendor1@test.com | Password: vendor123
INSERT INTO users (name, email, password, role) VALUES 
('Test Vendor', 'vendor1@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor');

-- Get the last inserted user ID (vendor1@test.com will have ID 2 if admin has ID 1)
-- Insert vendor profile for the test vendor
INSERT INTO vendors (user_id, phone, address, id_proof_type, id_proof_number) VALUES 
(2, '9876543210', '123 Market Street, Gadag', 'Aadhar Card', '1234-5678-9012');

-- ============================================
-- Additional Test Vendors (Optional)
-- ============================================

-- Test Vendor 2
-- Email: vendor2@test.com | Password: vendor123
INSERT INTO users (name, email, password, role) VALUES 
('Test Vendor 2', 'vendor2@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor');

INSERT INTO vendors (user_id, phone, address, id_proof_type, id_proof_number) VALUES 
(3, '9876543211', '456 Bazaar Road, Gadag', 'Aadhar Card', '2345-6789-0123');

-- Test Vendor 3
-- Email: vendor3@test.com | Password: vendor123
INSERT INTO users (name, email, password, role) VALUES 
('Test Vendor 3', 'vendor3@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor');

INSERT INTO vendors (user_id, phone, address, id_proof_type, id_proof_number) VALUES 
(4, '9876543212', '789 Trade Lane, Gadag', 'PAN Card', '3456-7890-1234');

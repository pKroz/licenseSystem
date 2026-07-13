-- LicenseFlow Database Schema
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS licenseflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE licenseflow_db;

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('administrador','cliente','soporte','vendedor','auditor') NOT NULL DEFAULT 'cliente',
    status ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
);

-- Access logs
CREATE TABLE access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Password resets
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Clients
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(200) NOT NULL,
    ruc_dni VARCHAR(20) NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    representative VARCHAR(150) NULL,
    type ENUM('empresa','persona') NOT NULL DEFAULT 'empresa',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
);

-- Client-User association
CREATE TABLE client_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY uk_client_user (client_id, user_id)
);

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    version VARCHAR(20) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    modules JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL
);

-- Licenses
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    product_id INT NOT NULL,
    plan VARCHAR(50) NOT NULL,
    status ENUM('active','expired','suspended','cancelled') NOT NULL DEFAULT 'active',
    starts_at DATE NOT NULL,
    expires_at DATE NOT NULL,
    max_users INT NULL,
    max_devices INT NULL,
    max_installations INT NULL,
    modules JSON NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- License change history
CREATE TABLE license_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    changed_by INT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (license_id) REFERENCES licenses(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Validation logs
CREATE TABLE validation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NULL,
    license_key VARCHAR(64) NOT NULL,
    result ENUM('active','expired','suspended','cancelled','invalid') NOT NULL,
    ip_address VARCHAR(45) NULL,
    domain VARCHAR(255) NULL,
    device_id VARCHAR(255) NULL,
    installation_id VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (license_id) REFERENCES licenses(id)
);

-- API Keys (for external validation)
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    client_id INT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- Audit logs
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    entity VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Email templates
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    type ENUM('license_expiring','license_expired','license_activated','license_suspended','license_cancelled','license_renewed') NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
);

-- Default admin user (password: Admin1234!)
INSERT INTO users (username, email, password, full_name, role, status, created_at) VALUES
('admin', 'admin@licenseflow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'administrador', 'active', NOW());

-- Sample product
INSERT INTO products (name, description, version, status, modules, created_at) VALUES
('LicenseFlow Pro', 'Sistema de gestión de licencias', '2.0', 'active', '["dashboard","reportes","api","notificaciones"]', NOW());

-- Sample client
INSERT INTO clients (company_name, ruc_dni, email, phone, representative, type, created_at) VALUES
('TechCorp SAC', '20123456789', 'contacto@techcorp.com', '01-234-5678', 'Juan Pérez', 'empresa', NOW());

-- Sample API key
INSERT INTO api_keys (name, api_key, status, created_at) VALUES
('Default API Key', 'lf_test_key_abc123xyz789', 'active', NOW());

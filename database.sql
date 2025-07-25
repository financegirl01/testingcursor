-- Inventory Management System Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS inventory_management;
USE inventory_management;

-- Users table for authentication
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Categories table for item organization
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items table
CREATE TABLE items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT,
    unit_price DECIMAL(10,2) NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    minimum_stock INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Suppliers table
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    country VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inventory table
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    supplier_id INT,
    quantity_in_stock INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    location VARCHAR(100),
    batch_number VARCHAR(50),
    expiry_date DATE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- Stock movements table for tracking inventory changes
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_number VARCHAR(50),
    notes TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert sample data

-- Users (password is 'password123' hashed)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@inventory.com', 'admin'),
('manager1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Manager', 'john@inventory.com', 'manager'),
('staff1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Staff', 'jane@inventory.com', 'staff');

-- Categories
INSERT INTO categories (name, description) VALUES
('Electronics', 'Electronic devices and components'),
('Office Supplies', 'General office and administrative supplies'),
('Raw Materials', 'Manufacturing raw materials'),
('Finished Goods', 'Completed products ready for sale'),
('Tools & Equipment', 'Tools and equipment for operations');

-- Suppliers
INSERT INTO suppliers (name, contact_person, email, phone, address, city, state, zip_code, country) VALUES
('Tech Solutions Inc', 'Mike Johnson', 'mike@techsolutions.com', '+1-555-0101', '123 Tech Street', 'San Francisco', 'CA', '94102', 'USA'),
('Office Depot Pro', 'Sarah Wilson', 'sarah@officedepot.com', '+1-555-0102', '456 Business Ave', 'New York', 'NY', '10001', 'USA'),
('Global Materials Ltd', 'David Chen', 'david@globalmaterials.com', '+1-555-0103', '789 Industrial Blvd', 'Chicago', 'IL', '60601', 'USA'),
('Equipment Express', 'Lisa Brown', 'lisa@equipmentexpress.com', '+1-555-0104', '321 Tool Lane', 'Houston', 'TX', '77001', 'USA');

-- Items
INSERT INTO items (name, description, category_id, unit_price, sku, minimum_stock) VALUES
('Laptop Computer', 'High-performance business laptop', 1, 899.99, 'ELEC-LAP-001', 5),
('Wireless Mouse', 'Ergonomic wireless mouse', 1, 29.99, 'ELEC-MOU-001', 20),
('Office Paper', 'A4 size premium office paper (500 sheets)', 2, 12.99, 'OFF-PAP-001', 50),
('Ballpoint Pens', 'Blue ink ballpoint pens (pack of 10)', 2, 5.99, 'OFF-PEN-001', 100),
('Steel Rods', 'Stainless steel rods 1m length', 3, 45.00, 'RAW-STE-001', 25),
('Plastic Pellets', 'High-grade plastic pellets (1kg)', 3, 8.50, 'RAW-PLA-001', 200),
('Assembled Widget', 'Finished product widget type A', 4, 125.00, 'FIN-WID-001', 15),
('Power Drill', 'Cordless power drill with battery', 5, 89.99, 'TOO-DRI-001', 8),
('Safety Helmets', 'Industrial safety helmets', 5, 25.00, 'TOO-HEL-001', 30);

-- Inventory
INSERT INTO inventory (item_id, supplier_id, quantity_in_stock, location, batch_number) VALUES
(1, 1, 12, 'Warehouse A-1', 'LAP2024001'),
(2, 1, 45, 'Warehouse A-2', 'MOU2024001'),
(3, 2, 150, 'Warehouse B-1', 'PAP2024001'),
(4, 2, 300, 'Warehouse B-2', 'PEN2024001'),
(5, 3, 75, 'Warehouse C-1', 'STE2024001'),
(6, 3, 500, 'Warehouse C-2', 'PLA2024001'),
(7, 3, 25, 'Warehouse D-1', 'WID2024001'),
(8, 4, 15, 'Warehouse E-1', 'DRI2024001'),
(9, 4, 60, 'Warehouse E-2', 'HEL2024001');

-- Stock movements
INSERT INTO stock_movements (item_id, movement_type, quantity, reference_number, notes, user_id) VALUES
(1, 'in', 12, 'PO-2024-001', 'Initial stock purchase', 1),
(2, 'in', 50, 'PO-2024-002', 'Initial stock purchase', 1),
(2, 'out', 5, 'SO-2024-001', 'Sale to customer', 2),
(3, 'in', 200, 'PO-2024-003', 'Bulk paper purchase', 1),
(3, 'out', 50, 'SO-2024-002', 'Office consumption', 2),
(4, 'in', 300, 'PO-2024-004', 'Pen supply restock', 1),
(5, 'in', 100, 'PO-2024-005', 'Raw material delivery', 1),
(5, 'out', 25, 'WO-2024-001', 'Used in production', 3);
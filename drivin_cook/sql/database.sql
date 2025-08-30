CREATE DATABASE drivin_cook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE drivin_cook;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    type ENUM('admin', 'franchisee') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE franchisees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    company_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    entry_fee_paid DECIMAL(10,2) DEFAULT 50000.00,
    commission_rate DECIMAL(3,2) DEFAULT 4.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE trucks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_plate VARCHAR(20) UNIQUE NOT NULL,
    model VARCHAR(50),
    franchisee_id INT,
    status ENUM('available', 'assigned', 'maintenance', 'broken') DEFAULT 'available',
    location VARCHAR(100),
    last_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (franchisee_id) REFERENCES franchisees(id) ON DELETE SET NULL
);

CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    address TEXT,
    manager_name VARCHAR(100)
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category ENUM('ingredient', 'prepared_dish', 'beverage') NOT NULL,
    price DECIMAL(8,2) NOT NULL,
    warehouse_id INT,
    stock_quantity INT DEFAULT 0,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE stock_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    franchisee_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    total_amount DECIMAL(10,2),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'delivered', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (franchisee_id) REFERENCES franchisees(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES stock_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    franchisee_id INT NOT NULL,
    sale_date DATE NOT NULL,
    daily_revenue DECIMAL(10,2) NOT NULL,
    commission_due DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (franchisee_id) REFERENCES franchisees(id)
);


INSERT INTO users (email, password, type) VALUES 
('admin@drivinCook.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('franchisee1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'franchisee');

INSERT INTO franchisees (user_id, name, company_name, phone, address) VALUES 
(2, 'Jean Martin', 'Martin Food Truck', '0123456789', '15 rue de la Paix, 75001 Paris');

INSERT INTO warehouses (name, address, manager_name) VALUES 
('Entrepôt Nord', '10 Avenue du Nord, 93000 Bobigny', 'Pierre Dupont'),
('Entrepôt Sud', '25 Boulevard du Sud, 94000 Créteil', 'Marie Durand'),
('Entrepôt Est', '8 Rue de l\'Est, 77000 Meaux', 'Paul Martin'),
('Entrepôt Ouest', '30 Avenue de l\'Ouest, 78000 Versailles', 'Sophie Moreau');

INSERT INTO trucks (license_plate, model, status) VALUES 
('AB-123-CD', 'Food Truck Premium', 'available'),
('EF-456-GH', 'Food Truck Standard', 'available');

INSERT INTO products (name, category, price, warehouse_id, stock_quantity) VALUES 
('Farine bio', 'ingredient', 3.50, 1, 100),
('Burger fermier', 'prepared_dish', 8.50, 1, 50),
('Coca Cola', 'beverage', 2.50, 1, 200);
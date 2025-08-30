USE drivin_cook;

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    loyalty_points INT DEFAULT 0,
    language ENUM('fr', 'en', 'es') DEFAULT 'fr',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_fr VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    name_es VARCHAR(100) NOT NULL,
    description_fr TEXT,
    description_en TEXT,
    description_es TEXT,
    price DECIMAL(8,2) NOT NULL,
    category ENUM('burger', 'salad', 'drink', 'dessert', 'starter') NOT NULL,
    image_url VARCHAR(255),
    available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE client_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    truck_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pickup_time DATETIME,
    pickup_location VARCHAR(100),
    status ENUM('pending', 'confirmed', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('card', 'cash', 'loyalty_points') DEFAULT 'card',
    loyalty_points_used INT DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (truck_id) REFERENCES trucks(id) ON DELETE SET NULL
);

CREATE TABLE client_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(8,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES client_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE
);

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    location VARCHAR(100),
    max_participants INT DEFAULT 50,
    current_participants INT DEFAULT 0,
    price DECIMAL(8,2) DEFAULT 0,
    status ENUM('upcoming', 'active', 'completed', 'cancelled') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE event_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    client_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participation (event_id, client_id)
);

CREATE TABLE loyalty_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    points_change INT NOT NULL,
    reason VARCHAR(100),
    order_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES client_orders(id) ON DELETE SET NULL
);

CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    subscribed BOOLEAN DEFAULT TRUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

INSERT INTO menus (name_fr, name_en, name_es, description_fr, description_en, description_es, price, category, available) VALUES
('Burger Fermier', 'Farm Burger', 'Hamburguesa Campera', 'Burger artisanal avec steak haché fermier, légumes frais et sauce maison', 'Artisanal burger with farm beef, fresh vegetables and homemade sauce', 'Hamburguesa artesanal con carne de granja, verduras frescas y salsa casera', 12.50, 'burger', TRUE),

('Salade César', 'Caesar Salad', 'Ensalada César', 'Salade fraîche avec poulet grillé, croûtons et parmesan', 'Fresh salad with grilled chicken, croutons and parmesan', 'Ensalada fresca con pollo a la parrilla, picatostes y parmesano', 9.80, 'salad', TRUE),

('Coca Cola', 'Coca Cola', 'Coca Cola', 'Boisson gazeuse rafraîchissante 33cl', 'Refreshing soda drink 33cl', 'Bebida gaseosa refrescante 33cl', 2.50, 'drink', TRUE),

('Tarte aux Pommes', 'Apple Pie', 'Tarta de Manzana', 'Tarte maison aux pommes avec cannelle', 'Homemade apple pie with cinnamon', 'Tarta casera de manzana con canela', 4.50, 'dessert', TRUE),

('Bruschetta', 'Bruschetta', 'Bruschetta', 'Pain grillé aux tomates, basilic et mozzarella', 'Grilled bread with tomatoes, basil and mozzarella', 'Pan tostado con tomates, albahaca y mozzarella', 6.20, 'starter', TRUE);

INSERT INTO clients (email, password, firstname, lastname, phone, address, loyalty_points, language) VALUES
('marie.dubois@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marie', 'Dubois', '0123456789', '15 rue de la Paix, 75001 Paris', 150, 'fr'),
('john.smith@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Smith', '0987654321', '25 Boulevard Saint-Germain, 75006 Paris', 75, 'en'),
('carlos.garcia@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos', 'Garcia', '0147258369', '8 Avenue des Champs, 75008 Paris', 220, 'es');

INSERT INTO events (title, description, event_date, event_time, location, max_participants, price) VALUES
('Dégustation Burgers Bio', 'Venez découvrir notre nouvelle gamme de burgers biologiques avec des produits locaux de qualité', '2024-12-15', '18:00:00', 'Food Truck - Place de la République', 30, 5.00),
('Atelier Cuisine Mobile', 'Apprenez les secrets de la cuisine de rue avec nos chefs expérimentés', '2024-12-22', '14:00:00', 'Entrepôt Nord - Bobigny', 20, 15.00),
('Soirée Food Truck', 'Soirée conviviale avec musique et dégustation de nos spécialités', '2024-12-30', '19:30:00', 'Esplanade de Vincennes', 50, 0.00);

INSERT INTO client_orders (client_id, truck_id, total_amount, pickup_location, status, payment_method) VALUES
(1, 1, 22.30, 'Place de la Bastille', 'completed', 'card'),
(2, 2, 15.60, 'Gare du Nord', 'pending', 'loyalty_points'),
(3, 1, 31.40, 'Châtelet Les Halles', 'confirmed', 'card');

INSERT INTO client_order_items (order_id, menu_id, quantity, unit_price) VALUES
(1, 1, 1, 12.50), -- Burger Fermier
(1, 3, 2, 2.50),  -- 2 Coca Cola
(1, 4, 1, 4.50),  -- Tarte aux Pommes
(2, 2, 1, 9.80),  -- Salade César
(2, 5, 1, 6.20),  -- Bruschetta
(3, 1, 2, 12.50), -- 2 Burger Fermier
(3, 2, 1, 9.80),  -- Salade César
(3, 3, 1, 2.50);  -- Coca Cola

INSERT INTO loyalty_history (client_id, points_change, reason, order_id) VALUES
(1, 22, 'Points gagnés pour commande', 1),
(1, -50, 'Points utilisés pour réduction', NULL),
(2, 16, 'Points gagnés pour commande', 2),
(3, 31, 'Points gagnés pour commande', 3);

INSERT INTO newsletter_subscribers (client_id, subscribed) VALUES
(1, TRUE),
(2, TRUE),
(3, FALSE);

INSERT INTO event_participants (event_id, client_id) VALUES
(1, 1),
(1, 2),
(2, 1),
(3, 3);
CREATE DATABASE IF NOT EXISTS cafeteria_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cafeteria_management;

CREATE TABLE IF NOT EXISTS users (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 phone VARCHAR(20) NOT NULL, password_hash VARCHAR(100) NOT NULL,
 role ENUM('CUSTOMER','ADMIN') NOT NULL DEFAULT 'CUSTOMER',
 status ENUM('ACTIVE','BLOCKED') NOT NULL DEFAULT 'ACTIVE',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_users_role_status(role,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL UNIQUE, icon VARCHAR(80) DEFAULT '',
 description VARCHAR(500) DEFAULT '', status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS food_items (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, category_id BIGINT NOT NULL, name VARCHAR(140) NOT NULL,
 price DECIMAL(10,2) NOT NULL CHECK(price >= 0), description VARCHAR(1000) DEFAULT '',
 image VARCHAR(500) DEFAULT '', popular BOOLEAN NOT NULL DEFAULT FALSE,
 status ENUM('AVAILABLE','UNAVAILABLE') NOT NULL DEFAULT 'AVAILABLE',
 CONSTRAINT fk_food_category FOREIGN KEY(category_id) REFERENCES categories(id),
 INDEX idx_food_category_status(category_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, order_ref VARCHAR(32) NOT NULL UNIQUE, user_id BIGINT NOT NULL,
 customer_name VARCHAR(120) NOT NULL, phone VARCHAR(20) NOT NULL, email VARCHAR(190) NOT NULL,
 total DECIMAL(10,2) NOT NULL CHECK(total >= 0), payment_method VARCHAR(30) NOT NULL,
 payment_status ENUM('INITIATED','PENDING_VERIFICATION','PAID','FAILED','REJECTED','REFUNDED') NOT NULL DEFAULT 'INITIATED',
 status ENUM('AWAITING_PAYMENT','PAYMENT_REVIEW','CONFIRMED','PREPARING','READY','COMPLETED','CANCELLED') NOT NULL DEFAULT 'AWAITING_PAYMENT',
 pickup VARCHAR(80) DEFAULT '', notes VARCHAR(500) DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_order_user FOREIGN KEY(user_id) REFERENCES users(id),
 INDEX idx_order_user_date(user_id,created_at), INDEX idx_order_status_payment(status,payment_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, order_id BIGINT NOT NULL, food_item_id BIGINT NULL,
 item_name VARCHAR(140) NOT NULL, unit_price DECIMAL(10,2) NOT NULL CHECK(unit_price >= 0),
 quantity INT NOT NULL CHECK(quantity BETWEEN 1 AND 20), line_total DECIMAL(10,2) NOT NULL,
 CONSTRAINT fk_item_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_item_food FOREIGN KEY(food_item_id) REFERENCES food_items(id) ON DELETE SET NULL,
 INDEX idx_order_items_order(order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, order_id BIGINT NOT NULL UNIQUE,
 amount DECIMAL(10,2) NOT NULL CHECK(amount >= 0), method VARCHAR(30) NOT NULL,
 transaction_reference VARCHAR(120) NULL UNIQUE,
 status ENUM('INITIATED','PENDING_VERIFICATION','PAID','FAILED','REJECTED','REFUNDED') NOT NULL DEFAULT 'INITIATED',
 claimed_at DATETIME NULL, verified_by BIGINT NULL, verified_at DATETIME NULL, rejection_reason VARCHAR(500) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_payment_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_payment_verifier FOREIGN KEY(verified_by) REFERENCES users(id),
 INDEX idx_payment_status(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedback (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, user_id BIGINT NOT NULL, rating TINYINT NOT NULL CHECK(rating BETWEEN 1 AND 5),
 message VARCHAR(1000) DEFAULT '', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_feedback_user FOREIGN KEY(user_id) REFERENCES users(id), INDEX idx_feedback_date(created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_audit_logs (
 id BIGINT PRIMARY KEY AUTO_INCREMENT, admin_user_id BIGINT NOT NULL, action VARCHAR(60) NOT NULL,
 entity_type VARCHAR(60) NOT NULL, entity_id BIGINT NOT NULL, details VARCHAR(1000) DEFAULT '',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_audit_admin FOREIGN KEY(admin_user_id) REFERENCES users(id), INDEX idx_audit_date(created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories(name,icon,description) VALUES
 ('Breakfast','bi-brightness-high','Breakfast and South Indian dishes'),
 ('Snacks','bi-basket','Quick snacks'),
 ('Fast Food','bi-lightning-charge','Burgers, rolls and pizza'),
 ('Meals','bi-egg-fried','Lunch and meal options'),
 ('Beverages','bi-cup-hot','Tea, coffee and cold drinks');

INSERT IGNORE INTO food_items(category_id,name,price,description,popular,status) VALUES
 ((SELECT id FROM categories WHERE name='Breakfast'),'Masala Dosa',60,'Crisp dosa with spiced potato, sambar and chutney',TRUE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Snacks'),'Veg Sandwich',40,'Grilled vegetable sandwich',FALSE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Fast Food'),'Cheese Burger',80,'Vegetarian burger with cheese',TRUE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Fast Food'),'Paneer Roll',70,'Paneer tikka roll',TRUE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Fast Food'),'Veg Pizza',120,'Vegetable pizza',FALSE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Beverages'),'Cold Coffee',50,'Chilled coffee',TRUE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Beverages'),'Tea',15,'Freshly brewed tea',FALSE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Snacks'),'Samosa',15,'Potato-filled pastry',TRUE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Snacks'),'French Fries',50,'Crispy salted fries',FALSE,'AVAILABLE'),
 ((SELECT id FROM categories WHERE name='Meals'),'Veg Biryani',90,'Vegetable biryani',TRUE,'AVAILABLE');

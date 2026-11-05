CREATE DATABASE IF NOT EXISTS trustcash CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trustcash;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(150) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 name VARCHAR(80) NOT NULL,
 type ENUM('income','expense') NOT NULL,
 CONSTRAINT fk_cat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 UNIQUE KEY unique_user_category (user_id, name, type)
);

CREATE TABLE transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 category_id INT NULL,
 type ENUM('income','expense') NOT NULL,
 amount DECIMAL(14,2) NOT NULL,
 note VARCHAR(255) DEFAULT '',
 transaction_date DATE NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_tx_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
 INDEX idx_user_date (user_id, transaction_date)
);

CREATE TABLE budgets (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 month CHAR(7) NOT NULL,
 amount DECIMAL(14,2) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_budget_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 UNIQUE KEY unique_user_month (user_id, month)
);

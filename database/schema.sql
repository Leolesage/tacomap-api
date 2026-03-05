CREATE DATABASE IF NOT EXISTS tacomap_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tacomap_db;

CREATE TABLE IF NOT EXISTS api_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS tacos_places (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  `date` DATETIME NOT NULL,
  price INT NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  contact_name VARCHAR(255) NOT NULL,
  contact_email VARCHAR(255) NOT NULL,
  photo VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

INSERT INTO api_users (email, password_hash, created_at)
VALUES ('admin@tacomap.local', '$2b$12$rKvZL8Mt1FDFHT6Wj3CgcOwml6xIBjs9UL7E7bKnUso4wk5Nzpcjq', NOW());

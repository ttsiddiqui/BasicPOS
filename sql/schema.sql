-- BasicPOS by WebC
-- Table names start with bp_ so they can share a cPanel database.
-- Prefer install.php. It creates these tables and the two logins.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS bp_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  business_name VARCHAR(120) NOT NULL,
  currency_symbol VARCHAR(10) NOT NULL DEFAULT 'Rs',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  receipt_footer VARCHAR(180) NOT NULL DEFAULT 'Thank you for your purchase',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Karachi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('admin', 'cashier') NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_brands (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_brands_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_units (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL,
  UNIQUE KEY uq_units_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  barcode VARCHAR(64) NULL,
  product_code VARCHAR(64) NULL,
  unit_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  brand_id INT UNSIGNED NULL,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_products_barcode (barcode),
  UNIQUE KEY uq_products_code (product_code),
  KEY idx_products_name (name),
  KEY idx_products_active (active),
  CONSTRAINT fk_products_unit FOREIGN KEY (unit_id) REFERENCES bp_units (id),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES bp_categories (id),
  CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES bp_brands (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_discounts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  discount_type ENUM('fixed', 'percent') NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  apply_to ENUM('all', 'selected') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  starts_on DATE NULL,
  ends_on DATE NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_discount_products (
  discount_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (discount_id, product_id),
  CONSTRAINT fk_dp_discount FOREIGN KEY (discount_id) REFERENCES bp_discounts (id) ON DELETE CASCADE,
  CONSTRAINT fk_dp_product FOREIGN KEY (product_id) REFERENCES bp_products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_sales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL,
  client_token VARCHAR(64) NULL,
  user_id INT UNSIGNED NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  amount_received DECIMAL(12,2) NOT NULL DEFAULT 0,
  change_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  voided TINYINT(1) NOT NULL DEFAULT 0,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_sales_invoice (invoice_no),
  UNIQUE KEY uq_sales_token (client_token),
  KEY idx_sales_created (created_at),
  KEY idx_sales_voided (voided),
  CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES bp_users (id),
  CONSTRAINT fk_sales_voided_by FOREIGN KEY (voided_by) REFERENCES bp_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_sale_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  unit_name VARCHAR(40) NOT NULL DEFAULT '',
  qty DECIMAL(12,3) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL,
  cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  KEY idx_sale_items_sale (sale_id),
  CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES bp_sales (id),
  CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES bp_products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_purchases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(30) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  total_cost DECIMAL(12,2) NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  purchased_on DATE NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_purchases_ref (reference_no),
  KEY idx_purchases_date (purchased_on),
  CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES bp_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_purchase_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  qty DECIMAL(12,3) NOT NULL,
  cost_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  KEY idx_purchase_items_purchase (purchase_id),
  CONSTRAINT fk_pi_purchase FOREIGN KEY (purchase_id) REFERENCES bp_purchases (id),
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES bp_products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_expenses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  expense_date DATE NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_expenses_date (expense_date),
  CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES bp_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL DEFAULT '',
  position VARCHAR(80) NOT NULL DEFAULT '',
  monthly_salary DECIMAL(12,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  joined_on DATE NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_advances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  kind ENUM('advance', 'loan') NOT NULL DEFAULT 'advance',
  amount DECIMAL(12,2) NOT NULL,
  remaining DECIMAL(12,2) NOT NULL,
  given_on DATE NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_advances_employee (employee_id),
  KEY idx_advances_date (given_on),
  CONSTRAINT fk_advances_employee FOREIGN KEY (employee_id) REFERENCES bp_employees (id),
  CONSTRAINT fk_advances_user FOREIGN KEY (user_id) REFERENCES bp_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_payrolls (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  period_year SMALLINT UNSIGNED NOT NULL,
  period_month TINYINT UNSIGNED NOT NULL,
  base_salary DECIMAL(12,2) NOT NULL,
  advance_deducted DECIMAL(12,2) NOT NULL DEFAULT 0,
  net_pay DECIMAL(12,2) NOT NULL,
  paid_on DATE NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_payroll_period (employee_id, period_year, period_month),
  KEY idx_payroll_paid (paid_on),
  CONSTRAINT fk_payrolls_employee FOREIGN KEY (employee_id) REFERENCES bp_employees (id),
  CONSTRAINT fk_payrolls_user FOREIGN KEY (user_id) REFERENCES bp_users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bp_payroll_deductions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  payroll_id INT UNSIGNED NOT NULL,
  advance_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  KEY idx_pd_payroll (payroll_id),
  CONSTRAINT fk_pd_payroll FOREIGN KEY (payroll_id) REFERENCES bp_payrolls (id) ON DELETE CASCADE,
  CONSTRAINT fk_pd_advance FOREIGN KEY (advance_id) REFERENCES bp_advances (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Spice Market: модуль статистики и мониторинга.
-- Импортируется после основной базы spice_market (phpMyAdmin → Импорт или mysql < stats_monitoring.sql).
-- Существующие таблицы не изменяются, скрипт можно выполнять повторно.

USE spice_market;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS site_visits (
  visit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_id CHAR(32) NOT NULL,
  session_id CHAR(32) NOT NULL,
  user_id INT UNSIGNED NULL,
  page VARCHAR(120) NOT NULL,
  query_string VARCHAR(255) NULL,
  product_id INT UNSIGNED NULL,
  http_status SMALLINT UNSIGNED NOT NULL DEFAULT 200,
  referrer_type ENUM('direct', 'internal', 'search', 'social', 'external') NOT NULL DEFAULT 'direct',
  referrer_host VARCHAR(120) NULL,
  device_type ENUM('desktop', 'mobile', 'tablet') NOT NULL DEFAULT 'desktop',
  browser VARCHAR(30) NOT NULL,
  os VARCHAR(30) NOT NULL,
  is_bot TINYINT(1) NOT NULL DEFAULT 0,
  is_admin_area TINYINT(1) NOT NULL DEFAULT 0,
  ip_hash CHAR(64) NOT NULL,
  response_time_ms DECIMAL(8,2) NOT NULL DEFAULT 0,
  memory_kb INT UNSIGNED NOT NULL DEFAULT 0,
  db_queries SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (visit_id),
  KEY idx_site_visits_created_at (created_at),
  KEY idx_site_visits_session_id (session_id),
  KEY idx_site_visits_visitor_id (visitor_id),
  KEY idx_site_visits_product_id (product_id),
  KEY idx_site_visits_user_id (user_id),
  CONSTRAINT fk_site_visits_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_site_visits_product
    FOREIGN KEY (product_id) REFERENCES products (product_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Журнал просмотров страниц (трекер посещаемости)';

CREATE TABLE IF NOT EXISTS auth_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type ENUM('login_success', 'login_failed', 'logout', 'register', 'admin_denied') NOT NULL,
  user_id INT UNSIGNED NULL,
  email_masked VARCHAR(120) NULL,
  ip_hash CHAR(64) NOT NULL,
  user_agent VARCHAR(255) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (event_id),
  KEY idx_auth_events_created_at (created_at),
  KEY idx_auth_events_type_created (event_type, created_at),
  KEY idx_auth_events_ip_created (ip_hash, created_at),
  KEY idx_auth_events_user_id (user_id),
  CONSTRAINT fk_auth_events_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='События входа, регистрации и доступа к админ-панели';

CREATE TABLE IF NOT EXISTS search_queries (
  search_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  query_text VARCHAR(120) NOT NULL,
  results_count INT UNSIGNED NOT NULL DEFAULT 0,
  category_slug VARCHAR(100) NULL,
  visitor_id CHAR(32) NULL,
  user_id INT UNSIGNED NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (search_id),
  KEY idx_search_queries_created_at (created_at),
  KEY idx_search_queries_text (query_text),
  KEY idx_search_queries_user_id (user_id),
  CONSTRAINT fk_search_queries_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Поисковые запросы в каталоге';

CREATE TABLE IF NOT EXISTS app_errors (
  error_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  level ENUM('notice', 'warning', 'error', 'exception', 'fatal') NOT NULL,
  message VARCHAR(1000) NOT NULL,
  file VARCHAR(255) NULL,
  line INT UNSIGNED NULL,
  page VARCHAR(255) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (error_id),
  KEY idx_app_errors_created_at (created_at),
  KEY idx_app_errors_level_created (level, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Журнал ошибок PHP';

CREATE TABLE IF NOT EXISTS api_requests (
  request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service VARCHAR(40) NOT NULL,
  endpoint VARCHAR(255) NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  response_time_ms DECIMAL(8,2) NOT NULL,
  is_success TINYINT(1) NOT NULL,
  used_fallback TINYINT(1) NOT NULL DEFAULT 0,
  error_message VARCHAR(255) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  KEY idx_api_requests_created_at (created_at),
  KEY idx_api_requests_service_created (service, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Запросы к внешним API';

CREATE TABLE IF NOT EXISTS health_checks (
  check_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_ok TINYINT(1) NOT NULL,
  site_status SMALLINT UNSIGNED NULL,
  site_response_ms DECIMAL(8,2) NULL,
  db_ok TINYINT(1) NOT NULL,
  db_response_ms DECIMAL(8,2) NULL,
  api_ok TINYINT(1) NULL,
  api_response_ms DECIMAL(8,2) NULL,
  disk_free_mb INT UNSIGNED NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (check_id),
  KEY idx_health_checks_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Проверки доступности сайта, БД и API';

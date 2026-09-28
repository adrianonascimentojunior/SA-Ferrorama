-- Importar em um banco vazio frota_ferroviaria (MySQL 8.4). TIMESTAMP normaliza UTC entre sessões.
SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE TABLE users (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(255) NOT NULL UNIQUE,
 password_hash TEXT NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'operator', status VARCHAR(20) NOT NULL DEFAULT 'active',
 job_title VARCHAR(120) NOT NULL DEFAULT 'Operador ferroviário', avatar_url TEXT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT users_role_check CHECK (role IN ('operator','manager','super_admin')),
 CONSTRAINT users_status_check CHECK (status IN ('active','disabled','banned'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE password_resets (
 user_id BIGINT PRIMARY KEY, code_hash TEXT NOT NULL, expires_at DATETIME NOT NULL, attempts SMALLINT NOT NULL DEFAULT 0,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE stations (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, city VARCHAR(120) NOT NULL,
 latitude DECIMAL(9,6) NOT NULL, longitude DECIMAL(9,6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE trains (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(32) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL,
 type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'stopped', capacity INT NOT NULL DEFAULT 0,
 distance_km DECIMAL(12,1) NOT NULL DEFAULT 0, consumption_l DECIMAL(12,1) NOT NULL DEFAULT 0,
 punctuality_pct DECIMAL(5,2) NOT NULL DEFAULT 100, latitude DECIMAL(9,6), longitude DECIMAL(9,6), station_id BIGINT,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL,
 CONSTRAINT trains_type_check CHECK (type IN ('locomotive','composition')),
 CONSTRAINT trains_status_check CHECK (status IN ('operating','maintenance','stopped','inactive')),
 CONSTRAINT trains_capacity_check CHECK (capacity >= 0),
 CONSTRAINT trains_punctuality_check CHECK (punctuality_pct BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE sensors (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, train_id BIGINT NOT NULL, code VARCHAR(40) NOT NULL UNIQUE,
 type VARCHAR(40) NOT NULL, unit VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
 latest_value DECIMAL(12,2), latest_reading_at TIMESTAMP NULL DEFAULT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE CASCADE,
 CONSTRAINT sensors_status_check CHECK (status IN ('active','warning','offline'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE sensor_readings (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, sensor_id BIGINT NOT NULL, value DECIMAL(12,2) NOT NULL,
 recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, source VARCHAR(20) NOT NULL DEFAULT 'device', actor_id BIGINT,
 FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE CASCADE,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX sensor_readings_time_idx (sensor_id, recorded_at, id),
 CONSTRAINT sensor_readings_source_check CHECK (source IN ('device','admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE maintenances (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, train_id BIGINT NOT NULL, title VARCHAR(180) NOT NULL, scheduled_at DATETIME NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending', notes TEXT, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE CASCADE,
 CONSTRAINT maintenances_status_check CHECK (status IN ('pending','in_progress','completed','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE alerts (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, train_id BIGINT, sensor_id BIGINT, title VARCHAR(180) NOT NULL,
 message TEXT NOT NULL, severity VARCHAR(20) NOT NULL DEFAULT 'info', status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, resolved_at TIMESTAMP NULL DEFAULT NULL,
 FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE SET NULL,
 FOREIGN KEY (sensor_id) REFERENCES sensors(id) ON DELETE SET NULL,
 CONSTRAINT alerts_severity_check CHECK (severity IN ('critical','warning','info','system')),
 CONSTRAINT alerts_status_check CHECK (status IN ('active','resolved'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE notification_reads (
 user_id BIGINT NOT NULL, alert_id BIGINT NOT NULL, read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (user_id,alert_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE notification_preferences (
 user_id BIGINT PRIMARY KEY, email_enabled BOOLEAN NOT NULL DEFAULT TRUE,
 push_enabled BOOLEAN NOT NULL DEFAULT TRUE, sms_enabled BOOLEAN NOT NULL DEFAULT FALSE,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE schedules (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, train_id BIGINT NOT NULL, origin_id BIGINT NOT NULL, destination_id BIGINT NOT NULL,
 departure_at DATETIME NOT NULL, arrival_at DATETIME NOT NULL, base_price DECIMAL(10,2) NOT NULL,
 FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE CASCADE,
 FOREIGN KEY (origin_id) REFERENCES stations(id), FOREIGN KEY (destination_id) REFERENCES stations(id),
 CONSTRAINT schedules_stations_check CHECK (origin_id <> destination_id),
 CONSTRAINT schedules_time_check CHECK (arrival_at > departure_at),
 CONSTRAINT schedules_price_check CHECK (base_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE ticket_simulations (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, schedule_id BIGINT NOT NULL, return_schedule_id BIGINT,
 passengers SMALLINT NOT NULL, total DECIMAL(10,2) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (schedule_id) REFERENCES schedules(id), FOREIGN KEY (return_schedule_id) REFERENCES schedules(id),
 CONSTRAINT ticket_passengers_check CHECK (passengers BETWEEN 1 AND 8)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE support_requests (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id BIGINT NOT NULL, subject VARCHAR(160) NOT NULL,
 message TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE app_settings (
 `key` VARCHAR(80) PRIMARY KEY, value VARCHAR(255) NOT NULL, description TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE audit_logs (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, actor_id BIGINT, action VARCHAR(80) NOT NULL, entity VARCHAR(80) NOT NULL,
 entity_id BIGINT, details JSON NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL, INDEX audit_logs_time_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELIMITER //
CREATE PROCEDURE refresh_sensor_latest(IN target_id BIGINT)
BEGIN
 UPDATE sensors s SET
  latest_value = (SELECT r.value FROM sensor_readings r WHERE r.sensor_id=target_id ORDER BY r.recorded_at DESC,r.id DESC LIMIT 1),
  latest_reading_at = (SELECT r.recorded_at FROM sensor_readings r WHERE r.sensor_id=target_id ORDER BY r.recorded_at DESC,r.id DESC LIMIT 1),
  updated_at = UTC_TIMESTAMP()
 WHERE s.id=target_id;
END//
CREATE TRIGGER sensor_readings_ai AFTER INSERT ON sensor_readings FOR EACH ROW
BEGIN CALL refresh_sensor_latest(NEW.sensor_id); END//
CREATE TRIGGER sensor_readings_au AFTER UPDATE ON sensor_readings FOR EACH ROW
BEGIN
 CALL refresh_sensor_latest(NEW.sensor_id);
 IF OLD.sensor_id <> NEW.sensor_id THEN CALL refresh_sensor_latest(OLD.sensor_id); END IF;
END//
CREATE TRIGGER sensor_readings_ad AFTER DELETE ON sensor_readings FOR EACH ROW
BEGIN CALL refresh_sensor_latest(OLD.sensor_id); END//
DELIMITER ;

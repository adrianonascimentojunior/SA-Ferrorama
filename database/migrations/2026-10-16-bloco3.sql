-- Executar uma vez após a migração do Bloco 2. Preserva leituras e alertas existentes.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE routes (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, train_id BIGINT NOT NULL, origin VARCHAR(80) NOT NULL,
 destination VARCHAR(80) NOT NULL, travel_date DATE NOT NULL, departure_time TIME NOT NULL,
 arrival_time TIME NULL, status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
 CONSTRAINT fk_routes_trains FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE RESTRICT,
 INDEX routes_date_idx (travel_date, departure_time),
 CONSTRAINT routes_status_check CHECK (status IN ('scheduled','in_progress','completed','cancelled')),
 CONSTRAINT routes_stations_check CHECK (origin <> destination),
 CONSTRAINT routes_time_check CHECK (arrival_time IS NULL OR arrival_time > departure_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sensor_readings ADD COLUMN unit VARCHAR(20) NULL AFTER value;
-- Copiar unidades antes do UPDATE: o gatilho de leituras atualiza sensors e o MySQL
-- proíbe que a tabela também apareça no próprio UPDATE que dispara o gatilho.
CREATE TEMPORARY TABLE bloco3_sensor_units (id BIGINT PRIMARY KEY, unit VARCHAR(20) NOT NULL) ENGINE=MEMORY;
INSERT INTO bloco3_sensor_units SELECT id,unit FROM sensors;
UPDATE sensor_readings r JOIN bloco3_sensor_units u ON u.id=r.sensor_id SET r.unit=u.unit;
DROP TEMPORARY TABLE bloco3_sensor_units;
ALTER TABLE sensor_readings MODIFY COLUMN unit VARCHAR(20) NOT NULL;
CREATE INDEX sensor_readings_recorded_idx ON sensor_readings (recorded_at, id);

ALTER TABLE alerts
 ADD COLUMN reading_id BIGINT NULL AFTER sensor_id,
 ADD COLUMN sensor_type VARCHAR(40) NULL AFTER reading_id,
 ADD INDEX alerts_reading_idx (reading_id),
 ADD CONSTRAINT fk_alerts_readings FOREIGN KEY (reading_id) REFERENCES sensor_readings(id) ON DELETE CASCADE;

-- O limite antigo de temperatura deixa de ser editável: os perfis do Bloco 3 ficam em includes/limites.php.
DELETE FROM app_settings WHERE `key`='critical_temperature_c';

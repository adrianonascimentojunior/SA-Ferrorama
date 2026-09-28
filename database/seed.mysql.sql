SET NAMES utf8mb4;
SET time_zone = '+00:00';
INSERT INTO app_settings VALUES
('db_statement_timeout_ms','5000','Tempo máximo de consultas SELECT em milissegundos no MySQL.'),
('alert_retention_days','90','Janela de histórico de alertas exibida na central.'),
('maintenance_mode','false','Sinalização de manutenção da aplicação.'),
('critical_temperature_c','85','Limite de temperatura crítica para sensores.');
INSERT INTO stations (name,city,latitude,longitude) VALUES
('Estação Central','São Paulo',-23.550520,-46.633308),('Campinas','Campinas',-22.905560,-47.060830),
('Jundiaí','Jundiaí',-23.186390,-46.884170),('Sorocaba','Sorocaba',-23.501530,-47.452590),
('Santos','Santos',-23.960830,-46.333610);
INSERT INTO trains (code,name,type,status,capacity,distance_km,consumption_l,punctuality_pct,latitude,longitude,station_id) VALUES
('ATR-101','Expresso Violeta','composition','operating',240,12480,3180,96.8,-23.19,-46.88,3),
('ATR-102','Linha Horizonte','composition','operating',180,9300,2420,94.2,-22.91,-47.06,2),
('LOC-203','Atlas Diesel','locomotive','maintenance',0,18700,7100,88.5,-23.50,-47.45,4),
('LOC-204','Vetor Sul','locomotive','stopped',0,7500,2790,98.1,-23.55,-46.63,1),
('ATR-105','Costa Azul','composition','inactive',200,11200,3010,91.6,-23.96,-46.33,5);
INSERT INTO sensors (train_id,code,type,unit,status) VALUES
(1,'TMP-101','Temperatura','°C','active'),(1,'VIB-101','Vibração','mm/s','active'),
(2,'TMP-102','Temperatura','°C','warning'),(3,'FRE-203','Freio','bar','warning'),
(4,'MOT-204','Motor','rpm','offline'),(5,'TMP-105','Temperatura','°C','offline');
INSERT INTO sensor_readings (sensor_id,value) VALUES (1,62.3),(2,2.1),(3,82.4),(4,4.7);
INSERT INTO maintenances (train_id,title,scheduled_at,status,notes) VALUES
(3,'Revisão do sistema de freios',UTC_TIMESTAMP() + INTERVAL 3 HOUR,'in_progress','Inspeção preventiva'),
(4,'Calibração dos sensores',UTC_TIMESTAMP() + INTERVAL 1 DAY,'pending',NULL),
(2,'Troca de filtro de ar',UTC_TIMESTAMP() + INTERVAL 2 DAY,'pending',NULL);
INSERT INTO alerts (train_id,sensor_id,title,message,severity,status) VALUES
(2,3,'Temperatura elevada','Sensor TMP-102 próximo ao limite operacional.','warning','active'),
(3,4,'Pressão do freio irregular','Inspecionar o circuito pneumático.','critical','active'),
(4,5,'Sensor sem comunicação','Última leitura indisponível.','info','active'),
(1,1,'Sincronização concluída','Dados da frota atualizados.','system','resolved');
INSERT INTO schedules (train_id,origin_id,destination_id,departure_at,arrival_at,base_price) VALUES
(1,1,3,UTC_TIMESTAMP() + INTERVAL 1 DAY,UTC_TIMESTAMP() + INTERVAL 25 HOUR,38.50),
(1,3,1,UTC_TIMESTAMP() + INTERVAL 27 HOUR,UTC_TIMESTAMP() + INTERVAL 28 HOUR,38.50),
(2,1,2,UTC_TIMESTAMP() + INTERVAL 2 DAY,UTC_TIMESTAMP() + INTERVAL 50 HOUR,62.00),
(2,2,1,UTC_TIMESTAMP() + INTERVAL 52 HOUR,UTC_TIMESTAMP() + INTERVAL 54 HOUR,62.00),
(5,1,5,UTC_TIMESTAMP() + INTERVAL 3 DAY,UTC_TIMESTAMP() + INTERVAL 74 HOUR,72.00);

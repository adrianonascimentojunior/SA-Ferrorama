-- Execute uma vez no banco existente, antes de publicar o Bloco 2.
-- Campos novos permanecem nulos nos trens legados: capacidade em passageiros
-- não permite inferir capacidade em toneladas nem o ano do modelo.
ALTER TABLE trains
  ADD COLUMN model_year SMALLINT NULL AFTER capacity,
  ADD COLUMN capacity_tons DECIMAL(8,2) NULL AFTER model_year,
  ADD COLUMN last_inspection DATE NULL AFTER capacity_tons,
  ADD CONSTRAINT trains_model_year_check CHECK (model_year IS NULL OR model_year BETWEEN 1900 AND 2100),
  ADD CONSTRAINT trains_capacity_tons_check CHECK (capacity_tons IS NULL OR capacity_tons > 0);

ALTER TABLE sensors
  ADD COLUMN location VARCHAR(80) NULL AFTER status,
  ADD COLUMN segment VARCHAR(80) NULL AFTER location,
  ADD COLUMN reading_indicator VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER segment,
  ADD CONSTRAINT sensors_reading_indicator_check CHECK (reading_indicator IN ('normal','attention','critical'));

ALTER TABLE sensors DROP FOREIGN KEY sensors_ibfk_1;
ALTER TABLE sensors ADD CONSTRAINT fk_sensors_trains
  FOREIGN KEY (train_id) REFERENCES trains(id) ON DELETE RESTRICT;

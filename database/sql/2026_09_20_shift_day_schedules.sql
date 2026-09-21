-- 2026_09_20: horarios por día en los turnos (módulo Nómina)
--
-- Agrega `day_schedules` a `shifts`: un objeto JSON cuya clave es el día ISO
-- (1 = lunes ... 7 = domingo) y su valor el horario del día:
--   {"1": {"start_time": "09:00:00", "end_time": "18:00:00", "meal_minutes": 60}}
-- Los días ausentes en el objeto son días de descanso. Solo aplica a los
-- turnos de tipo "per_day" (Por día); los turnos fijos y flexibles siguen
-- usando `start_time` / `end_time` / `days`.

ALTER TABLE `shifts`
  ADD COLUMN `day_schedules` json DEFAULT NULL AFTER `days`;

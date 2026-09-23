-- Construmax2 — Nómina: búsqueda del PIN de kiosco (sep 22 2026)
-- El PIN se identifica ahora en el kiosco solo con el PIN (sin número de empleado).
-- La app guarda el PIN con bcrypt y una huella HMAC ('kiosk_pin_lookup') para
-- encontrarlo; los PINs capturados antes de esta actualización se migran solos
-- la primera vez que se usan en el kiosco.

ALTER TABLE `payroll_profiles`
  ADD COLUMN `kiosk_pin_lookup` varchar(64) NULL AFTER `kiosk_pin`;

ALTER TABLE `payroll_profiles`
  ADD INDEX `payroll_profiles_kiosk_pin_lookup_index` (`kiosk_pin_lookup`);

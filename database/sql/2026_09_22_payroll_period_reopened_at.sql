-- Construmax2 — Nómina: periodos semanales (sep 22 2026)
-- Los periodos son semanales, de lunes a domingo, y se abren/cierran solos
-- (cierre domingos 23:59, apertura lunes 00:00). Un periodo REABIERTO a mano
-- queda exento del cierre automático para que se pueda ajustar; se marca con
-- esta columna.

ALTER TABLE `payroll_periods`
  ADD COLUMN `reopened_at` timestamp NULL DEFAULT NULL AFTER `closed_at`;

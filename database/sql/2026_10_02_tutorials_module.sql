
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `tutorials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tutorials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `duration` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tutorials_created_by_foreign` (`created_by`),
  CONSTRAINT `tutorials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `tutorials` WRITE;
/*!40000 ALTER TABLE `tutorials` DISABLE KEYS */;
INSERT INTO `tutorials` VALUES (1,'Módulo de inicio','Aprende a usar la pantalla de inicio como tu organizador diario. Conoce los accesos rápidos, revisa tu agenda del día, monitorea tus tickets asignados y mantén bajo control el estado de las operaciones, costos y facturación en tiempo real.','01:13','/videos/inicio.mp4','/videos/inicio.jpg',1,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02'),(2,'Módulo de analíticas','Descubre cómo usar los filtros globales para evaluar el rendimiento operativo y comercial. Aprende a alternar divisas, explorar el mapa interactivo de servicios y auditar los pagos de tus técnicos externos y estados de facturación.','01:34','/videos/analiticas.mp4','/videos/analiticas.jpg',2,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02'),(3,'Módulo de clientes','Domina el control de tu base comercial. Aprende a buscar clientes, dar de alta registros con datos fiscales, configurar sucursales geográficas y asignar contactos específicos con accesos directos a llamadas o WhatsApp.','01:48','/videos/clientes.mp4','/videos/clientes.jpg',3,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02'),(4,'Módulo de tickets','Controla el ciclo de vida de una orden de servicio. Aprende a usar los filtros y la vista Kanban, crear tickets con plantillas automatizadas, compartir órdenes de trabajo con técnicos externos y recibir sus evidencias desde el campo.','08:08','/videos/tickets.mp4','/videos/tickets.jpg',4,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02'),(5,'Módulo de presupuestos','Vincula las finanzas con la operación en obra. Aprende a estructurar conceptos de costo, gestionar la cotización en pesos o dólares con tipo de cambio automático, registrar abonos de clientes y controlar los pagos a contratistas.','08:07','/videos/presupuestos.mp4','/videos/presupuestos.jpg',5,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02'),(6,'Módulo de costos','Estructura catálogos de costos detallados por partidas con cálculo de IVA automático. Aprende a generar e imprimir presupuestos formales y a utilizar el historial de versiones para comparar los cambios solicitados por el cliente.','06:31','/videos/costos.mp4','/videos/costos.jpg',6,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02'),(7,'Módulo de facturación','Audita evidencias de campo y registra facturas en el sistema. Aprende cómo el ERP calcula automáticamente el vencimiento del crédito según el cliente y cómo activa alertas de cobro inmediato si se cumplen los plazos establecidos.','04:48','/videos/facturacion.mp4','/videos/facturacion.jpg',7,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02'),(8,'Configuración de notificaciones','Un tutorial rápido para personalizar los flujos de comunicación. Aprende a asignar qué usuarios reciben alertas automáticas en su correo y campana digital según los eventos de costos, operaciones y vencimientos de facturas.','02:13','/videos/notificaciones.mp4','/videos/notificaciones.jpg',8,NULL,'2026-10-02 19:19:02','2026-10-02 19:19:02');
/*!40000 ALTER TABLE `tutorials` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ============================================================
-- Tutoriales: permisos para crear, editar y eliminar.
-- ============================================================

INSERT IGNORE INTO `permissions` (`name`, `guard_name`, `category`, `description`, `created_at`, `updated_at`)
VALUES
    ('tutorials.create', 'web', 'Tutoriales', 'Agregar tutoriales con su video y miniatura', NOW(), NOW()),
    ('tutorials.edit', 'web', 'Tutoriales', 'Editar tutoriales existentes', NOW(), NOW()),
    ('tutorials.delete', 'web', 'Tutoriales', 'Eliminar tutoriales del sistema', NOW(), NOW());

-- Asignar los permisos al rol Super Admin (los demás roles se otorgan desde la UI de roles).
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id
FROM `permissions` p
JOIN `roles` r ON r.name = 'Super Admin'
WHERE p.name IN ('tutorials.create', 'tutorials.edit', 'tutorials.delete')
  AND p.guard_name = 'web'
  AND NOT EXISTS (
      SELECT 1 FROM `role_has_permissions` rhp
      WHERE rhp.permission_id = p.id AND rhp.role_id = r.id
  );

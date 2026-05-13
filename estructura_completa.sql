-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: gestion_comercialalimentos
-- ------------------------------------------------------
-- Server version	8.0.43

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

--
-- Table structure for table `activainactiva_operador`
--

DROP TABLE IF EXISTS `activainactiva_operador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activainactiva_operador` (
  `IdOperador` int DEFAULT NULL,
  `IdIdentificador` int DEFAULT NULL,
  `ActivoInactivo` tinyint(1) DEFAULT NULL,
  `IdCliente` int DEFAULT NULL,
  `IdSucursal` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activofijo_activo_ajustaactivo`
--

DROP TABLE IF EXISTS `activofijo_activo_ajustaactivo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activofijo_activo_ajustaactivo` (
  `IdActivoFijoAjustaActivo` int NOT NULL,
  `IdActivoFijoInserta` int NOT NULL,
  `IdDiario` int NOT NULL,
  `D_H` varchar(1) COLLATE utf8mb4_general_ci NOT NULL,
  `Glosa` varchar(250) COLLATE utf8mb4_general_ci NOT NULL,
  `MontoBolivianos` decimal(10,2) NOT NULL,
  `TipoDeCambio` decimal(10,6) NOT NULL,
  `OtraMoneda` decimal(10,2) NOT NULL,
  `IdContraCuenta` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorIngreso` int DEFAULT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorEdita` int DEFAULT NULL,
  `FechaEdita` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activofijo_activo_ajustadepreciacion`
--

DROP TABLE IF EXISTS `activofijo_activo_ajustadepreciacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activofijo_activo_ajustadepreciacion` (
  `IdActivoFijoAjustaDepreciacion` int NOT NULL,
  `IdActivoFijoInserta` int NOT NULL,
  `IdDiario` int NOT NULL,
  `D_H` varchar(1) COLLATE utf8mb4_general_ci NOT NULL,
  `Glosa` varchar(250) COLLATE utf8mb4_general_ci NOT NULL,
  `MontoBolivianos` decimal(10,2) NOT NULL,
  `TipoDeCambio` decimal(10,6) NOT NULL,
  `OtraMoneda` decimal(10,2) NOT NULL,
  `IdContraCuenta` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorEdita` int NOT NULL,
  `FechaEdita` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activofijo_activo_deprecia`
--

DROP TABLE IF EXISTS `activofijo_activo_deprecia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activofijo_activo_deprecia` (
  `IdActivoFijoDeprecia` int NOT NULL,
  `IdActivoFijoInserta` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdDiario` int NOT NULL,
  `NumeroCorrelativo` int NOT NULL,
  `MontoBolivianos` decimal(10,2) NOT NULL,
  `TipoDeCambio` decimal(10,6) NOT NULL,
  `OtraMoneda` decimal(10,2) NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorEdita` int NOT NULL,
  `FechaEdita` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activofijo_activo_inserta`
--

DROP TABLE IF EXISTS `activofijo_activo_inserta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activofijo_activo_inserta` (
  `IdActivoFijoInserta` int NOT NULL,
  `IdActivoFijo` int NOT NULL,
  `Unidades` decimal(10,2) NOT NULL,
  `IdDiario` int NOT NULL,
  `MontoBolivianos` decimal(10,2) NOT NULL,
  `TipoDeCambio` decimal(10,6) NOT NULL,
  `MontoOtraMoneda` decimal(10,2) NOT NULL,
  `Impuestos` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `NumeroFactura` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreditoFiscal` decimal(10,2) NOT NULL,
  `IUE_Retenciones` decimal(10,2) NOT NULL,
  `IT_Retenciones` decimal(10,2) NOT NULL,
  `ValorNetoBolivianos` decimal(10,2) NOT NULL,
  `ValorNetoOtraMoneda` decimal(10,2) NOT NULL,
  `Deducibilidad` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `IdContraCuenta` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `IdActividad` int NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` timestamp NULL DEFAULT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activofijo_detalle`
--

DROP TABLE IF EXISTS `activofijo_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activofijo_detalle` (
  `IdActivoFijo` int NOT NULL,
  `CodigoActivo` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `Detalle` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `IdTablaDepreciacion` int NOT NULL,
  `IdCuentaActivoFijo` int NOT NULL,
  `IdCuentaDepAcumulada` int NOT NULL,
  `IdCuentaDepGasto` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `Marca` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Serie` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Foto` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activofijo_tabladepreciacion`
--

DROP TABLE IF EXISTS `activofijo_tabladepreciacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activofijo_tabladepreciacion` (
  `IdTablaDepreciacion` int NOT NULL,
  `Bienes` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `VidaUtil` decimal(10,2) NOT NULL,
  `Porcentaje` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `administracion_modulos_activos`
--

DROP TABLE IF EXISTS `administracion_modulos_activos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `administracion_modulos_activos` (
  `IdModuloActivo` int NOT NULL,
  `Modulo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `IdCuenta` int NOT NULL,
  `IdCliente` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `analisisautorizacion`
--

DROP TABLE IF EXISTS `analisisautorizacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `analisisautorizacion` (
  `IdContaPropiamente` int DEFAULT NULL,
  `NumeroDiario` bigint DEFAULT NULL,
  `IdCuenta` int DEFAULT NULL,
  `Glosa` text COLLATE utf8mb4_general_ci,
  `D_H` text COLLATE utf8mb4_general_ci,
  `MontoBolivianos` decimal(10,2) DEFAULT NULL,
  `IdFecha` int DEFAULT NULL,
  `IdCliente` int DEFAULT NULL,
  `Autorizado` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `analisisbalance`
--

DROP TABLE IF EXISTS `analisisbalance`;
/*!50001 DROP VIEW IF EXISTS `analisisbalance`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `analisisbalance` AS SELECT 
 1 AS `Fecha`,
 1 AS `TipoDeCuenta`,
 1 AS `Cuenta`,
 1 AS `Descripcion`,
 1 AS `D_H`,
 1 AS `MontoBolivianos`,
 1 AS `IdCliente`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `analisiscuenta`
--

DROP TABLE IF EXISTS `analisiscuenta`;
/*!50001 DROP VIEW IF EXISTS `analisiscuenta`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `analisiscuenta` AS SELECT 
 1 AS `Contabilizado`,
 1 AS `Fecha`,
 1 AS `TipoDeCuenta`,
 1 AS `Cuenta`,
 1 AS `IdCuenta`,
 1 AS `Descripcion`,
 1 AS `D_H`,
 1 AS `MontoBolivianos`,
 1 AS `MontoOtraMoneda`,
 1 AS `IdCliente`,
 1 AS `IdIdentificador`,
 1 AS `CI_NIT`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `analisismayores`
--

DROP TABLE IF EXISTS `analisismayores`;
/*!50001 DROP VIEW IF EXISTS `analisismayores`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `analisismayores` AS SELECT 
 1 AS `IdOperadorIngreso`,
 1 AS `IdTipoDiario`,
 1 AS `IdCliente`,
 1 AS `Contabilizado`,
 1 AS `IdDiario`,
 1 AS `Fecha`,
 1 AS `Cuenta`,
 1 AS `TipoDeCuenta`,
 1 AS `Descripcion`,
 1 AS `NumeroDiario`,
 1 AS `Glosa`,
 1 AS `D_H`,
 1 AS `MontoBolivianos`,
 1 AS `MontoOtraMoneda`,
 1 AS `NumeroSucursal`,
 1 AS `Identificador`,
 1 AS `Nombre`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `analisisresultados`
--

DROP TABLE IF EXISTS `analisisresultados`;
/*!50001 DROP VIEW IF EXISTS `analisisresultados`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `analisisresultados` AS SELECT 
 1 AS `Fecha`,
 1 AS `TipoDeCuenta`,
 1 AS `Cuenta`,
 1 AS `Descripcion`,
 1 AS `D_H`,
 1 AS `MontoBolivianos`,
 1 AS `IdCliente`,
 1 AS `Deducible`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `checklist_definicion_inicio_sucursales`
--

DROP TABLE IF EXISTS `checklist_definicion_inicio_sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_definicion_inicio_sucursales` (
  `IdInicio` int NOT NULL AUTO_INCREMENT,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `IdOperador` int NOT NULL,
  `FechaHoraInicio` datetime NOT NULL,
  `FechaHoraFinal` datetime DEFAULT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `Latitud` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `Longitud` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`IdInicio`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_encuesta`
--

DROP TABLE IF EXISTS `checklist_encuesta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_encuesta` (
  `IdEncuesta` int NOT NULL AUTO_INCREMENT,
  `Nombre Encuesta` varchar(300) COLLATE utf8mb3_unicode_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `ActivoInactivo` int NOT NULL,
  PRIMARY KEY (`IdEncuesta`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_encuesta_area`
--

DROP TABLE IF EXISTS `checklist_encuesta_area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_encuesta_area` (
  `IdArea` int NOT NULL AUTO_INCREMENT,
  `Area` varchar(200) COLLATE utf8mb3_unicode_ci NOT NULL,
  `IdEncuesta` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdArea`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_encuesta_calificacion`
--

DROP TABLE IF EXISTS `checklist_encuesta_calificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_encuesta_calificacion` (
  `IdCalifica` int NOT NULL AUTO_INCREMENT,
  `IdEncuesta` int NOT NULL,
  `RangoInicial` int NOT NULL,
  `RangoFinal` int NOT NULL,
  `Mensaje` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`IdCalifica`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_encuesta_pregunta`
--

DROP TABLE IF EXISTS `checklist_encuesta_pregunta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_encuesta_pregunta` (
  `IdPregunta` int NOT NULL AUTO_INCREMENT,
  `IdArea` int NOT NULL,
  `IdEncuesta` int NOT NULL,
  `Pregunta` varchar(200) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdPregunta`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_encuesta_puntaje`
--

DROP TABLE IF EXISTS `checklist_encuesta_puntaje`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_encuesta_puntaje` (
  `IdPuntaje` int NOT NULL AUTO_INCREMENT,
  `IdEncuesta` int NOT NULL,
  `Detalle` varchar(200) COLLATE utf8mb3_unicode_ci NOT NULL,
  `Puntaje` int NOT NULL,
  `IdCliente` int NOT NULL,
  PRIMARY KEY (`IdPuntaje`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_encuestado`
--

DROP TABLE IF EXISTS `checklist_encuestado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_encuestado` (
  `IdEncuestado` int NOT NULL AUTO_INCREMENT,
  `IdEncuesta` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Fecha` date NOT NULL,
  `NumeroEncuesta` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdEncuestado`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_encuestado_propiamente`
--

DROP TABLE IF EXISTS `checklist_encuestado_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_encuestado_propiamente` (
  `IdPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdEncuestado` int NOT NULL,
  `IdPregunta` int NOT NULL,
  `IdPuntaje` int NOT NULL,
  PRIMARY KEY (`IdPropiamente`),
  KEY `IdEncuestado` (`IdEncuestado`),
  CONSTRAINT `checklist_encuestado_propiamente_ibfk_1` FOREIGN KEY (`IdEncuestado`) REFERENCES `checklist_encuestado` (`IdEncuestado`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2966 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_equipo_cafetera`
--

DROP TABLE IF EXISTS `checklist_equipo_cafetera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_equipo_cafetera` (
  `IdCafetera` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdEquipo` int NOT NULL,
  `Cantidad` decimal(10,0) NOT NULL,
  `Existencia` int NOT NULL,
  `Temperatura` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Alarma` int NOT NULL,
  `ConexionElectrica` int NOT NULL,
  `InstalacionDeGas` int NOT NULL,
  PRIMARY KEY (`IdCafetera`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_equipo_cafetera_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=335 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_equipo_congelacion`
--

DROP TABLE IF EXISTS `checklist_equipo_congelacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_equipo_congelacion` (
  `IdCongelacion` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdEquipo` int NOT NULL,
  `Cantidad` int NOT NULL,
  `Existencia` int NOT NULL,
  `Temperatura` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Alarma` int NOT NULL,
  `ConexionElectrica` int NOT NULL,
  `InstalacionDeGas` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdCongelacion`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdOperador` (`IdOperador`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_equipo_congelacion_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=361 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_equipo_exhibidor`
--

DROP TABLE IF EXISTS `checklist_equipo_exhibidor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_equipo_exhibidor` (
  `IdExhibidor` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdEquipo` int NOT NULL,
  `Cantidad` decimal(10,0) NOT NULL,
  `Existencia` int NOT NULL,
  `Temperatura` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Alarma` int NOT NULL,
  `ConexionElectrica` int NOT NULL,
  `InstalacionDeGas` int NOT NULL,
  PRIMARY KEY (`IdExhibidor`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_equipo_exhibidor_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_equipo_fritadoras`
--

DROP TABLE IF EXISTS `checklist_equipo_fritadoras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_equipo_fritadoras` (
  `IdFritadoras` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdEquipo` int NOT NULL,
  `Cantidad` decimal(10,0) NOT NULL,
  `Existencia` int NOT NULL,
  `Temperatura` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Alarma` int NOT NULL,
  `ConexionElectrica` int NOT NULL,
  `InstalacionDeGas` int NOT NULL,
  PRIMARY KEY (`IdFritadoras`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_equipo_fritadoras_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_equipo_horno`
--

DROP TABLE IF EXISTS `checklist_equipo_horno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_equipo_horno` (
  `IdHorno` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdEquipo` int NOT NULL,
  `Cantidad` decimal(10,2) NOT NULL,
  `Existencia` int NOT NULL,
  `Temperatura` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Alarma` int NOT NULL,
  `ConexionElectrica` int NOT NULL,
  `InstalacionDeGas` int NOT NULL,
  PRIMARY KEY (`IdHorno`),
  KEY `checklist_equipo_horno_ibfk_3` (`IdCheckList`),
  CONSTRAINT `checklist_equipo_horno_ibfk_3` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=283 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_equipo_licuadora`
--

DROP TABLE IF EXISTS `checklist_equipo_licuadora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_equipo_licuadora` (
  `IdLicuadora` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdEquipo` int NOT NULL,
  `Cantidad` decimal(10,0) NOT NULL,
  `Existencia` int NOT NULL,
  `Temperatura` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Alarma` int NOT NULL,
  `ConexionElectrica` int NOT NULL,
  `InstalacionDeGas` int NOT NULL,
  PRIMARY KEY (`IdLicuadora`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_equipo_licuadora_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=310 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_equipo_refrigeracion`
--

DROP TABLE IF EXISTS `checklist_equipo_refrigeracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_equipo_refrigeracion` (
  `IdRefrigeracion` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdEquipo` int NOT NULL,
  `Cantidad` decimal(10,0) NOT NULL,
  `Existencia` int NOT NULL,
  `Temperatura` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Alarma` int NOT NULL,
  `ConexionElectrica` int NOT NULL,
  `InstalacionDeGas` int NOT NULL,
  PRIMARY KEY (`IdRefrigeracion`),
  KEY `checklist_equipo_refrigeracion_ibfk_1` (`IdCheckList`),
  CONSTRAINT `checklist_equipo_refrigeracion_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=490 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_infraestructura_aireacondicionado`
--

DROP TABLE IF EXISTS `checklist_infraestructura_aireacondicionado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_infraestructura_aireacondicionado` (
  `IdAireAcondicionado` int NOT NULL AUTO_INCREMENT,
  `IdDetalle` int NOT NULL,
  `IdChecklist` int NOT NULL,
  `Existencia` int NOT NULL,
  `Funciona` int NOT NULL,
  `Temperatura` int NOT NULL,
  PRIMARY KEY (`IdAireAcondicionado`),
  KEY `IdDetalle` (`IdDetalle`),
  KEY `IdChecklist` (`IdChecklist`),
  CONSTRAINT `checklist_infraestructura_aireacondicionado_ibfk_1` FOREIGN KEY (`IdDetalle`) REFERENCES `checklist_mantenimiento_infraestructura` (`IdDetalle`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checklist_infraestructura_aireacondicionado_ibfk_2` FOREIGN KEY (`IdChecklist`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_infraestructura_bano`
--

DROP TABLE IF EXISTS `checklist_infraestructura_bano`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_infraestructura_bano` (
  `IdBano` int NOT NULL AUTO_INCREMENT,
  `IdDetalle` int NOT NULL,
  `IdChecklist` int NOT NULL,
  `Existencia` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Funciona` int NOT NULL,
  PRIMARY KEY (`IdBano`),
  KEY `IdDetalle` (`IdDetalle`),
  KEY `IdChecklist` (`IdChecklist`),
  CONSTRAINT `checklist_infraestructura_bano_ibfk_1` FOREIGN KEY (`IdChecklist`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=202 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_infraestructura_fachada`
--

DROP TABLE IF EXISTS `checklist_infraestructura_fachada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_infraestructura_fachada` (
  `IdFachada` int NOT NULL AUTO_INCREMENT,
  `IdDetalle` int NOT NULL,
  `IdCheckList` int NOT NULL,
  `Estado` int NOT NULL,
  `Limpieza` int NOT NULL,
  `UsoCorrecto` int NOT NULL,
  PRIMARY KEY (`IdFachada`),
  KEY `IdDetalle` (`IdDetalle`),
  KEY `checklist_infraestructura_fachada_ibfk_2` (`IdCheckList`),
  CONSTRAINT `checklist_infraestructura_fachada_ibfk_2` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_infraestructura_jardinera`
--

DROP TABLE IF EXISTS `checklist_infraestructura_jardinera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_infraestructura_jardinera` (
  `IdJardinera` int NOT NULL AUTO_INCREMENT,
  `IdDetalle` int NOT NULL,
  `IdChecklist` int NOT NULL,
  `Existencia` int NOT NULL,
  `Limpieza` int NOT NULL,
  `Estado` int NOT NULL,
  PRIMARY KEY (`IdJardinera`),
  KEY `IdDetalle` (`IdDetalle`),
  KEY `IdChecklist` (`IdChecklist`),
  CONSTRAINT `checklist_infraestructura_jardinera_ibfk_1` FOREIGN KEY (`IdDetalle`) REFERENCES `checklist_mantenimiento_infraestructura` (`IdDetalle`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checklist_infraestructura_jardinera_ibfk_2` FOREIGN KEY (`IdChecklist`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_infraestructura_sectores`
--

DROP TABLE IF EXISTS `checklist_infraestructura_sectores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_infraestructura_sectores` (
  `IdSectores` int NOT NULL AUTO_INCREMENT,
  `IdDetalle` int NOT NULL,
  `IdChecklist` int NOT NULL,
  `NivelDeLimpieza` int NOT NULL,
  PRIMARY KEY (`IdSectores`),
  KEY `IdDetalle` (`IdDetalle`),
  KEY `IdChecklist` (`IdChecklist`),
  CONSTRAINT `checklist_infraestructura_sectores_ibfk_1` FOREIGN KEY (`IdDetalle`) REFERENCES `checklist_mantenimiento_infraestructura` (`IdDetalle`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checklist_infraestructura_sectores_ibfk_2` FOREIGN KEY (`IdChecklist`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_infraestructura_sectorlavado`
--

DROP TABLE IF EXISTS `checklist_infraestructura_sectorlavado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_infraestructura_sectorlavado` (
  `IdSectorLavado` int NOT NULL AUTO_INCREMENT,
  `IdDetalle` int NOT NULL,
  `IdChecklist` int NOT NULL,
  `Existencia` int NOT NULL,
  `Funciona` int NOT NULL,
  PRIMARY KEY (`IdSectorLavado`),
  KEY `IdDetalle` (`IdDetalle`),
  KEY `IdChecklist` (`IdChecklist`),
  CONSTRAINT `checklist_infraestructura_sectorlavado_ibfk_1` FOREIGN KEY (`IdDetalle`) REFERENCES `checklist_mantenimiento_infraestructura` (`IdDetalle`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checklist_infraestructura_sectorlavado_ibfk_2` FOREIGN KEY (`IdChecklist`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_infraestructura_seguridad`
--

DROP TABLE IF EXISTS `checklist_infraestructura_seguridad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_infraestructura_seguridad` (
  `IdSeguridad` int NOT NULL AUTO_INCREMENT,
  `IdDetalle` int NOT NULL,
  `IdCheckList` int NOT NULL,
  `Existencia` int NOT NULL,
  `Estado` int NOT NULL,
  `Robo` int NOT NULL,
  PRIMARY KEY (`IdSeguridad`),
  KEY `IdDetalle` (`IdDetalle`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_infraestructura_seguridad_ibfk_1` FOREIGN KEY (`IdDetalle`) REFERENCES `checklist_mantenimiento_infraestructura` (`IdDetalle`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checklist_infraestructura_seguridad_ibfk_2` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=126 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_mantenimiento_equipo`
--

DROP TABLE IF EXISTS `checklist_mantenimiento_equipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_mantenimiento_equipo` (
  `IdEquipo` int NOT NULL AUTO_INCREMENT,
  `Tipo` varchar(15) COLLATE utf8mb3_unicode_ci NOT NULL,
  `Descripcion` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `Serie` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `FotoEquipo` blob NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdEquipo`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdOperador` (`IdOperador`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `checklist_mantenimiento_equipo_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checklist_mantenimiento_equipo_ibfk_2` FOREIGN KEY (`IdOperador`) REFERENCES `todos_operador` (`IdOperador`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=382 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_mantenimiento_infraestructura`
--

DROP TABLE IF EXISTS `checklist_mantenimiento_infraestructura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_mantenimiento_infraestructura` (
  `IdDetalle` int NOT NULL AUTO_INCREMENT,
  `Area` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `Descripcion` varchar(40) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdDetalle`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdOperador` (`IdOperador`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `checklist_mantenimiento_infraestructura_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `checklist_mantenimiento_infraestructura_ibfk_2` FOREIGN KEY (`IdOperador`) REFERENCES `todos_operador` (`IdOperador`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=230 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_mantenimiento_personal`
--

DROP TABLE IF EXISTS `checklist_mantenimiento_personal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_mantenimiento_personal` (
  `IdPersonal` int NOT NULL AUTO_INCREMENT,
  `IdSector` int NOT NULL,
  `IdCargo` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdPersonal`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_mantenimiento_producto`
--

DROP TABLE IF EXISTS `checklist_mantenimiento_producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_mantenimiento_producto` (
  `IdCheckListProducto` int NOT NULL AUTO_INCREMENT,
  `IdProducto` int NOT NULL,
  `Estado` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdCheckListProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=1364 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_mantenimiento_sucursal`
--

DROP TABLE IF EXISTS `checklist_mantenimiento_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_mantenimiento_sucursal` (
  `IdCheckListSucursal` int NOT NULL AUTO_INCREMENT,
  `IdIdentificadorIdOperador` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdOperadorIncluye` int NOT NULL,
  `FechaInclusion` datetime NOT NULL,
  `IdOperadorActualiza` int DEFAULT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  PRIMARY KEY (`IdCheckListSucursal`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_atencioncliente_aspectofisico`
--

DROP TABLE IF EXISTS `checklist_personal_atencioncliente_aspectofisico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_atencioncliente_aspectofisico` (
  `IdCajerosAspectoFisico` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Nivel_SiNo` int NOT NULL,
  PRIMARY KEY (`IdCajerosAspectoFisico`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_atencioncliente_aspectofisico_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_atencioncliente_aspectos`
--

DROP TABLE IF EXISTS `checklist_personal_atencioncliente_aspectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_atencioncliente_aspectos` (
  `IdAtencionClienteAspectos` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Estado_SiNo` int NOT NULL,
  `Limpieza_SiNo` int NOT NULL,
  PRIMARY KEY (`IdAtencionClienteAspectos`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_atencioncliente_aspectos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_cajeros_aspectofisico`
--

DROP TABLE IF EXISTS `checklist_personal_cajeros_aspectofisico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_cajeros_aspectofisico` (
  `IdCajerosAspectoFisico` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Nivel_SiNo` int NOT NULL,
  PRIMARY KEY (`IdCajerosAspectoFisico`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_cajeros_aspectofisico_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_cajeros_aspectos`
--

DROP TABLE IF EXISTS `checklist_personal_cajeros_aspectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_cajeros_aspectos` (
  `IdCajerosAspectos` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Estado_SiNo` int NOT NULL,
  `Limpieza_SiNo` int NOT NULL,
  PRIMARY KEY (`IdCajerosAspectos`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_cajeros_aspectos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=284 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_camarero_aspectofisico`
--

DROP TABLE IF EXISTS `checklist_personal_camarero_aspectofisico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_camarero_aspectofisico` (
  `IdCamareroAspectoFisico` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Nivel_SiNo` int NOT NULL,
  PRIMARY KEY (`IdCamareroAspectoFisico`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_camarero_aspectofisico_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_camarero_aspectos`
--

DROP TABLE IF EXISTS `checklist_personal_camarero_aspectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_camarero_aspectos` (
  `IdCamareroAspectos` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Estado_SiNo` int NOT NULL,
  `Limpieza_SiNo` int NOT NULL,
  PRIMARY KEY (`IdCamareroAspectos`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_camarero_aspectos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_concepto`
--

DROP TABLE IF EXISTS `checklist_personal_concepto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_concepto` (
  `IdCheckListPersonal` int NOT NULL AUTO_INCREMENT,
  `AreaTrabajo` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `Concepto` varchar(20) COLLATE utf8mb3_unicode_ci NOT NULL,
  `Detalle` varchar(30) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ActivoInactivo` int NOT NULL,
  `IdCliente` int NOT NULL,
  PRIMARY KEY (`IdCheckListPersonal`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_horneros_aspectofisico`
--

DROP TABLE IF EXISTS `checklist_personal_horneros_aspectofisico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_horneros_aspectofisico` (
  `IdHorneroAspectoFisico` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Nivel_SiNo` int NOT NULL,
  PRIMARY KEY (`IdHorneroAspectoFisico`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_horneros_aspectofisico_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_personal_horneros_aspectos`
--

DROP TABLE IF EXISTS `checklist_personal_horneros_aspectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_personal_horneros_aspectos` (
  `IdHorneros` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Estado_SiNo` int NOT NULL,
  `Limpieza_SiNo` int NOT NULL,
  PRIMARY KEY (`IdHorneros`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_personal_horneros_aspectos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_cocidos`
--

DROP TABLE IF EXISTS `checklist_producto_cocidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_cocidos` (
  `IdCocido` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdCocido`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_cocidos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=717 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_congelados`
--

DROP TABLE IF EXISTS `checklist_producto_congelados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_congelados` (
  `IdCongelado` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdCongelado`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_congelados_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1175 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_embotellados`
--

DROP TABLE IF EXISTS `checklist_producto_embotellados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_embotellados` (
  `IdEmbotellados` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdEmbotellados`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_embotellados_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=227 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_jugos`
--

DROP TABLE IF EXISTS `checklist_producto_jugos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_jugos` (
  `IdJugos` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdJugos`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_jugos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_lineaprecocidos`
--

DROP TABLE IF EXISTS `checklist_producto_lineaprecocidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_lineaprecocidos` (
  `IdLinea` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdLinea`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_lineaprecocidos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_precocidos`
--

DROP TABLE IF EXISTS `checklist_producto_precocidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_precocidos` (
  `IdPrecocido` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdPrecocido`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_precocidos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_refrigerados`
--

DROP TABLE IF EXISTS `checklist_producto_refrigerados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_refrigerados` (
  `IdRefrigerado` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdRefrigerado`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_refrigerados_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=857 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_salsas`
--

DROP TABLE IF EXISTS `checklist_producto_salsas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_salsas` (
  `IdSalsas` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdSalsas`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_salsas_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `checklist_producto_secos`
--

DROP TABLE IF EXISTS `checklist_producto_secos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checklist_producto_secos` (
  `IdSecos` int NOT NULL AUTO_INCREMENT,
  `IdCheckList` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Exitencia` int NOT NULL,
  `Vencido` int NOT NULL,
  `Desportillado` int NOT NULL,
  `Aplastado` int NOT NULL,
  `MalAlmacen` int NOT NULL,
  PRIMARY KEY (`IdSecos`),
  KEY `IdCheckList` (`IdCheckList`),
  CONSTRAINT `checklist_producto_secos_ibfk_1` FOREIGN KEY (`IdCheckList`) REFERENCES `checklist_definicion_inicio_sucursales` (`IdInicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_comprobante_egreso`
--

DROP TABLE IF EXISTS `conta_comprobante_egreso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_comprobante_egreso` (
  `IdEgreso` int NOT NULL AUTO_INCREMENT,
  `IdDiario` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdCuentaDebe` int NOT NULL,
  `IdCuentaHaber` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Glosa` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `TotalBolivianos` decimal(10,2) NOT NULL,
  `NumeroEgreso` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdEgreso`),
  KEY `conta_comprobante_egreso_ibfk_1` (`IdCliente`),
  KEY `conta_comprobante_egreso_ibfk_2` (`IdSucursal`),
  KEY `conta_comprobante_egreso_ibfk_3` (`IdOperador`),
  KEY `conta_comprobante_egreso_ibfk_4` (`IdIdentificador`),
  KEY `conta_comprobante_egreso_ibfk_5` (`IdCuentaDebe`),
  KEY `conta_comprobante_egreso_ibfk_6` (`IdFecha`)
) ENGINE=InnoDB AUTO_INCREMENT=4265 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_comprobante_ingreso`
--

DROP TABLE IF EXISTS `conta_comprobante_ingreso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_comprobante_ingreso` (
  `IdIngreso` int NOT NULL AUTO_INCREMENT,
  `IdDiario` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdCuentaDebe` int NOT NULL,
  `IdCuentaHaber` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Glosa` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `TotalBolivianos` decimal(10,2) NOT NULL,
  `NumeroIngreso` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdIngreso`)
) ENGINE=InnoDB AUTO_INCREMENT=1397 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_cuenta`
--

DROP TABLE IF EXISTS `conta_cuenta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_cuenta` (
  `IdCuenta` int NOT NULL AUTO_INCREMENT,
  `Cuenta` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `Descripcion` text COLLATE utf8mb4_general_ci NOT NULL,
  `TipoDeCuenta` text COLLATE utf8mb4_general_ci NOT NULL,
  `IdMoneda` int NOT NULL,
  `ActivoFijo` float NOT NULL,
  `AbiertoCerrado` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdOperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorEdita` int NOT NULL,
  `FechaActualiza` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`IdCuenta`),
  KEY `IdMoneda` (`IdMoneda`),
  KEY `IdCliente` (`IdCliente`,`IdOperadorIngreso`,`IdOperadorEdita`),
  KEY `IdOperadorIngreso` (`IdOperadorIngreso`),
  KEY `IdOperadorEdita` (`IdOperadorEdita`)
) ENGINE=InnoDB AUTO_INCREMENT=2947 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_cuenta_sucursales`
--

DROP TABLE IF EXISTS `conta_cuenta_sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_cuenta_sucursales` (
  `IdCuentaSucursales` int NOT NULL AUTO_INCREMENT,
  `IdCuenta` int NOT NULL,
  `Cuenta` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `DinamicaCuenta` varchar(300) COLLATE utf8mb4_general_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdCuentaSucursales`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdCuenta` (`IdCuenta`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_diario`
--

DROP TABLE IF EXISTS `conta_diario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_diario` (
  `IdDiario` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `IdTipoDiario` int NOT NULL,
  `NumeroDiario` bigint NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `Contabilizado` tinyint(1) NOT NULL,
  `IdOperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorEdita` int NOT NULL,
  `FechaEdita` datetime DEFAULT NULL,
  PRIMARY KEY (`IdDiario`),
  UNIQUE KEY `IdDiario` (`IdDiario`),
  KEY `IdTipoDiario` (`IdTipoDiario`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdOperadorIngreso` (`IdOperadorIngreso`),
  KEY `IdOperadorEdita` (`IdOperadorEdita`),
  KEY `IdCliente` (`IdCliente`)
) ENGINE=InnoDB AUTO_INCREMENT=14574 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_diario_propiamente`
--

DROP TABLE IF EXISTS `conta_diario_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_diario_propiamente` (
  `IdContaPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdDiario` int NOT NULL,
  `IdCuenta` int NOT NULL,
  `Glosa` varchar(400) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  `D_H` text CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  `MontoBolivianos` decimal(10,2) NOT NULL,
  `TipoCambio` decimal(10,6) NOT NULL,
  `MontoOtraMoneda` decimal(10,2) NOT NULL,
  `IdIdentificador` int NOT NULL,
  `IdActividad` int NOT NULL,
  `Deducible` tinytext COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`IdContaPropiamente`),
  KEY `IdDiario` (`IdDiario`),
  KEY `IdCuenta` (`IdCuenta`),
  KEY `IdIdentificador` (`IdIdentificador`),
  KEY `IdActividad` (`IdActividad`)
) ENGINE=InnoDB AUTO_INCREMENT=121684 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_factorcambio`
--

DROP TABLE IF EXISTS `conta_factorcambio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_factorcambio` (
  `IdFactor` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `IdMoneda` int NOT NULL,
  `FactorCambio` decimal(10,6) NOT NULL,
  PRIMARY KEY (`IdFactor`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdMoneda` (`IdMoneda`),
  KEY `FactorCambio` (`FactorCambio`)
) ENGINE=InnoDB AUTO_INCREMENT=48136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_importa_cuentasporcobrar`
--

DROP TABLE IF EXISTS `conta_importa_cuentasporcobrar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_importa_cuentasporcobrar` (
  `Fecha` date NOT NULL,
  `Factura` int NOT NULL,
  `NIT` int NOT NULL,
  `Nombre` text COLLATE utf8mb4_general_ci NOT NULL,
  `Placa` text COLLATE utf8mb4_general_ci NOT NULL,
  `Monto` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_informe_estadocuentas`
--

DROP TABLE IF EXISTS `conta_informe_estadocuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_informe_estadocuentas` (
  `IdClientesCuentas` int NOT NULL AUTO_INCREMENT,
  `IdNITClienteEmpresa` int NOT NULL,
  `IdCuenta` int NOT NULL,
  `IdClienteSistema` int NOT NULL,
  PRIMARY KEY (`IdClientesCuentas`),
  KEY `IdNIT` (`IdNITClienteEmpresa`),
  KEY `IdCuenta` (`IdCuenta`),
  KEY `IdClienteSistema` (`IdClienteSistema`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_moneda`
--

DROP TABLE IF EXISTS `conta_moneda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_moneda` (
  `IdMoneda` int NOT NULL AUTO_INCREMENT,
  `Moneda` text COLLATE utf8mb4_general_ci NOT NULL,
  `Abreviacion` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdMoneda`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_tipodiario`
--

DROP TABLE IF EXISTS `conta_tipodiario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_tipodiario` (
  `IdTipoDiario` int NOT NULL AUTO_INCREMENT,
  `TipoDiario` text COLLATE utf8mb4_general_ci NOT NULL,
  `Abreviacion` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdTipoDiario`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conta_ultimaactualizacion_diferenciacambio`
--

DROP TABLE IF EXISTS `conta_ultimaactualizacion_diferenciacambio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conta_ultimaactualizacion_diferenciacambio` (
  `IdDiferenciaDeCambio` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdDiferenciaDeCambio`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdOperador` (`IdOperador`),
  KEY `IdFecha` (`IdFecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_compras`
--

DROP TABLE IF EXISTS `impuestos_compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_compras` (
  `IdCompras` int NOT NULL AUTO_INCREMENT,
  `NumeroCorrelativo` int NOT NULL,
  `IdDiario` int NOT NULL,
  `IdCuenta` int NOT NULL,
  `IdAlmacen` int NOT NULL,
  `IdTipoFactura` int NOT NULL,
  `NumeroFactura` int NOT NULL,
  `IdNIT` int NOT NULL,
  `NumeroDUI` bigint NOT NULL,
  `NumeroAutorizacion` bigint NOT NULL,
  `IdFecha` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `FechaIngreso` datetime NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `ImporteFactura` decimal(10,2) NOT NULL,
  `ImporteExcento` decimal(10,2) NOT NULL,
  `ImporteDescuentos` decimal(10,2) NOT NULL,
  `CodigoControl` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `Observacion` varchar(250) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdCompras`),
  KEY `IdAlmacen` (`IdAlmacen`),
  KEY `impuestos_compras_ibfk_2` (`IdTipoFactura`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdNIT` (`IdNIT`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdOperadorIngresa` (`IdOperadorIngresa`)
) ENGINE=InnoDB AUTO_INCREMENT=2587 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_compras_detalle`
--

DROP TABLE IF EXISTS `impuestos_compras_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_compras_detalle` (
  `IdComprasDetalle` int NOT NULL AUTO_INCREMENT,
  `IdCompras` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Unidades` decimal(10,4) NOT NULL,
  `TotalBolivianos` decimal(10,2) NOT NULL,
  `Precio` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdComprasDetalle`),
  KEY `IdProducto` (`IdProducto`),
  KEY `impuestos_compras_detalle_ibfk_1` (`IdCompras`)
) ENGINE=InnoDB AUTO_INCREMENT=8839 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_compras_tipofactura`
--

DROP TABLE IF EXISTS `impuestos_compras_tipofactura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_compras_tipofactura` (
  `IdTipoFactura` int NOT NULL AUTO_INCREMENT,
  `FacturaRecibo` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `Numero` int NOT NULL,
  `Descripcion` varchar(400) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdTipoFactura`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas`
--

DROP TABLE IF EXISTS `impuestos_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas` (
  `IdVentas` int NOT NULL AUTO_INCREMENT,
  `FechaVenta` datetime NOT NULL,
  `NumeroFactura` int NOT NULL,
  `NumeroAutorizacion` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `IdEstado` int NOT NULL,
  `IdNIT` int NOT NULL,
  `ImporteVenta` decimal(10,2) NOT NULL,
  `ImporteExcento` decimal(10,2) NOT NULL,
  `ImporteExportaciones` decimal(10,2) NOT NULL,
  `ImporteTasaCero` decimal(10,2) NOT NULL,
  `ImporteDescuentos` decimal(10,2) NOT NULL,
  `CodigoControl` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdClienteSucursal` int NOT NULL,
  `FechaUltimaActualizcion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorActualiza` int NOT NULL,
  `Entrega` tinyint(1) NOT NULL,
  `TicketDia` int NOT NULL,
  `FechaEntrega` datetime DEFAULT NULL,
  `LiquidadoVendedor` bigint NOT NULL,
  `LugarVenta` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `IdComisionista` int NOT NULL,
  `Observacion` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdVentas`)
) ENGINE=InnoDB AUTO_INCREMENT=79943 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_comisionitas`
--

DROP TABLE IF EXISTS `impuestos_ventas_comisionitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_comisionitas` (
  `IdComisionista` int NOT NULL AUTO_INCREMENT,
  `IdIdentificador` int NOT NULL,
  `Comision` decimal(10,2) NOT NULL,
  `IdCliente` int NOT NULL,
  PRIMARY KEY (`IdComisionista`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_detalle`
--

DROP TABLE IF EXISTS `impuestos_ventas_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_detalle` (
  `idventasdetalle` int NOT NULL AUTO_INCREMENT,
  `idventas` int NOT NULL,
  `IdVentaGrupo` int NOT NULL,
  `idrelacionventainventario` int NOT NULL,
  `unidades` decimal(10,2) NOT NULL,
  `preciounidades` decimal(10,2) NOT NULL,
  `totalbolivianos` decimal(10,2) NOT NULL,
  `PorcentajeDescuento` decimal(2,0) NOT NULL,
  `Descuento` decimal(2,0) NOT NULL,
  `TotalBolivianosFacturado` decimal(2,0) NOT NULL,
  `entregado` tinyint(1) NOT NULL,
  `fechaentrega` datetime DEFAULT NULL,
  PRIMARY KEY (`idventasdetalle`),
  KEY `impuestos_ventas_detalle_ibfk_2` (`idrelacionventainventario`),
  KEY `IdVentaGrupo` (`IdVentaGrupo`),
  KEY `idventas` (`idventas`)
) ENGINE=InnoDB AUTO_INCREMENT=221766 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_dosificacion`
--

DROP TABLE IF EXISTS `impuestos_ventas_dosificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_dosificacion` (
  `IdDosificacion` int NOT NULL AUTO_INCREMENT,
  `IdTipoDeDosificacion` int NOT NULL,
  `Autorizacion` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `LlaveDosificacion` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `Actividad` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `PrimeraLeyenda` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `SegundaLeyenda` varchar(300) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `FechaActivacion` date NOT NULL,
  `FechaLimiteEmision` date NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdDosificacion`),
  UNIQUE KEY `Autorizacion` (`Autorizacion`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_dosificacion_tipodefacturacion`
--

DROP TABLE IF EXISTS `impuestos_ventas_dosificacion_tipodefacturacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_dosificacion_tipodefacturacion` (
  `IdTipoFacturacion` int NOT NULL AUTO_INCREMENT,
  `Detalle` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdTipoFacturacion`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_estado`
--

DROP TABLE IF EXISTS `impuestos_ventas_estado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_estado` (
  `IdVentasEstado` int NOT NULL AUTO_INCREMENT,
  `Abreviacion` varchar(1) COLLATE utf8mb4_general_ci NOT NULL,
  `Detalle` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdVentasEstado`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_liquidacion`
--

DROP TABLE IF EXISTS `impuestos_ventas_liquidacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_liquidacion` (
  `IdVentasLiquidacion` int NOT NULL AUTO_INCREMENT,
  `IdVentas` int NOT NULL,
  `IdDiario` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `IdCuenta` int NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  `EfectivoRecibido` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdVentasLiquidacion`),
  KEY `IdVentas` (`IdVentas`),
  CONSTRAINT `impuestos_ventas_liquidacion_ibfk_1` FOREIGN KEY (`IdVentas`) REFERENCES `impuestos_ventas` (`IdVentas`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78653 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_liquidacion_concepto`
--

DROP TABLE IF EXISTS `impuestos_ventas_liquidacion_concepto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_liquidacion_concepto` (
  `IdConceptoLiquidacion` int NOT NULL AUTO_INCREMENT,
  `Concepto` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `IdCuenta` int NOT NULL,
  `IdCliente` int NOT NULL,
  PRIMARY KEY (`IdConceptoLiquidacion`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdCuenta` (`IdCuenta`),
  CONSTRAINT `impuestos_ventas_liquidacion_concepto_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_liquidacion_supervisor`
--

DROP TABLE IF EXISTS `impuestos_ventas_liquidacion_supervisor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_liquidacion_supervisor` (
  `IdLiquidacionSupervisor` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `vEntasVendedores` decimal(10,2) NOT NULL,
  `vEntasConfirma` decimal(10,2) NOT NULL,
  `eFectivoBolivianosVendedores` decimal(10,2) NOT NULL,
  `eFectivoBolivianosConfirma` decimal(10,2) NOT NULL,
  `cLientesVendedores` decimal(10,2) NOT NULL,
  `cLientesConfirmaVendedores` decimal(10,2) NOT NULL,
  `pOrCobrarPersonalVendedores` decimal(10,2) NOT NULL,
  `pOrCobrarPersonalConfirma` decimal(10,0) NOT NULL,
  `tArjetaATCVendedores` decimal(10,2) NOT NULL,
  `tArjetaATCconfirma` decimal(10,2) NOT NULL,
  `dIfVendedorVendedores` decimal(10,2) NOT NULL,
  `dIfVendedorConfirma` decimal(10,2) NOT NULL,
  `LiquidadoAdministrador` tinyint(1) NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `iDcliente` int NOT NULL,
  `iDsucursal` int NOT NULL,
  `iDtipoOperadorSupervisor` int NOT NULL,
  `iDoperadorSupervisor` int NOT NULL,
  PRIMARY KEY (`IdLiquidacionSupervisor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_liquidacion_vendedor`
--

DROP TABLE IF EXISTS `impuestos_ventas_liquidacion_vendedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_liquidacion_vendedor` (
  `iDLiquidacionVendedor` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `IdDiario` int NOT NULL,
  `vEntas` decimal(10,2) NOT NULL,
  `vEntasConfirma` decimal(10,2) NOT NULL,
  `eFectivoBolivianos` decimal(10,2) NOT NULL,
  `eFectivoBolivianosConfirma` decimal(10,2) NOT NULL,
  `cLientes` decimal(10,2) NOT NULL,
  `cLientesConfirma` decimal(10,2) NOT NULL,
  `pOrCobrarPersonal` decimal(10,2) NOT NULL,
  `pOrCobrarPersonalConfirma` decimal(10,2) NOT NULL,
  `tArjetaATC` decimal(10,2) NOT NULL,
  `tArjetaATCconfirma` decimal(10,2) NOT NULL,
  `dIfVendedor` decimal(10,2) NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `dIfVendedorConfirma` decimal(10,2) NOT NULL,
  `LiquidadoSupervisor` float NOT NULL,
  `iDcliente` int NOT NULL,
  `iDsucursal` int NOT NULL,
  `iDtipoOperadorVentas` int NOT NULL,
  `iDoperadorVendedor` int NOT NULL,
  PRIMARY KEY (`iDLiquidacionVendedor`),
  KEY `iDcliente` (`iDcliente`),
  KEY `iDsucursal` (`iDsucursal`),
  CONSTRAINT `impuestos_ventas_liquidacion_vendedor_ibfk_1` FOREIGN KEY (`iDcliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `impuestos_ventas_liquidacion_vendedor_ibfk_2` FOREIGN KEY (`iDsucursal`) REFERENCES `todos_cliente_sucursal` (`IdClienteSucursal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4715 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impuestos_ventas_lugar_venta`
--

DROP TABLE IF EXISTS `impuestos_ventas_lugar_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impuestos_ventas_lugar_venta` (
  `IdLugar` int NOT NULL AUTO_INCREMENT,
  `Orden` int NOT NULL,
  `Lugar` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdLugar`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `impuestos_ventas_lugar_venta_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `impuestos_ventas_lugar_venta_ibfk_2` FOREIGN KEY (`IdSucursal`) REFERENCES `todos_cliente_sucursal` (`IdClienteSucursal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_ajustesprincipal`
--

DROP TABLE IF EXISTS `inventario_ajustesprincipal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_ajustesprincipal` (
  `IdAjustesPrincipal` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `ConceptoOperacion` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `IdTipoOperacion` int NOT NULL,
  `NumeroCorrelativo` int NOT NULL,
  `IdAlmacen` int NOT NULL,
  `IdRealizadoPor` int NOT NULL,
  `IdAutorizadoPor` int NOT NULL,
  `Explicacion` text COLLATE utf8mb4_general_ci,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorEdita` int NOT NULL,
  `FechaActualiza` datetime NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdAjustesPrincipal`),
  KEY `IdFecha` (`IdFecha`,`IdTipoOperacion`,`IdAlmacen`,`IdRealizadoPor`,`IdAutorizadoPor`,`IdOperadorIngresa`,`IdOperadorEdita`),
  KEY `IdTipoOperacion` (`IdTipoOperacion`),
  KEY `IdAlmacen` (`IdAlmacen`),
  KEY `IdRealizadoPor` (`IdRealizadoPor`),
  KEY `IdAutorizadoPor` (`IdAutorizadoPor`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `inventario_ajustesprincipal_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inventario_ajustesprincipal_ibfk_2` FOREIGN KEY (`IdSucursal`) REFERENCES `todos_cliente_sucursal` (`IdClienteSucursal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2857 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_ajustespropiamente`
--

DROP TABLE IF EXISTS `inventario_ajustespropiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_ajustespropiamente` (
  `IdAjustesPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdAjustesPrincipal` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Unidades` decimal(10,2) NOT NULL,
  `Bolivianos` decimal(10,0) NOT NULL,
  UNIQUE KEY `IdAjustesPropiamente` (`IdAjustesPropiamente`),
  KEY `IdProducto` (`IdProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=7617 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_almacen`
--

DROP TABLE IF EXISTS `inventario_almacen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_almacen` (
  `IdAlmacen` int NOT NULL AUTO_INCREMENT,
  `Almacen` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `AlmacenPrincipal` tinyint NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdAlmacen`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `inventario_almacen_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inventario_almacen_ibfk_2` FOREIGN KEY (`IdSucursal`) REFERENCES `todos_cliente_sucursal` (`IdClienteSucursal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_entregarecepcion`
--

DROP TABLE IF EXISTS `inventario_entregarecepcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_entregarecepcion` (
  `IdEntregaRecepcion` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `NumeroEntregaRecepcion` int NOT NULL,
  `IdAlmacenEntrega` int NOT NULL,
  `IdAlmacenRecepciona` int NOT NULL,
  `Detalle` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `ActivoInactivo` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  PRIMARY KEY (`IdEntregaRecepcion`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `inventario_entregarecepcion_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inventario_entregarecepcion_ibfk_2` FOREIGN KEY (`IdSucursal`) REFERENCES `todos_cliente_sucursal` (`IdClienteSucursal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_entregarecepcion_propiamente`
--

DROP TABLE IF EXISTS `inventario_entregarecepcion_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_entregarecepcion_propiamente` (
  `IdEntregaRecepcionPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdEntregaRecepcion` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Unidades` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdEntregaRecepcionPropiamente`),
  KEY `IdEntregaRecepcion` (`IdEntregaRecepcion`)
) ENGINE=InnoDB AUTO_INCREMENT=6367 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_fisicorealizado`
--

DROP TABLE IF EXISTS `inventario_fisicorealizado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_fisicorealizado` (
  `IdFisico` int NOT NULL AUTO_INCREMENT,
  `NumeroCorrelativo` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdAlmacen` int NOT NULL,
  `IdRealizadoPor` int NOT NULL,
  `IdEncargadoSucursal` int NOT NULL,
  `Observacion` varchar(200) COLLATE utf8mb3_unicode_ci NOT NULL,
  `ActivoInactivo` bit(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdFisico`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `inventario_fisicorealizado_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inventario_fisicorealizado_ibfk_2` FOREIGN KEY (`IdSucursal`) REFERENCES `todos_cliente_sucursal` (`IdClienteSucursal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_fisicorealizado_detalle`
--

DROP TABLE IF EXISTS `inventario_fisicorealizado_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_fisicorealizado_detalle` (
  `IdFisicoPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdFisico` int NOT NULL,
  `IdProducto` int NOT NULL,
  `UnidadesSaldo` decimal(10,3) NOT NULL,
  `Unidades` decimal(10,2) NOT NULL,
  `UnidadesAjuste` decimal(10,3) NOT NULL,
  PRIMARY KEY (`IdFisicoPropiamente`),
  KEY `inventario_fisicorealizado_detalle_ibfk_1` (`IdFisico`),
  KEY `inventario_fisicorealizado_detalle_ibfk_2` (`IdProducto`),
  CONSTRAINT `inventario_fisicorealizado_detalle_ibfk_1` FOREIGN KEY (`IdFisico`) REFERENCES `inventario_fisicorealizado` (`IdFisico`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inventario_fisicorealizado_detalle_ibfk_2` FOREIGN KEY (`IdProducto`) REFERENCES `inventario_productodetalle` (`IdProducto`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8615 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_ingresosprincipal`
--

DROP TABLE IF EXISTS `inventario_ingresosprincipal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_ingresosprincipal` (
  `IdIngresosPrincipal` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `NumeroCorrelativo` int NOT NULL,
  `IdDiario` int NOT NULL,
  `IdResponsableCompra` int NOT NULL,
  `IdAlmacen` int NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `Fechaingreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ActivoInactivo` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdIngresosPrincipal`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdResponsableCompra` (`IdResponsableCompra`),
  KEY `IdOperadorIngresa` (`IdOperadorIngresa`),
  KEY `IdOperadorActualiza` (`IdOperadorActualiza`),
  KEY `IdOperadorActualiza_2` (`IdOperadorActualiza`),
  KEY `IdCliente_2` (`IdCliente`),
  KEY `IdSucursal_2` (`IdSucursal`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdFecha_2` (`IdFecha`)
) ENGINE=InnoDB AUTO_INCREMENT=1098 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_ingresospropiamente`
--

DROP TABLE IF EXISTS `inventario_ingresospropiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_ingresospropiamente` (
  `IdIngresosPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdIngresosPrincipal` int NOT NULL,
  `IdProveedor` int NOT NULL,
  `Factura` tinytext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `Numero` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Unidades` decimal(10,4) NOT NULL,
  `TotalCompra` decimal(10,2) NOT NULL,
  `Precio` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdIngresosPropiamente`),
  KEY `IdIngresosPrincipal` (`IdIngresosPrincipal`),
  KEY `IdProveedor` (`IdProveedor`),
  KEY `IdProducto` (`IdProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=2274 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_producto_estado`
--

DROP TABLE IF EXISTS `inventario_producto_estado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_producto_estado` (
  `IdEstado` int NOT NULL AUTO_INCREMENT,
  `Estado` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IOperador` int NOT NULL,
  PRIMARY KEY (`IdEstado`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_producto_linea`
--

DROP TABLE IF EXISTS `inventario_producto_linea`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_producto_linea` (
  `IdLinea` int NOT NULL AUTO_INCREMENT,
  `IdEstado` int NOT NULL,
  `Linea` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdLinea`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdOperador` (`IdOperador`),
  KEY `IdEstado` (`IdEstado`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_productodetalle`
--

DROP TABLE IF EXISTS `inventario_productodetalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_productodetalle` (
  `IdProducto` int NOT NULL AUTO_INCREMENT,
  `IdGrupoAnalisis` int NOT NULL,
  `IdLineaProducto` int NOT NULL,
  `IdGrupoProducto` int NOT NULL,
  `IdEstadoProducto` int NOT NULL,
  `IdUnidadMedida` int NOT NULL,
  `OrdenInformes` int NOT NULL,
  `Codigo` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `Descripcion` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `Precio` decimal(10,2) NOT NULL,
  `ActivoInactivo` int NOT NULL,
  `CkeckListRuta` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorInserta` int NOT NULL,
  `FechaInserta` datetime NOT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  `CierrePermanente` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdProducto`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdGrupoAnalisis` (`IdGrupoAnalisis`),
  KEY `IdGrupoProducto` (`IdGrupoProducto`),
  KEY `IdUnidadMedida` (`IdUnidadMedida`),
  KEY `IdLineaProducto` (`IdLineaProducto`),
  KEY `IdEstadoProducto` (`IdEstadoProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=2592 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_productodetalle_precio_costo`
--

DROP TABLE IF EXISTS `inventario_productodetalle_precio_costo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_productodetalle_precio_costo` (
  `IdPrecioCosto` int NOT NULL AUTO_INCREMENT,
  `IdProducto` int NOT NULL,
  `IdFecha` int NOT NULL,
  `PrecioCosto` decimal(10,2) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdPrecioCosto`),
  KEY `IdProducto` (`IdProducto`),
  CONSTRAINT `inventario_productodetalle_precio_costo_ibfk_1` FOREIGN KEY (`IdProducto`) REFERENCES `inventario_productodetalle` (`IdProducto`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=145 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_productogrupo`
--

DROP TABLE IF EXISTS `inventario_productogrupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_productogrupo` (
  `IdProductoGrupo` int NOT NULL AUTO_INCREMENT,
  `IdLinea` int NOT NULL,
  `Grupo` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdProductoGrupo`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_productogrupoanalisis`
--

DROP TABLE IF EXISTS `inventario_productogrupoanalisis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_productogrupoanalisis` (
  `IdGrupoAnalisis` int NOT NULL AUTO_INCREMENT,
  `Grupo` tinytext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdGrupoAnalisis`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_propiamente`
--

DROP TABLE IF EXISTS `inventario_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_propiamente` (
  `IdInventarioPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdTipoDeOperacion` int NOT NULL,
  `IdDocumento` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdAlmacen` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Glosa` text CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `D_H` tinytext CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `Unidades` decimal(10,4) NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdInventarioPropiamente`),
  KEY `IdTipoOperacion` (`IdDocumento`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdAlmacen` (`IdAlmacen`),
  KEY `IdProducto` (`IdProducto`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdTipoDeOperacion` (`IdTipoDeOperacion`)
) ENGINE=InnoDB AUTO_INCREMENT=394501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_proveedor`
--

DROP TABLE IF EXISTS `inventario_proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_proveedor` (
  `IdProveedor` int NOT NULL AUTO_INCREMENT,
  `IdNIT` int NOT NULL,
  `Telefono` int NOT NULL,
  `Direccion` tinytext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdProveedor`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdNIT` (`IdNIT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_relacion_ventainventario`
--

DROP TABLE IF EXISTS `inventario_relacion_ventainventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_relacion_ventainventario` (
  `IdDetalleProducto` int NOT NULL AUTO_INCREMENT,
  `IdVentaGrupo` int NOT NULL,
  `Codigo` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `Detalle` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `NombreCortoFactura` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `PrecioVenta` decimal(11,2) NOT NULL,
  `ActivoInactivo` int NOT NULL,
  `ImagenProducto` blob,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorInserta` int NOT NULL,
  `FechaInserta` datetime NOT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  `CierrePermanente` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdDetalleProducto`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdVentaGrupo` (`IdVentaGrupo`)
) ENGINE=InnoDB AUTO_INCREMENT=819 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_relacion_ventainventario_detalle`
--

DROP TABLE IF EXISTS `inventario_relacion_ventainventario_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_relacion_ventainventario_detalle` (
  `IdDetalleProductoPorcion` int NOT NULL AUTO_INCREMENT,
  `IdDetalleProducto` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Porcion` decimal(11,6) NOT NULL,
  `IdOperadorInserta` int NOT NULL,
  `FechaInserta` datetime NOT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  PRIMARY KEY (`IdDetalleProductoPorcion`),
  KEY `IdDetalleProducto` (`IdDetalleProducto`),
  KEY `IdProducto` (`IdProducto`),
  CONSTRAINT `inventario_relacion_ventainventario_detalle_ibfk_1` FOREIGN KEY (`IdDetalleProducto`) REFERENCES `inventario_relacion_ventainventario` (`IdDetalleProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=3058 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_relacion_ventainventario_grupouno`
--

DROP TABLE IF EXISTS `inventario_relacion_ventainventario_grupouno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_relacion_ventainventario_grupouno` (
  `IdVentaGrupo` int NOT NULL AUTO_INCREMENT,
  `Detalle` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `Orden` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdVentaGrupo`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_relacion_ventainventario_preciomayorista`
--

DROP TABLE IF EXISTS `inventario_relacion_ventainventario_preciomayorista`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_relacion_ventainventario_preciomayorista` (
  `IdPrecioMayorista` int NOT NULL AUTO_INCREMENT,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Precio` decimal(10,2) NOT NULL,
  `IdOperadorInserta` int NOT NULL,
  `FechaInserta` datetime NOT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  PRIMARY KEY (`IdPrecioMayorista`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdIdentificador` (`IdIdentificador`),
  KEY `IdProducto` (`IdProducto`),
  KEY `IdSucursal` (`IdSucursal`),
  CONSTRAINT `inventario_relacion_ventainventario_preciomayorista_ibfk_1` FOREIGN KEY (`IdProducto`) REFERENCES `inventario_relacion_ventainventario` (`IdDetalleProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=499 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_relacion_ventainventario_preciosucursal`
--

DROP TABLE IF EXISTS `inventario_relacion_ventainventario_preciosucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_relacion_ventainventario_preciosucursal` (
  `IdPrecio` int NOT NULL AUTO_INCREMENT,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `PrecioDiferenciadoA` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `IdProducto` int NOT NULL,
  `Precio` decimal(10,2) NOT NULL,
  `IdOperadorInserta` int NOT NULL,
  `FechaInserta` datetime NOT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  PRIMARY KEY (`IdPrecio`),
  KEY `IdCliiente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdProducto` (`IdProducto`),
  CONSTRAINT `inventario_relacion_ventainventario_preciosucursal_ibfk_1` FOREIGN KEY (`IdProducto`) REFERENCES `inventario_relacion_ventainventario` (`IdDetalleProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_salidas_otrosegresos`
--

DROP TABLE IF EXISTS `inventario_salidas_otrosegresos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_salidas_otrosegresos` (
  `IdSalidaOtrosEgresos` int NOT NULL AUTO_INCREMENT,
  `IdSalidasPrincipal` int NOT NULL,
  `IdCuenta` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Monto` decimal(10,2) NOT NULL,
  `TipoDeCambio` decimal(10,2) NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  `GlosaEgresos` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  UNIQUE KEY `IdSalidaOtrosEgresos` (`IdSalidaOtrosEgresos`),
  KEY `IdSalidaPrincipal` (`IdSalidasPrincipal`),
  KEY `IdCuenta` (`IdCuenta`),
  KEY `IdIdentificador` (`IdIdentificador`)
) ENGINE=InnoDB AUTO_INCREMENT=4453 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_salidas_otrosingresos`
--

DROP TABLE IF EXISTS `inventario_salidas_otrosingresos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_salidas_otrosingresos` (
  `IdSalidasOtrosIngresos` int NOT NULL AUTO_INCREMENT,
  `IdSalidasPrincipal` int NOT NULL,
  `IdCuenta` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Monto` decimal(10,2) NOT NULL,
  `TipoDeCambio` decimal(10,2) NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  `GlosaIngresos` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,
  UNIQUE KEY `IdSalidasOtrosIngresos` (`IdSalidasOtrosIngresos`),
  KEY `IdSalidasPrincipal` (`IdSalidasPrincipal`),
  KEY `IdCuenta` (`IdCuenta`),
  KEY `IdIdentificador` (`IdIdentificador`)
) ENGINE=InnoDB AUTO_INCREMENT=879 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_salidasliquidacion`
--

DROP TABLE IF EXISTS `inventario_salidasliquidacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_salidasliquidacion` (
  `IdSalidaLiquidacion` int NOT NULL AUTO_INCREMENT,
  `IdSalidasPrincipal` int NOT NULL,
  `IdCuenta` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Monto` decimal(10,2) NOT NULL,
  `TipoDeCambio` decimal(10,2) NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdSalidaLiquidacion`),
  KEY `IdCuenta` (`IdCuenta`),
  KEY `IdIdentificador` (`IdIdentificador`),
  KEY `Bolivianos` (`Bolivianos`)
) ENGINE=InnoDB AUTO_INCREMENT=569 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_salidasprincipal`
--

DROP TABLE IF EXISTS `inventario_salidasprincipal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_salidasprincipal` (
  `IdSalidaPrincipal` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `NumeroCorrelativo` int NOT NULL,
  `IdDiario` int NOT NULL,
  `TotalVentaFacturada` decimal(10,2) NOT NULL,
  `IdoperadorIngresa` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ActivoInactivo` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdSalidaPrincipal`),
  KEY `IdDiario` (`IdDiario`)
) ENGINE=InnoDB AUTO_INCREMENT=444 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_salidaspropiamente`
--

DROP TABLE IF EXISTS `inventario_salidaspropiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_salidaspropiamente` (
  `IdSalidaPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdSalidasPrincipal` int NOT NULL,
  `IdRelacionVentaInventario` int NOT NULL,
  `Unidades` decimal(10,6) NOT NULL,
  `PrecioUnidad` decimal(10,4) NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdSalidaPropiamente`),
  KEY `IdRelacionVentaInventario` (`IdSalidasPrincipal`)
) ENGINE=InnoDB AUTO_INCREMENT=16461 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_tipooperacion`
--

DROP TABLE IF EXISTS `inventario_tipooperacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_tipooperacion` (
  `IdTipoOperacion` int NOT NULL AUTO_INCREMENT,
  `Detalle` tinytext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `Concepto` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `ActivoInactivo` int NOT NULL,
  `IdCliente` int NOT NULL,
  PRIMARY KEY (`IdTipoOperacion`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventario_unidadmedida`
--

DROP TABLE IF EXISTS `inventario_unidadmedida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_unidadmedida` (
  `IdUnidadMedida` int NOT NULL AUTO_INCREMENT,
  `UnidadMedida` tinytext CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  PRIMARY KEY (`IdUnidadMedida`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_administrador`
--

DROP TABLE IF EXISTS `menu_administrador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_administrador` (
  `Id` int NOT NULL AUTO_INCREMENT,
  `Description` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `Link` varchar(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `Parent` int NOT NULL,
  `Node_Order` int NOT NULL,
  `Informes` int NOT NULL,
  `Digitador` int NOT NULL,
  `Supervisor` int NOT NULL,
  `Administrador` int NOT NULL,
  `EstadoCuenta` int NOT NULL,
  `VentaMostrador` int NOT NULL,
  `VentaRestaurante` int NOT NULL,
  `VentaSupervisor` int NOT NULL,
  `VentaAdministracion` int NOT NULL,
  `VentaMayorista` int NOT NULL,
  `MonitorCocina` int NOT NULL,
  `ComercialGerente` int NOT NULL,
  `ComercialSupervisor` int NOT NULL,
  `ComercialDistribucion` tinyint NOT NULL,
  `Produccion` tinyint(1) NOT NULL,
  `ProduccionSupervisor` tinyint(1) NOT NULL,
  `ProduccionGerente` tinyint(1) NOT NULL,
  `Fiscal` tinyint(1) NOT NULL,
  `FiscalSupervisor` tinyint(1) NOT NULL,
  `FiscalGerente` tinyint(1) NOT NULL,
  `ControlInterno` tinyint(1) NOT NULL,
  `ControlInternoPrecios` tinyint(1) NOT NULL,
  PRIMARY KEY (`Id`),
  KEY `Parent` (`Parent`)
) ENGINE=InnoDB AUTO_INCREMENT=341 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menu_operador`
--

DROP TABLE IF EXISTS `menu_operador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_operador` (
  `IdMenuOperador` int NOT NULL AUTO_INCREMENT,
  `IdMenu` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdMenuOperador`)
) ENGINE=InnoDB AUTO_INCREMENT=749 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `metodo_pago_mapeo`
--

DROP TABLE IF EXISTS `metodo_pago_mapeo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `metodo_pago_mapeo` (
  `idMapeoMetodoPago` int NOT NULL AUTO_INCREMENT,
  `codigo_siat` int NOT NULL COMMENT 'codigo del método de pago en FACTURACIÓN (tabla metodo_pago)',
  `idContaCuenta` int NOT NULL COMMENT 'ID de la cuenta contable en GESTIÓN (tabla conta_cuenta)',
  `idCliente` int NOT NULL COMMENT 'ID del cliente/empresa',
  `idSucursal` int NOT NULL COMMENT 'ID de la sucursal',
  `activo` tinyint DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idMapeoMetodoPago`),
  UNIQUE KEY `uk_mapeo` (`idCliente`,`idSucursal`,`codigo_siat`,`idContaCuenta`),
  KEY `idx_metodo_pago` (`codigo_siat`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_distribucion`
--

DROP TABLE IF EXISTS `operacion_distribucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_distribucion` (
  `IdDistribucion` int NOT NULL AUTO_INCREMENT,
  `FechadeDistribucion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `FechaPedido` date NOT NULL,
  `NumeroNotaDistribucion` int NOT NULL,
  `IdLinea` int NOT NULL,
  `IdGrupo` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorSolicita` int NOT NULL,
  `IdOperadorDistribuye` int NOT NULL,
  PRIMARY KEY (`IdDistribucion`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_distribucion_propiamente`
--

DROP TABLE IF EXISTS `operacion_distribucion_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_distribucion_propiamente` (
  `IdDistribucionPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdDistribucion` int NOT NULL,
  `IdPedidos` int NOT NULL,
  PRIMARY KEY (`IdDistribucionPropiamente`),
  KEY `IdDistribucion` (`IdDistribucion`)
) ENGINE=InnoDB AUTO_INCREMENT=686 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_fichatecnica`
--

DROP TABLE IF EXISTS `operacion_fichatecnica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_fichatecnica` (
  `IdFicha` int NOT NULL AUTO_INCREMENT,
  `NumeroCorrelativo` int NOT NULL,
  `IdLineaProducto` int NOT NULL,
  `IdProductoTerminado` int NOT NULL,
  `Unidades` decimal(10,2) NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `FechaIngreso` datetime NOT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `FechaEdita` datetime DEFAULT NULL,
  PRIMARY KEY (`IdFicha`),
  UNIQUE KEY `IdProductoTerminado_2` (`IdProductoTerminado`),
  KEY `IdProductoTerminado` (`IdProductoTerminado`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_fichatecnica_propiamente`
--

DROP TABLE IF EXISTS `operacion_fichatecnica_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_fichatecnica_propiamente` (
  `IdFicha_Propiamente` int NOT NULL AUTO_INCREMENT,
  `IdFicha` int NOT NULL,
  `IdProductoInsumo` int NOT NULL,
  `Unidades` decimal(10,0) NOT NULL,
  `IdOperadorIngresa` int NOT NULL,
  `FechaIngreso` datetime NOT NULL,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` datetime DEFAULT NULL,
  PRIMARY KEY (`IdFicha_Propiamente`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_mensajes_clientes`
--

DROP TABLE IF EXISTS `operacion_mensajes_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_mensajes_clientes` (
  `IdMensajes` int NOT NULL AUTO_INCREMENT,
  `IdTipo` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Celular` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdMensajes`),
  KEY `IdTipo` (`IdTipo`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_mensajes_clientes_tipo`
--

DROP TABLE IF EXISTS `operacion_mensajes_clientes_tipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_mensajes_clientes_tipo` (
  `IdTipo` int NOT NULL AUTO_INCREMENT,
  `Tipo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdTipo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_produccion_cronograma`
--

DROP TABLE IF EXISTS `operacion_produccion_cronograma`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_produccion_cronograma` (
  `IdCronograma` int NOT NULL AUTO_INCREMENT,
  `Lunes` int DEFAULT NULL,
  `Martes` int DEFAULT NULL,
  `Miercoles` int DEFAULT NULL,
  `Jueves` int DEFAULT NULL,
  `Viernes` int DEFAULT NULL,
  `Sabado` int DEFAULT NULL,
  `Domingo` int DEFAULT NULL,
  `IdCliente` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdCronograma`),
  KEY `Domingo` (`Domingo`),
  KEY `Jueves` (`Jueves`),
  KEY `Lunes` (`Lunes`),
  KEY `Martes` (`Martes`),
  KEY `Miercoles` (`Miercoles`),
  KEY `Sabado` (`Sabado`),
  KEY `Viernes` (`Viernes`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdOperador` (`IdOperador`)
) ENGINE=InnoDB AUTO_INCREMENT=213 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_produccion_ordenproduccion`
--

DROP TABLE IF EXISTS `operacion_produccion_ordenproduccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_produccion_ordenproduccion` (
  `IdOrden` int NOT NULL AUTO_INCREMENT,
  `FechaRealizadaOrdenProduccion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `FechaDePedidos` date NOT NULL,
  `NumeroOrden` int NOT NULL,
  `IdLineaProducto` int NOT NULL,
  `IdProductoGrupo` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  `FechaIngresoInventarios` datetime NOT NULL,
  `ActivoInactivoProduccion` tinyint(1) NOT NULL,
  `IdOperadorProduccion` int NOT NULL,
  PRIMARY KEY (`IdOrden`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_produccion_ordenproduccion_propiamente`
--

DROP TABLE IF EXISTS `operacion_produccion_ordenproduccion_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_produccion_ordenproduccion_propiamente` (
  `IdOrdenPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdOrden` int NOT NULL,
  `IdProducto` int NOT NULL,
  `UnidadesOrdenadas` decimal(10,2) NOT NULL,
  `UnidadesProducidas` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdOrdenPropiamente`),
  KEY `IdOrden` (`IdOrden`),
  KEY `IdProducto` (`IdProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=222 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_ventas_pedidos`
--

DROP TABLE IF EXISTS `operacion_ventas_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_ventas_pedidos` (
  `IdPedidos` int NOT NULL AUTO_INCREMENT,
  `IdTipoPedido` int NOT NULL,
  `ProduceDistribuye` tinyint(1) NOT NULL,
  `FechaRealiza` datetime NOT NULL,
  `FechaDelPedido` date NOT NULL,
  `IdProducto` int NOT NULL,
  `Unidades` decimal(10,2) NOT NULL,
  `AutorizadoNoAutorizado` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `idOperador` int NOT NULL,
  `IdOperadorPedidoExtraordinario` int NOT NULL,
  `UnidadesAutoriza` decimal(10,2) NOT NULL,
  `IdOperadorAutoriza` int NOT NULL,
  `IdDistribucion` int NOT NULL,
  `UnidadesDistribuidas` decimal(10,2) NOT NULL,
  `DistribuidoSiNo` tinyint(1) NOT NULL,
  `IdOperadorRecibe` int NOT NULL,
  `UnidadesRecibidas` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdPedidos`),
  KEY `IdCliente` (`IdCliente`),
  KEY `idOperador` (`idOperador`),
  KEY `operacion_ventas_pedidos_ibfk_4` (`IdSucursal`),
  KEY `operacion_ventas_pedidos_ibfk_3` (`IdProducto`)
) ENGINE=InnoDB AUTO_INCREMENT=103295 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_ventas_pedidos_devolucion`
--

DROP TABLE IF EXISTS `operacion_ventas_pedidos_devolucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_ventas_pedidos_devolucion` (
  `IdDevolucion` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `NumeroDevolucion` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdDevolucion`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdOperador` (`IdOperador`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdCliente` (`IdCliente`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_ventas_pedidos_devolucion_1`
--

DROP TABLE IF EXISTS `operacion_ventas_pedidos_devolucion_1`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_ventas_pedidos_devolucion_1` (
  `IdDevolucionPropiamente` int NOT NULL AUTO_INCREMENT,
  `IdDevolucion` int NOT NULL,
  `IdProducto` int NOT NULL,
  `Unidades` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdDevolucionPropiamente`),
  KEY `IdProducto` (`IdProducto`),
  KEY `IdDevolucion` (`IdDevolucion`)
) ENGINE=InnoDB AUTO_INCREMENT=511 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_ventas_pedidos_horalimite`
--

DROP TABLE IF EXISTS `operacion_ventas_pedidos_horalimite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_ventas_pedidos_horalimite` (
  `IdHoraLimite` int NOT NULL AUTO_INCREMENT,
  `Hora` int NOT NULL,
  `ActivaControlDia` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdHoraLimite`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_ventas_pedidos_programados`
--

DROP TABLE IF EXISTS `operacion_ventas_pedidos_programados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_ventas_pedidos_programados` (
  `IdProgramado` int NOT NULL,
  `Dia` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `IdProducto` int NOT NULL,
  `Unidades` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdProgramado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `operacion_ventas_pedidos_tipopedido`
--

DROP TABLE IF EXISTS `operacion_ventas_pedidos_tipopedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacion_ventas_pedidos_tipopedido` (
  `IdTipoPedido` int NOT NULL,
  `Detalle` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `IdCliente` int NOT NULL,
  PRIMARY KEY (`IdTipoPedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_contrato`
--

DROP TABLE IF EXISTS `personal_datos_contrato`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_contrato` (
  `IdContrato` int NOT NULL,
  `IdEmpleado` int NOT NULL,
  `FechaInicio` date NOT NULL,
  `FechaFinal` date DEFAULT NULL,
  `Motivo` text COLLATE utf8mb4_general_ci,
  `IdActividad` int NOT NULL,
  `IdSector` int NOT NULL,
  `IdCargo` int NOT NULL,
  `NumeroContrato` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `NumeroCNS` text COLLATE utf8mb4_general_ci NOT NULL,
  `BonoAntiguedad` tinyint(1) NOT NULL,
  `IdAFP` int DEFAULT NULL,
  `IdPlaza` int DEFAULT NULL,
  `IdCliente` int NOT NULL,
  `IdoperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualizacion` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`IdContrato`),
  KEY `IdEmpleado` (`IdEmpleado`),
  KEY `IdEmpleado_2` (`IdEmpleado`),
  KEY `IdAFP` (`IdAFP`),
  KEY `IdDistrito` (`IdPlaza`),
  KEY `IdoperadorIngreso` (`IdoperadorIngreso`),
  KEY `IdSector` (`IdSector`),
  KEY `IdCargo` (`IdCargo`),
  KEY `IdOperadorActualiza` (`IdOperadorActualiza`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdActividad` (`IdActividad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_contrato_descuentosconcepto`
--

DROP TABLE IF EXISTS `personal_datos_contrato_descuentosconcepto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_contrato_descuentosconcepto` (
  `IdDescuentos` int NOT NULL,
  `Concepto` text COLLATE utf8mb4_general_ci NOT NULL,
  `Porcentaje` decimal(10,2) NOT NULL,
  `LaboralPatronal` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdDescuentos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_contrato_pagos`
--

DROP TABLE IF EXISTS `personal_datos_contrato_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_contrato_pagos` (
  `IdContratoPagos` int NOT NULL,
  `IdContrato` int DEFAULT NULL,
  `Fecha` date NOT NULL,
  `IdContratoPagosConcepto` int DEFAULT NULL,
  `MontoBs` double DEFAULT NULL,
  `AportaNoAporta` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdContratoPagos`),
  KEY `IdContrato` (`IdContrato`),
  KEY `IdContratoPagosConcepto` (`IdContratoPagosConcepto`),
  KEY `IdFecha` (`Fecha`),
  KEY `IdContrato_2` (`IdContrato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_contrato_pagosconcepto`
--

DROP TABLE IF EXISTS `personal_datos_contrato_pagosconcepto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_contrato_pagosconcepto` (
  `IdContratoPagosConcepto` int NOT NULL DEFAULT '0',
  `Concepto` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Nivel` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`IdContratoPagosConcepto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_contrato_sector`
--

DROP TABLE IF EXISTS `personal_datos_contrato_sector`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_contrato_sector` (
  `IdSector` int DEFAULT NULL,
  `SectorCargo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  UNIQUE KEY `IdSector` (`IdSector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_contrato_sectorcargo`
--

DROP TABLE IF EXISTS `personal_datos_contrato_sectorcargo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_contrato_sectorcargo` (
  `IdCargo` int NOT NULL,
  `IdSector` int DEFAULT NULL,
  `Cargo` tinytext COLLATE utf8mb4_general_ci,
  UNIQUE KEY `IdCargo` (`IdCargo`),
  KEY `IdSector` (`IdSector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_contratoafp`
--

DROP TABLE IF EXISTS `personal_datos_contratoafp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_contratoafp` (
  `IdAfp` int NOT NULL,
  `Detalle` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  UNIQUE KEY `IdAfp` (`IdAfp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_datos_empleado`
--

DROP TABLE IF EXISTS `personal_datos_empleado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_datos_empleado` (
  `IdEmpleado` int NOT NULL,
  `IdIdentificador` int DEFAULT NULL,
  `Sexo` tinytext COLLATE utf8mb4_general_ci,
  `FechaNacimiento` date DEFAULT NULL,
  `Lugar de Nacimiento` text COLLATE utf8mb4_general_ci NOT NULL,
  `Direccion` text COLLATE utf8mb4_general_ci NOT NULL,
  `Telefono` int DEFAULT NULL,
  `Celular` int DEFAULT NULL,
  `Email` mediumtext COLLATE utf8mb4_general_ci,
  `Nacionalidad` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `Foto` mediumblob,
  `IdCliente` int DEFAULT NULL,
  `IdoperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorActualiza` int NOT NULL,
  `FechaActualiza` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`IdEmpleado`),
  UNIQUE KEY `IdIdentificador_2` (`IdIdentificador`),
  KEY `IdIdentificador` (`IdIdentificador`),
  KEY `IdoperadorIngreso` (`IdoperadorIngreso`,`IdOperadorActualiza`),
  KEY `IdOperadorActualiza` (`IdOperadorActualiza`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdoperadorIngreso_2` (`IdoperadorIngreso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_memorandum_llamadaatencion`
--

DROP TABLE IF EXISTS `personal_memorandum_llamadaatencion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_memorandum_llamadaatencion` (
  `IdLlamadaAtencion` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdContrato` int NOT NULL,
  `NumeroCorrelativo` int NOT NULL,
  `DiasFalta` decimal(10,2) NOT NULL,
  `DiasSancion` decimal(10,2) NOT NULL,
  `Detalle` text COLLATE utf8mb4_general_ci NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdOperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`IdLlamadaAtencion`),
  UNIQUE KEY `IdLlamadaAtencion` (`IdLlamadaAtencion`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdContrato` (`IdContrato`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdOperadorIngreso` (`IdOperadorIngreso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_planillaextra_principal`
--

DROP TABLE IF EXISTS `personal_planillaextra_principal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_planillaextra_principal` (
  `IdPlanillaExtra` int NOT NULL,
  `NumeroCorrelativo` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdPlanillaExtraConcepto` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `AportaNoAporta` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdPlanillaExtra`),
  KEY `IdFecha` (`IdFecha`,`IdPlanillaExtraConcepto`,`IdCliente`,`IdSucursal`,`IdOperadorIngreso`),
  KEY `IdOperadorIngreso` (`IdOperadorIngreso`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdCliente_2` (`IdCliente`),
  KEY `IdPlanillaExtraConcepto` (`IdPlanillaExtraConcepto`),
  KEY `IdSucursal` (`IdSucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_planillaextra_propiamente`
--

DROP TABLE IF EXISTS `personal_planillaextra_propiamente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_planillaextra_propiamente` (
  `IdPLanillaExtraPropiamente` int NOT NULL,
  `IdPlanillaExtra` int NOT NULL,
  `IdContrato` int NOT NULL,
  `MontoBs` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdPLanillaExtraPropiamente`),
  KEY `IdPlanillaExtra` (`IdPlanillaExtra`,`IdContrato`),
  KEY `IdContrato` (`IdContrato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_planillasueldos_detalle`
--

DROP TABLE IF EXISTS `personal_planillasueldos_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_planillasueldos_detalle` (
  `IdPlanillaSueldos` int NOT NULL,
  `IdPlanillaSueldosPrincipal` int NOT NULL,
  `IdConcepto` int NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  `PagoDescuento` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdPlanillaSueldos`),
  KEY `IdConcepto` (`IdConcepto`),
  KEY `IdConcepto_2` (`IdConcepto`),
  KEY `IdPlanillaSueldosPrincipal` (`IdPlanillaSueldosPrincipal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_planillasueldos_principal`
--

DROP TABLE IF EXISTS `personal_planillasueldos_principal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_planillasueldos_principal` (
  `IdPlanillaSueldosPrincipal` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdFecha` int NOT NULL,
  `IdContrato` int NOT NULL,
  `DiasTrabajados` decimal(10,2) NOT NULL,
  `AntiguedadAños` int NOT NULL,
  `AntiguedadMeses` int NOT NULL,
  `AntiguedadDias` int NOT NULL,
  `Contabilizado` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdPlanillaSueldosPrincipal`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdContrato` (`IdContrato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_todos`
--

DROP TABLE IF EXISTS `personal_todos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_todos` (
  `IdDatosFinancieros` int NOT NULL,
  `Detalle` text COLLATE utf8mb4_general_ci NOT NULL,
  `Bolivianos` decimal(10,2) NOT NULL,
  PRIMARY KEY (`IdDatosFinancieros`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supervisores_enviomensaje_grupo`
--

DROP TABLE IF EXISTS `supervisores_enviomensaje_grupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisores_enviomensaje_grupo` (
  `IdGrupo` int NOT NULL AUTO_INCREMENT,
  `Grupo` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `OpciongGrupo` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `IdCliente` int NOT NULL,
  PRIMARY KEY (`IdGrupo`),
  KEY `IdCliente` (`IdCliente`),
  CONSTRAINT `supervisores_enviomensaje_grupo_ibfk_1` FOREIGN KEY (`IdCliente`) REFERENCES `todos_cliente` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supervisores_enviomensajes`
--

DROP TABLE IF EXISTS `supervisores_enviomensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisores_enviomensajes` (
  `IdSupervisores` int NOT NULL AUTO_INCREMENT,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `IdArea` int NOT NULL,
  `Celular` int NOT NULL,
  PRIMARY KEY (`IdSupervisores`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `supervisres_area`
--

DROP TABLE IF EXISTS `supervisres_area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `supervisres_area` (
  `IdArea` int NOT NULL AUTO_INCREMENT,
  `IdCliente` int NOT NULL,
  `Area` varchar(50) COLLATE utf8mb3_unicode_ci NOT NULL,
  PRIMARY KEY (`IdArea`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_cliente`
--

DROP TABLE IF EXISTS `todos_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_cliente` (
  `IdCliente` int NOT NULL,
  `Nombre` text CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `NIT` bigint NOT NULL,
  `Direccion` text CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci,
  `Fono` int DEFAULT NULL,
  `Celular` int DEFAULT NULL,
  `CIRepresentanteLegal` bigint NOT NULL,
  `NombreRepresentanteLegal` text CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish_ci NOT NULL,
  `IdFechaInicioOperaciones` int NOT NULL,
  `facturacion_habilitada` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=Sin facturación, 1=Con facturación',
  PRIMARY KEY (`IdCliente`),
  UNIQUE KEY `NIT` (`NIT`),
  KEY `IdFechaInicioPeraciones` (`IdFechaInicioOperaciones`),
  KEY `IdFechaInicioOperaciones` (`IdFechaInicioOperaciones`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_cliente_actividad`
--

DROP TABLE IF EXISTS `todos_cliente_actividad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_cliente_actividad` (
  `IdActividad` int NOT NULL,
  `IdCliente` int NOT NULL,
  `Actividad` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdActividad`),
  UNIQUE KEY `IdActividad` (`IdActividad`),
  KEY `IdCliente` (`IdCliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_cliente_plaza`
--

DROP TABLE IF EXISTS `todos_cliente_plaza`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_cliente_plaza` (
  `IdPlaza` int NOT NULL AUTO_INCREMENT,
  `Plaza` text COLLATE utf8mb4_general_ci NOT NULL,
  `Abreviacion` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdPlaza`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_cliente_sucursal`
--

DROP TABLE IF EXISTS `todos_cliente_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_cliente_sucursal` (
  `IdClienteSucursal` int NOT NULL AUTO_INCREMENT,
  `IdCliente` int NOT NULL,
  `IdPlaza` int NOT NULL,
  `Nombre` text COLLATE utf8mb4_general_ci NOT NULL,
  `Direccion` varchar(38) COLLATE utf8mb4_general_ci NOT NULL,
  `Telefono` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `Celular` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `NumeroSucursal` int DEFAULT NULL,
  `ActivaInactivaR` tinyint(1) NOT NULL,
  `Orden` int NOT NULL,
  `Categoria` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `ControlInternoEfectivo` int NOT NULL,
  `facturacion_habilitada` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=Sin facturación, 1=Con facturación',
  PRIMARY KEY (`IdClienteSucursal`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdPlaza` (`IdPlaza`),
  KEY `IdCliente_2` (`IdCliente`),
  KEY `IdPlaza_2` (`IdPlaza`)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_fecha`
--

DROP TABLE IF EXISTS `todos_fecha`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_fecha` (
  `IdFecha` int NOT NULL AUTO_INCREMENT,
  `Fecha` date NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  `CierreSucursal` tinyint(1) NOT NULL,
  `CierrePermanente` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdFecha`),
  UNIQUE KEY `Fecha` (`Fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=7798 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_fecha_auxiliar_sucursal`
--

DROP TABLE IF EXISTS `todos_fecha_auxiliar_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_fecha_auxiliar_sucursal` (
  `IdFechaAuxiliar` int NOT NULL AUTO_INCREMENT,
  `IdFecha` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `FechaApertura` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`IdFechaAuxiliar`),
  KEY `IdFecha` (`IdFecha`),
  KEY `IdCliente` (`IdCliente`),
  KEY `todos_fecha_auxiliar_sucursal_ibfk_3` (`IdSucursal`)
) ENGINE=InnoDB AUTO_INCREMENT=198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_firmasautorizadas`
--

DROP TABLE IF EXISTS `todos_firmasautorizadas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_firmasautorizadas` (
  `IdFirmaAutorizada` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdIdentificador` int NOT NULL,
  `Nivel` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  `Cargo` tinytext COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdFirmaAutorizada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_identificador`
--

DROP TABLE IF EXISTS `todos_identificador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_identificador` (
  `IdIdentificador` int NOT NULL AUTO_INCREMENT,
  `CI_NIT` bigint NOT NULL,
  `Nombre` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  `IdOperadorIngreso` int NOT NULL,
  `FechaIngreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `IdOperadorEdita` int NOT NULL,
  `FechaEdita` datetime NOT NULL,
  PRIMARY KEY (`IdIdentificador`),
  UNIQUE KEY `CI_NIT` (`CI_NIT`),
  KEY `IdOperadorIngreso` (`IdOperadorIngreso`),
  KEY `IdOperadorEdita` (`IdOperadorEdita`)
) ENGINE=InnoDB AUTO_INCREMENT=37671 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_operador`
--

DROP TABLE IF EXISTS `todos_operador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_operador` (
  `IdOperador` int NOT NULL AUTO_INCREMENT,
  `IdIdentificador` int NOT NULL,
  `Iniciales` varchar(5) COLLATE utf8mb4_general_ci NOT NULL,
  `Clave` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `NombreAcceso` char(20) COLLATE utf8mb4_general_ci NOT NULL,
  `DireccionDomicilio` text COLLATE utf8mb4_general_ci NOT NULL,
  `TelefonoDomicilio` int NOT NULL,
  `NumeroCelular` int NOT NULL,
  `IdOperadorTipo` int NOT NULL,
  `ActivoInactivo` tinyint(1) NOT NULL,
  PRIMARY KEY (`IdOperador`),
  UNIQUE KEY `NombreAcceso` (`NombreAcceso`),
  UNIQUE KEY `IdIdentificador_2` (`IdIdentificador`),
  KEY `IdIdentificador` (`IdIdentificador`),
  KEY `IdOperadorTipo` (`IdOperadorTipo`)
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_operador_sucursaldb`
--

DROP TABLE IF EXISTS `todos_operador_sucursaldb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_operador_sucursaldb` (
  `IdSucursalDB` int NOT NULL AUTO_INCREMENT,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  `IdOperador` int NOT NULL,
  PRIMARY KEY (`IdSucursalDB`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdOperador` (`IdOperador`),
  KEY `IdCliente` (`IdCliente`),
  KEY `IdCliente_2` (`IdCliente`),
  KEY `IdSucursal_2` (`IdSucursal`),
  KEY `IdOperador_2` (`IdOperador`)
) ENGINE=InnoDB AUTO_INCREMENT=335 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_operador_tipo`
--

DROP TABLE IF EXISTS `todos_operador_tipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_operador_tipo` (
  `IdOperadorTipo` int NOT NULL,
  `Detalle` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`IdOperadorTipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_parametros_almacen`
--

DROP TABLE IF EXISTS `todos_parametros_almacen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_parametros_almacen` (
  `IdTodosParametrosInventarios` int NOT NULL,
  `IdAlmacenPrincipal` int NOT NULL,
  `IdAlmacenSnack` int NOT NULL,
  `IdCliente` int NOT NULL,
  `IdSucursal` int NOT NULL,
  PRIMARY KEY (`IdTodosParametrosInventarios`),
  KEY `IdAlmacen` (`IdAlmacenPrincipal`),
  KEY `IdSucursal` (`IdSucursal`),
  KEY `IdCliente` (`IdCliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `todos_parametros_cuentas`
--

DROP TABLE IF EXISTS `todos_parametros_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `todos_parametros_cuentas` (
  `IdParametros` int NOT NULL,
  `IdCliente` int NOT NULL,
  `VentasFacturadas` int DEFAULT NULL,
  `VentasNoFacturadas` int NOT NULL,
  `DebitoFiscalIVA` int NOT NULL,
  `ITPagados` int NOT NULL,
  `ITxPagar` int NOT NULL,
  `ControlDFIVA` int NOT NULL,
  `ComprasFacturadas` int NOT NULL,
  `ComprasNoFacturadas` int NOT NULL,
  `CreditoFiscalIVA` int NOT NULL,
  `CuentaPersonalVendedor` int NOT NULL,
  `Inventario` int NOT NULL,
  `Proveedores` int NOT NULL,
  `DiferenciaInventarioSobrante` int NOT NULL,
  `DiferenciaInventarioFaltante` int NOT NULL,
  `AnticipoProveedores` int NOT NULL,
  `DiferenciaDeCambioIngreso` int NOT NULL,
  `DiferenciaDeCambioGasto` int NOT NULL,
  `AporteLaboralPasivo` int NOT NULL,
  `AportePatronalPasivo` int NOT NULL,
  `CajaSaludPasivo` int NOT NULL,
  `AguinaldoPorPagarPasivo` int NOT NULL,
  `IndemnizacionPasivo` int NOT NULL,
  `SueldosGasto` int NOT NULL,
  `AportesPatronalesGasto` int NOT NULL,
  `CajaSaludGasto` int NOT NULL,
  `AguinaldoGasto` int NOT NULL,
  `IndemnizacionGasto` int NOT NULL,
  `DescuentosDiciplinarios` int NOT NULL,
  `CajaBolivianos` int NOT NULL,
  `CajaChica` int NOT NULL,
  PRIMARY KEY (`IdParametros`),
  KEY `IdCliente` (`IdCliente`),
  KEY `CreditoFiscal` (`ComprasNoFacturadas`),
  KEY `ComprasDiesel` (`ComprasFacturadas`),
  KEY `CajaDolares` (`ITxPagar`),
  KEY `CajaBolivianos` (`ITPagados`),
  KEY `ConsumoPropio` (`DebitoFiscalIVA`),
  KEY `ConsumoPropio_2` (`DebitoFiscalIVA`),
  KEY `DebitoFiscal` (`VentasNoFacturadas`),
  KEY `VentasGas` (`VentasFacturadas`),
  KEY `OtrosGastosImpositivos` (`CreditoFiscalIVA`),
  KEY `CuentaPersonalVendedor` (`CuentaPersonalVendedor`),
  KEY `InventarioDiesel` (`Inventario`),
  KEY `DiferenciaInventarioSobrante` (`DiferenciaInventarioSobrante`),
  KEY `DiferencaiInventarioFaltante` (`DiferenciaInventarioFaltante`),
  KEY `AnticipoProveedores` (`AnticipoProveedores`),
  KEY `DiferenciaDeCambioIngreso` (`DiferenciaDeCambioIngreso`),
  KEY `DiferenciaDeCambioGasto` (`DiferenciaDeCambioGasto`),
  KEY `AporteLaboralPasivo` (`AporteLaboralPasivo`),
  KEY `AportePatronalPasivo` (`AportePatronalPasivo`),
  KEY `CajaSaludPasivo` (`CajaSaludPasivo`),
  KEY `AguinaldoPorPagarPasivo` (`AguinaldoPorPagarPasivo`),
  KEY `IndemnizacionPasivo` (`IndemnizacionPasivo`),
  KEY `SueldosGasto` (`SueldosGasto`),
  KEY `SueldosGasto_2` (`SueldosGasto`),
  KEY `AportesPatronalesGasto` (`AportesPatronalesGasto`),
  KEY `CajaSaludGasto` (`CajaSaludGasto`),
  KEY `IndemnizacionGasto` (`IndemnizacionGasto`),
  KEY `DescuentosDiciplinarios` (`DescuentosDiciplinarios`),
  KEY `Proveedores` (`Proveedores`),
  KEY `CajaBolivianos_2` (`CajaBolivianos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `analisisbalance`
--

/*!50001 DROP VIEW IF EXISTS `analisisbalance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`cacho`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `analisisbalance` AS select 1 AS `Fecha`,1 AS `TipoDeCuenta`,1 AS `Cuenta`,1 AS `Descripcion`,1 AS `D_H`,1 AS `MontoBolivianos`,1 AS `IdCliente` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `analisiscuenta`
--

/*!50001 DROP VIEW IF EXISTS `analisiscuenta`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`cacho`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `analisiscuenta` AS select 1 AS `Contabilizado`,1 AS `Fecha`,1 AS `TipoDeCuenta`,1 AS `Cuenta`,1 AS `IdCuenta`,1 AS `Descripcion`,1 AS `D_H`,1 AS `MontoBolivianos`,1 AS `MontoOtraMoneda`,1 AS `IdCliente`,1 AS `IdIdentificador`,1 AS `CI_NIT` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `analisismayores`
--

/*!50001 DROP VIEW IF EXISTS `analisismayores`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`cacho`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `analisismayores` AS select 1 AS `IdOperadorIngreso`,1 AS `IdTipoDiario`,1 AS `IdCliente`,1 AS `Contabilizado`,1 AS `IdDiario`,1 AS `Fecha`,1 AS `Cuenta`,1 AS `TipoDeCuenta`,1 AS `Descripcion`,1 AS `NumeroDiario`,1 AS `Glosa`,1 AS `D_H`,1 AS `MontoBolivianos`,1 AS `MontoOtraMoneda`,1 AS `NumeroSucursal`,1 AS `Identificador`,1 AS `Nombre` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `analisisresultados`
--

/*!50001 DROP VIEW IF EXISTS `analisisresultados`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`cacho`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `analisisresultados` AS select 1 AS `Fecha`,1 AS `TipoDeCuenta`,1 AS `Cuenta`,1 AS `Descripcion`,1 AS `D_H`,1 AS `MontoBolivianos`,1 AS `IdCliente`,1 AS `Deducible` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-11 20:35:40

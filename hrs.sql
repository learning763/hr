-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.16.0.7229
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for hr
CREATE DATABASE IF NOT EXISTS `hr` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `hr`;

-- Dumping structure for table hr.def_rank
CREATE TABLE IF NOT EXISTS `def_rank` (
  `rank_code` int(11) NOT NULL AUTO_INCREMENT,
  `rank_name` longtext DEFAULT NULL,
  `rank_abvt` longtext DEFAULT NULL,
  `rank_nep` longtext DEFAULT NULL,
  `rank_unicode` longtext DEFAULT NULL,
  `rank_unicode_full` longtext DEFAULT NULL,
  `rank_neps` longtext DEFAULT NULL,
  `group_rank` longtext DEFAULT NULL,
  `rank_level` longtext DEFAULT NULL,
  `cv_rank` longtext DEFAULT NULL,
  `is_active` varchar(2) DEFAULT NULL,
  UNIQUE KEY `pkrank_code` (`rank_code`),
  KEY `rank_code` (`rank_code`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Dumping data for table hr.def_rank: ~129 rows (approximately)
INSERT INTO `def_rank` (`rank_code`, `rank_name`, `rank_abvt`, `rank_nep`, `rank_unicode`, `rank_unicode_full`, `rank_neps`, `group_rank`, `rank_level`, `cv_rank`, `is_active`) VALUES
	(3, 'GENERAL', 'GEN', 'dxf/yL', 'महारथी', 'महारथी', 'dxf/yL', 'Rathi', 'lalzi^ >])fL', NULL, 'Y'),
	(5, 'LIEUTENANT GENERAL', 'LT GEN', '/yL', 'रथी', 'रथी', '/yL', 'Rathi', 'lalzi^ >])fL', NULL, 'Y'),
	(7, 'MAJOR GENERAL', 'MAJ GEN', 'pk/yL', 'उ.र.', 'उपरथी', 'p=/=', 'Rathi', 'lalzi^ >])fL', NULL, 'Y'),
	(9, 'BRIGADIER GENERAL', 'BRIG GEN', ';xfos /yL', 'स.र.', 'सहायक रथी', ';=/', 'Rathi', '/F=k= k|yd >])fL', 'GAZATED I', 'Y'),
	(10, 'ACTING BRIGADIER GENERAL', 'ACT BRI GEN', 'cf=;xfos /yL', 'आ.सहायक रथी', 'आ.सहायक रथी', 'cf=;=/=', 'Rathi', '/F=k= k|yd >])fL', 'GAZATED I', 'Y'),
	(11, 'COLONEL', 'COL', 'dxf ;]gfgL', 'म.से.', 'महा सेनानी', 'd=;]=', 'Adhikrit', '/F=k= k|yd >])fL', 'GAZATED I', 'Y'),
	(12, 'ACTING COLONEL', 'ACT COL', 'cf=dxf ;]gfgL', 'आ.महा सेनानी', 'आ.महा सेनानी', 'cf=d=;]=', 'Adhikrit', '/F=k= k|yd >])fL', 'GAZATED I', 'Y'),
	(13, 'LIEUTENANT COLONEL', 'LT COL', 'k|d\'v ;]gfgL', 'प्र.से.', 'प्रमुख सेनानी', 'k|=;]=', 'Adhikrit', '/f=k= bf]>f] >])fL', 'GAZATED II', 'Y'),
	(14, 'ACTING LIEUTENANT COLONEL', 'ACT LT COL', 'cf=k|d\'v ;]gfgL', 'आ. प्रमुख सेनानी', 'आ. प्रमुख सेनानी', 'cf=k|=;]=', 'Adhikrit', '/f=k= bf]>f] >])fL', 'GAZATED II', 'Y'),
	(15, 'MAJOR', 'MAJ', ';]gfgL', 'सेनानी', 'सेनानी', ';]gfgL', 'Adhikrit', '/f=k= bf]>f] >])fL', 'GAZATED II', 'Y'),
	(16, 'ACTING MAJOR', 'ACT MAJ', 'cf=;]gfgL', 'आ.सेनानी', 'आ.सेनानी', 'cf=;]gfgL', 'Adhikrit', '/f=k= bf]>f] >])fL', 'GAZATED II', 'Y'),
	(17, 'CAPTAIN', 'CAPT', ';x ;]gfgL', 'सह से.', 'सह सेनानी', ';x=;]=', 'Adhikrit', '/f=k= t]>f] >])fL', 'GAZATED III', 'Y'),
	(18, 'ACTING CAPTAIN', 'ACT CAPT', 'cf=;x ;]gfgL', 'आ.सह सेनानी', 'आ.सह सेनानी', 'cf=;x=;]=', 'Adhikrit', '/f=k= t]>f] >])fL', 'GAZATED III', 'Y'),
	(19, 'LIEUTENANT', 'LT', 'pk ;]gfgL', 'उ.से.', 'उप सेनानी', 'p=;]=', 'Adhikrit', '/f=k= t]>f] >])fL', 'GAZATED III', 'Y'),
	(21, 'SECOND LIEUTENANT', '2ND LT', ';xfos ;]gfgL', 'स.से.', 'सहायक सेनानी', ';=;]=', 'Adhikrit', '/f=k= t]>f] >])fL', 'GAZATED III', 'Y'),
	(23, 'OFFICER CADET (DIRECT ENTRY)', 'OFF CDT (DE)', 'clws[t Sof*]^ z"?', 'अधिकृत क्याडेट शुरु', 'अधिकृत क्याडेट शुरु', NULL, 'Akya', NULL, NULL, NULL),
	(24, 'OFFICER CADET(J C O)', 'OFF CDT (JCO)', 'klbs Sof*]^', 'पदिक क्याडेट', 'पदिक क्याडेट', NULL, 'Pakya', NULL, NULL, NULL),
	(25, 'OFFICER CADET (J C O CLK)', 'OFF CDT (JCO C)', 'klbs sd{rf/L Sof*]^', 'पदिक कर्मचारी क्याडेट', 'पदिक कर्मचारी क्याडेट', NULL, 'Pakya', NULL, NULL, NULL),
	(26, 'OFFICER CADET (INSERVICE)', 'OFF CDT (INSERV', 'OG;le{; Sof*]^', 'इन्सर्भिस क्याडेट', 'इन्सर्भिस क्याडेट', NULL, 'Akya', NULL, NULL, NULL),
	(27, 'HONORARY CAPTAIN', 'HON CAPT', 'dfgfy{ ;x ;]gfgL', 'मानार्थ सह सेनानी', 'मानार्थ सह सेनानी', 'df=;x=;]=', 'Padik', '/f=k=c=k|yd >])fL', 'NON GAZ I', 'Y'),
	(29, 'HONORARY LIEUTENANT', 'HON LT', 'dfgfy{ pk ;]gfgL', 'मानार्थ उप सेनानी', 'मानार्थ उप सेनानी', 'df=p=;]=', 'Padik', '/f=k=c=k|yd >])fL', 'NON GAZ I', 'Y'),
	(31, 'SUBEDAR MAJOR', 'SU MAJ', 'k|d\'v ;\'a]bf/', 'प्रमुख सुवेदार', 'प्रमुख सुवेदार', 'k|=;"=', 'Padik', '/f=k=c=k|yd >])fL', 'NON GAZ I', NULL),
	(33, 'SENIOR SUBEDAR', 'SR SU', 'l;lgo/ ;"a]bf/', 'सिनियर सुवेदार', 'सिनियर सुवेदार', 'l;=;"=', 'Padik', '/f=k=c=k|yd >])fL', 'NON GAZ I', NULL),
	(35, 'SUBEDAR', 'SU', ';\'a]bf/', 'सु.', 'सु.', ';"=', 'Padik', '/f=k=c=k|yd >])fL', 'NON GAZ I', 'Y'),
	(37, 'JEMADAR', 'JEM', 'hdbf/', 'जम.', 'जम.', 'hd=', 'Padik', '/f=k=c=l$lto >])fL -v/bf/ ;/x_', 'NON GAZ II', 'Y'),
	(39, 'BATTALION HAVALDAR MAJOR', 'BAT HAV MAJ', 'u)f sfo{ x\'$f', 'गण कार्य हुद्बा', 'गण कार्य हुद्बा', 'u=sf=x"=', 'NCOS', '/f=k=c=t[lto >])fL', 'NON GAZ III', NULL),
	(40, 'HAVALDAR MAJOR', 'SGT.MAJ', NULL, 'कार्य हुद्बा', 'कार्य हुद्बा', NULL, NULL, NULL, NULL, NULL),
	(41, 'BATTALION QUARTERMASTER HAVALDAR', 'BAT QM HAV', 'u)f k|aGw x\'$f', 'गण प्रबन्ध हुद्बा', 'गण प्रबन्ध हुद्बा', 'u=k|=x\'=', 'NCOS', '/f=k=c=t[lto >])fL', 'NON GAZ III', NULL),
	(42, 'QUARTER MASTER HAVALDAR', 'QM.SGT', NULL, 'प्रबन्ध हुद्बा', 'प्रबन्ध हुद्बा', NULL, NULL, NULL, NULL, NULL),
	(43, 'COMPANY HAVALDAR MAJOR', 'COY HAV MAJ', 'u"Nd sfo{ x\'$f', 'गुल्म कार्य हुद्बा', 'गुल्म कार्य हुद्बा', 'u"=sf=x\'=', 'NCOS', '/f=k=c=t[lto >])fL', 'NON GAZ III', NULL),
	(44, 'LOCAL LT.COL', 'LOC LT COL', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(45, 'COMPANY QUARTERMASTER HAVALDAR', 'COY QM HAV', 'u"Nd k|aGw x\'$f', 'गुल्म प्रबन्ध हुद्बा', 'गुल्म प्रबन्ध हुद्बा', 'u\'=k|=x\'=', 'NCOS', '/f=k=c=t[lto >])fL', 'NON GAZ III', NULL),
	(47, 'HAVALDAR', 'HAV', 'xjNbf/', 'हु.', 'हु.', 'x\'=', 'NCOS', '/f=k=c=t[lto >])fL', 'NON GAZ III', 'Y'),
	(49, 'NAIK', 'NAIK', 'cdNbf/', 'अम.', 'अम.', 'cd=', 'NCOS', '/f=k=c=t[lto >])fL', 'NON GAZ III', 'Y'),
	(51, 'LANCE NAIK', 'L NAIK', 'Ko\'&', 'प्युठ', 'प्युठ', 'Ko\'=', 'NCOS', '/f=k=c=rt\'y{ >])fL', 'NON GAZ IV', 'Y'),
	(53, 'SOLDIER', 'SOLDIER', 'l;kfxL', 'सिपाही', 'सिपाही', 'l;=', 'NCOS', '/f=k=c=rt\'y{ >])fL', 'NON GAZ IV', 'Y'),
	(54, 'NCE (V LEVEL)', 'NCE (V LEVEL)', 'Pg=l;=O{= kf+rf} :t/', 'एन.सि.ई.  पाँचौ स्तर', 'एन.सि.ई.  पाँचौ स्तर', 'Pg=l;=O{= kf+rf', NULL, '>])fLljlxg kf+rf}  ', NULL, NULL),
	(55, 'RECRUIT', 'RECRUIT', ';}Go', 'सैन्य', 'सैन्य', NULL, 'Sainya', NULL, NULL, NULL),
	(56, 'RECRUIT AGENT', 'RECRUIT AGENT', 'P= ;}Go', 'ए.सैन्य', 'ए.सैन्य', 'P= ;}Go', NULL, NULL, NULL, NULL),
	(57, 'BOY', 'BOY', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(58, 'NCE (IV LEVEL)', 'NCE (IV LEVEL)', 'Pg=l;=O{= rf}yf] :t/', 'एन.सि.ई. चौथो स्तर', 'एन.सि.ई. चौथो स्तर', 'Pg=l;=O{= rf}yf', NULL, '>])fLljlxg rt\'y{  ', NULL, NULL),
	(59, 'KOTE', 'KOTE', 'sf]t]', 'कोते', 'कोते', 'sf]t]', NULL, '/f=k=c=k|yd >])fL', NULL, NULL),
	(60, 'NCE (III LEVEL)', 'NCE (III LEVEL)', 'Pg=l;=O{= t];|f] :t/', 'एन.सि.ई. तेस्रो स्तर', 'एन.सि.ई. तेस्रो स्तर', 'Pg=l;=O{= t];|f', NULL, '>])fLljlxg t[tLo ', NULL, NULL),
	(61, 'PIPA CIVIL', 'PIPA CIVIL', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(62, 'NCE (II LEVEL)', 'NCE (II LEVEL)', 'Pg=l;=O{= bf];|f] :t/ ', 'एन.सि.ई. दोस्रो स्तर', 'एन.सि.ई. दोस्रो स्तर', 'Pg=l;=O{= bf];|', NULL, '>])fLljlxg lÃƒÂ¥tLo ', NULL, NULL),
	(63, 'NCE (I LEVEL)', 'NCE (I LEVEL)', 'Pg=l;=O{= k|yd :t/', 'एन.सि.ई. प्रथम स्तर', 'एन.सि.ई. प्रथम स्तर', 'Pg=l;=O{= k|yd ', NULL, '>])fLljlxg k|yd ', NULL, NULL),
	(64, 'KALIGADH(NCE)ARMY', 'KALIGADH(NCE)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(65, 'SENIOR ENGINEER', 'SENIOR ENGR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(66, 'JUNIOR ENGINEER', 'JUNIOR ENGR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(67, 'SENIOR MISTRI/SUPERVISOR', 'SENIOR MISTRI', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(68, 'JUNIOR MISTRI', 'JUNIOR MISTRI', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(69, 'EMPRUVER', 'EMPRUVER', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(70, 'GAZATED I', 'GAZATED I', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(71, 'CLEARNER', 'CLEANER', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(72, 'GAZATED II', 'GAZATED II', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(73, 'RADIO AND ELECTRICAL ENGINEER', 'RADIOENGR', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(74, 'GAZATED III', 'GAZATED III', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(75, 'SA PRA NI (SASHASTRA PRAHARI)', 'SA PRA NI', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(76, 'CIVIL MASTER MA VI', 'CI MA MA VI', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(77, 'OFFICER CADET(SGT)', 'OFF CADET(HAV.)', 'x\'$F Sof*]^', 'हुद्बा क्याडेट', 'हुद्बा क्याडेट', NULL, 'Hukya', NULL, NULL, NULL),
	(78, 'NON GAZ I', 'NON GAZ I', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(79, 'HALUKA SAWARI CHALAK 6TH LEVEL', 'SAWARI CHA/6TH', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(80, 'CIVIL MASTER NI MA', 'CI MA NI MA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(81, 'HALUKA SAWARI CHALAK 5TH LEVEL', 'SAWARI CHA/5TH', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(82, 'NON GAZ II', 'NON GAZ II', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(83, 'PEON/PARICHAR 6TH LEVEL', 'PEON/PAR 6TH LV', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(84, 'CIVIL MASTER PRA', 'CI MA PRA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(85, 'PEON/PARICHAR 4TH LEVEL', 'PEON/PAR 4TH LV', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(86, 'NON GAZ III', 'NON GAZ III', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(87, 'PEON/PARICHAR 5TH LEVEL', 'PEON/PAR 5TH LV', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(88, 'NON GAZ IV', 'NON GAZ IV', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(89, 'PEON/PARICHAR 1ST LEVEL', 'PEON/PAR 1ST LV', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(90, 'PEON/PARICHAR 2ND LEVEL', 'PEON/PAR 2ND LV', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(91, 'PEON/PARICHAR 3RD LEVEL', 'PEON/PAR 3RD LV', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(92, 'HALUKA SAWARI CHALAK 1ST LEVEL', 'SAWARI CHA/1ST', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(93, 'HALUKA SAWARI CHALAK 2ND LEVEL', 'SAWARI CHA/2ND', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(94, 'HALUKA SAWARI CHALAK 3RD LEVEL', 'SAWARI CHA/3RD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(95, 'HALUKA SAWARI CHALAK 4TH LEVEL', 'SAWARI CHA/4TH', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(96, 'BHARI SAWARI CHALAK 1ST LEVEL', 'BHARI CHA/1ST', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(97, 'BHARI SAWARI CHALAK 2ND LEVEL', 'BHARI CHA/2ND', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(98, 'BHARI SAWARI CHALAK 3RD LEVEL', 'BHARI CHA/3RD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(99, 'UNCLASSIFIED RANK', 'UN RANK', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
	(100, 'FELLOW CHARTERED ACCOUNTANT', 'FCA', NULL, 'वरिष्ठ चार्टर्ड एकाउन्टेन्ट', 'वरिष्ठ चार्टर्ड एकाउन्टेन्ट', NULL, NULL, NULL, NULL, 'Y'),
	(101, 'JAG BRIGADIER GENERAL', 'JAG BRI GEN', ';xfos /yL', 'प्राड सहायक रथी', 'प्राड सहायक रथी', ';=/', 'Rathi', '/F=k= k|yd >])fL', 'GAZATED I', 'Y'),
	(102, 'SECTION OFFICER', 'SECTION OFFICER', NULL, 'शाखा अधिकृत', 'शाखा अधिकृत', NULL, NULL, NULL, NULL, 'Y'),
	(103, 'MAJOR (Rtd.)', 'MAJOR (Rtd.)', NULL, 'सेनानी (अ.प्रा.)', 'सेनानी (अ.प्रा.)', NULL, NULL, NULL, NULL, 'Y'),
	(104, 'LIEUTENANT COLONEL (Rtd.)', 'LT COL (Rtd.)', NULL, 'प्र. से. (अ.प्रा.)', 'प्र. से. (अ.प्रा.)', NULL, NULL, NULL, NULL, 'Y'),
	(105, 'NAYEB SUBBA', 'NA.SU.', NULL, 'नायव सुब्बा', 'नायव सुब्बा', NULL, NULL, NULL, NULL, 'Y'),
	(106, 'SU.KA. (Rtd.)', 'SU.KA. (Rtd.)', NULL, 'सु.क. (अ.प्रा.)', 'सु.क. (अ.प्रा.)', NULL, NULL, NULL, NULL, 'Y'),
	(107, 'JAMA.KA. (Rtd.)', 'JAMA.KA. (Rtd.)', NULL, 'जम.क. (अ.प्रा.)', 'जम.क. (अ.प्रा.)', NULL, NULL, NULL, NULL, 'Y'),
	(108, 'Maj. Gen. (Rtd.)', 'Maj. Gen. (Rtd.)', NULL, 'उ.र. (अ.प्रा.)', 'उ.र. (अ.प्रा.)', NULL, NULL, NULL, NULL, 'Y'),
	(109, 'COLONEL (Rtd.)', 'COL (Rtd.)', 'dxf ;]gfgL', 'म.से. (अ.प्रा.)', 'म.से. (अ.प्रा.)', 'd=;]=', 'Adhikrit', '/F=k= k|yd >])fL', 'GAZATED I', 'Y'),
	(110, 'HONORARY MAJOR GENERAL', 'HON MAJ GEN', NULL, 'मा.उ.र.', 'मानार्थ उप रथी', NULL, 'Rathi', '', '', 'Y'),
	(111, 'Police Constable', 'Police Constable', NULL, 'प्रहरी जवान', 'प्रहरी जवान', NULL, NULL, NULL, NULL, NULL),
	(112, 'Police Head Constable', 'Police Head Constable', NULL, 'प्रहरी हवल्दार', 'प्रहरी हवल्दार', NULL, NULL, NULL, NULL, NULL),
	(113, 'Assistant Sub Inspector of Police', 'Assistant Sub Inspector of Police', NULL, 'प्रहरी सहायक निरीक्षक', 'प्रहरी सहायक निरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(114, 'Sub Inspector of Police', 'Sub Inspector of Police', NULL, 'प्रहरी नायव निरीक्षक', 'प्रहरी नायव निरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(115, 'Police Inspector', 'Police Inspector', NULL, 'प्रहरी निरीक्षक', 'प्रहरी निरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(116, 'Deputy Superintendent of Police', 'DSP', NULL, 'प्रहरी नायब उपरीक्षक', 'प्रहरी नायब उपरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(117, 'Superintendent of Police', 'SP', NULL, 'प्रहरी उपरीक्षक', 'प्रहरी उपरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(118, 'Senior Superintendent of Police', 'SSP', NULL, 'प्रहरी वरिष्ठ उपरीक्षक', 'प्रहरी वरिष्ठ उपरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(119, 'Deputy Inspector General of Police', 'DIGP', NULL, 'प्रहरी नायब महानिरीक्षक', 'प्रहरी नायब महानिरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(120, 'Additional Inspector General of Police', 'AIGP', NULL, 'प्रहरी अतिरिक्त महानिरीक्षक', 'प्रहरी अतिरिक्त महानिरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(121, 'Inspector General of Police', 'IGP', NULL, 'प्रहरी महानिरीक्षक', 'प्रहरी महानिरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(122, 'Follower(APF)', 'Follower', NULL, 'सशस्त्र प्रहरी परिचर', 'सशस्त्र प्रहरी परिचर', NULL, NULL, NULL, NULL, NULL),
	(123, 'Constable (APF)', 'Constable', NULL, 'सशस्त्र प्रहरी जवान', 'सशस्त्र प्रहरी जवान', NULL, NULL, NULL, NULL, NULL),
	(124, 'Assistant Head Constable (APF)', 'AHC', NULL, 'सशस्त्र प्रहरी सहायक हवल्दार', 'सशस्त्र प्रहरी सहायक हवल्दार', NULL, NULL, NULL, NULL, NULL),
	(125, 'Head Constable (APF)', 'HC', NULL, 'सशस्त्र प्रहरी हवल्दार', 'सशस्त्र प्रहरी हवल्दार', NULL, NULL, NULL, NULL, NULL),
	(126, 'Senior Head Constable (APF)', 'SHC', NULL, 'सशस्त्र प्रहरी वरिष्ठ हवल्दार', 'सशस्त्र प्रहरी वरिष्ठ हवल्दार', NULL, NULL, NULL, NULL, NULL),
	(127, 'Assistant Sub Inspector (APF)', 'ASI', NULL, 'सशस्त्र प्रहरी सहायक निरीक्षक', 'सशस्त्र प्रहरी सहायक निरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(128, 'Sub Inspector(APF)', 'SI', NULL, 'सशस्त्र प्रहरी नायब निरीक्षक', 'सशस्त्र प्रहरी नायब निरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(129, 'Senior Sub Inspector (APF)', 'SSI', NULL, 'सशस्त्र प्रहरी वरिष्ठ नायब निरीक्षक', 'सशस्त्र प्रहरी वरिष्ठ नायब निरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(130, 'Inspector(APF)', 'INS', NULL, 'सशस्त्र प्रहरी निरीक्षक', 'सशस्त्र प्रहरी निरीक्षक', NULL, NULL, NULL, NULL, 'Y'),
	(131, 'Deputy Superintendent (APF)', 'DSP', NULL, 'सशस्त्र प्रहरी नायब उपरीक्षक', 'सशस्त्र प्रहरी नायब उपरीक्षक', NULL, NULL, NULL, NULL, 'Y'),
	(132, 'Superintendent (APF)', 'SP', NULL, 'सशस्त्र प्रहरी उपरीक्षक', 'सशस्त्र प्रहरी उपरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(133, 'Senior Superintendent (APF)', 'SSP', NULL, 'सशस्त्र प्रहरी वरिष्ठ उपरीक्षक', 'सशस्त्र प्रहरी वरिष्ठ उपरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(134, 'Deputy Inspector General (APF)', 'DIG', NULL, 'सशस्त्र प्रहरी नायब महानिरीक्षक', 'सशस्त्र प्रहरी नायब महानिरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(135, 'Additional Inspector General (APF)', 'AIG', NULL, 'सशस्त्र प्रहरी अतिरिक्त महानिरीक्षक', 'सशस्त्र प्रहरी अतिरिक्त महानिरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(136, 'Inspector General (APF)', 'IG', NULL, 'सशस्त्र प्रहरी महानिरीक्षक', 'सशस्त्र प्रहरी महानिरीक्षक', NULL, NULL, NULL, NULL, NULL),
	(137, 'Brigj. Gen. (Rtd.)', 'Brigj. Gen. (Rtd.)', NULL, 'स.र. (अ.प्रा.)', 'स.र. (अ.प्रा.)', NULL, NULL, NULL, NULL, 'Y'),
	(138, 'PRITANA QUARTERMASTER HAVALDAR', 'PRI HAV MAJ', NULL, 'पृतना प्रबन्ध हुद्बा', 'पृतना प्रबन्ध हुद्बा', NULL, NULL, NULL, NULL, 'Y'),
	(139, 'BRIGADE QUARTERMASTER HAVALDAR', 'BRIG  QUARTERMASTER HAVALDAR', NULL, 'बाहिनी प्रबन्ध हुद्बा', 'बाहिनी प्रबन्ध हुद्बा', NULL, NULL, NULL, NULL, 'Y'),
	(140, 'HONORARY COLONEL', 'HON COL', NULL, 'मानार्थ महा सेनानी', 'मानार्थ महा सेनानी', NULL, 'Adhikrit', NULL, 'GAZATED I', 'Y'),
	(141, 'HONORARY COLONEL (Rtd.)', 'HON COL (Rtd.)', NULL, 'मानार्थ महा सेनानी (अ.प्रा.)', 'मानार्थ महा सेनानी (अ.प्रा.)', NULL, 'Adhikrit', NULL, 'GAZATED I', 'Y'),
	(142, 'LIEUTENANT GENERAL(Rtd.)', 'Lt.Gen.(Rtd.)', NULL, 'रथी (अ.प्रा.)', 'रथी(अ.प्रा.)', NULL, 'Rathi', NULL, NULL, 'Y'),
	(143, 'HONORARY BRIGADIER GENERAL (Ret.)', 'HONORARY BRIGADIER GENERAL (Ret.)', NULL, 'मानार्थ सहायक रथी', 'मानार्थ सहायक रथी', NULL, NULL, NULL, NULL, 'Y'),
	(144, 'BRIGADE HAVALDAR MAJOR', 'BRIG  HAV MAJ', NULL, 'बाहिनी कार्य हुद्बा', 'बाहिनी कार्य हुद्बा', NULL, NULL, NULL, NULL, 'Y'),
	(145, 'LOGISTIC ADMINISTRATIVE LIEUTENANT COLONEL', 'L.A. Lt.Col.', NULL, 'ब.का. प्रमुख सेनानी', 'ब.का. प्रमुख सेनानी', NULL, NULL, NULL, NULL, 'Y'),
	(147, 'LOG ADM CAPTAIN', 'LOG ADM CAPTAIN', ';x ;]gfgL', 'ब.का. सह सेनानी', 'ब.का. सह सेनानी', ';x=;]=', 'Adhikrit', '/f=k= t]>f] >])fL', 'GAZATED III', 'Y');

-- Dumping structure for table hr.event_participants
CREATE TABLE IF NOT EXISTS `event_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.event_participants: ~0 rows (approximately)

-- Dumping structure for table hr.events
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `event_type` varchar(50) DEFAULT 'meeting',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'medium',
  `status` varchar(20) DEFAULT 'upcoming',
  `personnel_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.events: ~0 rows (approximately)

-- Dumping structure for table hr.leave_balance
CREATE TABLE IF NOT EXISTS `leave_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_id` varchar(20) DEFAULT NULL,
  `gharpari_bida_days` decimal(5,1) DEFAULT 0.0,
  `parba_bida_days` decimal(5,1) DEFAULT 0.0,
  `bhaeepari_bida_days` decimal(5,1) DEFAULT 0.0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `personnel_id` (`personnel_id`),
  UNIQUE KEY `idx_personnel_id` (`personnel_id`),
  UNIQUE KEY `unique_personnel` (`personnel_id`)
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.leave_balance: ~6 rows (approximately)
INSERT INTO `leave_balance` (`id`, `personnel_id`, `gharpari_bida_days`, `parba_bida_days`, `bhaeepari_bida_days`, `last_updated`) VALUES
	(183, '8512', 15.0, 12.0, 10.0, '2026-06-04 04:38:43'),
	(184, '8571', 15.0, 12.0, 10.0, '2026-06-04 09:20:18'),
	(185, '8572', 14.0, 12.0, 10.0, '2026-06-04 04:46:38'),
	(186, '8589', 30.0, 12.0, 10.0, '2026-06-04 06:37:00'),
	(187, '8590', 15.0, 12.0, 10.0, '2026-06-04 09:20:28'),
	(188, '857', 15.0, 12.0, 10.0, '2026-06-04 09:20:45'),
	(189, '8927', 15.0, 12.0, 10.0, '2026-06-10 05:01:25');

-- Dumping structure for table hr.leave_requests
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_id` varchar(20) DEFAULT NULL,
  `leave_type` enum('annual','sick','casual','emergency','study','maternity','paternity','gharpari_bida','parba_bida','bhaeepari_bida') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `leave_days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `initiating_officer` varchar(20) DEFAULT NULL,
  `accepting_officer` varchar(20) DEFAULT NULL,
  `verifying_officer` varchar(20) DEFAULT NULL,
  `verifying_officer_approved` tinyint(1) DEFAULT 0,
  `verifying_officer_approved_by` varchar(20) DEFAULT NULL,
  `verifying_officer_approved_at` datetime DEFAULT NULL,
  `verifying_officer_remarks` text DEFAULT NULL,
  `initiating_officer_approved` tinyint(1) DEFAULT 0,
  `initiating_officer_approved_by` varchar(20) DEFAULT NULL,
  `initiating_officer_approved_at` datetime DEFAULT NULL,
  `initiating_officer_remarks` text DEFAULT NULL,
  `accepting_officer_approved` tinyint(1) DEFAULT 0,
  `accepting_officer_approved_by` varchar(20) DEFAULT NULL,
  `accepting_officer_approved_at` datetime DEFAULT NULL,
  `accepting_officer_remarks` text DEFAULT NULL,
  `receiver_id` varchar(20) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `alternate_officer` varchar(100) DEFAULT NULL,
  `support_officer` int(11) DEFAULT NULL,
  `finalizing_officer` int(11) DEFAULT NULL,
  `status` enum('pending','verified','initiating_approved','forwarded','approved','rejected','cancelled') DEFAULT 'pending',
  `created_by` varchar(20) DEFAULT NULL,
  `approved_by` varchar(20) DEFAULT NULL,
  `forwarded_by` varchar(20) DEFAULT NULL,
  `forwarded_at` datetime DEFAULT NULL,
  `forwarded_to` varchar(20) DEFAULT NULL,
  `forward_remarks` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approver_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_personnel` (`personnel_id`),
  KEY `fk_leave_initiating_officer_approved_by` (`initiating_officer_approved_by`),
  KEY `fk_leave_accepting_officer_approved_by` (`accepting_officer_approved_by`),
  KEY `idx_initiating_officer` (`initiating_officer`),
  KEY `idx_accepting_officer` (`accepting_officer`),
  KEY `idx_receiver_id` (`receiver_id`),
  KEY `idx_verifying_officer` (`verifying_officer`),
  KEY `fk_leave_verifying_officer_approved_by` (`verifying_officer_approved_by`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.leave_requests: ~6 rows (approximately)
INSERT INTO `leave_requests` (`id`, `personnel_id`, `leave_type`, `start_date`, `end_date`, `leave_days`, `reason`, `initiating_officer`, `accepting_officer`, `verifying_officer`, `verifying_officer_approved`, `verifying_officer_approved_by`, `verifying_officer_approved_at`, `verifying_officer_remarks`, `initiating_officer_approved`, `initiating_officer_approved_by`, `initiating_officer_approved_at`, `initiating_officer_remarks`, `accepting_officer_approved`, `accepting_officer_approved_by`, `accepting_officer_approved_at`, `accepting_officer_remarks`, `receiver_id`, `contact_number`, `alternate_officer`, `support_officer`, `finalizing_officer`, `status`, `created_by`, `approved_by`, `forwarded_by`, `forwarded_at`, `forwarded_to`, `forward_remarks`, `approved_at`, `approver_remarks`, `created_at`, `updated_at`) VALUES
	(72, '8572', 'gharpari_bida', '2026-06-04', '2026-06-04', 1, 'ok', '857', '8512', '8589', 1, NULL, '2026-06-04 10:30:41', 'ok', 1, NULL, '2026-06-04 10:31:18', 'ok', 1, NULL, '2026-06-04 10:31:38', 'okok', '8589', NULL, NULL, NULL, NULL, 'approved', '8589', NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:31:38', NULL, '2026-06-04 04:45:09', '2026-06-04 04:46:38'),
	(73, '8572', 'gharpari_bida', '2026-06-26', '2026-06-27', 2, 'ok', '857', '8512', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:53:08', '2026-06-04 04:53:08'),
	(74, '8571', 'gharpari_bida', '2026-06-04', '2026-06-04', 1, 'ok', '8512', '8589', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8512', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 09:18:24', '2026-06-04 09:21:03'),
	(75, '8572', 'bhaeepari_bida', '2026-06-21', '2026-06-22', 2, 'ok', '8571', '8512', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:30:55', '2026-06-04 10:30:55'),
	(76, '8512', 'gharpari_bida', '2026-06-24', '2026-06-26', 3, 'ok', '8572', '8571', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:31:26', '2026-06-04 10:31:26'),
	(77, '8571', 'parba_bida', '2026-06-23', '2026-06-26', 4, 'ok', '8572', '8512', '8589', 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '8572', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 10:32:03', '2026-06-04 10:32:03');

-- Dumping structure for table hr.leave_settings
CREATE TABLE IF NOT EXISTS `leave_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `gharpari_bida_default` decimal(5,1) DEFAULT 0.0,
  `parba_bida_default` decimal(5,1) DEFAULT 0.0,
  `bhaeepari_bida_default` decimal(5,1) DEFAULT 0.0,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.leave_settings: ~0 rows (approximately)

-- Dumping structure for table hr.leave_types
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_name` varchar(100) NOT NULL,
  `available_days` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_name` (`leave_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr.leave_types: ~0 rows (approximately)

-- Dumping structure for table hr.military_personnel_status
CREATE TABLE IF NOT EXISTS `military_personnel_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_number` varchar(20) DEFAULT NULL,
  `personnel_name` varchar(255) NOT NULL,
  `rank` varchar(100) NOT NULL,
  `status` enum('present','leave','sick','work','workout','tdy','course') NOT NULL,
  `record_date` date NOT NULL,
  `in_time` time DEFAULT NULL,
  `out_time` time DEFAULT NULL,
  `remarks` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`record_date`),
  KEY `idx_personnel_number` (`personnel_number`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.military_personnel_status: ~0 rows (approximately)

-- Dumping structure for table hr.personnel
CREATE TABLE IF NOT EXISTS `personnel` (
  `personnel_number` int(10) NOT NULL DEFAULT 0,
  `full_name_en` varchar(100) DEFAULT NULL,
  `full_name_ne` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT 'Male',
  `blood_group` enum('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
  `rank` int(11) DEFAULT NULL,
  `current_status` enum('Active','Retired') DEFAULT 'Active',
  `unit` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `signature` varchar(500) DEFAULT NULL,
  `joint_date` date DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `ward_number` varchar(10) DEFAULT NULL,
  `village_tole` varchar(200) DEFAULT NULL,
  `religion` varchar(50) DEFAULT 'Hindu',
  `military_status` varchar(20) DEFAULT 'Single',
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `recruitment_date` date DEFAULT NULL,
  `commission_date` date DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `spouse_name` varchar(100) DEFAULT NULL,
  `children_names` text DEFAULT NULL,
  `family_notes` text DEFAULT NULL,
  `grandfather_name` varchar(100) DEFAULT NULL,
  `higher_education` text DEFAULT NULL,
  `military_trainings` text DEFAULT NULL,
  `training` text DEFAULT NULL,
  `training_address` varchar(255) DEFAULT NULL,
  `training1` varchar(200) DEFAULT NULL,
  `training1_address` varchar(255) DEFAULT NULL,
  `training2` varchar(200) DEFAULT NULL,
  `training2_address` varchar(255) DEFAULT NULL,
  `training3` varchar(200) DEFAULT NULL,
  `training4` varchar(200) DEFAULT NULL,
  `training5` varchar(200) DEFAULT NULL,
  `foreign_training` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` tinyint(4) NOT NULL DEFAULT 0,
  `password_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when password was last updated',
  `appointment` varchar(255) DEFAULT NULL,
  `profile_picture_path` varchar(255) DEFAULT NULL,
  `received_person_name` varchar(255) DEFAULT NULL,
  `professional_trainings` longtext DEFAULT NULL COMMENT 'Dynamic professional trainings stored as JSON array' CHECK (json_valid(`professional_trainings`)),
  `foreign_trainings` longtext DEFAULT NULL COMMENT 'Dynamic foreign trainings stored as JSON array' CHECK (json_valid(`foreign_trainings`)),
  PRIMARY KEY (`personnel_number`) USING BTREE,
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_full_name_en` (`full_name_en`),
  KEY `idx_province` (`province`),
  KEY `idx_district` (`district`),
  KEY `idx_municipality` (`municipality`),
  KEY `idx_current_status` (`current_status`) USING BTREE,
  KEY `idx_personnel_number` (`personnel_number`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr.personnel: ~6 rows (approximately)
INSERT INTO `personnel` (`personnel_number`, `full_name_en`, `full_name_ne`, `dob`, `gender`, `blood_group`, `rank`, `current_status`, `unit`, `email`, `signature`, `joint_date`, `contact`, `phone`, `address`, `province`, `district`, `municipality`, `ward_number`, `village_tole`, `religion`, `military_status`, `password`, `remember_token`, `recruitment_date`, `commission_date`, `father_name`, `mother_name`, `spouse_name`, `children_names`, `family_notes`, `grandfather_name`, `higher_education`, `military_trainings`, `training`, `training_address`, `training1`, `training1_address`, `training2`, `training2_address`, `training3`, `training4`, `training5`, `foreign_training`, `created_at`, `updated_at`, `role`, `password_updated_at`, `appointment`, `profile_picture_path`, `received_person_name`, `professional_trainings`, `foreign_trainings`) VALUES
	(8512, 'Suraj shahi', 'सुरज शाही', '2026-06-09', 'Male', 'A-', 19, 'Active', 'Artillery', 'superadmin@gmail.com', '/uploads/signatures/8512_signature_1780548947.png', '2026-06-04', '9841121325', '9841121325', NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$5XeiiICmCOyh6r7PAPibXOLGaK5R0JP.BqofEoNhHWRiJLwkr54tW', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:38:43', '2026-06-10 09:35:20', 2, NULL, NULL, 'uploads/profile_photos/8512_photo_1780548939.jpeg', NULL, NULL, NULL),
	(8571, 'Dikshya Thapa', 'दिक्षा थापा', '2026-06-09', 'Female', 'B-', 19, 'Active', 'Intelligence Corps', 'admin@gmail.com', '/uploads/signatures/857_signature_1780548928.png', '2026-06-04', '9841121325', '9841121325', NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$nIOXIsopIfey72s7mlLKjeloQjARQcfuc33sOB2OKXMGBUpYv/VdC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:40:59', '2026-06-10 09:35:14', 1, NULL, NULL, 'uploads/profile_photos/857_photo_1780548918.jpeg', NULL, NULL, NULL),
	(8572, 'Susmin Basnet', 'सुस्मिन बस्नेत', '2026-06-09', 'Male', 'O-', 19, 'Active', 'Cyber Security Directorate', 'user@gmail.com', '/uploads/signatures/8572_signature_1780548905.png', '2026-06-04', '9841121325', '9841121325', NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$JCIm.C8mfKlpBy6nV2hqTuchedQsgD6X2Cp7HXVE9/gJI8gOtYxCq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:41:51', '2026-06-10 10:41:26', 0, NULL, NULL, 'uploads/profile_photos/8572_photo_1780548896.jpeg', NULL, NULL, NULL),
	(8589, 'Manish Ghimire', 'मनिष घिमिरे', '2026-06-09', 'Male', 'AB-', 19, 'Active', 'Corps of Engineers', 'manish@gmail.com', '/uploads/signatures/8589_signature_1780548879.png', '2026-06-21', '9841121325', '9841121325', NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$JCIm.C8mfKlpBy6nV2hqTuchedQsgD6X2Cp7HXVE9/gJI8gOtYxCq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:43:09', '2026-06-10 09:35:18', 0, NULL, NULL, 'uploads/profile_photos/8589_photo_1780548865.jpeg', NULL, NULL, NULL),
	(8927, 'Rakesh Dangol', 'राकेश डंगोल', '1991-02-20', 'Male', 'B+', 19, 'Active', 'Corps of Engineers', 'rdangol@gmail.com', '/uploads/signatures/8589_signature_1780548879.png', '2026-06-21', '9841121325', '9841121325', '', NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$JCIm.C8mfKlpBy6nV2hqTuchedQsgD6X2Cp7HXVE9/gJI8gOtYxCq', NULL, '0000-00-00', '0000-00-00', '', '', '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:43:09', '2026-06-10 10:20:10', 2, NULL, NULL, 'uploads/profile_photos/8589_photo_1780548865.jpeg', NULL, NULL, NULL),
	(282531, 'Uma Shanker Yadav', 'उमा शंकर यादव', '2006-06-09', 'Male', 'O+', 37, 'Active', 'Cyber Security Directorate', 'uma@gmail.com', '/uploads/signatures/8572_signature_1780548905.png', '2026-06-04', '9841121325', '9841121325', NULL, NULL, NULL, NULL, NULL, NULL, 'Hindu', 'Single', '$2y$10$pcmPO8kxrTAfbddmTcfkbe5k90pl6tu/LGM04nTvG1ufmy4A6Hhcm', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-04 04:41:51', '2026-06-10 10:41:24', 0, NULL, NULL, 'uploads/profile_photos/8572_photo_1780548896.jpeg', NULL, NULL, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

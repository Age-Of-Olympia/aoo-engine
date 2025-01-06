-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : mariadb-aoo4
-- Généré le : lun. 06 jan. 2025 à 00:01
-- Version du serveur : 11.5.2-MariaDB-ubu2404
-- Version de PHP : 8.2.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `aoo4-fromscratch`
--

-- --------------------------------------------------------

--
-- Structure de la table `coords`
--

CREATE TABLE `coords` (
  `id` int(11) NOT NULL,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `z` int(11) NOT NULL DEFAULT 0,
  `plan` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `coords`
--

INSERT INTO `coords` (`id`, `x`, `y`, `z`, `plan`) VALUES
(1, 0, 0, 0, 'gaia'),
(2, 0, -1, 0, 'gaia'),
(3, 1, 0, 0, 'gaia'),
(4, 1, -1, 0, 'gaia'),
(5, 2, 0, 0, 'gaia'),
(6, 0, -2, 0, 'gaia'),
(7, 1, -2, 0, 'gaia'),
(8, 2, -2, 0, 'gaia'),
(9, 2, -3, 0, 'gaia'),
(10, 3, -2, 0, 'gaia'),
(11, 4, -2, 0, 'gaia'),
(12, 2, -4, 0, 'gaia'),
(13, 3, -3, 0, 'gaia'),
(14, 3, -4, 0, 'gaia'),
(15, 4, -4, 0, 'gaia'),
(16, 4, -3, 0, 'gaia'),
(17, 2, -5, 0, 'gaia'),
(18, 3, -5, 0, 'gaia'),
(19, 4, -5, 0, 'gaia'),
(20, 5, -5, 0, 'gaia'),
(21, 5, -4, 0, 'gaia'),
(22, 5, -3, 0, 'gaia'),
(23, 5, -2, 0, 'gaia'),
(24, 0, -3, 0, 'gaia'),
(25, 1, -3, 0, 'gaia'),
(26, 2, -6, 0, 'gaia'),
(27, 3, -6, 0, 'gaia'),
(28, 4, -6, 0, 'gaia'),
(29, 5, -6, 0, 'gaia'),
(30, 6, -6, 0, 'gaia'),
(31, 5, -7, 0, 'gaia'),
(32, 6, -5, 0, 'gaia'),
(33, 7, -5, 0, 'gaia'),
(34, 7, -6, 0, 'gaia'),
(35, 6, -7, 0, 'gaia'),
(36, 8, -5, 0, 'gaia'),
(37, 9, -6, 0, 'gaia'),
(38, 10, -7, 0, 'gaia'),
(39, 10, -8, 0, 'gaia'),
(40, 9, -9, 0, 'gaia'),
(41, 8, -10, 0, 'gaia'),
(42, 7, -10, 0, 'gaia'),
(43, 6, -9, 0, 'gaia'),
(44, 5, -8, 0, 'gaia'),
(45, 8, -6, 0, 'gaia'),
(46, 9, -7, 0, 'gaia'),
(47, 10, -9, 0, 'gaia'),
(48, 9, -10, 0, 'gaia'),
(49, 6, -10, 0, 'gaia'),
(50, 5, -9, 0, 'gaia'),
(51, 7, -11, 0, 'gaia'),
(52, 8, -11, 0, 'gaia'),
(53, -2, -1, 0, 'gaia'),
(54, 0, 3, 0, 'gaia'),
(55, 0, 2, 0, 'gaia'),
(56, -2, -2, 0, 'gaia'),
(57, -4, 1, 0, 'gaia'),
(58, -4, 0, 0, 'gaia'),
(59, 2, -1, 0, 'gaia'),
(60, -1, 1, 0, 'gaia'),
(61, 0, 1, 0, 'gaia'),
(62, -1, 0, 0, 'gaia'),
(63, -1, -1, 0, 'gaia'),
(64, -1, -2, 0, 'gaia'),
(65, -1, -3, 0, 'gaia'),
(66, 1, -4, 0, 'gaia'),
(67, 1, -5, 0, 'gaia'),
(68, 1, -6, 0, 'gaia'),
(69, 4, -7, 0, 'gaia'),
(70, 4, -8, 0, 'gaia'),
(71, 4, -9, 0, 'gaia'),
(72, 5, -10, 0, 'gaia'),
(73, 1, 1, 0, 'gaia'),
(74, 2, 1, 0, 'gaia'),
(75, 3, 1, 0, 'gaia'),
(76, 3, 0, 0, 'gaia'),
(77, 3, -1, 0, 'gaia'),
(78, 4, -1, 0, 'gaia'),
(79, 5, -1, 0, 'gaia'),
(80, 6, -1, 0, 'gaia'),
(81, 6, -2, 0, 'gaia'),
(82, 6, -3, 0, 'gaia'),
(83, 6, -4, 0, 'gaia'),
(84, 7, -4, 0, 'gaia'),
(85, 8, -4, 0, 'gaia'),
(86, 9, -4, 0, 'gaia'),
(87, 9, -5, 0, 'gaia'),
(88, 10, -5, 0, 'gaia'),
(89, 10, -6, 0, 'gaia'),
(90, 6, -11, 0, 'gaia'),
(91, 9, -11, 0, 'gaia'),
(92, 10, -10, 0, 'gaia'),
(93, 11, -6, 0, 'gaia'),
(94, 11, -7, 0, 'gaia'),
(95, 11, -8, 0, 'gaia'),
(96, 11, -9, 0, 'gaia'),
(97, 7, -7, 0, 'gaia'),
(98, 8, -7, 0, 'gaia'),
(99, 7, -8, 0, 'gaia'),
(100, 8, -8, 0, 'gaia'),
(101, 3, -3, 0, 'gaia2'),
(102, 4, -4, 0, 'gaia2'),
(103, 2, -2, 0, 'gaia2'),
(104, 3, -2, 0, 'gaia2'),
(105, 4, -2, 0, 'gaia2'),
(106, 4, -3, 0, 'gaia2'),
(107, 2, -3, 0, 'gaia2'),
(108, 2, -4, 0, 'gaia2'),
(109, 3, -4, 0, 'gaia2'),
(110, 1, -2, 0, 'gaia2'),
(111, 2, -1, 0, 'gaia2'),
(112, 2, 0, 0, 'gaia2'),
(113, 1, 0, 0, 'gaia2'),
(114, 0, 0, 0, 'gaia2'),
(115, 0, -1, 0, 'gaia2'),
(116, 0, -2, 0, 'gaia2'),
(117, 1, -1, 0, 'gaia2'),
(118, 5, -2, 0, 'gaia2'),
(119, 5, -3, 0, 'gaia2'),
(120, 5, -4, 0, 'gaia2'),
(121, 2, -5, 0, 'gaia2'),
(122, 3, -5, 0, 'gaia2'),
(123, 4, -5, 0, 'gaia2'),
(124, 5, -5, 0, 'gaia2'),
(125, 0, -3, 0, 'gaia2'),
(126, 1, -3, 0, 'gaia2'),
(127, 2, -6, 0, 'gaia2'),
(128, 3, -6, 0, 'gaia2'),
(129, 4, -6, 0, 'gaia2'),
(130, 5, -6, 0, 'gaia2'),
(131, 6, -5, 0, 'gaia2'),
(132, 6, -6, 0, 'gaia2'),
(133, 7, -5, 0, 'gaia2'),
(134, 5, -7, 0, 'gaia2'),
(135, 7, -6, 0, 'gaia2'),
(136, 6, -7, 0, 'gaia2'),
(137, 8, -5, 0, 'gaia2'),
(138, 9, -6, 0, 'gaia2'),
(139, 10, -7, 0, 'gaia2'),
(140, 10, -8, 0, 'gaia2'),
(141, 9, -9, 0, 'gaia2'),
(142, 8, -10, 0, 'gaia2'),
(143, 7, -10, 0, 'gaia2'),
(144, 6, -9, 0, 'gaia2'),
(145, 5, -8, 0, 'gaia2'),
(146, 5, -9, 0, 'gaia2'),
(147, 6, -10, 0, 'gaia2'),
(148, 8, -6, 0, 'gaia2'),
(149, 9, -7, 0, 'gaia2'),
(150, 10, -9, 0, 'gaia2'),
(151, 9, -10, 0, 'gaia2'),
(152, 7, -11, 0, 'gaia2'),
(153, 8, -11, 0, 'gaia2'),
(154, -2, -1, 0, 'gaia2'),
(155, -4, 1, 0, 'gaia2'),
(156, 0, 3, 0, 'gaia2'),
(157, -2, -2, 0, 'gaia2'),
(158, -4, 0, 0, 'gaia2'),
(159, 0, 2, 0, 'gaia2'),
(160, 7, -7, 0, 'gaia2'),
(161, 6, -8, 0, 'gaia2'),
(162, 7, -8, 0, 'gaia2'),
(163, 7, -9, 0, 'gaia2'),
(164, 8, -8, 0, 'gaia2'),
(165, 8, -9, 0, 'gaia2'),
(166, 9, -8, 0, 'gaia2'),
(167, 8, -7, 0, 'gaia2'),
(168, -1, 1, 0, 'gaia2'),
(169, -1, 0, 0, 'gaia2'),
(170, -1, -1, 0, 'gaia2'),
(171, -1, -2, 0, 'gaia2'),
(172, -1, -3, 0, 'gaia2'),
(173, 1, -4, 0, 'gaia2'),
(174, 1, -5, 0, 'gaia2'),
(175, 1, -6, 0, 'gaia2'),
(176, 4, -7, 0, 'gaia2'),
(177, 4, -8, 0, 'gaia2'),
(178, 4, -9, 0, 'gaia2'),
(179, 5, -10, 0, 'gaia2'),
(180, 0, 1, 0, 'gaia2'),
(181, 1, 1, 0, 'gaia2'),
(182, 2, 1, 0, 'gaia2'),
(183, 3, 1, 0, 'gaia2'),
(184, 3, 0, 0, 'gaia2'),
(185, 3, -1, 0, 'gaia2'),
(186, 4, -1, 0, 'gaia2'),
(187, 5, -1, 0, 'gaia2'),
(188, 6, -1, 0, 'gaia2'),
(189, 6, -2, 0, 'gaia2'),
(190, 6, -3, 0, 'gaia2'),
(191, 6, -4, 0, 'gaia2'),
(192, 7, -4, 0, 'gaia2'),
(193, 8, -4, 0, 'gaia2'),
(194, 9, -4, 0, 'gaia2'),
(195, 9, -5, 0, 'gaia2'),
(196, 10, -5, 0, 'gaia2'),
(197, 10, -6, 0, 'gaia2'),
(198, 6, -11, 0, 'gaia2'),
(199, 9, -11, 0, 'gaia2'),
(200, 10, -10, 0, 'gaia2'),
(201, 11, -9, 0, 'gaia2'),
(202, 11, -8, 0, 'gaia2'),
(203, 11, -7, 0, 'gaia2'),
(204, 11, -6, 0, 'gaia2'),
(12670, -10, -10, 0, 'enfers'),
(12671, -22, 22, 0, 'enfers'),
(12672, -22, 21, 0, 'enfers'),
(12673, -21, 20, 0, 'enfers'),
(12674, -20, 20, 0, 'enfers'),
(12675, -19, 19, 0, 'enfers'),
(12676, 43, 19, 0, 'enfers'),
(12677, 44, 19, 0, 'enfers'),
(12678, 43, 18, 0, 'enfers'),
(12679, 44, 18, 0, 'enfers'),
(12680, 0, 0, 0, 'enfers'),
(12681, 1, 0, 0, 'enfers'),
(12682, -1, -1, 0, 'enfers'),
(12683, -2, -2, 0, 'enfers'),
(12684, -2, -3, 0, 'enfers'),
(12685, -3, -4, 0, 'enfers'),
(12686, 2, 0, 0, 'enfers'),
(12687, 3, -1, 0, 'enfers'),
(12688, 3, -2, 0, 'enfers'),
(12689, 4, -3, 0, 'enfers'),
(12690, 2, 1, 0, 'enfers'),
(12691, 2, 2, 0, 'enfers'),
(12692, 3, 3, 0, 'enfers'),
(12693, -1, 0, 0, 'enfers'),
(12694, -2, 1, 0, 'enfers'),
(12695, -3, 2, 0, 'enfers'),
(12696, -3, 3, 0, 'enfers'),
(12697, 0, 1, 0, 'enfers'),
(12698, 1, 1, 0, 'enfers'),
(12699, -1, 1, 0, 'enfers'),
(12700, -4, 4, 0, 'enfers'),
(12701, 4, 4, 0, 'enfers'),
(12702, 5, -4, 0, 'enfers'),
(12703, -4, -4, 0, 'enfers'),
(12704, -12, -11, 0, 'enfers'),
(12705, -12, -12, 0, 'enfers'),
(12706, -12, 12, 0, 'enfers'),
(12707, -12, 11, 0, 'enfers'),
(12708, 12, 12, 0, 'enfers'),
(12709, 12, 11, 0, 'enfers'),
(12710, 12, -12, 0, 'enfers'),
(12711, 12, -13, 0, 'enfers'),
(12712, -6, -6, 0, 'enfers'),
(12713, -7, -7, 0, 'enfers'),
(12714, -7, 7, 0, 'enfers'),
(12715, -6, 6, 0, 'enfers'),
(12716, 7, 7, 0, 'enfers'),
(12717, 6, 6, 0, 'enfers'),
(12718, 7, -7, 0, 'enfers'),
(12719, 6, -6, 0, 'enfers'),
(12720, 8, -3, 0, 'enfers'),
(12721, 7, -3, 0, 'enfers'),
(12722, 6, -2, 0, 'enfers'),
(12723, -7, 3, 0, 'enfers'),
(12724, -8, 3, 0, 'enfers'),
(12725, -6, 2, 0, 'enfers'),
(12726, 3, 8, 0, 'enfers'),
(12727, 3, 7, 0, 'enfers'),
(12728, 2, 6, 0, 'enfers'),
(12729, -3, -8, 0, 'enfers'),
(12730, -3, -7, 0, 'enfers'),
(12731, -2, -6, 0, 'enfers'),
(12732, 5, 2, 0, 'enfers'),
(12733, 6, 2, 0, 'enfers'),
(12734, 7, 1, 0, 'enfers'),
(12735, 1, -7, 0, 'enfers'),
(12736, 2, -6, 0, 'enfers'),
(12737, 2, -5, 0, 'enfers'),
(12738, -7, -1, 0, 'enfers'),
(12739, -6, -2, 0, 'enfers'),
(12740, -5, -2, 0, 'enfers'),
(12741, -1, 7, 0, 'enfers'),
(12742, -2, 6, 0, 'enfers'),
(12743, -2, 5, 0, 'enfers'),
(12744, -13, -11, 0, 'enfers'),
(12745, -11, -11, 0, 'enfers'),
(12746, -11, -12, 0, 'enfers'),
(12747, -11, -13, 0, 'enfers'),
(12748, -10, 10, 0, 'enfers'),
(12749, -11, 11, 0, 'enfers'),
(12750, -11, 12, 0, 'enfers'),
(12751, -13, 12, 0, 'enfers'),
(12752, -13, 10, 0, 'enfers'),
(12753, 10, 10, 0, 'enfers'),
(12754, 11, 11, 0, 'enfers'),
(12755, 13, 11, 0, 'enfers'),
(12756, 11, 12, 0, 'enfers'),
(12757, 10, -10, 0, 'enfers'),
(12758, 11, -11, 0, 'enfers'),
(12759, 11, -12, 0, 'enfers'),
(12760, 13, -13, 0, 'enfers'),
(12761, 14, -14, 0, 'enfers'),
(12762, -22, 23, 0, 'enfers'),
(12763, -21, 22, 0, 'enfers'),
(12764, -23, 21, 0, 'enfers'),
(12765, -23, -22, 0, 'enfers'),
(12766, -24, -23, 0, 'enfers'),
(12767, -25, -24, 0, 'enfers'),
(12768, -26, -24, 0, 'enfers'),
(12769, -27, -25, 0, 'enfers'),
(12770, -28, -26, 0, 'enfers'),
(12771, -22, -22, 0, 'enfers'),
(12772, -29, -26, 0, 'enfers'),
(12773, -30, -27, 0, 'enfers'),
(12774, -21, -22, 0, 'enfers'),
(12775, -20, -23, 0, 'enfers'),
(12776, -19, -23, 0, 'enfers'),
(12777, -18, -22, 0, 'enfers'),
(12778, -17, -22, 0, 'enfers'),
(12779, -16, -21, 0, 'enfers'),
(12780, -15, -21, 0, 'enfers'),
(12781, -14, -21, 0, 'enfers'),
(12782, -13, -22, 0, 'enfers'),
(12783, -12, -22, 0, 'enfers'),
(12784, -31, -28, 0, 'enfers'),
(12785, -32, -28, 0, 'enfers'),
(12786, -33, -29, 0, 'enfers'),
(12787, -34, -30, 0, 'enfers'),
(12788, -35, -30, 0, 'enfers'),
(12789, -36, -31, 0, 'enfers'),
(12790, -37, -32, 0, 'enfers'),
(12791, -38, -32, 0, 'enfers'),
(12792, -39, -33, 0, 'enfers'),
(12793, -40, -34, 0, 'enfers'),
(12794, -41, -34, 0, 'enfers'),
(12795, -42, -35, 0, 'enfers'),
(12796, -43, -36, 0, 'enfers'),
(12797, -44, -36, 0, 'enfers'),
(12798, -45, -37, 0, 'enfers'),
(12799, -44, -37, 0, 'enfers'),
(12800, -43, -37, 0, 'enfers'),
(12801, -42, -37, 0, 'enfers'),
(12802, -46, -38, 0, 'enfers'),
(12803, -47, -38, 0, 'enfers'),
(12804, -45, -38, 0, 'enfers'),
(12805, -44, -38, 0, 'enfers'),
(12806, -43, -38, 0, 'enfers'),
(12807, -48, -39, 0, 'enfers'),
(12808, -47, -39, 0, 'enfers'),
(12809, -46, -39, 0, 'enfers'),
(12810, -45, -39, 0, 'enfers'),
(12811, -44, -39, 0, 'enfers'),
(12812, -43, -39, 0, 'enfers'),
(12813, -50, -40, 0, 'enfers'),
(12814, -49, -40, 0, 'enfers'),
(12815, -48, -40, 0, 'enfers'),
(12816, -47, -40, 0, 'enfers'),
(12817, -46, -40, 0, 'enfers'),
(12818, -45, -40, 0, 'enfers'),
(12819, -44, -40, 0, 'enfers'),
(12820, -43, -40, 0, 'enfers'),
(12821, -42, -40, 0, 'enfers'),
(12822, -51, -41, 0, 'enfers'),
(12823, -50, -41, 0, 'enfers'),
(12824, -49, -41, 0, 'enfers'),
(12825, -48, -41, 0, 'enfers'),
(12826, -47, -41, 0, 'enfers'),
(12827, -46, -41, 0, 'enfers'),
(12828, -45, -41, 0, 'enfers'),
(12829, -44, -41, 0, 'enfers'),
(12830, -43, -41, 0, 'enfers'),
(12831, -42, -41, 0, 'enfers'),
(12832, -41, -41, 0, 'enfers'),
(12833, -50, -42, 0, 'enfers'),
(12834, -49, -42, 0, 'enfers'),
(12835, -48, -42, 0, 'enfers'),
(12836, -47, -42, 0, 'enfers'),
(12837, -46, -42, 0, 'enfers'),
(12838, -45, -42, 0, 'enfers'),
(12839, -44, -42, 0, 'enfers'),
(12840, -43, -42, 0, 'enfers'),
(12841, -48, -43, 0, 'enfers'),
(12842, -47, -43, 0, 'enfers'),
(12843, -46, -43, 0, 'enfers'),
(12844, -45, -43, 0, 'enfers'),
(12845, -44, -43, 0, 'enfers'),
(12846, -47, -44, 0, 'enfers'),
(12847, -46, -44, 0, 'enfers'),
(12848, -45, -44, 0, 'enfers'),
(12849, -49, -43, 0, 'enfers'),
(12850, -11, -21, 0, 'enfers'),
(12851, -10, -21, 0, 'enfers'),
(12852, -9, -21, 0, 'enfers'),
(12853, -8, -20, 0, 'enfers'),
(12854, -7, -20, 0, 'enfers'),
(12855, -6, -21, 0, 'enfers'),
(12856, -5, -21, 0, 'enfers'),
(12857, -4, -21, 0, 'enfers'),
(12858, -3, -22, 0, 'enfers'),
(12859, -2, -22, 0, 'enfers'),
(12860, -1, -22, 0, 'enfers'),
(12861, 0, -21, 0, 'enfers'),
(12862, 1, -21, 0, 'enfers'),
(12863, 2, -22, 0, 'enfers'),
(12864, 3, -22, 0, 'enfers'),
(12865, 4, -22, 0, 'enfers'),
(12866, 5, -21, 0, 'enfers'),
(12867, 6, -21, 0, 'enfers'),
(12868, 7, -22, 0, 'enfers'),
(12869, 8, -22, 0, 'enfers'),
(12870, 9, -22, 0, 'enfers'),
(12871, 10, -21, 0, 'enfers'),
(12872, 11, -21, 0, 'enfers'),
(12873, 12, -22, 0, 'enfers'),
(12874, 13, -22, 0, 'enfers'),
(12875, 14, -22, 0, 'enfers'),
(12876, 15, -21, 0, 'enfers'),
(12877, 16, -21, 0, 'enfers'),
(12878, 17, -20, 0, 'enfers'),
(12879, 18, -20, 0, 'enfers'),
(12880, 19, -20, 0, 'enfers'),
(12881, 20, -19, 0, 'enfers'),
(12882, 21, -19, 0, 'enfers'),
(12883, 22, -18, 0, 'enfers'),
(12884, 23, -18, 0, 'enfers'),
(12885, 24, -18, 0, 'enfers'),
(12886, 25, -17, 0, 'enfers'),
(12887, 26, -17, 0, 'enfers'),
(12888, 27, -16, 0, 'enfers'),
(12889, 28, -15, 0, 'enfers'),
(12890, 29, -14, 0, 'enfers'),
(12891, 29, -13, 0, 'enfers'),
(12892, 30, -12, 0, 'enfers'),
(12893, 30, -11, 0, 'enfers'),
(12894, 30, -10, 0, 'enfers'),
(12895, 31, -9, 0, 'enfers'),
(12896, 31, -8, 0, 'enfers'),
(12897, 32, -7, 0, 'enfers'),
(12898, 32, -6, 0, 'enfers'),
(12899, 32, -5, 0, 'enfers'),
(12900, 33, -4, 0, 'enfers'),
(12901, 33, -3, 0, 'enfers'),
(12902, 34, -2, 0, 'enfers'),
(12903, 34, -1, 0, 'enfers'),
(12904, 34, 0, 0, 'enfers'),
(12905, 35, 1, 0, 'enfers'),
(12906, 35, 2, 0, 'enfers'),
(12907, 36, 3, 0, 'enfers'),
(12908, 36, 4, 0, 'enfers'),
(12909, 36, 5, 0, 'enfers'),
(12910, 37, 6, 0, 'enfers'),
(12911, 37, 7, 0, 'enfers'),
(12912, 38, 8, 0, 'enfers'),
(12913, 38, 9, 0, 'enfers'),
(12914, 38, 10, 0, 'enfers'),
(12915, 39, 11, 0, 'enfers'),
(12916, 39, 12, 0, 'enfers'),
(12917, 40, 13, 0, 'enfers'),
(12918, 40, 14, 0, 'enfers'),
(12919, 40, 15, 0, 'enfers'),
(12920, 41, 16, 0, 'enfers'),
(12921, 41, 17, 0, 'enfers'),
(12922, 42, 18, 0, 'enfers'),
(12923, 42, 19, 0, 'enfers'),
(12924, 43, 20, 0, 'enfers'),
(12925, 44, 20, 0, 'enfers'),
(12926, 43, 17, 0, 'enfers'),
(12927, 44, 17, 0, 'enfers'),
(12928, 45, 19, 0, 'enfers'),
(12929, 45, 18, 0, 'enfers'),
(12930, 42, 17, 0, 'enfers'),
(15318, 0, 0, 0, 'banque_des_lutins'),
(15457, 0, 0, 0, 'arcadia'),
(15458, -1, 2, 0, 'arcadia'),
(15459, 0, 2, 0, 'arcadia'),
(15460, 1, 2, 0, 'arcadia'),
(15461, -2, 1, 0, 'arcadia'),
(15462, -1, 1, 0, 'arcadia'),
(15463, 0, 1, 0, 'arcadia'),
(15464, 1, 1, 0, 'arcadia'),
(15465, 2, 1, 0, 'arcadia'),
(15466, 2, 0, 0, 'arcadia'),
(15467, 2, -1, 0, 'arcadia'),
(15468, 1, -1, 0, 'arcadia'),
(15469, 1, 0, 0, 'arcadia'),
(15470, 0, -1, 0, 'arcadia'),
(15471, -1, -1, 0, 'arcadia'),
(15472, -1, 0, 0, 'arcadia'),
(15473, -2, 0, 0, 'arcadia'),
(15474, -2, -1, 0, 'arcadia'),
(15475, -1, -2, 0, 'arcadia'),
(15476, 0, -2, 0, 'arcadia'),
(15477, 1, -2, 0, 'arcadia'),
(15503, -2, 2, 0, 'arcadia'),
(15504, -1, 3, 0, 'arcadia'),
(15505, 0, 3, 0, 'arcadia'),
(15506, 1, 3, 0, 'arcadia'),
(15507, 2, 2, 0, 'arcadia'),
(15508, 3, 1, 0, 'arcadia'),
(15509, 3, 0, 0, 'arcadia'),
(15510, 3, -1, 0, 'arcadia'),
(15511, 2, -2, 0, 'arcadia'),
(15512, 1, -3, 0, 'arcadia'),
(15513, 0, -3, 0, 'arcadia'),
(15514, -1, -3, 0, 'arcadia'),
(15515, -2, -2, 0, 'arcadia'),
(15516, -3, -1, 0, 'arcadia'),
(15517, -3, 0, 0, 'arcadia'),
(15518, -3, 1, 0, 'arcadia'),
(15519, -3, 2, 0, 'arcadia'),
(15520, -2, 3, 0, 'arcadia'),
(15521, -1, 4, 0, 'arcadia'),
(15522, 0, 4, 0, 'arcadia'),
(15523, 1, 4, 0, 'arcadia'),
(15524, 2, 3, 0, 'arcadia'),
(15525, 3, 2, 0, 'arcadia'),
(15526, 4, 1, 0, 'arcadia'),
(15527, 4, 0, 0, 'arcadia'),
(15528, 4, -1, 0, 'arcadia'),
(15529, 3, -2, 0, 'arcadia'),
(15530, 2, -3, 0, 'arcadia'),
(15531, 1, -4, 0, 'arcadia'),
(15532, 0, -4, 0, 'arcadia'),
(15533, -1, -4, 0, 'arcadia'),
(15534, -2, -3, 0, 'arcadia'),
(15535, -3, -2, 0, 'arcadia'),
(15536, -4, -1, 0, 'arcadia'),
(15537, -4, 0, 0, 'arcadia'),
(15538, -4, 1, 0, 'arcadia'),
(15539, -4, 2, 0, 'arcadia'),
(15540, -3, 3, 0, 'arcadia'),
(15541, -2, 4, 0, 'arcadia'),
(15542, -1, 5, 0, 'arcadia'),
(15543, 0, 5, 0, 'arcadia'),
(15544, 1, 5, 0, 'arcadia'),
(15545, 2, 4, 0, 'arcadia'),
(15546, 3, 3, 0, 'arcadia'),
(15547, 4, 2, 0, 'arcadia'),
(15548, 5, 1, 0, 'arcadia'),
(15549, 5, 0, 0, 'arcadia'),
(15550, 5, -1, 0, 'arcadia'),
(15551, 4, -2, 0, 'arcadia'),
(15552, 3, -3, 0, 'arcadia'),
(15553, 2, -4, 0, 'arcadia'),
(15554, 1, -5, 0, 'arcadia'),
(15555, 0, -5, 0, 'arcadia'),
(15556, -1, -5, 0, 'arcadia'),
(15557, -2, -4, 0, 'arcadia'),
(15558, -3, -3, 0, 'arcadia'),
(15559, -4, -2, 0, 'arcadia'),
(15560, -5, -1, 0, 'arcadia'),
(15561, -5, 0, 0, 'arcadia'),
(15562, -5, 1, 0, 'arcadia'),
(15564, 8, 4, 0, 'arcadia'),
(15565, 7, 4, 0, 'arcadia'),
(15566, 6, 4, 0, 'arcadia'),
(15567, 7, 3, 0, 'arcadia'),
(15568, 8, 3, 0, 'arcadia'),
(15573, 6, 3, 0, 'arcadia'),
(15576, 5, 4, 0, 'arcadia'),
(16595, 4, 9, 0, 'arcadia'),
(16596, 9, -1, 0, 'arcadia'),
(16597, 1, -10, 0, 'arcadia'),
(16598, -9, -6, 0, 'arcadia'),
(16599, -10, 5, 0, 'arcadia'),
(16600, -7, 9, 0, 'arcadia'),
(16601, 9, -7, 0, 'arcadia'),
(16602, -6, -9, 0, 'arcadia'),
(16603, 8, 6, 0, 'arcadia'),
(16604, -2, 9, 0, 'arcadia'),
(16605, -10, 0, 0, 'arcadia'),
(16606, -6, -8, 0, 'arcadia'),
(16607, -5, -7, 0, 'arcadia'),
(16608, -5, -8, 0, 'arcadia'),
(16609, -3, -4, 0, 'arcadia'),
(16610, 6, -9, 0, 'arcadia'),
(16611, 7, -5, 0, 'arcadia'),
(16612, 7, 2, 0, 'arcadia'),
(16613, 2, 8, 0, 'arcadia'),
(16614, -3, 7, 0, 'arcadia'),
(16615, -5, 4, 0, 'arcadia'),
(16616, -8, 2, 0, 'arcadia'),
(16617, -7, 2, 0, 'arcadia'),
(16618, -6, 3, 0, 'arcadia'),
(16619, -6, 2, 0, 'arcadia'),
(16620, -5, 2, 0, 'arcadia'),
(16621, -5, 3, 0, 'arcadia'),
(16622, -4, 3, 0, 'arcadia'),
(16623, -4, 4, 0, 'arcadia'),
(16624, -3, 4, 0, 'arcadia'),
(16625, -3, 5, 0, 'arcadia'),
(16626, -2, 5, 0, 'arcadia'),
(16627, -1, 6, 0, 'arcadia'),
(16628, 0, 6, 0, 'arcadia'),
(16629, 1, 6, 0, 'arcadia'),
(16630, 2, 5, 0, 'arcadia'),
(16631, 3, 6, 0, 'arcadia'),
(16632, 3, 5, 0, 'arcadia'),
(16633, 3, 4, 0, 'arcadia'),
(16634, 4, 4, 0, 'arcadia'),
(16635, 4, 3, 0, 'arcadia'),
(16636, 5, 2, 0, 'arcadia'),
(16637, 5, 3, 0, 'arcadia'),
(16638, 9, 3, 0, 'arcadia'),
(16639, 8, 5, 0, 'arcadia'),
(16640, 7, 5, 0, 'arcadia'),
(16641, 6, 5, 0, 'arcadia'),
(16642, 5, 5, 0, 'arcadia'),
(16643, 4, 5, 0, 'arcadia'),
(16644, 6, 2, 0, 'arcadia'),
(16645, 6, 1, 0, 'arcadia'),
(16646, 6, 0, 0, 'arcadia'),
(16647, 6, -1, 0, 'arcadia'),
(16648, 5, -2, 0, 'arcadia'),
(16649, 6, -2, 0, 'arcadia'),
(16650, 6, -3, 0, 'arcadia'),
(16651, 5, -3, 0, 'arcadia'),
(16652, 4, -3, 0, 'arcadia'),
(16653, 7, -4, 0, 'arcadia'),
(16654, 7, -3, 0, 'arcadia'),
(16655, 7, -2, 0, 'arcadia'),
(16656, 7, -1, 0, 'arcadia'),
(16657, 10, -1, 0, 'arcadia'),
(16658, 10, 0, 0, 'arcadia'),
(16659, 10, 1, 0, 'arcadia'),
(16660, 9, 2, 0, 'arcadia'),
(16661, 7, -6, 0, 'arcadia'),
(16662, 8, -7, 0, 'arcadia'),
(16663, 8, -8, 0, 'arcadia'),
(16664, 7, -9, 0, 'arcadia'),
(16665, 7, -8, 0, 'arcadia'),
(16666, 7, -7, 0, 'arcadia'),
(16667, 1, -6, 0, 'arcadia'),
(16668, 1, -7, 0, 'arcadia'),
(16669, 1, -8, 0, 'arcadia'),
(16670, 1, -9, 0, 'arcadia'),
(16671, 1, 7, 0, 'arcadia'),
(16672, 1, 8, 0, 'arcadia'),
(16673, 1, 9, 0, 'arcadia'),
(16674, 2, 7, 0, 'arcadia'),
(16675, 2, 6, 0, 'arcadia'),
(16676, 2, 9, 0, 'arcadia'),
(16677, 3, 9, 0, 'arcadia'),
(16678, 3, 8, 0, 'arcadia'),
(16679, 3, 7, 0, 'arcadia'),
(16680, 4, 8, 0, 'arcadia'),
(16681, 4, 7, 0, 'arcadia'),
(16682, 4, 6, 0, 'arcadia'),
(16683, 5, 6, 0, 'arcadia'),
(16684, 6, 6, 0, 'arcadia'),
(16685, 7, 6, 0, 'arcadia'),
(16686, 7, 7, 0, 'arcadia'),
(16687, 6, 7, 0, 'arcadia'),
(16688, 5, 7, 0, 'arcadia'),
(16689, 5, 8, 0, 'arcadia'),
(16690, 6, 8, 0, 'arcadia'),
(16692, 8, 7, 0, 'arcadia'),
(16704, 10, 5, 0, 'arcadia'),
(16705, 10, 4, 0, 'arcadia'),
(16706, 10, 3, 0, 'arcadia'),
(16707, 10, 2, 0, 'arcadia'),
(16708, 9, 6, 0, 'arcadia'),
(16709, 9, 5, 0, 'arcadia'),
(16710, 9, 4, 0, 'arcadia'),
(16711, 10, -2, 0, 'arcadia'),
(16712, 10, -3, 0, 'arcadia'),
(16713, 10, -4, 0, 'arcadia'),
(16714, 10, -5, 0, 'arcadia'),
(16715, 9, -6, 0, 'arcadia'),
(16716, 5, -9, 0, 'arcadia'),
(16717, 4, -9, 0, 'arcadia'),
(16718, 6, -10, 0, 'arcadia'),
(16719, 5, -10, 0, 'arcadia'),
(16720, 4, -10, 0, 'arcadia'),
(16721, 2, -10, 0, 'arcadia'),
(16722, 3, -10, 0, 'arcadia'),
(16723, 3, -4, 0, 'arcadia'),
(16724, 4, -4, 0, 'arcadia'),
(16725, 5, -4, 0, 'arcadia'),
(16726, 6, -4, 0, 'arcadia'),
(16727, 6, -5, 0, 'arcadia'),
(16728, 6, -6, 0, 'arcadia'),
(16729, 2, -5, 0, 'arcadia'),
(16730, 3, -5, 0, 'arcadia'),
(16731, 4, -5, 0, 'arcadia'),
(16732, 5, -5, 0, 'arcadia'),
(16733, 4, -6, 0, 'arcadia'),
(16734, 5, -6, 0, 'arcadia'),
(16735, 6, -7, 0, 'arcadia'),
(16736, 6, -8, 0, 'arcadia'),
(16737, 5, -8, 0, 'arcadia'),
(16738, 5, -7, 0, 'arcadia'),
(16739, 3, -6, 0, 'arcadia'),
(16740, 2, -6, 0, 'arcadia'),
(16741, 2, -7, 0, 'arcadia'),
(16742, 3, -7, 0, 'arcadia'),
(16743, 4, -7, 0, 'arcadia'),
(16744, 4, -8, 0, 'arcadia'),
(16745, 3, -8, 0, 'arcadia'),
(16746, 2, -9, 0, 'arcadia'),
(16747, 3, -9, 0, 'arcadia'),
(16748, 2, -8, 0, 'arcadia'),
(16749, 8, -6, 0, 'arcadia'),
(16750, 8, -5, 0, 'arcadia'),
(16751, 9, -5, 0, 'arcadia'),
(16752, 9, -4, 0, 'arcadia'),
(16753, 8, -4, 0, 'arcadia'),
(16754, 8, -3, 0, 'arcadia'),
(16755, 9, -3, 0, 'arcadia'),
(16756, 9, -2, 0, 'arcadia'),
(16757, 8, -2, 0, 'arcadia'),
(16758, 8, -1, 0, 'arcadia'),
(16759, 8, 0, 0, 'arcadia'),
(16760, 8, 1, 0, 'arcadia'),
(16761, 8, 2, 0, 'arcadia'),
(16762, 7, 1, 0, 'arcadia'),
(16763, 7, 0, 0, 'arcadia'),
(16764, 9, 0, 0, 'arcadia'),
(16765, 9, 1, 0, 'arcadia'),
(16767, -7, -7, 0, 'arcadia'),
(16768, -6, -3, 0, 'arcadia'),
(16769, -4, -9, 0, 'arcadia'),
(16770, -5, -9, 0, 'arcadia'),
(16771, 0, 8, 0, 'arcadia'),
(16772, -1, 8, 0, 'arcadia'),
(16777, -2, 7, 0, 'arcadia'),
(16779, 0, 7, 0, 'arcadia'),
(16780, -1, 7, 0, 'arcadia'),
(16781, -2, 6, 0, 'arcadia'),
(16782, -3, 6, 0, 'arcadia'),
(16783, -4, 6, 0, 'arcadia'),
(16784, -5, 6, 0, 'arcadia'),
(16785, -4, 5, 0, 'arcadia'),
(16786, -5, 5, 0, 'arcadia'),
(16787, -6, 5, 0, 'arcadia'),
(16788, -7, 5, 0, 'arcadia'),
(16789, -2, -8, 0, 'arcadia'),
(16790, -3, -9, 0, 'arcadia'),
(16791, -4, -10, 0, 'arcadia'),
(16792, -1, -7, 0, 'arcadia'),
(16793, -1, -6, 0, 'arcadia'),
(16794, -10, -5, 0, 'arcadia'),
(16795, -8, -7, 0, 'arcadia'),
(16796, -7, -8, 0, 'arcadia'),
(16797, -5, -10, 0, 'arcadia'),
(16798, 0, -6, 0, 'arcadia'),
(16799, 0, -7, 0, 'arcadia'),
(16800, 0, -8, 0, 'arcadia'),
(16801, -1, -8, 0, 'arcadia'),
(16802, -3, -10, 0, 'arcadia'),
(16803, -2, -10, 0, 'arcadia'),
(16804, -1, -9, 0, 'arcadia'),
(16805, -2, -9, 0, 'arcadia'),
(16806, -1, -10, 0, 'arcadia'),
(16807, 0, -9, 0, 'arcadia'),
(16808, 0, -10, 0, 'arcadia'),
(16809, -10, -1, 0, 'arcadia'),
(16810, -6, 1, 0, 'arcadia'),
(16811, -10, -2, 0, 'arcadia'),
(16812, -10, -3, 0, 'arcadia'),
(16813, -10, -4, 0, 'arcadia'),
(16814, -10, 1, 0, 'arcadia'),
(16815, -10, 2, 0, 'arcadia'),
(16816, -10, 4, 0, 'arcadia'),
(16817, -10, 3, 0, 'arcadia'),
(16818, -6, -2, 0, 'arcadia'),
(16819, -5, -2, 0, 'arcadia'),
(16820, -4, -3, 0, 'arcadia'),
(16821, -5, -3, 0, 'arcadia'),
(16822, -4, -4, 0, 'arcadia'),
(16823, -5, -4, 0, 'arcadia'),
(16824, -6, -4, 0, 'arcadia'),
(16825, -6, -1, 0, 'arcadia'),
(16826, -6, 0, 0, 'arcadia'),
(16827, -7, 1, 0, 'arcadia'),
(16828, -7, 0, 0, 'arcadia'),
(16829, -7, -1, 0, 'arcadia'),
(16830, -7, -2, 0, 'arcadia'),
(16831, -7, -5, 0, 'arcadia'),
(16832, -7, -4, 0, 'arcadia'),
(16833, -7, -3, 0, 'arcadia'),
(16834, -3, -7, 0, 'arcadia'),
(16835, -5, -6, 0, 'arcadia'),
(16836, -3, -6, 0, 'arcadia'),
(16837, -4, -5, 0, 'arcadia'),
(16838, -4, -6, 0, 'arcadia'),
(16839, -4, -7, 0, 'arcadia'),
(16840, -4, -8, 0, 'arcadia'),
(16841, -3, -8, 0, 'arcadia'),
(16842, -2, -7, 0, 'arcadia'),
(16843, -2, -6, 0, 'arcadia'),
(16844, -2, -5, 0, 'arcadia'),
(16845, -3, -5, 0, 'arcadia'),
(16846, -6, -7, 0, 'arcadia'),
(16847, -6, -5, 0, 'arcadia'),
(16848, -5, -5, 0, 'arcadia'),
(16849, -6, -6, 0, 'arcadia'),
(16850, -8, -6, 0, 'arcadia'),
(16851, -7, -6, 0, 'arcadia'),
(16852, -8, -5, 0, 'arcadia'),
(16853, -9, -5, 0, 'arcadia'),
(16854, -8, -4, 0, 'arcadia'),
(16855, -9, -4, 0, 'arcadia'),
(16856, -9, -3, 0, 'arcadia'),
(16857, -8, -3, 0, 'arcadia'),
(16858, -8, -2, 0, 'arcadia'),
(16859, -9, -2, 0, 'arcadia'),
(16860, -8, -1, 0, 'arcadia'),
(16861, -9, -1, 0, 'arcadia'),
(16862, -9, 0, 0, 'arcadia'),
(16863, -8, 0, 0, 'arcadia'),
(16864, -9, 1, 0, 'arcadia'),
(16865, -8, 1, 0, 'arcadia'),
(16866, -9, 2, 0, 'arcadia'),
(16867, -9, 3, 0, 'arcadia'),
(16868, -9, 4, 0, 'arcadia'),
(16869, -7, 4, 0, 'arcadia'),
(16870, -6, 4, 0, 'arcadia'),
(16871, -7, 3, 0, 'arcadia'),
(16872, -8, 3, 0, 'arcadia'),
(16873, -8, 4, 0, 'arcadia'),
(16874, -6, 6, 0, 'arcadia'),
(16875, -4, 7, 0, 'arcadia'),
(16876, 0, 9, 0, 'arcadia'),
(16877, -2, 8, 0, 'arcadia'),
(16878, -4, 8, 0, 'arcadia'),
(16879, -3, 8, 0, 'arcadia'),
(16880, -5, 7, 0, 'arcadia'),
(16881, -6, 7, 0, 'arcadia'),
(16882, -7, 6, 0, 'arcadia'),
(16883, -8, 5, 0, 'arcadia'),
(16884, -9, 5, 0, 'arcadia'),
(16885, -1, 9, 0, 'arcadia'),
(16998, -2, 2, 0, 'banque_des_lutins'),
(16999, -1, 2, 0, 'banque_des_lutins'),
(17000, 0, 2, 0, 'banque_des_lutins'),
(17001, 1, 2, 0, 'banque_des_lutins'),
(17002, 2, 2, 0, 'banque_des_lutins'),
(17003, 2, 1, 0, 'banque_des_lutins'),
(17004, 1, 1, 0, 'banque_des_lutins'),
(17005, 0, 1, 0, 'banque_des_lutins'),
(17006, -1, 1, 0, 'banque_des_lutins'),
(17007, -2, 1, 0, 'banque_des_lutins'),
(17008, -2, 0, 0, 'banque_des_lutins'),
(17009, -1, 0, 0, 'banque_des_lutins'),
(17010, 1, 0, 0, 'banque_des_lutins'),
(17011, 2, 0, 0, 'banque_des_lutins'),
(17012, 2, -1, 0, 'banque_des_lutins'),
(17013, 1, -1, 0, 'banque_des_lutins'),
(17014, 0, -1, 0, 'banque_des_lutins'),
(17015, -1, -1, 0, 'banque_des_lutins'),
(17016, -2, -1, 0, 'banque_des_lutins'),
(17017, -1, -2, 0, 'banque_des_lutins'),
(17018, 0, -2, 0, 'banque_des_lutins'),
(17019, 1, -2, 0, 'banque_des_lutins'),
(17020, 2, -2, 0, 'banque_des_lutins'),
(17021, -2, -2, 0, 'banque_des_lutins'),
(17022, -3, 3, 0, 'banque_des_lutins'),
(17023, -2, 3, 0, 'banque_des_lutins'),
(17024, -1, 3, 0, 'banque_des_lutins'),
(17025, 0, 3, 0, 'banque_des_lutins'),
(17026, 1, 3, 0, 'banque_des_lutins'),
(17027, 2, 3, 0, 'banque_des_lutins'),
(17028, 3, 3, 0, 'banque_des_lutins'),
(17029, 3, 2, 0, 'banque_des_lutins'),
(17030, 3, 1, 0, 'banque_des_lutins'),
(17031, 3, 0, 0, 'banque_des_lutins'),
(17032, 3, -1, 0, 'banque_des_lutins'),
(17033, 3, -2, 0, 'banque_des_lutins'),
(17034, 3, -3, 0, 'banque_des_lutins'),
(17035, 2, -3, 0, 'banque_des_lutins'),
(17036, 1, -3, 0, 'banque_des_lutins'),
(17037, 0, -3, 0, 'banque_des_lutins'),
(17038, -1, -3, 0, 'banque_des_lutins'),
(17039, -2, -3, 0, 'banque_des_lutins'),
(17040, -3, -3, 0, 'banque_des_lutins'),
(17041, -3, -2, 0, 'banque_des_lutins'),
(17042, -3, -1, 0, 'banque_des_lutins'),
(17043, -3, 0, 0, 'banque_des_lutins'),
(17044, -3, 1, 0, 'banque_des_lutins'),
(17045, -3, 2, 0, 'banque_des_lutins'),
(17046, -1, -4, 0, 'banque_des_lutins'),
(17047, 1, -4, 0, 'banque_des_lutins'),
(17048, 4, 3, 0, 'banque_des_lutins'),
(17049, 5, 3, 0, 'banque_des_lutins'),
(17050, 6, 3, 0, 'banque_des_lutins'),
(17051, 7, 3, 0, 'banque_des_lutins'),
(17052, 8, 3, 0, 'banque_des_lutins'),
(17053, 9, 3, 0, 'banque_des_lutins'),
(17054, 9, 2, 0, 'banque_des_lutins'),
(17055, 9, 1, 0, 'banque_des_lutins'),
(17056, 9, 0, 0, 'banque_des_lutins'),
(17057, 9, -1, 0, 'banque_des_lutins'),
(17058, 9, -2, 0, 'banque_des_lutins'),
(17059, 9, -3, 0, 'banque_des_lutins'),
(17060, 9, -4, 0, 'banque_des_lutins'),
(17061, 9, -5, 0, 'banque_des_lutins'),
(17062, 9, -6, 0, 'banque_des_lutins'),
(17063, 8, -6, 0, 'banque_des_lutins'),
(17064, 7, -6, 0, 'banque_des_lutins'),
(17065, 6, -6, 0, 'banque_des_lutins'),
(17066, 5, -6, 0, 'banque_des_lutins'),
(17067, -4, 3, 0, 'banque_des_lutins'),
(17068, -5, 3, 0, 'banque_des_lutins'),
(17069, -6, 3, 0, 'banque_des_lutins'),
(17070, -7, 3, 0, 'banque_des_lutins'),
(17071, -8, 3, 0, 'banque_des_lutins'),
(17072, -9, 3, 0, 'banque_des_lutins'),
(17073, -9, 2, 0, 'banque_des_lutins'),
(17074, -9, 1, 0, 'banque_des_lutins'),
(17075, -9, 0, 0, 'banque_des_lutins'),
(17076, -9, -1, 0, 'banque_des_lutins'),
(17077, -9, -2, 0, 'banque_des_lutins'),
(17078, -9, -3, 0, 'banque_des_lutins'),
(17079, -9, -4, 0, 'banque_des_lutins'),
(17080, -9, -5, 0, 'banque_des_lutins'),
(17081, -9, -6, 0, 'banque_des_lutins'),
(17082, -8, -6, 0, 'banque_des_lutins'),
(17083, -7, -6, 0, 'banque_des_lutins'),
(17084, -6, -6, 0, 'banque_des_lutins'),
(17085, -5, -6, 0, 'banque_des_lutins'),
(17086, -5, -7, 0, 'banque_des_lutins'),
(17087, -5, -8, 0, 'banque_des_lutins'),
(17088, 5, -7, 0, 'banque_des_lutins'),
(17089, 5, -8, 0, 'banque_des_lutins'),
(17090, 4, -8, 0, 'banque_des_lutins'),
(17091, -4, -8, 0, 'banque_des_lutins'),
(17092, -3, -8, 0, 'banque_des_lutins'),
(17093, -2, -8, 0, 'banque_des_lutins'),
(17094, 3, -8, 0, 'banque_des_lutins'),
(17095, 2, -8, 0, 'banque_des_lutins'),
(17096, 2, -9, 0, 'banque_des_lutins'),
(17097, -2, -9, 0, 'banque_des_lutins'),
(17098, 0, -4, 0, 'banque_des_lutins'),
(17099, 0, -5, 0, 'banque_des_lutins'),
(17100, 1, -5, 0, 'banque_des_lutins'),
(17101, 2, -5, 0, 'banque_des_lutins'),
(17102, 2, -4, 0, 'banque_des_lutins'),
(17103, 3, -4, 0, 'banque_des_lutins'),
(17104, 3, -5, 0, 'banque_des_lutins'),
(17105, 3, -6, 0, 'banque_des_lutins'),
(17106, 3, -7, 0, 'banque_des_lutins'),
(17107, 2, -7, 0, 'banque_des_lutins'),
(17108, 2, -6, 0, 'banque_des_lutins'),
(17109, 1, -6, 0, 'banque_des_lutins'),
(17110, 0, -6, 0, 'banque_des_lutins'),
(17111, -1, -6, 0, 'banque_des_lutins'),
(17112, -1, -5, 0, 'banque_des_lutins'),
(17113, -2, -5, 0, 'banque_des_lutins'),
(17114, -2, -4, 0, 'banque_des_lutins'),
(17115, -3, -4, 0, 'banque_des_lutins'),
(17116, -3, -5, 0, 'banque_des_lutins'),
(17117, -3, -6, 0, 'banque_des_lutins'),
(17118, -3, -7, 0, 'banque_des_lutins'),
(17119, -2, -7, 0, 'banque_des_lutins'),
(17120, -2, -6, 0, 'banque_des_lutins'),
(17121, -1, -7, 0, 'banque_des_lutins'),
(17122, 0, -7, 0, 'banque_des_lutins'),
(17123, 1, -7, 0, 'banque_des_lutins'),
(17124, 1, -8, 0, 'banque_des_lutins'),
(17125, 0, -8, 0, 'banque_des_lutins'),
(17126, -1, -8, 0, 'banque_des_lutins'),
(17127, -1, -9, 0, 'banque_des_lutins'),
(17128, 0, -9, 0, 'banque_des_lutins'),
(17129, 1, -9, 0, 'banque_des_lutins'),
(17130, 1, -10, 0, 'banque_des_lutins'),
(17131, 0, -10, 0, 'banque_des_lutins'),
(17132, -1, -10, 0, 'banque_des_lutins'),
(17133, 4, -7, 0, 'banque_des_lutins'),
(17134, 4, -6, 0, 'banque_des_lutins'),
(17135, 4, -5, 0, 'banque_des_lutins'),
(17136, 4, -4, 0, 'banque_des_lutins'),
(17137, 4, -3, 0, 'banque_des_lutins'),
(17138, 5, -3, 0, 'banque_des_lutins'),
(17139, 5, -4, 0, 'banque_des_lutins'),
(17140, -4, -3, 0, 'banque_des_lutins'),
(17141, -5, -3, 0, 'banque_des_lutins'),
(17142, -5, -4, 0, 'banque_des_lutins'),
(17143, 5, -5, 0, 'banque_des_lutins'),
(17144, -4, -7, 0, 'banque_des_lutins'),
(17145, -4, -6, 0, 'banque_des_lutins'),
(17146, -4, -5, 0, 'banque_des_lutins'),
(17147, -4, -4, 0, 'banque_des_lutins'),
(17148, -5, -5, 0, 'banque_des_lutins'),
(17149, -8, -5, 0, 'banque_des_lutins'),
(17150, -7, -5, 0, 'banque_des_lutins'),
(17151, -6, -5, 0, 'banque_des_lutins'),
(17152, -6, -4, 0, 'banque_des_lutins'),
(17153, -7, -4, 0, 'banque_des_lutins'),
(17154, -8, -4, 0, 'banque_des_lutins'),
(17155, -8, -3, 0, 'banque_des_lutins'),
(17156, -7, -3, 0, 'banque_des_lutins'),
(17157, -6, -3, 0, 'banque_des_lutins'),
(17158, -4, -2, 0, 'banque_des_lutins'),
(17159, -5, -2, 0, 'banque_des_lutins'),
(17160, -6, -2, 0, 'banque_des_lutins'),
(17161, -7, -2, 0, 'banque_des_lutins'),
(17162, -8, -2, 0, 'banque_des_lutins'),
(17163, -8, -1, 0, 'banque_des_lutins'),
(17164, -7, -1, 0, 'banque_des_lutins'),
(17165, -6, -1, 0, 'banque_des_lutins'),
(17166, -5, -1, 0, 'banque_des_lutins'),
(17167, -4, -1, 0, 'banque_des_lutins'),
(17168, -4, 0, 0, 'banque_des_lutins'),
(17169, -5, 0, 0, 'banque_des_lutins'),
(17170, -6, 0, 0, 'banque_des_lutins'),
(17171, -7, 0, 0, 'banque_des_lutins'),
(17172, -8, 0, 0, 'banque_des_lutins'),
(17173, -8, 1, 0, 'banque_des_lutins'),
(17174, -8, 2, 0, 'banque_des_lutins'),
(17175, -7, 2, 0, 'banque_des_lutins'),
(17176, -7, 1, 0, 'banque_des_lutins'),
(17177, -6, 1, 0, 'banque_des_lutins'),
(17178, -6, 2, 0, 'banque_des_lutins'),
(17179, -5, 2, 0, 'banque_des_lutins'),
(17180, -5, 1, 0, 'banque_des_lutins'),
(17181, -4, 1, 0, 'banque_des_lutins'),
(17182, -4, 2, 0, 'banque_des_lutins'),
(17183, 6, -5, 0, 'banque_des_lutins'),
(17184, 6, -4, 0, 'banque_des_lutins'),
(17185, 6, -3, 0, 'banque_des_lutins'),
(17186, 6, -2, 0, 'banque_des_lutins'),
(17187, 5, -2, 0, 'banque_des_lutins'),
(17188, 4, -2, 0, 'banque_des_lutins'),
(17189, 4, -1, 0, 'banque_des_lutins'),
(17190, 5, -1, 0, 'banque_des_lutins'),
(17191, 6, -1, 0, 'banque_des_lutins'),
(17192, 6, 0, 0, 'banque_des_lutins'),
(17193, 5, 0, 0, 'banque_des_lutins'),
(17194, 4, 0, 0, 'banque_des_lutins'),
(17195, 4, 1, 0, 'banque_des_lutins'),
(17196, 5, 1, 0, 'banque_des_lutins'),
(17197, 6, 1, 0, 'banque_des_lutins'),
(17198, 6, 2, 0, 'banque_des_lutins'),
(17199, 5, 2, 0, 'banque_des_lutins'),
(17200, 4, 2, 0, 'banque_des_lutins'),
(17201, 7, 2, 0, 'banque_des_lutins'),
(17202, 8, 2, 0, 'banque_des_lutins'),
(17203, 8, 1, 0, 'banque_des_lutins'),
(17204, 7, 1, 0, 'banque_des_lutins'),
(17205, 7, 0, 0, 'banque_des_lutins'),
(17206, 8, 0, 0, 'banque_des_lutins'),
(17207, 8, -1, 0, 'banque_des_lutins'),
(17208, 7, -1, 0, 'banque_des_lutins'),
(17209, 7, -2, 0, 'banque_des_lutins'),
(17210, 8, -2, 0, 'banque_des_lutins'),
(17211, 8, -3, 0, 'banque_des_lutins'),
(17212, 7, -3, 0, 'banque_des_lutins'),
(17213, 7, -4, 0, 'banque_des_lutins'),
(17214, 8, -4, 0, 'banque_des_lutins'),
(17215, 8, -5, 0, 'banque_des_lutins'),
(17216, 7, -5, 0, 'banque_des_lutins'),
(17217, -2, -10, 0, 'banque_des_lutins'),
(17218, 2, -10, 0, 'banque_des_lutins'),
(17219, -5, -9, 0, 'banque_des_lutins'),
(17220, 5, -9, 0, 'banque_des_lutins'),
(17221, -9, -7, 0, 'banque_des_lutins'),
(17222, -8, -7, 0, 'banque_des_lutins'),
(17223, -7, -7, 0, 'banque_des_lutins'),
(17224, -6, -7, 0, 'banque_des_lutins'),
(17225, -6, -8, 0, 'banque_des_lutins'),
(17226, -7, -8, 0, 'banque_des_lutins'),
(17227, -8, -8, 0, 'banque_des_lutins'),
(17228, -9, -8, 0, 'banque_des_lutins'),
(17229, -6, -9, 0, 'banque_des_lutins'),
(17230, -4, -9, 0, 'banque_des_lutins'),
(17231, -3, -9, 0, 'banque_des_lutins'),
(17232, 3, -9, 0, 'banque_des_lutins'),
(17233, 4, -9, 0, 'banque_des_lutins'),
(17234, 6, -9, 0, 'banque_des_lutins'),
(17235, 6, -8, 0, 'banque_des_lutins'),
(17236, 6, -7, 0, 'banque_des_lutins'),
(17237, 7, -7, 0, 'banque_des_lutins'),
(17238, 8, -7, 0, 'banque_des_lutins'),
(17239, 9, -7, 0, 'banque_des_lutins'),
(17240, 9, -8, 0, 'banque_des_lutins'),
(17241, 8, -8, 0, 'banque_des_lutins'),
(17242, 7, -8, 0, 'banque_des_lutins'),
(17243, -1, -11, 0, 'banque_des_lutins'),
(17244, 0, -11, 0, 'banque_des_lutins'),
(17245, 1, -11, 0, 'banque_des_lutins'),
(17246, 2, -11, 0, 'banque_des_lutins'),
(17247, 3, -10, 0, 'banque_des_lutins'),
(17248, 3, -11, 0, 'banque_des_lutins'),
(17249, 4, -10, 0, 'banque_des_lutins'),
(17250, 5, -10, 0, 'banque_des_lutins'),
(17251, 6, -10, 0, 'banque_des_lutins'),
(17252, 7, -10, 0, 'banque_des_lutins'),
(17253, 7, -9, 0, 'banque_des_lutins'),
(17254, 8, -9, 0, 'banque_des_lutins'),
(17255, -2, -11, 0, 'banque_des_lutins'),
(17256, -3, -10, 0, 'banque_des_lutins'),
(17257, -4, -10, 0, 'banque_des_lutins'),
(17258, -5, -10, 0, 'banque_des_lutins'),
(17259, -6, -10, 0, 'banque_des_lutins'),
(17260, -7, -10, 0, 'banque_des_lutins'),
(17261, -7, -9, 0, 'banque_des_lutins'),
(17262, -8, -9, 0, 'banque_des_lutins'),
(17263, -9, -9, 0, 'banque_des_lutins'),
(17264, -9, -10, 0, 'banque_des_lutins'),
(17265, -8, -10, 0, 'banque_des_lutins'),
(17266, -9, -11, 0, 'banque_des_lutins'),
(17267, -8, -11, 0, 'banque_des_lutins'),
(17268, -7, -11, 0, 'banque_des_lutins'),
(17269, -6, -11, 0, 'banque_des_lutins'),
(17270, -5, -11, 0, 'banque_des_lutins'),
(17271, -4, -11, 0, 'banque_des_lutins'),
(17272, -3, -11, 0, 'banque_des_lutins'),
(17273, 4, -11, 0, 'banque_des_lutins'),
(17274, 5, -11, 0, 'banque_des_lutins'),
(17275, 6, -11, 0, 'banque_des_lutins'),
(17276, 7, -11, 0, 'banque_des_lutins'),
(17277, 8, -11, 0, 'banque_des_lutins'),
(17278, 8, -10, 0, 'banque_des_lutins'),
(17279, 9, -10, 0, 'banque_des_lutins'),
(17280, 9, -9, 0, 'banque_des_lutins'),
(17281, 9, -11, 0, 'banque_des_lutins'),
(17282, -2, -12, 0, 'banque_des_lutins'),
(17283, 2, -12, 0, 'banque_des_lutins'),
(17284, 3, -12, 0, 'banque_des_lutins'),
(17285, 4, -12, 0, 'banque_des_lutins'),
(17286, 5, -12, 0, 'banque_des_lutins'),
(17287, 6, -12, 0, 'banque_des_lutins'),
(17288, 7, -12, 0, 'banque_des_lutins'),
(17289, 9, -12, 0, 'banque_des_lutins'),
(17290, 8, -12, 0, 'banque_des_lutins'),
(17291, -3, -12, 0, 'banque_des_lutins'),
(17292, -4, -12, 0, 'banque_des_lutins'),
(17293, -5, -12, 0, 'banque_des_lutins'),
(17294, -6, -12, 0, 'banque_des_lutins'),
(17295, -7, -12, 0, 'banque_des_lutins'),
(17296, -8, -12, 0, 'banque_des_lutins'),
(17297, -9, -12, 0, 'banque_des_lutins'),
(17298, -1, -12, 0, 'banque_des_lutins'),
(17299, 0, -12, 0, 'banque_des_lutins'),
(17300, 1, -12, 0, 'banque_des_lutins'),
(17301, 1, -13, 0, 'banque_des_lutins'),
(17302, 0, -13, 0, 'banque_des_lutins'),
(17303, -1, -13, 0, 'banque_des_lutins'),
(17304, -1, -14, 0, 'banque_des_lutins'),
(17305, 0, -14, 0, 'banque_des_lutins'),
(17306, 1, -14, 0, 'banque_des_lutins'),
(17307, 1, -15, 0, 'banque_des_lutins'),
(17308, 0, -15, 0, 'banque_des_lutins'),
(17309, -1, -15, 0, 'banque_des_lutins'),
(17310, -1, -16, 0, 'banque_des_lutins'),
(17311, 0, -16, 0, 'banque_des_lutins'),
(17312, 1, -16, 0, 'banque_des_lutins'),
(17313, 7, -13, 0, 'banque_des_lutins'),
(17314, 7, -14, 0, 'banque_des_lutins'),
(17315, 6, -14, 0, 'banque_des_lutins'),
(17316, 5, -14, 0, 'banque_des_lutins'),
(17317, -7, -13, 0, 'banque_des_lutins'),
(17318, -7, -14, 0, 'banque_des_lutins'),
(17319, -6, -14, 0, 'banque_des_lutins'),
(17320, -5, -14, 0, 'banque_des_lutins'),
(17321, -4, -14, 0, 'banque_des_lutins'),
(17322, -3, -14, 0, 'banque_des_lutins'),
(17323, -2, -14, 0, 'banque_des_lutins'),
(17324, 2, -14, 0, 'banque_des_lutins'),
(17325, 3, -14, 0, 'banque_des_lutins'),
(17326, 4, -14, 0, 'banque_des_lutins'),
(17327, 2, -13, 0, 'banque_des_lutins'),
(17328, 3, -13, 0, 'banque_des_lutins'),
(17329, 4, -13, 0, 'banque_des_lutins'),
(17330, 5, -13, 0, 'banque_des_lutins'),
(17331, 6, -13, 0, 'banque_des_lutins'),
(17332, 8, -13, 0, 'banque_des_lutins'),
(17333, 9, -13, 0, 'banque_des_lutins'),
(17334, 9, -14, 0, 'banque_des_lutins'),
(17335, 8, -14, 0, 'banque_des_lutins'),
(17336, 9, -15, 0, 'banque_des_lutins'),
(17337, 9, -16, 0, 'banque_des_lutins'),
(17338, 8, -16, 0, 'banque_des_lutins'),
(17339, 8, -15, 0, 'banque_des_lutins'),
(17340, 7, -15, 0, 'banque_des_lutins'),
(17341, 6, -15, 0, 'banque_des_lutins'),
(17342, 5, -15, 0, 'banque_des_lutins'),
(17343, 4, -15, 0, 'banque_des_lutins'),
(17344, 3, -15, 0, 'banque_des_lutins'),
(17345, 2, -15, 0, 'banque_des_lutins'),
(17346, 2, -16, 0, 'banque_des_lutins'),
(17347, 3, -16, 0, 'banque_des_lutins'),
(17348, 4, -16, 0, 'banque_des_lutins'),
(17349, 5, -16, 0, 'banque_des_lutins'),
(17350, 6, -16, 0, 'banque_des_lutins'),
(17351, 7, -16, 0, 'banque_des_lutins'),
(17352, -2, -16, 0, 'banque_des_lutins'),
(17353, -2, -15, 0, 'banque_des_lutins'),
(17354, -3, -15, 0, 'banque_des_lutins'),
(17355, -3, -16, 0, 'banque_des_lutins'),
(17356, -4, -16, 0, 'banque_des_lutins'),
(17357, -4, -15, 0, 'banque_des_lutins'),
(17358, -5, -15, 0, 'banque_des_lutins'),
(17359, -5, -16, 0, 'banque_des_lutins'),
(17360, -6, -16, 0, 'banque_des_lutins'),
(17361, -6, -15, 0, 'banque_des_lutins'),
(17362, -7, -15, 0, 'banque_des_lutins'),
(17363, -7, -16, 0, 'banque_des_lutins'),
(17364, -8, -16, 0, 'banque_des_lutins'),
(17365, -8, -15, 0, 'banque_des_lutins'),
(17366, -8, -14, 0, 'banque_des_lutins'),
(17367, -8, -13, 0, 'banque_des_lutins'),
(17368, -9, -13, 0, 'banque_des_lutins'),
(17369, -9, -14, 0, 'banque_des_lutins'),
(17370, -9, -15, 0, 'banque_des_lutins'),
(17371, -9, -16, 0, 'banque_des_lutins'),
(17372, -6, -13, 0, 'banque_des_lutins'),
(17373, -5, -13, 0, 'banque_des_lutins'),
(17374, -4, -13, 0, 'banque_des_lutins'),
(17375, -3, -13, 0, 'banque_des_lutins'),
(17376, -2, -13, 0, 'banque_des_lutins'),
(17377, 10, -6, 0, 'banque_des_lutins'),
(17378, 10, -5, 0, 'banque_des_lutins'),
(17379, 10, -4, 0, 'banque_des_lutins'),
(17380, 10, -3, 0, 'banque_des_lutins'),
(17381, 10, -2, 0, 'banque_des_lutins'),
(17382, 10, -1, 0, 'banque_des_lutins'),
(17383, 10, 0, 0, 'banque_des_lutins'),
(17384, 10, 1, 0, 'banque_des_lutins'),
(17385, 10, 2, 0, 'banque_des_lutins'),
(17386, 10, 3, 0, 'banque_des_lutins'),
(17387, 10, 4, 0, 'banque_des_lutins'),
(17388, 9, 4, 0, 'banque_des_lutins'),
(17389, 8, 4, 0, 'banque_des_lutins'),
(17390, 7, 4, 0, 'banque_des_lutins'),
(17391, 6, 4, 0, 'banque_des_lutins'),
(17392, 5, 4, 0, 'banque_des_lutins'),
(17393, 4, 4, 0, 'banque_des_lutins'),
(17394, 3, 4, 0, 'banque_des_lutins'),
(17395, 2, 4, 0, 'banque_des_lutins'),
(17396, 1, 4, 0, 'banque_des_lutins'),
(17397, 0, 4, 0, 'banque_des_lutins'),
(17398, -1, 4, 0, 'banque_des_lutins'),
(17399, -2, 4, 0, 'banque_des_lutins'),
(17400, -3, 4, 0, 'banque_des_lutins'),
(17401, -4, 4, 0, 'banque_des_lutins'),
(17402, -5, 4, 0, 'banque_des_lutins'),
(17403, -6, 4, 0, 'banque_des_lutins'),
(17404, -7, 4, 0, 'banque_des_lutins'),
(17405, -8, 4, 0, 'banque_des_lutins'),
(17406, -9, 4, 0, 'banque_des_lutins'),
(17407, -10, 4, 0, 'banque_des_lutins'),
(17408, -10, 3, 0, 'banque_des_lutins'),
(17409, -10, 2, 0, 'banque_des_lutins'),
(17410, -10, 1, 0, 'banque_des_lutins'),
(17411, -10, 0, 0, 'banque_des_lutins'),
(17412, -10, -1, 0, 'banque_des_lutins'),
(17413, -10, -2, 0, 'banque_des_lutins'),
(17414, -10, -3, 0, 'banque_des_lutins'),
(17415, -10, -4, 0, 'banque_des_lutins'),
(17416, -10, -5, 0, 'banque_des_lutins'),
(17417, -10, -6, 0, 'banque_des_lutins'),
(17418, 10, -7, 0, 'banque_des_lutins'),
(17419, 10, -8, 0, 'banque_des_lutins'),
(17420, 10, -9, 0, 'banque_des_lutins'),
(17421, 10, -10, 0, 'banque_des_lutins'),
(17422, 10, -11, 0, 'banque_des_lutins'),
(17423, 10, -12, 0, 'banque_des_lutins'),
(17424, 10, -13, 0, 'banque_des_lutins'),
(17425, 10, -14, 0, 'banque_des_lutins'),
(17426, 10, -15, 0, 'banque_des_lutins'),
(17427, 10, -16, 0, 'banque_des_lutins'),
(17428, -10, -16, 0, 'banque_des_lutins'),
(17429, -10, -15, 0, 'banque_des_lutins'),
(17430, -10, -14, 0, 'banque_des_lutins'),
(17431, -10, -13, 0, 'banque_des_lutins'),
(17432, -10, -12, 0, 'banque_des_lutins'),
(17433, -10, -11, 0, 'banque_des_lutins'),
(17434, -10, -10, 0, 'banque_des_lutins'),
(17435, -10, -9, 0, 'banque_des_lutins'),
(17436, -10, -8, 0, 'banque_des_lutins'),
(17437, -10, -7, 0, 'banque_des_lutins'),
(19770, -10, 10, 0, 'arcadia'),
(19771, -10, 9, 0, 'arcadia'),
(19772, -10, 8, 0, 'arcadia'),
(19773, -10, 7, 0, 'arcadia'),
(19774, -10, 6, 0, 'arcadia'),
(19775, -10, -6, 0, 'arcadia'),
(19777, -10, -7, 0, 'arcadia'),
(19778, -10, -8, 0, 'arcadia'),
(19780, -10, -9, 0, 'arcadia'),
(19781, -10, -10, 0, 'arcadia'),
(19782, -9, -10, 0, 'arcadia'),
(19783, -9, -9, 0, 'arcadia'),
(19784, -9, -8, 0, 'arcadia'),
(19785, -9, -7, 0, 'arcadia'),
(19788, -8, -8, 0, 'arcadia'),
(19789, -8, -9, 0, 'arcadia'),
(19790, -8, -10, 0, 'arcadia'),
(19791, -7, -10, 0, 'arcadia'),
(19792, -6, -10, 0, 'arcadia'),
(19793, -7, -9, 0, 'arcadia'),
(19798, 7, -10, 0, 'arcadia'),
(19799, 8, -9, 0, 'arcadia'),
(19800, 8, -10, 0, 'arcadia'),
(19801, 9, -10, 0, 'arcadia'),
(19802, 9, -9, 0, 'arcadia'),
(19803, 9, -8, 0, 'arcadia'),
(19804, 10, -6, 0, 'arcadia'),
(19805, 10, -7, 0, 'arcadia'),
(19806, 10, -8, 0, 'arcadia'),
(19807, 10, -9, 0, 'arcadia'),
(19808, 10, -10, 0, 'arcadia'),
(19809, 10, 6, 0, 'arcadia'),
(19810, 10, 7, 0, 'arcadia'),
(19811, 9, 7, 0, 'arcadia'),
(19812, 10, 8, 0, 'arcadia'),
(19813, 9, 8, 0, 'arcadia'),
(19814, 8, 8, 0, 'arcadia'),
(19815, 7, 8, 0, 'arcadia'),
(19816, 5, 9, 0, 'arcadia'),
(19817, 6, 9, 0, 'arcadia'),
(19818, 7, 9, 0, 'arcadia'),
(19819, 8, 9, 0, 'arcadia'),
(19820, 9, 9, 0, 'arcadia'),
(19821, 10, 9, 0, 'arcadia'),
(19822, 10, 10, 0, 'arcadia'),
(19823, 9, 10, 0, 'arcadia'),
(19824, 8, 10, 0, 'arcadia'),
(19825, 7, 10, 0, 'arcadia'),
(19826, 6, 10, 0, 'arcadia'),
(19827, 5, 10, 0, 'arcadia'),
(19828, 4, 10, 0, 'arcadia'),
(19829, 3, 10, 0, 'arcadia'),
(19830, 2, 10, 0, 'arcadia'),
(19831, 1, 10, 0, 'arcadia'),
(19832, 0, 10, 0, 'arcadia'),
(19833, -1, 10, 0, 'arcadia'),
(19834, -2, 10, 0, 'arcadia'),
(19835, -3, 9, 0, 'arcadia'),
(19836, -4, 9, 0, 'arcadia'),
(19837, -5, 8, 0, 'arcadia'),
(19838, -6, 8, 0, 'arcadia'),
(19839, -7, 7, 0, 'arcadia'),
(19841, -8, 6, 0, 'arcadia'),
(19842, -9, 6, 0, 'arcadia'),
(19843, -9, 7, 0, 'arcadia'),
(19844, -8, 7, 0, 'arcadia'),
(19845, -9, 8, 0, 'arcadia'),
(19846, -8, 8, 0, 'arcadia'),
(19847, -7, 8, 0, 'arcadia'),
(19848, -8, 9, 0, 'arcadia'),
(19849, -9, 10, 0, 'arcadia'),
(19850, -9, 9, 0, 'arcadia'),
(19851, -8, 10, 0, 'arcadia'),
(19852, -7, 10, 0, 'arcadia'),
(19853, -6, 9, 0, 'arcadia'),
(19854, -6, 10, 0, 'arcadia'),
(19855, -5, 9, 0, 'arcadia'),
(19856, -5, 10, 0, 'arcadia'),
(19857, -4, 10, 0, 'arcadia'),
(19858, -3, 10, 0, 'arcadia'),
(25894, 0, -4, 0, 'enfers'),
(27884, -42, -43, 0, 'enfers'),
(27885, -40, -41, 0, 'enfers'),
(27886, -39, -41, 0, 'enfers'),
(27887, -40, -42, 0, 'enfers'),
(27888, -40, -43, 0, 'enfers'),
(27889, -39, -43, 0, 'enfers'),
(34737, 3, -21, -2, 'gaia2'),
(35004, -5, 0, 0, 'enfers'),
(35010, 0, 0, -1, 'arcadia'),
(35011, 0, 1, -1, 'arcadia'),
(35012, -1, 0, -1, 'arcadia'),
(35013, 0, -1, -1, 'arcadia'),
(35014, 1, 0, -1, 'arcadia'),
(35015, 1, 1, -1, 'arcadia'),
(35016, 0, 2, -1, 'arcadia'),
(35017, -1, 1, -1, 'arcadia'),
(35018, -2, 0, -1, 'arcadia'),
(35019, -1, -1, -1, 'arcadia'),
(35020, 0, -2, -1, 'arcadia'),
(35021, 1, -1, -1, 'arcadia'),
(35022, 2, 0, -1, 'arcadia'),
(35023, 2, 1, -1, 'arcadia'),
(35024, 1, 2, -1, 'arcadia'),
(35025, 0, 3, -1, 'arcadia'),
(35026, -1, 2, -1, 'arcadia'),
(35027, -2, 1, -1, 'arcadia'),
(35028, -3, 0, -1, 'arcadia'),
(35029, -2, -1, -1, 'arcadia'),
(35030, -1, -2, -1, 'arcadia'),
(35031, 0, -3, -1, 'arcadia'),
(35032, 1, -2, -1, 'arcadia'),
(35033, 2, -1, -1, 'arcadia'),
(35034, 3, 0, -1, 'arcadia'),
(35035, 3, 1, -1, 'arcadia'),
(35036, 2, 2, -1, 'arcadia'),
(35037, 1, 3, -1, 'arcadia'),
(35038, 0, 4, -1, 'arcadia'),
(35039, -1, 3, -1, 'arcadia'),
(35040, -2, 2, -1, 'arcadia'),
(35041, -3, 1, -1, 'arcadia'),
(35042, -4, 0, -1, 'arcadia'),
(35043, -3, -1, -1, 'arcadia'),
(35044, -2, -2, -1, 'arcadia'),
(35045, -1, -3, -1, 'arcadia'),
(35046, 0, -4, -1, 'arcadia'),
(35047, 1, -3, -1, 'arcadia'),
(35048, 2, -2, -1, 'arcadia'),
(35049, 3, -1, -1, 'arcadia'),
(35050, 4, 0, -1, 'arcadia'),
(35051, 4, 1, -1, 'arcadia'),
(35052, 3, 2, -1, 'arcadia'),
(35053, 2, 3, -1, 'arcadia'),
(35054, 1, 4, -1, 'arcadia'),
(35055, 0, 5, -1, 'arcadia'),
(35056, -1, 4, -1, 'arcadia'),
(35057, -2, 3, -1, 'arcadia'),
(35058, -3, 2, -1, 'arcadia'),
(35059, -4, 1, -1, 'arcadia'),
(35060, -5, 0, -1, 'arcadia'),
(35061, -4, -1, -1, 'arcadia'),
(35062, -3, -2, -1, 'arcadia'),
(35063, -2, -3, -1, 'arcadia'),
(35064, -1, -4, -1, 'arcadia'),
(35065, 0, -5, -1, 'arcadia'),
(35066, 1, -4, -1, 'arcadia'),
(35067, 2, -3, -1, 'arcadia'),
(35068, 3, -2, -1, 'arcadia'),
(35069, 4, -1, -1, 'arcadia'),
(35070, 5, 0, -1, 'arcadia'),
(35071, 5, 1, -1, 'arcadia'),
(35072, 4, 2, -1, 'arcadia'),
(35073, 3, 3, -1, 'arcadia'),
(35074, 2, 4, -1, 'arcadia'),
(35075, 1, 5, -1, 'arcadia'),
(35076, 0, 6, -1, 'arcadia'),
(35077, -1, 5, -1, 'arcadia'),
(35078, -2, 4, -1, 'arcadia'),
(35079, -3, 3, -1, 'arcadia'),
(35080, -4, 2, -1, 'arcadia'),
(35081, -5, 1, -1, 'arcadia'),
(35082, -6, 0, -1, 'arcadia'),
(35083, -5, -1, -1, 'arcadia'),
(35084, -4, -2, -1, 'arcadia'),
(35085, -3, -3, -1, 'arcadia'),
(35086, -2, -4, -1, 'arcadia'),
(35087, -1, -5, -1, 'arcadia'),
(35088, 0, -6, -1, 'arcadia'),
(35089, 1, -5, -1, 'arcadia'),
(35090, 2, -4, -1, 'arcadia'),
(35091, 3, -3, -1, 'arcadia'),
(35092, 4, -2, -1, 'arcadia'),
(35093, 5, -1, -1, 'arcadia'),
(35094, 6, 0, -1, 'arcadia'),
(35095, 2, 5, -1, 'arcadia'),
(35096, 3, 4, -1, 'arcadia'),
(35097, 4, 3, -1, 'arcadia'),
(35098, 5, 2, -1, 'arcadia'),
(35099, -3, 4, -1, 'arcadia'),
(35100, -4, 3, -1, 'arcadia'),
(35101, -5, 2, -1, 'arcadia'),
(35102, -5, -2, -1, 'arcadia'),
(35103, -4, -3, -1, 'arcadia'),
(35104, -3, -4, -1, 'arcadia'),
(35105, -2, -5, -1, 'arcadia'),
(35106, 3, -4, -1, 'arcadia'),
(35107, 4, -3, -1, 'arcadia'),
(35108, 6, -1, -1, 'arcadia'),
(35109, 5, -2, -1, 'arcadia'),
(35110, -6, 1, -1, 'arcadia'),
(35111, -2, 5, -1, 'arcadia'),
(35112, -1, 6, -1, 'arcadia'),
(35113, -6, -1, -1, 'arcadia'),
(35114, 1, 6, -1, 'arcadia'),
(35115, 6, 1, -1, 'arcadia'),
(35116, 2, -5, -1, 'arcadia'),
(35117, 1, -6, -1, 'arcadia'),
(35118, -1, -6, -1, 'arcadia'),
(35119, 0, 7, -1, 'arcadia'),
(35120, -1, 7, -1, 'arcadia'),
(35121, -2, 6, -1, 'arcadia'),
(35122, -3, 5, -1, 'arcadia'),
(35123, -4, 4, -1, 'arcadia'),
(35124, -5, 3, -1, 'arcadia'),
(35125, -6, 2, -1, 'arcadia'),
(35126, -7, 1, -1, 'arcadia'),
(35127, -7, 0, -1, 'arcadia'),
(35128, -7, -1, -1, 'arcadia'),
(35129, -6, -2, -1, 'arcadia'),
(35130, -5, -3, -1, 'arcadia'),
(35131, -4, -4, -1, 'arcadia'),
(35132, -3, -5, -1, 'arcadia'),
(35133, -2, -6, -1, 'arcadia'),
(35134, -1, -7, -1, 'arcadia'),
(35135, 0, -7, -1, 'arcadia'),
(35136, 1, -7, -1, 'arcadia'),
(35137, 2, -6, -1, 'arcadia'),
(35138, 3, -5, -1, 'arcadia'),
(35139, 4, -4, -1, 'arcadia'),
(35140, 5, -3, -1, 'arcadia'),
(35141, 6, -2, -1, 'arcadia'),
(35142, 7, -1, -1, 'arcadia'),
(35143, 7, 0, -1, 'arcadia'),
(35144, 7, 1, -1, 'arcadia'),
(35145, 6, 2, -1, 'arcadia'),
(35146, 5, 3, -1, 'arcadia'),
(35147, 4, 4, -1, 'arcadia'),
(35148, 3, 5, -1, 'arcadia'),
(35149, 2, 6, -1, 'arcadia'),
(35150, 1, 7, -1, 'arcadia'),
(36410, 5, -16, -2, 'gaia2'),
(37475, 3, 6, -1, 'arcadia'),
(37476, 4, 6, -1, 'arcadia'),
(37477, 5, 6, -1, 'arcadia'),
(37478, 5, 5, -1, 'arcadia'),
(37479, 5, 4, -1, 'arcadia'),
(37480, 4, 5, -1, 'arcadia'),
(37481, 6, 3, -1, 'arcadia'),
(37482, 6, 4, -1, 'arcadia'),
(37483, 6, 5, -1, 'arcadia'),
(37484, 6, 6, -1, 'arcadia'),
(37485, 6, 7, -1, 'arcadia'),
(37486, 5, 7, -1, 'arcadia'),
(37487, 4, 7, -1, 'arcadia'),
(37488, 3, 7, -1, 'arcadia'),
(37489, 2, 7, -1, 'arcadia'),
(37490, -6, -3, -1, 'arcadia'),
(37491, -6, -4, -1, 'arcadia'),
(37492, -5, -4, -1, 'arcadia'),
(37493, -4, -5, -1, 'arcadia'),
(37494, -5, -5, -1, 'arcadia'),
(37495, -6, -5, -1, 'arcadia'),
(37496, -6, -6, -1, 'arcadia'),
(37497, -5, -6, -1, 'arcadia'),
(37498, -4, -6, -1, 'arcadia'),
(37499, -3, -6, -1, 'arcadia'),
(37500, -2, -7, -1, 'arcadia'),
(37501, -3, -7, -1, 'arcadia'),
(37502, -4, -7, -1, 'arcadia'),
(37503, -5, -7, -1, 'arcadia'),
(37504, -6, -7, -1, 'arcadia'),
(38239, 0, -138, 0, 'enfers'),
(38897, -8, 0, 0, 'enfers'),
(38898, -7, 0, 0, 'enfers'),
(38901, -6, 0, 0, 'enfers'),
(38992, -6, -1, 0, 'enfers'),
(39088, -6, 1, 0, 'enfers'),
(44740, 0, -18, 0, 'enfers'),
(44902, -15, 0, 0, 'enfers'),
(45023, -15, -15, 0, 'enfers'),
(45353, 1, -138, 0, 'enfers'),
(45674, -100, 0, 0, 'enfers'),
(45677, -7, -6, -1, 'arcadia'),
(46188, 8, -9, 0, 'gaia'),
(46283, -14, 0, 0, 'enfers'),
(46328, -13, 1, 0, 'enfers'),
(46329, -12, 2, 0, 'enfers'),
(46330, -11, 2, 0, 'enfers'),
(46331, -10, 2, 0, 'enfers'),
(46332, -9, 2, 0, 'enfers'),
(46333, -9, 3, 0, 'enfers'),
(46334, -8, 4, 0, 'enfers'),
(46335, -7, 4, 0, 'enfers'),
(46344, -13, 0, 0, 'enfers'),
(46345, -12, 0, 0, 'enfers'),
(46346, -11, 1, 0, 'enfers'),
(46347, -10, 1, 0, 'enfers'),
(46348, -9, 1, 0, 'enfers'),
(46349, -8, 1, 0, 'enfers'),
(46350, -7, 1, 0, 'enfers'),
(46351, -7, 2, 0, 'enfers'),
(46416, -6, 3, 0, 'enfers'),
(46417, -5, 3, 0, 'enfers'),
(46418, -4, 3, 0, 'enfers'),
(46419, -5, 2, 0, 'enfers'),
(46420, -4, 1, 0, 'enfers'),
(46421, -3, 1, 0, 'enfers'),
(46422, -2, 0, 0, 'enfers'),
(46736, 0, -210, 0, 'enfers'),
(47221, -140, 0, 0, 'enfers'),
(47610, -3, 0, 0, 'enfers'),
(47665, -11, 0, 0, 'enfers'),
(47666, -10, 0, 0, 'enfers'),
(47667, -8, 2, 0, 'enfers'),
(47717, -4, 2, 0, 'enfers'),
(48276, -4, -1, 0, 'enfers'),
(48531, 1, 2, 0, 'gaia2'),
(50671, 210, 0, 0, 'enfers'),
(50758, 70, 0, 0, 'enfers'),
(51525, 1, -2, -1, 'gaia2'),
(51580, -210, 0, 0, 'enfers'),
(51586, -210, -210, 0, 'enfers'),
(51587, 6, -8, 0, 'gaia');

-- --------------------------------------------------------

--
-- Structure de la table `forums_keywords`
--

CREATE TABLE `forums_keywords` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `postName` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `private` int(11) NOT NULL DEFAULT 0,
  `enchanted` int(1) NOT NULL DEFAULT 0,
  `vorpal` int(1) NOT NULL DEFAULT 0,
  `cursed` int(1) NOT NULL DEFAULT 0,
  `element` varchar(255) NOT NULL DEFAULT '',
  `blessed_by_id` int(11) DEFAULT NULL,
  `spell` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `items`
--

INSERT INTO `items` (`id`, `name`, `private`, `enchanted`, `vorpal`, `cursed`, `element`, `blessed_by_id`, `spell`) VALUES
(1, 'or', 0, 0, 0, 0, '', NULL, NULL),
(2, 'alcool_tourbe', 0, 0, 0, 0, '', NULL, NULL),
(3, 'altar', 0, 0, 0, 0, '', NULL, NULL),
(4, 'arbalete_poing', 0, 0, 0, 0, '', NULL, NULL),
(5, 'arc', 0, 0, 0, 0, '', NULL, NULL),
(6, 'armure_boue', 0, 0, 0, 0, '', NULL, NULL),
(7, 'armure_matelassee', 0, 0, 0, 0, '', NULL, NULL),
(8, 'baton_marche', 0, 0, 0, 0, '', NULL, NULL),
(9, 'bottes_marche', 0, 0, 0, 0, '', NULL, NULL),
(10, 'bouclier_parma', 0, 0, 0, 0, '', NULL, NULL),
(11, 'canne_a_peche', 0, 0, 0, 0, '', NULL, NULL),
(12, 'carreau', 0, 0, 0, 0, '', NULL, NULL),
(13, 'casque_illyrien', 0, 0, 0, 0, '', NULL, NULL),
(14, 'coffre_bois', 0, 0, 0, 0, '', NULL, NULL),
(15, 'encre', 0, 0, 0, 0, '', NULL, NULL),
(16, 'fleche', 0, 0, 0, 0, '', NULL, NULL),
(17, 'fustibale', 0, 0, 0, 0, '', NULL, NULL),
(18, 'gladius_entrainement', 0, 0, 0, 0, '', NULL, NULL),
(19, 'gladius', 0, 0, 0, 0, '', NULL, NULL),
(20, 'sceptre', 0, 0, 0, 0, '', NULL, NULL),
(21, 'hache_entrainement', 0, 0, 0, 0, '', NULL, NULL),
(22, 'lance', 0, 0, 0, 0, '', NULL, NULL),
(23, 'mur_bois', 0, 0, 0, 0, '', NULL, NULL),
(24, 'mur_bois_petrifie', 0, 0, 0, 0, '', NULL, NULL),
(25, 'mur_pierre', 0, 0, 0, 0, '', NULL, NULL),
(26, 'parchemin_sort', 0, 0, 0, 0, '', NULL, NULL),
(27, 'parchemin', 0, 0, 0, 0, '', NULL, NULL),
(28, 'piedestal_pierre', 0, 0, 0, 0, '', NULL, NULL),
(29, 'javelot_entrainement', 0, 0, 0, 0, '', NULL, NULL),
(30, 'pioche', 0, 0, 0, 0, '', NULL, NULL),
(31, 'projectile_magique', 0, 0, 0, 0, '', NULL, NULL),
(32, 'route', 0, 0, 0, 0, '', NULL, NULL),
(33, 'pugio', 0, 0, 0, 0, '', NULL, NULL),
(34, 'savon', 0, 0, 0, 0, '', NULL, NULL),
(35, 'table_bois', 0, 0, 0, 0, '', NULL, NULL),
(36, 'torche', 0, 0, 0, 0, '', NULL, NULL),
(37, 'anneau_horizon', 0, 0, 0, 0, '', NULL, NULL),
(38, 'anneau_caprice', 0, 0, 0, 0, '', NULL, NULL),
(39, 'anneau_puissance', 0, 0, 0, 0, '', NULL, NULL),
(40, 'armure_boue', 0, 0, 0, 1, '', NULL, NULL),
(41, 'bottes_sept_lieux', 0, 0, 0, 0, '', NULL, NULL),
(42, 'obole_sacree', 0, 0, 0, 0, '', NULL, NULL),
(43, 'armure_ecailles', 0, 0, 0, 0, '', NULL, NULL),
(44, 'belier', 0, 0, 0, 0, '', NULL, NULL),
(45, 'bouclier_clipeus', 0, 0, 0, 0, '', NULL, NULL),
(46, 'carnyx', 0, 0, 0, 0, '', NULL, NULL),
(47, 'javelot', 0, 0, 0, 0, '', NULL, NULL),
(48, 'aulos', 0, 0, 0, 0, '', NULL, NULL),
(49, 'baton_pellerin', 0, 0, 0, 0, '', NULL, NULL),
(50, 'bottes_talroval', 0, 0, 0, 0, '', NULL, NULL),
(51, 'coffre_bois_petrifie', 0, 0, 0, 0, '', NULL, NULL),
(52, 'cuirasse', 0, 0, 0, 0, '', NULL, NULL),
(53, 'flagrum', 0, 0, 0, 0, '', NULL, NULL),
(54, 'statue_ailee', 0, 0, 0, 0, '', NULL, NULL),
(55, 'targe', 0, 0, 0, 0, '', NULL, NULL),
(56, 'boleadoras', 0, 0, 0, 0, '', NULL, NULL),
(57, 'casse_tete', 0, 0, 0, 0, '', NULL, NULL),
(58, 'encre_tatouage', 0, 0, 0, 0, '', NULL, NULL),
(59, 'ikula_ceremoniel', 0, 0, 0, 0, '', NULL, NULL),
(60, 'manteau_feuillage', 0, 0, 0, 0, '', NULL, NULL),
(61, 'marque_main_blanche', 0, 0, 0, 0, '', NULL, NULL),
(62, 'robe_mage', 0, 0, 0, 0, '', NULL, NULL),
(63, 'cymbale', 0, 0, 0, 0, '', NULL, NULL),
(64, 'armure_hoplitique', 0, 0, 0, 0, '', NULL, NULL),
(65, 'bouclier_ancile', 0, 0, 0, 0, '', NULL, NULL),
(66, 'diademe', 0, 0, 0, 0, '', NULL, NULL),
(67, 'gastraphete', 0, 0, 0, 0, '', NULL, NULL),
(68, 'lame_benie', 0, 0, 0, 0, '', NULL, NULL),
(69, 'phorminx', 0, 0, 0, 0, '', NULL, NULL),
(70, 'piedestal', 0, 0, 0, 0, '', NULL, NULL),
(71, 'pilum', 0, 0, 0, 0, '', NULL, NULL),
(72, 'statue_gisant', 0, 0, 0, 0, '', NULL, NULL),
(73, 'statue_heroique', 0, 0, 0, 0, '', NULL, NULL),
(74, 'statue_monstrueuse', 0, 0, 0, 0, '', NULL, NULL),
(75, 'casque_phrygien', 0, 0, 0, 0, '', NULL, NULL),
(76, 'coffre_metal', 0, 0, 0, 0, '', NULL, NULL),
(77, 'cotte_mailles', 0, 0, 0, 0, '', NULL, NULL),
(78, 'grenade', 0, 0, 0, 0, '', NULL, NULL),
(79, 'labrys', 0, 0, 0, 0, '', NULL, NULL),
(80, 'marteau_guerre', 0, 0, 0, 0, '', NULL, NULL),
(81, 'biere_redoraane', 0, 0, 0, 0, '', NULL, NULL),
(82, 'conque', 0, 0, 0, 0, '', NULL, NULL),
(83, 'armet_incruste', 0, 0, 0, 0, '', NULL, NULL),
(84, 'trident', 0, 0, 0, 0, '', NULL, NULL),
(85, 'adonis', 0, 0, 0, 0, '', NULL, NULL),
(86, 'pierre', 0, 0, 0, 0, '', NULL, NULL),
(87, 'cendre', 0, 0, 0, 0, '', NULL, NULL),
(88, 'tourbe', 0, 0, 0, 0, '', NULL, NULL),
(89, 'bois', 0, 0, 0, 0, '', NULL, NULL),
(90, 'bronze', 0, 0, 0, 0, '', NULL, NULL),
(91, 'salpetre', 0, 0, 0, 0, '', NULL, NULL),
(92, 'nickel', 0, 0, 0, 0, '', NULL, NULL),
(93, 'cuir', 0, 0, 0, 0, '', NULL, NULL),
(94, 'bois_petrifie', 0, 0, 0, 0, '', NULL, NULL),
(95, 'pierre_mana', 0, 0, 0, 0, '', NULL, NULL),
(96, 'nara', 0, 0, 0, 0, '', NULL, NULL),
(97, 'ivoire', 0, 0, 0, 0, '', NULL, NULL),
(98, 'lotus_noir', 0, 0, 0, 0, '', NULL, NULL),
(99, 'houblon', 0, 0, 0, 0, '', NULL, NULL),
(100, 'lichen_sacre', 0, 0, 0, 0, '', NULL, NULL),
(101, 'coco', 0, 0, 0, 0, '', NULL, NULL),
(102, 'astral', 0, 0, 0, 0, '', NULL, NULL),
(103, 'cornemuse', 0, 0, 0, 0, '', NULL, NULL),
(104, 'baton_marche', 0, 1, 0, 0, '', NULL, NULL),
(105, 'armure_boue', 0, 1, 0, 0, '', NULL, NULL),
(106, 'baton_marche', 0, 0, 1, 0, '', NULL, NULL),
(107, 'baton_marche', 0, 0, 0, 1, '', NULL, NULL),
(109, 'poing', 0, 0, 0, 0, '', NULL, NULL),
(110, 'parchemin_sort', 0, 0, 0, 0, '', NULL, 'dmg1/lame_volante'),
(111, 'parchemin_sort', 0, 0, 0, 0, '', NULL, 'dmg2/desarmement'),
(112, 'parchemin_sort', 0, 0, 0, 0, '', NULL, 'soins/imposition_des_mains'),
(113, 'parchemin_sort', 0, 0, 0, 0, '', NULL, 'special/lame_benie'),
(117, 'pugio', 0, 1, 0, 0, '', NULL, NULL),
(121, 'pavot', 0, 0, 0, 0, '', NULL, NULL),
(123, 'echelle', 0, 0, 0, 0, '', NULL, NULL),
(124, 'menthe', 0, 0, 0, 0, '', NULL, NULL),
(125, 'armet_incruste', 0, 1, 0, 0, '', NULL, NULL),
(126, 'cafe', 0, 0, 0, 0, '', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `items_asks`
--

CREATE TABLE `items_asks` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `stock` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `items_bids`
--

CREATE TABLE `items_bids` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `stock` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `items_exchanges`
--

CREATE TABLE `items_exchanges` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_ok` tinyint(1) NOT NULL DEFAULT 0,
  `target_ok` tinyint(1) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `map_dialogs`
--

CREATE TABLE `map_dialogs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `map_elements`
--

CREATE TABLE `map_elements` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `endTime` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `map_elements`
--

INSERT INTO `map_elements` (`id`, `name`, `coords_id`, `endTime`) VALUES
(1, 'boue', 27884, 0),
(2, 'boue', 35058, 0),
(3, 'boue', 35059, 0),
(4, 'boue', 35070, 0),
(5, 'boue', 35079, 0),
(6, 'boue', 35080, 0),
(7, 'boue', 35086, 0),
(8, 'boue', 35092, 0),
(9, 'boue', 35093, 0),
(10, 'boue', 35099, 0),
(11, 'boue', 35100, 0),
(12, 'boue', 35101, 0),
(13, 'boue', 35104, 0),
(14, 'boue', 35105, 0),
(15, 'boue', 35107, 0),
(16, 'boue', 35108, 0),
(17, 'boue', 35109, 0),
(18, 'boue', 35118, 0),
(19, 'diamant', 11, 0),
(20, 'diamant', 12, 0),
(21, 'diamant', 17, 0),
(22, 'diamant', 18, 0),
(23, 'diamant', 19, 0),
(24, 'diamant', 21, 0),
(25, 'diamant', 22, 0),
(26, 'diamant', 23, 0),
(27, 'diamant', 54, 0),
(28, 'diamant', 57, 0),
(29, 'diamant', 105, 0),
(30, 'diamant', 108, 0),
(31, 'diamant', 118, 0),
(32, 'diamant', 119, 0),
(33, 'diamant', 120, 0),
(34, 'diamant', 121, 0),
(35, 'diamant', 122, 0),
(36, 'diamant', 123, 0),
(37, 'diamant', 155, 0),
(38, 'diamant', 156, 0),
(39, 'diamant', 35017, 0),
(40, 'diamant', 35027, 0),
(41, 'diamant', 35060, 0),
(42, 'diamant', 35063, 0),
(43, 'diamant', 35085, 0),
(44, 'diamant', 35100, 0),
(45, 'diamant', 35110, 0),
(46, 'diamant', 35113, 0),
(47, 'eau', 16595, 0),
(48, 'eau', 16596, 0),
(49, 'eau', 16597, 0),
(50, 'eau', 16598, 0),
(51, 'eau', 16599, 0),
(52, 'eau', 16600, 0),
(53, 'eau', 16601, 0),
(54, 'eau', 16602, 0),
(55, 'eau', 16604, 0),
(56, 'eau', 16605, 0),
(57, 'eau', 16610, 0),
(58, 'eau', 16613, 0),
(59, 'eau', 16657, 0),
(60, 'eau', 16658, 0),
(61, 'eau', 16659, 0),
(62, 'eau', 16660, 0),
(63, 'eau', 16663, 0),
(64, 'eau', 16664, 0),
(65, 'eau', 16665, 0),
(66, 'eau', 16669, 0),
(67, 'eau', 16670, 0),
(68, 'eau', 16672, 0),
(69, 'eau', 16673, 0),
(70, 'eau', 16676, 0),
(71, 'eau', 16677, 0),
(72, 'eau', 16686, 0),
(73, 'eau', 16689, 0),
(74, 'eau', 16690, 0),
(75, 'eau', 16692, 0),
(76, 'eau', 16704, 0),
(77, 'eau', 16705, 0),
(78, 'eau', 16706, 0),
(79, 'eau', 16707, 0),
(80, 'eau', 16708, 0),
(81, 'eau', 16710, 0),
(82, 'eau', 16711, 0),
(83, 'eau', 16712, 0),
(84, 'eau', 16713, 0),
(85, 'eau', 16714, 0),
(86, 'eau', 16715, 0),
(87, 'eau', 16717, 0),
(88, 'eau', 16718, 0),
(89, 'eau', 16719, 0),
(90, 'eau', 16720, 0),
(91, 'eau', 16721, 0),
(92, 'eau', 16722, 0),
(93, 'eau', 16746, 0),
(94, 'eau', 16751, 0),
(95, 'eau', 16756, 0),
(96, 'eau', 16758, 0),
(97, 'eau', 16759, 0),
(98, 'eau', 16764, 0),
(99, 'eau', 16765, 0),
(100, 'eau', 16769, 0),
(101, 'eau', 16770, 0),
(102, 'eau', 16771, 0),
(103, 'eau', 16790, 0),
(104, 'eau', 16791, 0),
(105, 'eau', 16794, 0),
(106, 'eau', 16795, 0),
(107, 'eau', 16796, 0),
(108, 'eau', 16797, 0),
(109, 'eau', 16802, 0),
(110, 'eau', 16803, 0),
(111, 'eau', 16804, 0),
(112, 'eau', 16806, 0),
(113, 'eau', 16807, 0),
(114, 'eau', 16808, 0),
(115, 'eau', 16809, 0),
(116, 'eau', 16811, 0),
(117, 'eau', 16812, 0),
(118, 'eau', 16813, 0),
(119, 'eau', 16814, 0),
(120, 'eau', 16815, 0),
(121, 'eau', 16816, 0),
(122, 'eau', 16817, 0),
(123, 'eau', 16853, 0),
(124, 'eau', 16855, 0),
(125, 'eau', 16856, 0),
(126, 'eau', 16859, 0),
(127, 'eau', 16864, 0),
(128, 'eau', 16866, 0),
(129, 'eau', 16868, 0),
(130, 'eau', 16876, 0),
(131, 'eau', 16878, 0),
(132, 'eau', 16879, 0),
(133, 'eau', 16881, 0),
(134, 'eau', 16882, 0),
(135, 'eau', 16883, 0),
(136, 'eau', 16884, 0),
(137, 'eau', 16885, 0),
(477, 'eau', 17275, 0),
(478, 'eau', 17277, 0),
(479, 'eau', 17281, 0),
(480, 'eau', 17285, 0),
(481, 'eau', 17286, 0),
(482, 'eau', 17287, 0),
(483, 'eau', 17327, 0),
(484, 'eau', 17328, 0),
(485, 'eau', 17329, 0),
(486, 'eau', 17366, 0),
(487, 'eau', 17367, 0),
(488, 'eau', 17369, 0),
(489, 'eau', 17372, 0),
(490, 'eau', 17373, 0),
(491, 'eau', 17374, 0),
(492, 'eau', 17375, 0),
(493, 'eau', 17376, 0),
(494, 'eau', 17422, 0),
(495, 'eau', 17430, 0),
(138, 'eau', 19770, 0),
(139, 'eau', 19771, 0),
(140, 'eau', 19772, 0),
(141, 'eau', 19773, 0),
(142, 'eau', 19774, 0),
(143, 'eau', 19775, 0),
(144, 'eau', 19777, 0),
(145, 'eau', 19778, 0),
(146, 'eau', 19780, 0),
(147, 'eau', 19781, 0),
(148, 'eau', 19782, 0),
(149, 'eau', 19783, 0),
(150, 'eau', 19784, 0),
(151, 'eau', 19785, 0),
(152, 'eau', 19788, 0),
(153, 'eau', 19789, 0),
(154, 'eau', 19790, 0),
(155, 'eau', 19791, 0),
(156, 'eau', 19792, 0),
(157, 'eau', 19793, 0),
(158, 'eau', 19798, 0),
(159, 'eau', 19799, 0),
(160, 'eau', 19800, 0),
(161, 'eau', 19801, 0),
(162, 'eau', 19802, 0),
(163, 'eau', 19803, 0),
(164, 'eau', 19804, 0),
(165, 'eau', 19805, 0),
(166, 'eau', 19806, 0),
(167, 'eau', 19807, 0),
(168, 'eau', 19808, 0),
(169, 'eau', 19809, 0),
(170, 'eau', 19810, 0),
(171, 'eau', 19811, 0),
(172, 'eau', 19812, 0),
(173, 'eau', 19813, 0),
(174, 'eau', 19814, 0),
(175, 'eau', 19815, 0),
(176, 'eau', 19816, 0),
(177, 'eau', 19817, 0),
(178, 'eau', 19818, 0),
(179, 'eau', 19819, 0),
(180, 'eau', 19820, 0),
(181, 'eau', 19821, 0),
(182, 'eau', 19822, 0),
(183, 'eau', 19823, 0),
(184, 'eau', 19824, 0),
(185, 'eau', 19825, 0),
(186, 'eau', 19826, 0),
(187, 'eau', 19827, 0),
(188, 'eau', 19828, 0),
(189, 'eau', 19829, 0),
(190, 'eau', 19830, 0),
(191, 'eau', 19831, 0),
(192, 'eau', 19832, 0),
(193, 'eau', 19833, 0),
(194, 'eau', 19834, 0),
(195, 'eau', 19835, 0),
(196, 'eau', 19836, 0),
(197, 'eau', 19837, 0),
(198, 'eau', 19838, 0),
(199, 'eau', 19839, 0),
(200, 'eau', 19841, 0),
(201, 'eau', 19842, 0),
(202, 'eau', 19843, 0),
(203, 'eau', 19844, 0),
(204, 'eau', 19845, 0),
(205, 'eau', 19846, 0),
(206, 'eau', 19847, 0),
(207, 'eau', 19848, 0),
(208, 'eau', 19849, 0),
(209, 'eau', 19850, 0),
(210, 'eau', 19851, 0),
(211, 'eau', 19852, 0),
(212, 'eau', 19853, 0),
(213, 'eau', 19854, 0),
(214, 'eau', 19855, 0),
(215, 'eau', 19856, 0),
(216, 'eau', 19857, 0),
(217, 'eau', 19858, 0),
(218, 'feu', 35035, 0),
(219, 'feu', 35048, 0),
(220, 'feu', 35050, 0),
(221, 'feu', 35051, 0),
(222, 'feu', 35052, 0),
(223, 'feu', 35070, 0),
(224, 'feu', 35071, 0),
(225, 'feu', 35094, 0),
(226, 'feu', 35096, 0),
(227, 'feu', 35097, 0),
(228, 'lave', 35036, 0),
(229, 'lave', 35051, 0),
(230, 'lave', 35052, 0),
(231, 'lave', 35071, 0),
(232, 'lave', 35072, 0),
(233, 'lave', 35073, 0),
(234, 'lave', 35094, 0),
(235, 'lave', 35095, 0),
(236, 'lave', 35097, 0),
(237, 'lave', 35098, 0),
(238, 'lave', 35115, 0),
(239, 'ronce', 12670, 0),
(240, 'ronce', 12671, 0),
(241, 'ronce', 12705, 0),
(242, 'ronce', 12707, 0),
(243, 'ronce', 12709, 0),
(244, 'ronce', 12711, 0),
(245, 'ronce', 12712, 0),
(246, 'ronce', 12713, 0),
(247, 'ronce', 12714, 0),
(248, 'ronce', 12715, 0),
(249, 'ronce', 12716, 0),
(250, 'ronce', 12717, 0),
(251, 'ronce', 12718, 0),
(252, 'ronce', 12719, 0),
(253, 'ronce', 12720, 0),
(254, 'ronce', 12721, 0),
(255, 'ronce', 12722, 0),
(256, 'ronce', 12723, 0),
(257, 'ronce', 12724, 0),
(258, 'ronce', 12725, 0),
(259, 'ronce', 12726, 0),
(260, 'ronce', 12727, 0),
(261, 'ronce', 12728, 0),
(262, 'ronce', 12729, 0),
(263, 'ronce', 12730, 0),
(264, 'ronce', 12731, 0),
(265, 'ronce', 12732, 0),
(266, 'ronce', 12733, 0),
(267, 'ronce', 12734, 0),
(268, 'ronce', 12735, 0),
(269, 'ronce', 12736, 0),
(270, 'ronce', 12737, 0),
(271, 'ronce', 12738, 0),
(272, 'ronce', 12739, 0),
(273, 'ronce', 12740, 0),
(274, 'ronce', 12741, 0),
(275, 'ronce', 12742, 0),
(276, 'ronce', 12743, 0),
(277, 'ronce', 12744, 0),
(278, 'ronce', 12745, 0),
(279, 'ronce', 12746, 0),
(280, 'ronce', 12747, 0),
(281, 'ronce', 12748, 0),
(282, 'ronce', 12749, 0),
(283, 'ronce', 12750, 0),
(284, 'ronce', 12751, 0),
(285, 'ronce', 12752, 0),
(286, 'ronce', 12753, 0),
(287, 'ronce', 12754, 0),
(288, 'ronce', 12755, 0),
(289, 'ronce', 12756, 0),
(290, 'ronce', 12757, 0),
(291, 'ronce', 12758, 0),
(292, 'ronce', 12759, 0),
(293, 'ronce', 12760, 0),
(294, 'ronce', 12761, 0),
(295, 'ronce', 12762, 0),
(296, 'ronce', 12763, 0),
(297, 'ronce', 12764, 0),
(298, 'styx', 12676, 0),
(299, 'styx', 12677, 0),
(300, 'styx', 12678, 0),
(301, 'styx', 12679, 0),
(302, 'styx', 12765, 0),
(303, 'styx', 12766, 0),
(304, 'styx', 12767, 0),
(305, 'styx', 12768, 0),
(306, 'styx', 12769, 0),
(307, 'styx', 12770, 0),
(308, 'styx', 12771, 0),
(309, 'styx', 12772, 0),
(310, 'styx', 12773, 0),
(311, 'styx', 12774, 0),
(312, 'styx', 12775, 0),
(313, 'styx', 12776, 0),
(314, 'styx', 12777, 0),
(315, 'styx', 12778, 0),
(316, 'styx', 12779, 0),
(317, 'styx', 12780, 0),
(318, 'styx', 12781, 0),
(319, 'styx', 12782, 0),
(320, 'styx', 12783, 0),
(321, 'styx', 12784, 0),
(322, 'styx', 12785, 0),
(323, 'styx', 12786, 0),
(324, 'styx', 12787, 0),
(325, 'styx', 12788, 0),
(326, 'styx', 12789, 0),
(327, 'styx', 12790, 0),
(328, 'styx', 12791, 0),
(329, 'styx', 12792, 0),
(330, 'styx', 12793, 0),
(331, 'styx', 12794, 0),
(332, 'styx', 12795, 0),
(333, 'styx', 12796, 0),
(334, 'styx', 12797, 0),
(335, 'styx', 12798, 0),
(336, 'styx', 12799, 0),
(337, 'styx', 12800, 0),
(338, 'styx', 12801, 0),
(339, 'styx', 12802, 0),
(340, 'styx', 12803, 0),
(341, 'styx', 12804, 0),
(342, 'styx', 12805, 0),
(343, 'styx', 12806, 0),
(344, 'styx', 12807, 0),
(345, 'styx', 12808, 0),
(346, 'styx', 12809, 0),
(347, 'styx', 12810, 0),
(348, 'styx', 12811, 0),
(349, 'styx', 12812, 0),
(350, 'styx', 12813, 0),
(351, 'styx', 12814, 0),
(352, 'styx', 12815, 0),
(353, 'styx', 12819, 0),
(354, 'styx', 12820, 0),
(355, 'styx', 12821, 0),
(356, 'styx', 12822, 0),
(357, 'styx', 12823, 0),
(358, 'styx', 12824, 0),
(359, 'styx', 12825, 0),
(360, 'styx', 12826, 0),
(361, 'styx', 12827, 0),
(362, 'styx', 12828, 0),
(363, 'styx', 12829, 0),
(364, 'styx', 12830, 0),
(365, 'styx', 12831, 0),
(366, 'styx', 12832, 0),
(367, 'styx', 12833, 0),
(368, 'styx', 12834, 0),
(369, 'styx', 12835, 0),
(370, 'styx', 12836, 0),
(371, 'styx', 12837, 0),
(372, 'styx', 12838, 0),
(373, 'styx', 12839, 0),
(374, 'styx', 12840, 0),
(375, 'styx', 12841, 0),
(376, 'styx', 12842, 0),
(377, 'styx', 12843, 0),
(378, 'styx', 12844, 0),
(379, 'styx', 12845, 0),
(380, 'styx', 12846, 0),
(381, 'styx', 12847, 0),
(382, 'styx', 12848, 0),
(383, 'styx', 12849, 0),
(384, 'styx', 12850, 0),
(385, 'styx', 12851, 0),
(386, 'styx', 12852, 0),
(387, 'styx', 12853, 0),
(388, 'styx', 12854, 0),
(389, 'styx', 12855, 0),
(390, 'styx', 12856, 0),
(391, 'styx', 12857, 0),
(392, 'styx', 12858, 0),
(393, 'styx', 12859, 0),
(394, 'styx', 12860, 0),
(395, 'styx', 12861, 0),
(396, 'styx', 12862, 0),
(397, 'styx', 12863, 0),
(398, 'styx', 12864, 0),
(399, 'styx', 12865, 0),
(400, 'styx', 12866, 0),
(401, 'styx', 12867, 0),
(402, 'styx', 12868, 0),
(403, 'styx', 12869, 0),
(404, 'styx', 12870, 0),
(405, 'styx', 12871, 0),
(406, 'styx', 12872, 0),
(407, 'styx', 12873, 0),
(408, 'styx', 12874, 0),
(409, 'styx', 12875, 0),
(410, 'styx', 12876, 0),
(411, 'styx', 12877, 0),
(412, 'styx', 12878, 0),
(413, 'styx', 12879, 0),
(414, 'styx', 12880, 0),
(415, 'styx', 12881, 0),
(416, 'styx', 12882, 0),
(417, 'styx', 12883, 0),
(418, 'styx', 12884, 0),
(419, 'styx', 12885, 0),
(420, 'styx', 12886, 0),
(421, 'styx', 12887, 0),
(422, 'styx', 12888, 0),
(423, 'styx', 12889, 0),
(424, 'styx', 12890, 0),
(425, 'styx', 12891, 0),
(426, 'styx', 12892, 0),
(427, 'styx', 12893, 0),
(428, 'styx', 12894, 0),
(429, 'styx', 12895, 0),
(430, 'styx', 12896, 0),
(431, 'styx', 12897, 0),
(432, 'styx', 12898, 0),
(433, 'styx', 12899, 0),
(434, 'styx', 12900, 0),
(435, 'styx', 12901, 0),
(436, 'styx', 12902, 0),
(437, 'styx', 12903, 0),
(438, 'styx', 12904, 0),
(439, 'styx', 12905, 0),
(440, 'styx', 12906, 0),
(441, 'styx', 12907, 0),
(442, 'styx', 12908, 0),
(443, 'styx', 12909, 0),
(444, 'styx', 12910, 0),
(445, 'styx', 12911, 0),
(446, 'styx', 12912, 0),
(447, 'styx', 12913, 0),
(448, 'styx', 12914, 0),
(449, 'styx', 12915, 0),
(450, 'styx', 12916, 0),
(451, 'styx', 12917, 0),
(452, 'styx', 12918, 0),
(453, 'styx', 12919, 0),
(454, 'styx', 12920, 0),
(455, 'styx', 12921, 0),
(456, 'styx', 12922, 0),
(457, 'styx', 12923, 0),
(458, 'styx', 12924, 0),
(459, 'styx', 12925, 0),
(460, 'styx', 12926, 0),
(461, 'styx', 12927, 0),
(462, 'styx', 12928, 0),
(463, 'styx', 12929, 0),
(464, 'styx', 12930, 0),
(465, 'styx', 35018, 0),
(466, 'styx', 35028, 0),
(467, 'styx', 35043, 0),
(468, 'styx', 35061, 0),
(469, 'styx', 35077, 0),
(470, 'styx', 35078, 0),
(471, 'styx', 35083, 0),
(472, 'styx', 35084, 0),
(473, 'styx', 35102, 0),
(474, 'styx', 35103, 0),
(475, 'styx', 35111, 0),
(476, 'styx', 35113, 0);

-- --------------------------------------------------------

--
-- Structure de la table `map_foregrounds`
--

CREATE TABLE `map_foregrounds` (
  `id` int(11) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `map_foregrounds`
--

INSERT INTO `map_foregrounds` (`id`, `coords_id`, `name`) VALUES
(1, 97, 'olympia-00'),
(2, 98, 'olympia-01'),
(3, 99, 'olympia-02'),
(4, 100, 'olympia-03'),
(5, 160, 'olympia-00'),
(6, 167, 'olympia-01'),
(7, 162, 'olympia-02'),
(8, 164, 'olympia-03'),
(9, 12697, 'porte_des_enfers-00'),
(10, 12698, 'porte_des_enfers-01'),
(11, 12680, 'porte_des_enfers-02'),
(12, 12681, 'porte_des_enfers-03'),
(13, 12704, 'gardien_stellaire-00'),
(14, 12705, 'gardien_stellaire-01'),
(15, 12706, 'gardien_stellaire-00'),
(16, 12707, 'gardien_stellaire-01'),
(17, 12708, 'gardien_stellaire-00'),
(18, 12709, 'gardien_stellaire-01'),
(19, 12710, 'gardien_stellaire-00'),
(20, 12711, 'gardien_stellaire-01'),
(21, 12826, 'asteroide-03'),
(22, 12827, 'asteroide-04'),
(23, 12828, 'asteroide-05'),
(24, 12816, 'asteroide-11'),
(25, 12817, 'asteroide-12'),
(26, 12818, 'asteroide-13'),
(27, 27885, 'triton_statue-00'),
(28, 27886, 'triton_statue-01'),
(29, 27887, 'triton_statue-02'),
(30, 27888, 'triton_statue-04'),
(31, 27889, 'triton_statue-05'),
(32, 17110, 'marchand'),
(33, 16998, 'echelle_haut'),
(34, 17001, 'tonneau'),
(35, 17002, 'tonneau'),
(36, 17003, 'marchand'),
(37, 17206, 'tonneau');

-- --------------------------------------------------------

--
-- Structure de la table `map_items`
--

CREATE TABLE `map_items` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `map_plants`
--

CREATE TABLE `map_plants` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `map_plants`
--

INSERT INTO `map_plants` (`id`, `name`, `coords_id`, `params`) VALUES
(1, 'adonis', 17229, NULL),
(2, 'adonis', 17230, NULL),
(3, 'adonis', 17231, NULL),
(4, 'adonis', 17232, NULL),
(5, 'adonis', 17233, NULL),
(6, 'adonis', 17234, NULL),
(7, 'adonis', 17235, NULL),
(8, 'adonis', 17236, NULL),
(9, 'adonis', 17237, NULL),
(10, 'adonis', 17225, NULL),
(11, 'adonis', 17224, NULL),
(12, 'adonis', 17223, NULL),
(13, 'cafe', 17269, NULL),
(14, 'cafe', 17270, NULL),
(15, 'cafe', 17271, NULL),
(16, 'cafe', 17272, NULL),
(17, 'cafe', 17282, NULL),
(18, 'cafe', 17291, NULL),
(19, 'cafe', 17292, NULL),
(20, 'cafe', 17293, NULL),
(21, 'cafe', 17294, NULL),
(22, 'astral', 17283, NULL),
(23, 'astral', 17284, NULL),
(24, 'astral', 17273, NULL),
(25, 'astral', 17274, NULL),
(26, 'astral', 17330, NULL),
(27, 'astral', 17331, NULL),
(28, 'lotus_noir', 17242, NULL),
(29, 'lotus_noir', 17241, NULL),
(30, 'lotus_noir', 17227, NULL),
(31, 'lotus_noir', 17226, NULL),
(32, 'menthe', 17280, NULL),
(33, 'menthe', 17279, NULL),
(34, 'menthe', 17278, NULL),
(35, 'menthe', 17263, NULL),
(36, 'menthe', 17264, NULL),
(37, 'menthe', 17265, NULL),
(38, 'pavot', 17290, NULL),
(39, 'pavot', 17332, NULL),
(40, 'pavot', 17335, NULL),
(41, 'pavot', 17334, NULL),
(42, 'pavot', 17333, NULL),
(43, 'pavot', 17266, NULL),
(44, 'pavot', 17267, NULL),
(45, 'pavot', 17296, NULL),
(46, 'pavot', 17297, NULL),
(47, 'pavot', 17368, NULL),
(48, 'lichen_sacre', 17362, NULL),
(49, 'lichen_sacre', 17361, NULL),
(50, 'lichen_sacre', 17358, NULL),
(51, 'lichen_sacre', 17357, NULL),
(52, 'lichen_sacre', 17354, NULL),
(53, 'lichen_sacre', 17353, NULL),
(54, 'lichen_sacre', 17345, NULL),
(55, 'lichen_sacre', 17344, NULL),
(56, 'lichen_sacre', 17343, NULL),
(57, 'lichen_sacre', 17342, NULL),
(58, 'lichen_sacre', 17341, NULL),
(59, 'lichen_sacre', 17340, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `map_tiles`
--

CREATE TABLE `map_tiles` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `coords_id` int(11) NOT NULL,
  `foreground` int(11) NOT NULL DEFAULT 0,
  `player_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `map_tiles`
--

INSERT INTO `map_tiles` (`id`, `name`, `coords_id`, `foreground`, `player_id`) VALUES
(1, 'carreaux', 1, 0, NULL),
(2, 'carreaux', 2, 0, NULL),
(3, 'carreaux', 3, 0, NULL),
(4, 'carreaux', 4, 0, NULL),
(5, 'carreaux', 5, 0, NULL),
(6, 'carreaux', 6, 0, NULL),
(7, 'carreaux', 7, 0, NULL),
(8, 'carreaux', 8, 0, NULL),
(9, 'carreaux', 9, 0, NULL),
(10, 'carreaux', 10, 0, NULL),
(11, 'carreaux', 11, 0, NULL),
(12, 'carreaux', 12, 0, NULL),
(13, 'carreaux', 13, 0, NULL),
(14, 'carreaux', 14, 0, NULL),
(15, 'carreaux', 15, 0, NULL),
(16, 'carreaux', 16, 0, NULL),
(17, 'carreaux', 17, 0, NULL),
(18, 'carreaux', 18, 0, NULL),
(19, 'carreaux', 19, 0, NULL),
(20, 'carreaux', 20, 0, NULL),
(21, 'carreaux', 21, 0, NULL),
(22, 'carreaux', 22, 0, NULL),
(23, 'carreaux', 23, 0, NULL),
(24, 'falaise', 24, 0, NULL),
(25, 'falaise', 25, 0, NULL),
(26, 'falaise', 26, 0, NULL),
(27, 'falaise', 27, 0, NULL),
(28, 'falaise', 28, 0, NULL),
(29, 'carreaux', 29, 0, NULL),
(30, 'carreaux', 30, 0, NULL),
(31, 'carreaux', 31, 0, NULL),
(32, 'carreaux', 32, 0, NULL),
(33, 'carreaux', 33, 0, NULL),
(34, 'falaise', 34, 0, NULL),
(35, 'falaise', 35, 0, NULL),
(36, 'carreaux', 36, 0, NULL),
(37, 'carreaux', 37, 0, NULL),
(38, 'carreaux', 38, 0, NULL),
(39, 'carreaux', 39, 0, NULL),
(40, 'carreaux', 40, 0, NULL),
(41, 'carreaux', 41, 0, NULL),
(42, 'carreaux', 42, 0, NULL),
(43, 'carreaux', 43, 0, NULL),
(44, 'carreaux', 44, 0, NULL),
(45, 'falaise', 45, 0, NULL),
(46, 'falaise', 46, 0, NULL),
(47, 'falaise', 47, 0, NULL),
(48, 'falaise', 48, 0, NULL),
(49, 'falaise', 49, 0, NULL),
(50, 'falaise', 50, 0, NULL),
(51, 'falaise', 51, 0, NULL),
(52, 'falaise', 52, 0, NULL),
(53, 'carreaux', 53, 0, NULL),
(54, 'carreaux', 54, 0, NULL),
(55, 'falaise', 55, 0, NULL),
(56, 'falaise', 56, 0, NULL),
(57, 'carreaux', 57, 0, NULL),
(58, 'falaise', 58, 0, NULL),
(59, 'carreaux', 59, 0, NULL),
(60, 'carreaux', 101, 0, NULL),
(61, 'carreaux', 102, 0, NULL),
(62, 'carreaux', 103, 0, NULL),
(63, 'carreaux', 104, 0, NULL),
(64, 'carreaux', 105, 0, NULL),
(65, 'carreaux', 106, 0, NULL),
(66, 'carreaux', 107, 0, NULL),
(67, 'carreaux', 108, 0, NULL),
(68, 'carreaux', 109, 0, NULL),
(69, 'carreaux', 110, 0, NULL),
(70, 'carreaux', 111, 0, NULL),
(71, 'carreaux', 112, 0, NULL),
(72, 'carreaux', 113, 0, NULL),
(73, 'carreaux', 114, 0, NULL),
(74, 'carreaux', 115, 0, NULL),
(75, 'carreaux', 116, 0, NULL),
(76, 'carreaux', 117, 0, NULL),
(77, 'carreaux', 118, 0, NULL),
(78, 'carreaux', 119, 0, NULL),
(79, 'carreaux', 120, 0, NULL),
(80, 'carreaux', 121, 0, NULL),
(81, 'carreaux', 122, 0, NULL),
(82, 'carreaux', 123, 0, NULL),
(83, 'carreaux', 124, 0, NULL),
(84, 'falaise', 125, 0, NULL),
(85, 'falaise', 126, 0, NULL),
(86, 'falaise', 127, 0, NULL),
(87, 'falaise', 128, 0, NULL),
(88, 'falaise', 129, 0, NULL),
(89, 'carreaux', 130, 0, NULL),
(90, 'carreaux', 131, 0, NULL),
(91, 'carreaux', 132, 0, NULL),
(92, 'carreaux', 133, 0, NULL),
(93, 'carreaux', 134, 0, NULL),
(94, 'falaise', 135, 0, NULL),
(95, 'falaise', 136, 0, NULL),
(96, 'carreaux', 137, 0, NULL),
(97, 'carreaux', 138, 0, NULL),
(98, 'carreaux', 139, 0, NULL),
(99, 'carreaux', 140, 0, NULL),
(100, 'carreaux', 141, 0, NULL),
(101, 'carreaux', 142, 0, NULL),
(102, 'carreaux', 143, 0, NULL),
(103, 'carreaux', 144, 0, NULL),
(104, 'carreaux', 145, 0, NULL),
(105, 'falaise', 146, 0, NULL),
(106, 'falaise', 147, 0, NULL),
(107, 'falaise', 148, 0, NULL),
(108, 'falaise', 149, 0, NULL),
(109, 'falaise', 150, 0, NULL),
(110, 'falaise', 151, 0, NULL),
(111, 'falaise', 152, 0, NULL),
(112, 'falaise', 153, 0, NULL),
(113, 'carreaux', 154, 0, NULL),
(114, 'carreaux', 155, 0, NULL),
(115, 'carreaux', 156, 0, NULL),
(116, 'falaise', 157, 0, NULL),
(117, 'falaise', 158, 0, NULL),
(118, 'falaise', 159, 0, NULL),
(119, 'escalier_vers_le_bas', 12671, 0, NULL),
(120, 'route', 12672, 0, NULL),
(121, 'route', 12673, 0, NULL),
(122, 'route', 12674, 0, NULL),
(123, 'route', 12675, 0, NULL),
(124, 'pit', 12676, 0, NULL),
(125, 'pit', 12677, 0, NULL),
(126, 'pit', 12678, 0, NULL),
(127, 'pit', 12679, 0, NULL),
(128, 'route', 12680, 0, NULL),
(129, 'route', 12681, 0, NULL),
(130, 'route', 12682, 0, NULL),
(131, 'route', 12683, 0, NULL),
(132, 'route', 12684, 0, NULL),
(133, 'route', 12685, 0, NULL),
(134, 'route', 12686, 0, NULL),
(135, 'route', 12687, 0, NULL),
(136, 'route', 12688, 0, NULL),
(137, 'route', 12689, 0, NULL),
(138, 'route', 12690, 0, NULL),
(139, 'route', 12691, 0, NULL),
(140, 'route', 12692, 0, NULL),
(141, 'route', 12693, 0, NULL),
(142, 'route', 12694, 0, NULL),
(143, 'route', 12695, 0, NULL),
(144, 'route', 12696, 0, NULL),
(145, 'route', 12697, 0, NULL),
(146, 'route', 12698, 0, NULL),
(147, 'route', 12699, 0, NULL),
(148, 'route', 12700, 0, NULL),
(149, 'route', 12701, 0, NULL),
(150, 'route', 12702, 0, NULL),
(151, 'route', 12703, 0, NULL),
(152, 'carreaux', 15463, 0, NULL),
(153, 'carreaux', 15472, 0, NULL),
(154, 'carreaux', 15457, 0, NULL),
(155, 'carreaux', 15469, 0, NULL),
(156, 'carreaux', 15470, 0, NULL),
(157, 'desert_de_l_egeon', 15519, 0, NULL),
(158, 'desert_de_l_egeon', 15520, 0, NULL),
(159, 'desert_de_l_egeon', 15521, 0, NULL),
(160, 'desert_de_l_egeon', 15522, 0, NULL),
(161, 'desert_de_l_egeon', 15523, 0, NULL),
(162, 'desert_de_l_egeon', 15524, 0, NULL),
(163, 'desert_de_l_egeon', 15525, 0, NULL),
(164, 'desert_de_l_egeon', 15526, 0, NULL),
(165, 'desert_de_l_egeon', 15527, 0, NULL),
(166, 'desert_de_l_egeon', 15528, 0, NULL),
(167, 'desert_de_l_egeon', 15529, 0, NULL),
(168, 'desert_de_l_egeon', 15530, 0, NULL),
(169, 'desert_de_l_egeon', 15531, 0, NULL),
(170, 'desert_de_l_egeon', 15532, 0, NULL),
(171, 'desert_de_l_egeon', 15533, 0, NULL),
(172, 'desert_de_l_egeon', 15534, 0, NULL),
(173, 'desert_de_l_egeon', 15535, 0, NULL),
(174, 'desert_de_l_egeon', 15536, 0, NULL),
(175, 'desert_de_l_egeon', 15537, 0, NULL),
(176, 'desert_de_l_egeon', 15538, 0, NULL),
(177, 'desert_de_l_egeon', 15539, 0, NULL),
(178, 'desert_de_l_egeon', 15540, 0, NULL),
(179, 'desert_de_l_egeon', 15541, 0, NULL),
(180, 'desert_de_l_egeon', 15542, 0, NULL),
(181, 'desert_de_l_egeon', 15543, 0, NULL),
(182, 'desert_de_l_egeon', 15544, 0, NULL),
(183, 'desert_de_l_egeon', 15545, 0, NULL),
(184, 'desert_de_l_egeon', 15546, 0, NULL),
(185, 'desert_de_l_egeon', 15547, 0, NULL),
(186, 'desert_de_l_egeon', 15548, 0, NULL),
(187, 'desert_de_l_egeon', 15549, 0, NULL),
(188, 'desert_de_l_egeon', 15550, 0, NULL),
(189, 'desert_de_l_egeon', 15551, 0, NULL),
(190, 'desert_de_l_egeon', 15552, 0, NULL),
(191, 'desert_de_l_egeon', 15553, 0, NULL),
(192, 'desert_de_l_egeon', 15554, 0, NULL),
(193, 'desert_de_l_egeon', 15555, 0, NULL),
(194, 'desert_de_l_egeon', 15556, 0, NULL),
(195, 'desert_de_l_egeon', 15557, 0, NULL),
(196, 'desert_de_l_egeon', 15558, 0, NULL),
(197, 'desert_de_l_egeon', 15559, 0, NULL),
(198, 'desert_de_l_egeon', 15560, 0, NULL),
(199, 'desert_de_l_egeon', 15561, 0, NULL),
(200, 'desert_de_l_egeon', 15562, 0, NULL),
(201, 'desert_de_l_egeon', 16595, 0, NULL),
(202, 'desert_de_l_egeon', 16598, 0, NULL),
(203, 'desert_de_l_egeon', 16601, 0, NULL),
(204, 'desert_de_l_egeon', 16602, 0, NULL),
(205, 'desert_de_l_egeon', 16603, 0, NULL),
(206, 'desert_de_l_egeon', 16604, 0, NULL),
(207, 'desert_de_l_egeon', 16605, 0, NULL),
(208, 'desert_de_l_egeon', 16606, 0, NULL),
(209, 'desert_de_l_egeon', 16607, 0, NULL),
(210, 'desert_de_l_egeon', 16608, 0, NULL),
(211, 'desert_de_l_egeon', 16609, 0, NULL),
(212, 'desert_de_l_egeon', 16610, 0, NULL),
(213, 'desert_de_l_egeon', 16611, 0, NULL),
(214, 'desert_de_l_egeon', 16612, 0, NULL),
(215, 'desert_de_l_egeon', 16613, 0, NULL),
(216, 'desert_de_l_egeon', 16614, 0, NULL),
(217, 'desert_de_l_egeon', 16615, 0, NULL),
(218, 'desert_de_l_egeon', 16616, 0, NULL),
(219, 'desert_de_l_egeon', 16617, 0, NULL),
(220, 'desert_de_l_egeon', 16618, 0, NULL),
(221, 'desert_de_l_egeon', 16619, 0, NULL),
(222, 'desert_de_l_egeon', 16620, 0, NULL),
(223, 'desert_de_l_egeon', 16621, 0, NULL),
(224, 'desert_de_l_egeon', 16622, 0, NULL),
(225, 'desert_de_l_egeon', 16623, 0, NULL),
(226, 'desert_de_l_egeon', 16624, 0, NULL),
(227, 'desert_de_l_egeon', 16625, 0, NULL),
(228, 'desert_de_l_egeon', 16626, 0, NULL),
(229, 'desert_de_l_egeon', 16627, 0, NULL),
(230, 'desert_de_l_egeon', 16628, 0, NULL),
(231, 'desert_de_l_egeon', 16629, 0, NULL),
(232, 'desert_de_l_egeon', 16630, 0, NULL),
(233, 'desert_de_l_egeon', 16631, 0, NULL),
(234, 'desert_de_l_egeon', 16632, 0, NULL),
(235, 'desert_de_l_egeon', 16633, 0, NULL),
(236, 'desert_de_l_egeon', 16634, 0, NULL),
(237, 'desert_de_l_egeon', 16635, 0, NULL),
(238, 'desert_de_l_egeon', 16636, 0, NULL),
(239, 'desert_de_l_egeon', 16637, 0, NULL),
(240, 'desert_de_l_egeon', 15576, 0, NULL),
(241, 'desert_de_l_egeon', 15573, 0, NULL),
(242, 'desert_de_l_egeon', 15573, 0, NULL),
(243, 'desert_de_l_egeon', 15567, 0, NULL),
(244, 'desert_de_l_egeon', 16638, 0, NULL),
(245, 'desert_de_l_egeon', 15564, 0, NULL),
(246, 'desert_de_l_egeon', 16639, 0, NULL),
(247, 'desert_de_l_egeon', 16640, 0, NULL),
(248, 'desert_de_l_egeon', 15565, 0, NULL),
(249, 'desert_de_l_egeon', 15566, 0, NULL),
(250, 'desert_de_l_egeon', 16641, 0, NULL),
(251, 'desert_de_l_egeon', 16642, 0, NULL),
(252, 'desert_de_l_egeon', 16643, 0, NULL),
(253, 'desert_de_l_egeon', 16644, 0, NULL),
(254, 'desert_de_l_egeon', 16645, 0, NULL),
(255, 'desert_de_l_egeon', 16646, 0, NULL),
(256, 'desert_de_l_egeon', 16647, 0, NULL),
(257, 'desert_de_l_egeon', 16648, 0, NULL),
(258, 'desert_de_l_egeon', 16649, 0, NULL),
(259, 'desert_de_l_egeon', 16650, 0, NULL),
(260, 'desert_de_l_egeon', 16651, 0, NULL),
(261, 'desert_de_l_egeon', 16652, 0, NULL),
(262, 'desert_de_l_egeon', 16653, 0, NULL),
(263, 'desert_de_l_egeon', 16654, 0, NULL),
(264, 'desert_de_l_egeon', 16655, 0, NULL),
(265, 'desert_de_l_egeon', 16656, 0, NULL),
(266, 'desert_de_l_egeon', 16660, 0, NULL),
(267, 'desert_de_l_egeon', 16661, 0, NULL),
(268, 'desert_de_l_egeon', 16662, 0, NULL),
(269, 'desert_de_l_egeon', 16663, 0, NULL),
(270, 'desert_de_l_egeon', 16665, 0, NULL),
(271, 'desert_de_l_egeon', 16666, 0, NULL),
(272, 'desert_de_l_egeon', 16667, 0, NULL),
(273, 'desert_de_l_egeon', 16668, 0, NULL),
(274, 'desert_de_l_egeon', 16669, 0, NULL),
(275, 'desert_de_l_egeon', 16671, 0, NULL),
(276, 'desert_de_l_egeon', 16672, 0, NULL),
(277, 'desert_de_l_egeon', 16673, 0, NULL),
(278, 'desert_de_l_egeon', 16674, 0, NULL),
(279, 'desert_de_l_egeon', 16675, 0, NULL),
(280, 'desert_de_l_egeon', 16676, 0, NULL),
(281, 'desert_de_l_egeon', 16677, 0, NULL),
(282, 'desert_de_l_egeon', 16678, 0, NULL),
(283, 'desert_de_l_egeon', 16679, 0, NULL),
(284, 'desert_de_l_egeon', 16680, 0, NULL),
(285, 'desert_de_l_egeon', 16681, 0, NULL),
(286, 'desert_de_l_egeon', 16682, 0, NULL),
(287, 'desert_de_l_egeon', 16683, 0, NULL),
(288, 'desert_de_l_egeon', 16684, 0, NULL),
(289, 'desert_de_l_egeon', 16685, 0, NULL),
(290, 'desert_de_l_egeon', 16686, 0, NULL),
(291, 'desert_de_l_egeon', 16687, 0, NULL),
(292, 'desert_de_l_egeon', 16688, 0, NULL),
(293, 'desert_de_l_egeon', 16689, 0, NULL),
(294, 'desert_de_l_egeon', 16690, 0, NULL),
(295, 'desert_de_l_egeon', 16692, 0, NULL),
(296, 'desert_de_l_egeon', 16704, 0, NULL),
(297, 'desert_de_l_egeon', 16706, 0, NULL),
(298, 'desert_de_l_egeon', 16708, 0, NULL),
(299, 'desert_de_l_egeon', 16709, 0, NULL),
(300, 'desert_de_l_egeon', 16710, 0, NULL),
(301, 'desert_de_l_egeon', 16712, 0, NULL),
(302, 'desert_de_l_egeon', 16713, 0, NULL),
(303, 'desert_de_l_egeon', 16714, 0, NULL),
(304, 'desert_de_l_egeon', 16715, 0, NULL),
(305, 'desert_de_l_egeon', 16716, 0, NULL),
(306, 'desert_de_l_egeon', 16717, 0, NULL),
(307, 'desert_de_l_egeon', 16719, 0, NULL),
(308, 'desert_de_l_egeon', 16720, 0, NULL),
(309, 'desert_de_l_egeon', 16722, 0, NULL),
(310, 'desert_de_l_egeon', 16723, 0, NULL),
(311, 'desert_de_l_egeon', 16724, 0, NULL),
(312, 'desert_de_l_egeon', 16725, 0, NULL),
(313, 'desert_de_l_egeon', 16726, 0, NULL),
(314, 'desert_de_l_egeon', 16727, 0, NULL),
(315, 'desert_de_l_egeon', 16728, 0, NULL),
(316, 'desert_de_l_egeon', 16729, 0, NULL),
(317, 'desert_de_l_egeon', 16730, 0, NULL),
(318, 'desert_de_l_egeon', 16730, 0, NULL),
(319, 'desert_de_l_egeon', 16731, 0, NULL),
(320, 'desert_de_l_egeon', 16732, 0, NULL),
(321, 'desert_de_l_egeon', 16733, 0, NULL),
(322, 'desert_de_l_egeon', 16734, 0, NULL),
(323, 'desert_de_l_egeon', 16735, 0, NULL),
(324, 'desert_de_l_egeon', 16736, 0, NULL),
(325, 'desert_de_l_egeon', 16737, 0, NULL),
(326, 'desert_de_l_egeon', 16738, 0, NULL),
(327, 'desert_de_l_egeon', 16739, 0, NULL),
(328, 'desert_de_l_egeon', 16740, 0, NULL),
(329, 'desert_de_l_egeon', 16741, 0, NULL),
(330, 'desert_de_l_egeon', 16742, 0, NULL),
(331, 'desert_de_l_egeon', 16743, 0, NULL),
(332, 'desert_de_l_egeon', 16744, 0, NULL),
(333, 'desert_de_l_egeon', 16745, 0, NULL),
(334, 'desert_de_l_egeon', 16745, 0, NULL),
(335, 'desert_de_l_egeon', 16746, 0, NULL),
(336, 'desert_de_l_egeon', 16747, 0, NULL),
(337, 'desert_de_l_egeon', 16748, 0, NULL),
(338, 'desert_de_l_egeon', 16749, 0, NULL),
(339, 'desert_de_l_egeon', 16750, 0, NULL),
(340, 'desert_de_l_egeon', 16751, 0, NULL),
(341, 'desert_de_l_egeon', 16752, 0, NULL),
(342, 'desert_de_l_egeon', 16753, 0, NULL),
(343, 'desert_de_l_egeon', 16754, 0, NULL),
(344, 'desert_de_l_egeon', 16755, 0, NULL),
(345, 'desert_de_l_egeon', 16756, 0, NULL),
(346, 'desert_de_l_egeon', 16757, 0, NULL),
(347, 'desert_de_l_egeon', 16758, 0, NULL),
(348, 'desert_de_l_egeon', 16759, 0, NULL),
(349, 'desert_de_l_egeon', 16760, 0, NULL),
(350, 'desert_de_l_egeon', 15568, 0, NULL),
(351, 'desert_de_l_egeon', 16761, 0, NULL),
(352, 'desert_de_l_egeon', 16762, 0, NULL),
(353, 'desert_de_l_egeon', 16763, 0, NULL),
(354, 'desert_de_l_egeon', 16765, 0, NULL),
(355, 'desert_de_l_egeon', 16767, 0, NULL),
(356, 'desert_de_l_egeon', 16768, 0, NULL),
(357, 'desert_de_l_egeon', 16769, 0, NULL),
(358, 'desert_de_l_egeon', 16770, 0, NULL),
(359, 'desert_de_l_egeon', 16771, 0, NULL),
(360, 'desert_de_l_egeon', 16772, 0, NULL),
(361, 'desert_de_l_egeon', 16777, 0, NULL),
(362, 'desert_de_l_egeon', 16779, 0, NULL),
(363, 'desert_de_l_egeon', 16780, 0, NULL),
(364, 'desert_de_l_egeon', 16781, 0, NULL),
(365, 'desert_de_l_egeon', 16782, 0, NULL),
(366, 'desert_de_l_egeon', 16783, 0, NULL),
(367, 'desert_de_l_egeon', 16784, 0, NULL),
(368, 'desert_de_l_egeon', 16785, 0, NULL),
(369, 'desert_de_l_egeon', 16786, 0, NULL),
(370, 'desert_de_l_egeon', 16787, 0, NULL),
(371, 'desert_de_l_egeon', 16788, 0, NULL),
(372, 'desert_de_l_egeon', 16789, 0, NULL),
(373, 'desert_de_l_egeon', 16790, 0, NULL),
(374, 'desert_de_l_egeon', 16791, 0, NULL),
(375, 'desert_de_l_egeon', 16792, 0, NULL),
(376, 'desert_de_l_egeon', 16793, 0, NULL),
(377, 'desert_de_l_egeon', 16794, 0, NULL),
(378, 'desert_de_l_egeon', 16795, 0, NULL),
(379, 'desert_de_l_egeon', 16796, 0, NULL),
(380, 'desert_de_l_egeon', 16798, 0, NULL),
(381, 'desert_de_l_egeon', 16799, 0, NULL),
(382, 'desert_de_l_egeon', 16799, 0, NULL),
(383, 'desert_de_l_egeon', 16800, 0, NULL),
(384, 'desert_de_l_egeon', 16801, 0, NULL),
(385, 'desert_de_l_egeon', 16802, 0, NULL),
(386, 'desert_de_l_egeon', 16803, 0, NULL),
(387, 'desert_de_l_egeon', 16804, 0, NULL),
(388, 'desert_de_l_egeon', 16805, 0, NULL),
(389, 'desert_de_l_egeon', 16807, 0, NULL),
(390, 'desert_de_l_egeon', 16809, 0, NULL),
(391, 'desert_de_l_egeon', 16810, 0, NULL),
(392, 'desert_de_l_egeon', 16817, 0, NULL),
(393, 'desert_de_l_egeon', 16818, 0, NULL),
(394, 'desert_de_l_egeon', 16819, 0, NULL),
(395, 'desert_de_l_egeon', 16820, 0, NULL),
(396, 'desert_de_l_egeon', 16821, 0, NULL),
(397, 'desert_de_l_egeon', 16822, 0, NULL),
(398, 'desert_de_l_egeon', 16823, 0, NULL),
(399, 'desert_de_l_egeon', 16821, 0, NULL),
(400, 'desert_de_l_egeon', 16824, 0, NULL),
(401, 'desert_de_l_egeon', 16825, 0, NULL),
(402, 'desert_de_l_egeon', 16826, 0, NULL),
(403, 'desert_de_l_egeon', 16827, 0, NULL),
(404, 'desert_de_l_egeon', 16827, 0, NULL),
(405, 'desert_de_l_egeon', 16827, 0, NULL),
(406, 'desert_de_l_egeon', 16828, 0, NULL),
(407, 'desert_de_l_egeon', 16829, 0, NULL),
(408, 'desert_de_l_egeon', 16830, 0, NULL),
(409, 'desert_de_l_egeon', 16830, 0, NULL),
(410, 'desert_de_l_egeon', 16831, 0, NULL),
(411, 'desert_de_l_egeon', 16832, 0, NULL),
(412, 'desert_de_l_egeon', 16818, 0, NULL),
(413, 'desert_de_l_egeon', 16833, 0, NULL),
(414, 'desert_de_l_egeon', 16834, 0, NULL),
(415, 'desert_de_l_egeon', 16834, 0, NULL),
(416, 'desert_de_l_egeon', 16834, 0, NULL),
(417, 'desert_de_l_egeon', 16835, 0, NULL),
(418, 'desert_de_l_egeon', 16834, 0, NULL),
(419, 'desert_de_l_egeon', 16836, 0, NULL),
(420, 'desert_de_l_egeon', 16837, 0, NULL),
(421, 'desert_de_l_egeon', 16838, 0, NULL),
(422, 'desert_de_l_egeon', 16838, 0, NULL),
(423, 'desert_de_l_egeon', 16839, 0, NULL),
(424, 'desert_de_l_egeon', 16840, 0, NULL),
(425, 'desert_de_l_egeon', 16840, 0, NULL),
(426, 'desert_de_l_egeon', 16841, 0, NULL),
(427, 'desert_de_l_egeon', 16842, 0, NULL),
(428, 'desert_de_l_egeon', 16843, 0, NULL),
(429, 'desert_de_l_egeon', 16844, 0, NULL),
(430, 'desert_de_l_egeon', 16845, 0, NULL),
(431, 'desert_de_l_egeon', 16846, 0, NULL),
(432, 'desert_de_l_egeon', 16847, 0, NULL),
(433, 'desert_de_l_egeon', 16848, 0, NULL),
(434, 'desert_de_l_egeon', 16849, 0, NULL),
(435, 'desert_de_l_egeon', 16850, 0, NULL),
(436, 'desert_de_l_egeon', 16851, 0, NULL),
(437, 'desert_de_l_egeon', 16852, 0, NULL),
(438, 'desert_de_l_egeon', 16853, 0, NULL),
(439, 'desert_de_l_egeon', 16854, 0, NULL),
(440, 'desert_de_l_egeon', 16855, 0, NULL),
(441, 'desert_de_l_egeon', 16856, 0, NULL),
(442, 'desert_de_l_egeon', 16857, 0, NULL),
(443, 'desert_de_l_egeon', 16858, 0, NULL),
(444, 'desert_de_l_egeon', 16859, 0, NULL),
(445, 'desert_de_l_egeon', 16860, 0, NULL),
(446, 'desert_de_l_egeon', 16861, 0, NULL),
(447, 'desert_de_l_egeon', 16862, 0, NULL),
(448, 'desert_de_l_egeon', 16863, 0, NULL),
(449, 'desert_de_l_egeon', 16864, 0, NULL),
(450, 'desert_de_l_egeon', 16862, 0, NULL),
(451, 'desert_de_l_egeon', 16865, 0, NULL),
(452, 'desert_de_l_egeon', 16864, 0, NULL),
(453, 'desert_de_l_egeon', 16866, 0, NULL),
(454, 'desert_de_l_egeon', 16867, 0, NULL),
(455, 'desert_de_l_egeon', 16868, 0, NULL),
(456, 'desert_de_l_egeon', 16869, 0, NULL),
(457, 'desert_de_l_egeon', 16870, 0, NULL),
(458, 'desert_de_l_egeon', 16617, 0, NULL),
(459, 'desert_de_l_egeon', 16871, 0, NULL),
(460, 'desert_de_l_egeon', 16872, 0, NULL),
(461, 'desert_de_l_egeon', 16872, 0, NULL),
(462, 'desert_de_l_egeon', 16873, 0, NULL),
(463, 'desert_de_l_egeon', 16874, 0, NULL),
(464, 'desert_de_l_egeon', 16875, 0, NULL),
(465, 'desert_de_l_egeon', 16876, 0, NULL),
(466, 'desert_de_l_egeon', 16877, 0, NULL),
(467, 'desert_de_l_egeon', 16878, 0, NULL),
(468, 'desert_de_l_egeon', 16879, 0, NULL),
(469, 'desert_de_l_egeon', 16880, 0, NULL),
(470, 'desert_de_l_egeon', 16881, 0, NULL),
(471, 'desert_de_l_egeon', 16882, 0, NULL),
(472, 'desert_de_l_egeon', 16883, 0, NULL),
(473, 'desert_de_l_egeon', 16885, 0, NULL),
(474, 'carreaux', 15504, 0, NULL),
(475, 'carreaux', 15505, 0, NULL),
(476, 'carreaux', 15506, 0, NULL),
(477, 'carreaux', 15507, 0, NULL),
(478, 'carreaux', 15460, 0, NULL),
(479, 'carreaux', 15459, 0, NULL),
(480, 'carreaux', 15458, 0, NULL),
(481, 'carreaux', 15503, 0, NULL),
(482, 'carreaux', 15518, 0, NULL),
(483, 'carreaux', 15461, 0, NULL),
(484, 'carreaux', 15462, 0, NULL),
(485, 'carreaux', 15464, 0, NULL),
(486, 'carreaux', 15465, 0, NULL),
(487, 'carreaux', 15508, 0, NULL),
(488, 'carreaux', 15509, 0, NULL),
(489, 'carreaux', 15466, 0, NULL),
(490, 'carreaux', 15510, 0, NULL),
(491, 'carreaux', 15467, 0, NULL),
(492, 'carreaux', 15468, 0, NULL),
(493, 'carreaux', 15511, 0, NULL),
(494, 'carreaux', 15477, 0, NULL),
(495, 'carreaux', 15512, 0, NULL),
(496, 'carreaux', 15513, 0, NULL),
(497, 'carreaux', 15514, 0, NULL),
(498, 'carreaux', 15476, 0, NULL),
(499, 'carreaux', 15475, 0, NULL),
(500, 'carreaux', 15515, 0, NULL),
(501, 'carreaux', 15471, 0, NULL),
(502, 'carreaux', 15474, 0, NULL),
(503, 'carreaux', 15516, 0, NULL),
(504, 'carreaux', 15517, 0, NULL),
(505, 'carreaux', 15473, 0, NULL),
(506, 'caverne', 35010, 0, NULL),
(507, 'caverne', 35011, 0, NULL),
(508, 'caverne', 35012, 0, NULL),
(509, 'caverne', 35013, 0, NULL),
(510, 'caverne', 35014, 0, NULL),
(511, 'caverne', 35015, 0, NULL),
(512, 'caverne', 35016, 0, NULL),
(513, 'caverne', 35017, 0, NULL),
(514, 'caverne', 35018, 0, NULL),
(515, 'caverne', 35019, 0, NULL),
(516, 'caverne', 35020, 0, NULL),
(517, 'caverne', 35021, 0, NULL),
(518, 'caverne', 35022, 0, NULL),
(519, 'caverne', 35024, 0, NULL),
(520, 'caverne', 35026, 0, NULL),
(521, 'caverne', 35027, 0, NULL),
(522, 'caverne', 35028, 0, NULL),
(523, 'caverne', 35029, 0, NULL),
(524, 'caverne', 35030, 0, NULL),
(525, 'caverne', 35031, 0, NULL),
(526, 'caverne', 35033, 0, NULL),
(527, 'caverne', 35034, 0, NULL),
(528, 'caverne', 35036, 0, NULL),
(529, 'caverne', 35037, 0, NULL),
(530, 'caverne', 35039, 0, NULL),
(531, 'caverne', 35040, 0, NULL),
(532, 'caverne', 35041, 0, NULL),
(533, 'caverne', 35042, 0, NULL),
(534, 'caverne', 35043, 0, NULL),
(535, 'caverne', 35044, 0, NULL),
(536, 'caverne', 35045, 0, NULL),
(537, 'caverne', 35046, 0, NULL),
(538, 'caverne', 35047, 0, NULL),
(539, 'caverne', 35050, 0, NULL),
(540, 'caverne', 35053, 0, NULL),
(541, 'caverne', 35055, 0, NULL),
(542, 'caverne', 35057, 0, NULL),
(543, 'caverne', 35058, 0, NULL),
(544, 'caverne', 35059, 0, NULL),
(545, 'caverne', 35060, 0, NULL),
(546, 'caverne', 35061, 0, NULL),
(547, 'caverne', 35062, 0, NULL),
(548, 'caverne', 35063, 0, NULL),
(549, 'caverne', 35065, 0, NULL),
(550, 'caverne', 35066, 0, NULL),
(551, 'caverne', 35067, 0, NULL),
(552, 'caverne', 35069, 0, NULL),
(553, 'caverne', 35070, 0, NULL),
(554, 'caverne', 35073, 0, NULL),
(555, 'caverne', 35074, 0, NULL),
(556, 'caverne', 35075, 0, NULL),
(557, 'caverne', 35076, 0, NULL),
(558, 'caverne', 35077, 0, NULL),
(559, 'caverne', 35078, 0, NULL),
(560, 'caverne', 35079, 0, NULL),
(561, 'caverne', 35080, 0, NULL),
(562, 'caverne', 35081, 0, NULL),
(563, 'caverne', 35082, 0, NULL),
(564, 'caverne', 35083, 0, NULL),
(565, 'caverne', 35084, 0, NULL),
(566, 'caverne', 35085, 0, NULL),
(567, 'caverne', 35086, 0, NULL),
(568, 'caverne', 35089, 0, NULL),
(569, 'caverne', 35090, 0, NULL),
(570, 'caverne', 35091, 0, NULL),
(571, 'caverne', 35092, 0, NULL),
(572, 'caverne', 35093, 0, NULL),
(573, 'caverne', 35096, 0, NULL),
(574, 'caverne', 35099, 0, NULL),
(575, 'caverne', 35100, 0, NULL),
(576, 'caverne', 35101, 0, NULL),
(577, 'caverne', 35102, 0, NULL),
(578, 'caverne', 35103, 0, NULL),
(579, 'caverne', 35104, 0, NULL),
(580, 'caverne', 35105, 0, NULL),
(581, 'caverne', 35107, 0, NULL),
(582, 'caverne', 35108, 0, NULL),
(583, 'caverne', 35109, 0, NULL),
(584, 'caverne', 35110, 0, NULL),
(585, 'caverne', 35111, 0, NULL),
(586, 'caverne', 35113, 0, NULL),
(587, 'caverne', 35114, 0, NULL),
(588, 'caverne', 35116, 0, NULL),
(589, 'caverne', 35118, 0, NULL),
(590, 'caverne', 35038, 0, NULL),
(591, 'rune1', 35025, 0, NULL),
(592, 'caverne', 35121, 0, NULL),
(593, 'caverne', 35122, 0, NULL),
(594, 'caverne', 35123, 0, NULL),
(595, 'caverne', 35124, 0, NULL),
(596, 'caverne', 35125, 0, NULL),
(597, 'caverne', 35126, 0, NULL),
(598, 'caverne', 35127, 0, NULL),
(599, 'caverne', 35128, 0, NULL),
(600, 'caverne', 35129, 0, NULL),
(601, 'caverne', 35130, 0, NULL),
(602, 'caverne', 35131, 0, NULL),
(603, 'caverne', 35132, 0, NULL),
(604, 'caverne', 35133, 0, NULL),
(605, 'caverne', 35134, 0, NULL),
(606, 'caverne', 35135, 0, NULL),
(607, 'caverne', 35136, 0, NULL),
(608, 'caverne', 35137, 0, NULL),
(609, 'caverne', 35138, 0, NULL),
(610, 'caverne', 35117, 0, NULL),
(611, 'caverne', 35023, 0, NULL),
(612, 'caverne', 35035, 0, NULL),
(613, 'caverne', 35052, 0, NULL),
(614, 'caverne', 35072, 0, NULL),
(615, 'caverne', 35051, 0, NULL),
(616, 'caverne', 35097, 0, NULL),
(617, 'caverne', 35098, 0, NULL),
(618, 'caverne', 35071, 0, NULL),
(619, 'caverne', 35115, 0, NULL),
(620, 'caverne', 35094, 0, NULL),
(621, 'caverne', 35032, 0, NULL),
(622, 'caverne', 35048, 0, NULL),
(623, 'caverne', 35068, 0, NULL),
(624, 'caverne', 35049, 0, NULL),
(625, 'caverne', 35095, 0, NULL),
(626, 'caverne', 35112, 0, NULL),
(627, 'caverne', 35120, 0, NULL),
(628, 'caverne', 35119, 0, NULL),
(629, 'caverne', 35150, 0, NULL),
(630, 'caverne', 35149, 0, NULL),
(631, 'caverne', 35148, 0, NULL),
(632, 'caverne', 35147, 0, NULL),
(633, 'caverne', 35146, 0, NULL),
(634, 'caverne', 35145, 0, NULL),
(635, 'caverne', 35144, 0, NULL),
(636, 'caverne', 35143, 0, NULL),
(637, 'caverne', 35142, 0, NULL),
(638, 'caverne', 35141, 0, NULL),
(639, 'caverne', 35140, 0, NULL),
(640, 'caverne', 35139, 0, NULL),
(641, 'caverne', 35106, 0, NULL),
(642, 'fefnir', 35096, 0, NULL),
(643, 'fefnir', 35095, 0, NULL),
(644, 'caverne', 35064, 0, NULL),
(645, 'caverne', 35087, 0, NULL),
(646, 'caverne', 35088, 0, NULL),
(647, 'caverne', 35025, 0, NULL),
(648, 'rune10', 35025, 0, NULL),
(649, 'caverne', 35054, 0, NULL),
(650, 'caverne', 35056, 0, NULL),
(651, 'rune15', 35056, 0, NULL),
(652, 'rune9', 35054, 0, NULL),
(653, 'desert_de_l_egeon', 37475, 0, NULL),
(654, 'desert_de_l_egeon', 37481, 0, NULL),
(655, 'desert_de_l_egeon', 37482, 0, NULL),
(656, 'desert_de_l_egeon', 37483, 0, NULL),
(657, 'desert_de_l_egeon', 37484, 0, NULL),
(658, 'desert_de_l_egeon', 37485, 0, NULL),
(659, 'desert_de_l_egeon', 37486, 0, NULL),
(660, 'desert_de_l_egeon', 37487, 0, NULL),
(661, 'desert_de_l_egeon', 37488, 0, NULL),
(662, 'desert_de_l_egeon', 37489, 0, NULL),
(663, 'desert_de_l_egeon', 37476, 0, NULL),
(664, 'desert_de_l_egeon', 37477, 0, NULL),
(665, 'lac_thetis', 37490, 0, NULL),
(666, 'lac_thetis', 37491, 0, NULL),
(667, 'lac_thetis', 37492, 0, NULL),
(668, 'lac_thetis', 37493, 0, NULL),
(669, 'lac_thetis', 37494, 0, NULL),
(670, 'lac_thetis', 37495, 0, NULL),
(671, 'lac_thetis', 37496, 0, NULL),
(672, 'lac_thetis', 37497, 0, NULL),
(673, 'lac_thetis', 37498, 0, NULL),
(674, 'lac_thetis', 37499, 0, NULL),
(675, 'lac_thetis', 37500, 0, NULL),
(676, 'lac_thetis', 37501, 0, NULL),
(677, 'lac_thetis', 37502, 0, NULL),
(678, 'lac_thetis', 37503, 0, NULL),
(679, 'lac_thetis', 37504, 0, NULL),
(680, 'desert_de_l_egeon', 37480, 0, NULL),
(681, 'desert_de_l_egeon', 37478, 0, NULL),
(682, 'desert_de_l_egeon', 37479, 0, NULL),
(683, 'carreaux', 16999, 0, NULL),
(684, 'carreaux', 17000, 0, NULL),
(685, 'carreaux', 17001, 0, NULL),
(686, 'carreaux', 17002, 0, NULL),
(687, 'carreaux', 17003, 0, NULL),
(688, 'carreaux', 17004, 0, NULL),
(689, 'carreaux', 17005, 0, NULL),
(690, 'carreaux', 17006, 0, NULL),
(691, 'carreaux', 17007, 0, NULL),
(692, 'carreaux', 17008, 0, NULL),
(693, 'carreaux', 17009, 0, NULL),
(694, 'carreaux', 15318, 0, NULL),
(695, 'carreaux', 17010, 0, NULL),
(696, 'carreaux', 17011, 0, NULL),
(697, 'carreaux', 17012, 0, NULL),
(698, 'carreaux', 17013, 0, NULL),
(699, 'carreaux', 17014, 0, NULL),
(700, 'carreaux', 17015, 0, NULL),
(701, 'carreaux', 17016, 0, NULL),
(702, 'carreaux', 17017, 0, NULL),
(703, 'carreaux', 17018, 0, NULL),
(704, 'carreaux', 17019, 0, NULL),
(705, 'carreaux', 17020, 0, NULL),
(706, 'carreaux', 17021, 0, NULL),
(707, 'pit', 16998, 0, NULL),
(708, 'route', 17037, 0, NULL),
(709, 'route', 17098, 0, NULL),
(710, 'route', 17099, 0, NULL),
(711, 'route', 17100, 0, NULL),
(712, 'route', 17101, 0, NULL),
(713, 'route', 17102, 0, NULL),
(714, 'route', 17103, 0, NULL),
(715, 'route', 17104, 0, NULL),
(716, 'route', 17105, 0, NULL),
(717, 'route', 17106, 0, NULL),
(718, 'route', 17107, 0, NULL),
(719, 'route', 17108, 0, NULL),
(720, 'route', 17109, 0, NULL),
(721, 'route', 17110, 0, NULL),
(722, 'route', 17111, 0, NULL),
(723, 'route', 17112, 0, NULL),
(724, 'route', 17113, 0, NULL),
(725, 'route', 17114, 0, NULL),
(726, 'route', 17115, 0, NULL),
(727, 'route', 17116, 0, NULL),
(728, 'route', 17117, 0, NULL),
(729, 'route', 17118, 0, NULL),
(730, 'route', 17119, 0, NULL),
(731, 'route', 17120, 0, NULL),
(732, 'route', 17121, 0, NULL),
(733, 'route', 17122, 0, NULL),
(734, 'route', 17123, 0, NULL),
(735, 'route', 17124, 0, NULL),
(736, 'route', 17125, 0, NULL),
(737, 'route', 17126, 0, NULL),
(738, 'route', 17127, 0, NULL),
(739, 'route', 17128, 0, NULL),
(740, 'route', 17129, 0, NULL),
(741, 'route', 17130, 0, NULL),
(742, 'route', 17131, 0, NULL),
(743, 'route', 17132, 0, NULL),
(744, 'route', 17133, 0, NULL),
(745, 'route', 17134, 0, NULL),
(746, 'route', 17135, 0, NULL),
(747, 'route', 17136, 0, NULL),
(748, 'route', 17144, 0, NULL),
(749, 'route', 17145, 0, NULL),
(750, 'route', 17146, 0, NULL),
(751, 'route', 17147, 0, NULL),
(752, 'carreaux', 17149, 0, NULL),
(753, 'carreaux', 17150, 0, NULL),
(754, 'carreaux', 17151, 0, NULL),
(755, 'carreaux', 17152, 0, NULL),
(756, 'carreaux', 17153, 0, NULL),
(757, 'carreaux', 17154, 0, NULL),
(758, 'carreaux', 17155, 0, NULL),
(759, 'carreaux', 17156, 0, NULL),
(760, 'carreaux', 17157, 0, NULL),
(761, 'carreaux', 17158, 0, NULL),
(762, 'carreaux', 17159, 0, NULL),
(763, 'carreaux', 17160, 0, NULL),
(764, 'carreaux', 17161, 0, NULL),
(765, 'carreaux', 17162, 0, NULL),
(766, 'carreaux', 17163, 0, NULL),
(767, 'carreaux', 17164, 0, NULL),
(768, 'carreaux', 17165, 0, NULL),
(769, 'carreaux', 17166, 0, NULL),
(770, 'carreaux', 17167, 0, NULL),
(771, 'carreaux', 17168, 0, NULL),
(772, 'carreaux', 17169, 0, NULL),
(773, 'carreaux', 17170, 0, NULL),
(774, 'carreaux', 17171, 0, NULL),
(775, 'carreaux', 17172, 0, NULL),
(776, 'carreaux', 17173, 0, NULL),
(777, 'carreaux', 17174, 0, NULL),
(778, 'carreaux', 17175, 0, NULL),
(779, 'carreaux', 17176, 0, NULL),
(780, 'carreaux', 17177, 0, NULL),
(781, 'carreaux', 17178, 0, NULL),
(782, 'carreaux', 17179, 0, NULL),
(783, 'carreaux', 17180, 0, NULL),
(784, 'carreaux', 17181, 0, NULL),
(785, 'carreaux', 17182, 0, NULL),
(786, 'carreaux', 17183, 0, NULL),
(787, 'carreaux', 17184, 0, NULL),
(788, 'carreaux', 17185, 0, NULL),
(789, 'carreaux', 17186, 0, NULL),
(790, 'carreaux', 17187, 0, NULL),
(791, 'carreaux', 17188, 0, NULL),
(792, 'carreaux', 17189, 0, NULL),
(793, 'carreaux', 17190, 0, NULL),
(794, 'carreaux', 17191, 0, NULL),
(795, 'carreaux', 17192, 0, NULL),
(796, 'carreaux', 17193, 0, NULL),
(797, 'carreaux', 17194, 0, NULL),
(798, 'carreaux', 17195, 0, NULL),
(799, 'carreaux', 17196, 0, NULL),
(800, 'carreaux', 17197, 0, NULL),
(801, 'carreaux', 17198, 0, NULL),
(802, 'carreaux', 17199, 0, NULL),
(803, 'carreaux', 17200, 0, NULL),
(804, 'carreaux', 17201, 0, NULL),
(805, 'carreaux', 17202, 0, NULL),
(806, 'carreaux', 17203, 0, NULL),
(807, 'carreaux', 17204, 0, NULL),
(808, 'carreaux', 17205, 0, NULL),
(809, 'carreaux', 17206, 0, NULL),
(810, 'carreaux', 17207, 0, NULL),
(811, 'carreaux', 17208, 0, NULL),
(812, 'carreaux', 17209, 0, NULL),
(813, 'carreaux', 17210, 0, NULL),
(814, 'carreaux', 17211, 0, NULL),
(815, 'carreaux', 17212, 0, NULL),
(816, 'carreaux', 17213, 0, NULL),
(817, 'carreaux', 17214, 0, NULL),
(818, 'carreaux', 17215, 0, NULL),
(819, 'carreaux', 17216, 0, NULL),
(820, 'eryn_dolen', 17223, 0, NULL),
(821, 'eryn_dolen', 17224, 0, NULL),
(822, 'eryn_dolen', 17225, 0, NULL),
(823, 'eryn_dolen', 17226, 0, NULL),
(824, 'eryn_dolen', 17227, 0, NULL),
(825, 'eryn_dolen', 17229, 0, NULL),
(826, 'eryn_dolen', 17219, 0, NULL),
(827, 'eryn_dolen', 17230, 0, NULL),
(828, 'eryn_dolen', 17231, 0, NULL),
(829, 'eryn_dolen', 17232, 0, NULL),
(830, 'eryn_dolen', 17233, 0, NULL),
(831, 'eryn_dolen', 17220, 0, NULL),
(832, 'eryn_dolen', 17234, 0, NULL),
(833, 'eryn_dolen', 17235, 0, NULL),
(834, 'eryn_dolen', 17236, 0, NULL),
(835, 'eryn_dolen', 17237, 0, NULL),
(836, 'eryn_dolen', 17241, 0, NULL),
(837, 'eryn_dolen', 17242, 0, NULL),
(838, 'lac_pegasus', 17243, 0, NULL),
(839, 'lac_pegasus', 17244, 0, NULL),
(840, 'lac_pegasus', 17245, 0, NULL),
(841, 'lac_pegasus', 17246, 0, NULL),
(842, 'lac_pegasus', 17218, 0, NULL),
(843, 'lac_pegasus', 17247, 0, NULL),
(844, 'lac_pegasus', 17248, 0, NULL),
(845, 'lac_pegasus', 17249, 0, NULL),
(846, 'lac_pegasus', 17250, 0, NULL),
(847, 'lac_pegasus', 17251, 0, NULL),
(848, 'lac_pegasus', 17252, 0, NULL),
(849, 'lac_pegasus', 17253, 0, NULL),
(850, 'lac_pegasus', 17254, 0, NULL),
(851, 'lac_pegasus', 17255, 0, NULL),
(852, 'lac_pegasus', 17217, 0, NULL),
(853, 'lac_pegasus', 17256, 0, NULL),
(854, 'lac_pegasus', 17257, 0, NULL),
(855, 'lac_pegasus', 17258, 0, NULL),
(856, 'lac_pegasus', 17259, 0, NULL),
(857, 'lac_pegasus', 17260, 0, NULL),
(858, 'lac_pegasus', 17261, 0, NULL),
(859, 'lac_pegasus', 17262, 0, NULL),
(860, 'eryn_dolen', 17263, 0, NULL),
(861, 'eryn_dolen', 17264, 0, NULL),
(862, 'eryn_dolen', 17265, 0, NULL),
(863, 'eryn_dolen', 17266, 0, NULL),
(864, 'eryn_dolen', 17267, 0, NULL),
(865, 'eryn_dolen', 17269, 0, NULL),
(866, 'eryn_dolen', 17270, 0, NULL),
(867, 'eryn_dolen', 17271, 0, NULL),
(868, 'eryn_dolen', 17272, 0, NULL),
(869, 'eryn_dolen', 17273, 0, NULL),
(870, 'eryn_dolen', 17274, 0, NULL),
(871, 'eryn_dolen', 17275, 0, NULL),
(872, 'eryn_dolen', 17277, 0, NULL),
(873, 'eryn_dolen', 17278, 0, NULL),
(874, 'eryn_dolen', 17279, 0, NULL),
(875, 'eryn_dolen', 17280, 0, NULL),
(876, 'eryn_dolen', 17281, 0, NULL),
(877, 'eryn_dolen', 17282, 0, NULL),
(878, 'eryn_dolen', 17283, 0, NULL),
(879, 'eryn_dolen', 17284, 0, NULL),
(880, 'eryn_dolen', 17285, 0, NULL),
(881, 'eryn_dolen', 17286, 0, NULL),
(882, 'eryn_dolen', 17287, 0, NULL),
(883, 'eryn_dolen', 17289, 0, NULL),
(884, 'eryn_dolen', 17290, 0, NULL),
(885, 'eryn_dolen', 17291, 0, NULL),
(886, 'eryn_dolen', 17292, 0, NULL),
(887, 'eryn_dolen', 17293, 0, NULL),
(888, 'eryn_dolen', 17294, 0, NULL),
(889, 'eryn_dolen', 17296, 0, NULL),
(890, 'eryn_dolen', 17297, 0, NULL),
(891, 'lac_pegasus', 17298, 0, NULL),
(892, 'lac_pegasus', 17299, 0, NULL),
(893, 'lac_pegasus', 17300, 0, NULL),
(894, 'lac_pegasus', 17301, 0, NULL),
(895, 'lac_pegasus', 17302, 0, NULL),
(896, 'lac_pegasus', 17303, 0, NULL),
(897, 'lac_pegasus', 17304, 0, NULL),
(898, 'lac_pegasus', 17305, 0, NULL),
(899, 'lac_pegasus', 17306, 0, NULL),
(900, 'lac_pegasus', 17307, 0, NULL),
(901, 'lac_pegasus', 17308, 0, NULL),
(902, 'lac_pegasus', 17309, 0, NULL),
(903, 'lac_pegasus', 17310, 0, NULL),
(904, 'lac_pegasus', 17311, 0, NULL),
(905, 'lac_pegasus', 17312, 0, NULL),
(906, 'lac_pegasus', 17276, 0, NULL),
(907, 'lac_pegasus', 17288, 0, NULL),
(908, 'lac_pegasus', 17313, 0, NULL),
(909, 'lac_pegasus', 17314, 0, NULL),
(910, 'lac_pegasus', 17315, 0, NULL),
(911, 'lac_pegasus', 17316, 0, NULL),
(912, 'lac_pegasus', 17268, 0, NULL),
(913, 'lac_pegasus', 17295, 0, NULL),
(914, 'lac_pegasus', 17317, 0, NULL),
(915, 'lac_pegasus', 17318, 0, NULL),
(916, 'lac_pegasus', 17319, 0, NULL),
(917, 'lac_pegasus', 17320, 0, NULL),
(918, 'lac_pegasus', 17321, 0, NULL),
(919, 'lac_pegasus', 17322, 0, NULL),
(920, 'lac_pegasus', 17323, 0, NULL),
(921, 'lac_pegasus', 17324, 0, NULL),
(922, 'lac_pegasus', 17325, 0, NULL),
(923, 'lac_pegasus', 17326, 0, NULL),
(924, 'eryn_dolen', 17327, 0, NULL),
(925, 'eryn_dolen', 17328, 0, NULL),
(926, 'eryn_dolen', 17329, 0, NULL),
(927, 'eryn_dolen', 17330, 0, NULL),
(928, 'eryn_dolen', 17331, 0, NULL),
(929, 'eryn_dolen', 17332, 0, NULL),
(930, 'eryn_dolen', 17333, 0, NULL),
(931, 'eryn_dolen', 17334, 0, NULL),
(932, 'eryn_dolen', 17335, 0, NULL),
(933, 'eryn_dolen', 17336, 0, NULL),
(934, 'eryn_dolen', 17337, 0, NULL),
(935, 'eryn_dolen', 17338, 0, NULL),
(936, 'eryn_dolen', 17339, 0, NULL),
(937, 'eryn_dolen', 17340, 0, NULL),
(938, 'eryn_dolen', 17341, 0, NULL),
(939, 'eryn_dolen', 17342, 0, NULL),
(940, 'eryn_dolen', 17343, 0, NULL),
(941, 'eryn_dolen', 17344, 0, NULL),
(942, 'eryn_dolen', 17345, 0, NULL),
(943, 'eryn_dolen', 17346, 0, NULL),
(944, 'eryn_dolen', 17347, 0, NULL),
(945, 'eryn_dolen', 17348, 0, NULL),
(946, 'eryn_dolen', 17349, 0, NULL),
(947, 'eryn_dolen', 17350, 0, NULL),
(948, 'eryn_dolen', 17351, 0, NULL),
(949, 'eryn_dolen', 17352, 0, NULL),
(950, 'eryn_dolen', 17353, 0, NULL),
(951, 'eryn_dolen', 17354, 0, NULL),
(952, 'eryn_dolen', 17355, 0, NULL),
(953, 'eryn_dolen', 17356, 0, NULL),
(954, 'eryn_dolen', 17357, 0, NULL),
(955, 'eryn_dolen', 17358, 0, NULL),
(956, 'eryn_dolen', 17359, 0, NULL),
(957, 'eryn_dolen', 17360, 0, NULL),
(958, 'eryn_dolen', 17361, 0, NULL),
(959, 'eryn_dolen', 17362, 0, NULL),
(960, 'eryn_dolen', 17363, 0, NULL),
(961, 'eryn_dolen', 17364, 0, NULL),
(962, 'eryn_dolen', 17365, 0, NULL),
(963, 'eryn_dolen', 17366, 0, NULL),
(964, 'eryn_dolen', 17367, 0, NULL),
(965, 'eryn_dolen', 17368, 0, NULL),
(966, 'eryn_dolen', 17369, 0, NULL),
(967, 'eryn_dolen', 17370, 0, NULL),
(968, 'eryn_dolen', 17371, 0, NULL),
(969, 'eryn_dolen', 17372, 0, NULL),
(970, 'eryn_dolen', 17373, 0, NULL),
(971, 'eryn_dolen', 17374, 0, NULL),
(972, 'eryn_dolen', 17375, 0, NULL),
(973, 'eryn_dolen', 17376, 0, NULL),
(974, 'eryn_dolen', 17238, 0, NULL),
(975, 'eryn_dolen', 17239, 0, NULL),
(976, 'eryn_dolen', 17240, 0, NULL),
(977, 'eryn_dolen', 17222, 0, NULL),
(978, 'eryn_dolen', 17221, 0, NULL),
(979, 'eryn_dolen', 17228, 0, NULL),
(980, 'lac_cenedril', 17377, 0, NULL),
(981, 'lac_cenedril', 17378, 0, NULL),
(982, 'lac_cenedril', 17379, 0, NULL),
(983, 'lac_cenedril', 17380, 0, NULL),
(984, 'lac_cenedril', 17381, 0, NULL),
(985, 'lac_cenedril', 17382, 0, NULL),
(986, 'lac_cenedril', 17383, 0, NULL),
(987, 'lac_cenedril', 17384, 0, NULL),
(988, 'lac_cenedril', 17385, 0, NULL),
(989, 'lac_cenedril', 17386, 0, NULL),
(990, 'lac_cenedril', 17387, 0, NULL),
(991, 'lac_cenedril', 17388, 0, NULL),
(992, 'lac_cenedril', 17389, 0, NULL),
(993, 'lac_cenedril', 17390, 0, NULL),
(994, 'lac_cenedril', 17391, 0, NULL),
(995, 'lac_cenedril', 17392, 0, NULL),
(996, 'lac_cenedril', 17393, 0, NULL),
(997, 'lac_cenedril', 17394, 0, NULL),
(998, 'lac_cenedril', 17395, 0, NULL),
(999, 'lac_cenedril', 17396, 0, NULL),
(1000, 'lac_cenedril', 17397, 0, NULL),
(1001, 'lac_cenedril', 17398, 0, NULL),
(1002, 'lac_cenedril', 17399, 0, NULL),
(1003, 'lac_cenedril', 17400, 0, NULL),
(1004, 'lac_cenedril', 17401, 0, NULL),
(1005, 'lac_cenedril', 17402, 0, NULL),
(1006, 'lac_cenedril', 17403, 0, NULL),
(1007, 'lac_cenedril', 17404, 0, NULL),
(1008, 'lac_cenedril', 17405, 0, NULL),
(1009, 'lac_cenedril', 17406, 0, NULL),
(1010, 'lac_cenedril', 17407, 0, NULL),
(1011, 'lac_cenedril', 17408, 0, NULL),
(1012, 'lac_cenedril', 17409, 0, NULL),
(1013, 'lac_cenedril', 17410, 0, NULL),
(1014, 'lac_cenedril', 17411, 0, NULL),
(1015, 'lac_cenedril', 17412, 0, NULL),
(1016, 'lac_cenedril', 17413, 0, NULL),
(1017, 'lac_cenedril', 17414, 0, NULL),
(1018, 'lac_cenedril', 17415, 0, NULL),
(1019, 'lac_cenedril', 17416, 0, NULL),
(1020, 'lac_cenedril', 17417, 0, NULL),
(1021, 'eryn_dolen', 17418, 0, NULL),
(1022, 'eryn_dolen', 17419, 0, NULL),
(1023, 'eryn_dolen', 17420, 0, NULL),
(1024, 'eryn_dolen', 17421, 0, NULL),
(1025, 'eryn_dolen', 17422, 0, NULL),
(1026, 'eryn_dolen', 17423, 0, NULL),
(1027, 'eryn_dolen', 17424, 0, NULL),
(1028, 'eryn_dolen', 17425, 0, NULL),
(1029, 'eryn_dolen', 17426, 0, NULL),
(1030, 'eryn_dolen', 17427, 0, NULL),
(1031, 'eryn_dolen', 17428, 0, NULL),
(1032, 'eryn_dolen', 17429, 0, NULL),
(1033, 'eryn_dolen', 17430, 0, NULL),
(1034, 'eryn_dolen', 17431, 0, NULL),
(1035, 'eryn_dolen', 17432, 0, NULL),
(1036, 'eryn_dolen', 17433, 0, NULL),
(1037, 'eryn_dolen', 17434, 0, NULL),
(1038, 'eryn_dolen', 17435, 0, NULL),
(1039, 'eryn_dolen', 17436, 0, NULL),
(1040, 'eryn_dolen', 17437, 0, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `map_triggers`
--

CREATE TABLE `map_triggers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `map_triggers`
--

INSERT INTO `map_triggers` (`id`, `name`, `coords_id`, `params`) VALUES
(1, 'forbidden', 60, ''),
(2, 'forbidden', 61, ''),
(3, 'forbidden', 62, ''),
(4, 'forbidden', 63, ''),
(5, 'forbidden', 64, ''),
(6, 'forbidden', 65, ''),
(7, 'forbidden', 24, ''),
(8, 'forbidden', 25, ''),
(9, 'forbidden', 66, ''),
(10, 'forbidden', 67, ''),
(11, 'forbidden', 68, ''),
(12, 'forbidden', 26, ''),
(13, 'forbidden', 27, ''),
(14, 'forbidden', 28, ''),
(15, 'forbidden', 69, ''),
(16, 'forbidden', 70, ''),
(17, 'forbidden', 71, ''),
(18, 'forbidden', 50, ''),
(19, 'forbidden', 72, ''),
(20, 'forbidden', 49, ''),
(21, 'forbidden', 73, ''),
(22, 'forbidden', 74, ''),
(23, 'forbidden', 75, ''),
(24, 'forbidden', 76, ''),
(25, 'forbidden', 77, ''),
(26, 'forbidden', 78, ''),
(27, 'forbidden', 79, ''),
(28, 'forbidden', 80, ''),
(29, 'forbidden', 81, ''),
(30, 'forbidden', 82, ''),
(31, 'forbidden', 83, ''),
(32, 'forbidden', 84, ''),
(33, 'forbidden', 85, ''),
(34, 'forbidden', 86, ''),
(35, 'forbidden', 87, ''),
(36, 'forbidden', 88, ''),
(37, 'forbidden', 89, ''),
(38, 'forbidden', 90, ''),
(39, 'forbidden', 51, ''),
(40, 'forbidden', 52, ''),
(41, 'forbidden', 91, ''),
(42, 'forbidden', 48, ''),
(43, 'forbidden', 92, ''),
(44, 'forbidden', 47, ''),
(45, 'forbidden', 93, ''),
(46, 'forbidden', 94, ''),
(47, 'forbidden', 95, ''),
(48, 'forbidden', 96, ''),
(49, 'rez', 136, ''),
(50, 'rez', 135, ''),
(51, 'rez', 160, ''),
(52, 'rez', 161, ''),
(53, 'rez', 162, ''),
(54, 'rez', 163, ''),
(55, 'rez', 164, ''),
(56, 'rez', 165, ''),
(57, 'rez', 166, ''),
(58, 'rez', 149, ''),
(59, 'rez', 167, ''),
(60, 'rez', 148, ''),
(61, 'forbidden', 168, ''),
(62, 'forbidden', 169, ''),
(63, 'forbidden', 170, ''),
(64, 'forbidden', 171, ''),
(65, 'forbidden', 172, ''),
(66, 'forbidden', 125, ''),
(67, 'forbidden', 126, ''),
(68, 'forbidden', 173, ''),
(69, 'forbidden', 174, ''),
(70, 'forbidden', 175, ''),
(71, 'forbidden', 127, ''),
(72, 'forbidden', 128, ''),
(73, 'forbidden', 129, ''),
(74, 'forbidden', 176, ''),
(75, 'forbidden', 177, ''),
(76, 'forbidden', 178, ''),
(77, 'forbidden', 146, ''),
(78, 'forbidden', 179, ''),
(79, 'forbidden', 147, ''),
(80, 'forbidden', 180, ''),
(81, 'forbidden', 181, ''),
(82, 'forbidden', 182, ''),
(83, 'forbidden', 183, ''),
(84, 'forbidden', 184, ''),
(85, 'forbidden', 185, ''),
(86, 'forbidden', 186, ''),
(87, 'forbidden', 187, ''),
(88, 'forbidden', 188, ''),
(89, 'forbidden', 189, ''),
(90, 'forbidden', 190, ''),
(91, 'forbidden', 191, ''),
(92, 'forbidden', 192, ''),
(93, 'forbidden', 193, ''),
(94, 'forbidden', 194, ''),
(95, 'forbidden', 195, ''),
(96, 'forbidden', 196, ''),
(97, 'forbidden', 197, ''),
(98, 'forbidden', 108, ''),
(99, 'forbidden', 121, ''),
(100, 'forbidden', 122, ''),
(101, 'forbidden', 123, ''),
(102, 'forbidden', 120, ''),
(103, 'forbidden', 119, ''),
(104, 'forbidden', 118, ''),
(105, 'forbidden', 105, ''),
(106, 'forbidden', 198, ''),
(107, 'forbidden', 152, ''),
(108, 'forbidden', 153, ''),
(109, 'forbidden', 199, ''),
(110, 'forbidden', 151, ''),
(111, 'forbidden', 200, ''),
(112, 'forbidden', 150, ''),
(113, 'forbidden', 201, ''),
(114, 'forbidden', 202, ''),
(115, 'forbidden', 203, ''),
(116, 'forbidden', 204, ''),
(117, 'rez', 12680, ''),
(118, 'rez', 12681, ''),
(119, 'rez', 12697, ''),
(120, 'rez', 12698, ''),
(121, 'tp', 12671, 'x,y,-1,nidhogg'),
(122, 'forbidden', 17362, ''),
(123, 'forbidden', 17361, ''),
(124, 'forbidden', 17358, ''),
(125, 'forbidden', 17357, ''),
(126, 'forbidden', 17354, ''),
(127, 'forbidden', 17353, ''),
(128, 'forbidden', 17345, ''),
(129, 'forbidden', 17344, ''),
(130, 'forbidden', 17343, ''),
(131, 'forbidden', 17342, ''),
(132, 'forbidden', 17341, ''),
(133, 'forbidden', 17340, ''),
(134, 'forbidden', 17335, ''),
(135, 'forbidden', 17332, ''),
(136, 'forbidden', 17290, ''),
(137, 'forbidden', 17278, ''),
(138, 'forbidden', 17279, ''),
(139, 'forbidden', 17280, ''),
(140, 'forbidden', 17241, ''),
(141, 'forbidden', 17242, ''),
(142, 'forbidden', 17235, ''),
(143, 'forbidden', 17234, ''),
(144, 'forbidden', 17233, ''),
(145, 'forbidden', 17232, ''),
(146, 'forbidden', 17231, ''),
(147, 'forbidden', 17230, ''),
(148, 'forbidden', 17229, ''),
(149, 'forbidden', 17225, ''),
(150, 'forbidden', 17226, ''),
(151, 'forbidden', 17227, ''),
(152, 'forbidden', 17263, ''),
(153, 'forbidden', 17264, ''),
(154, 'forbidden', 17265, ''),
(155, 'forbidden', 17267, ''),
(156, 'forbidden', 17296, ''),
(157, 'forbidden', 17367, ''),
(158, 'forbidden', 17366, ''),
(159, 'forbidden', 17372, ''),
(160, 'forbidden', 17294, ''),
(161, 'forbidden', 17269, ''),
(162, 'forbidden', 17270, ''),
(163, 'forbidden', 17271, ''),
(164, 'forbidden', 17272, ''),
(165, 'forbidden', 17282, ''),
(166, 'forbidden', 17375, ''),
(167, 'forbidden', 17374, ''),
(168, 'forbidden', 17373, ''),
(169, 'forbidden', 17376, ''),
(170, 'forbidden', 17327, ''),
(171, 'forbidden', 17283, ''),
(172, 'forbidden', 17284, ''),
(173, 'forbidden', 17273, ''),
(174, 'forbidden', 17274, ''),
(175, 'forbidden', 17287, ''),
(176, 'forbidden', 17331, ''),
(177, 'forbidden', 17330, ''),
(178, 'forbidden', 17329, ''),
(179, 'forbidden', 17328, '');

-- --------------------------------------------------------

--
-- Structure de la table `map_walls`
--

CREATE TABLE `map_walls` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `player_id` int(11) DEFAULT NULL,
  `coords_id` int(11) NOT NULL,
  `damages` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `map_walls`
--

INSERT INTO `map_walls` (`id`, `name`, `player_id`, `coords_id`, `damages`) VALUES
(116, 'gaia', NULL, 59, 0),
(117, 'gaia', NULL, 111, 0),
(118, 'pilier', NULL, 15504, 0),
(119, 'pilier', NULL, 15506, 0),
(120, 'pilier', NULL, 15508, 0),
(121, 'pilier', NULL, 15510, 0),
(122, 'pilier', NULL, 15512, 0),
(123, 'pilier', NULL, 15514, 0),
(124, 'pilier', NULL, 15516, 0),
(125, 'pilier', NULL, 15518, 0),
(126, 'cocotier3', NULL, 16609, 0),
(127, 'cocotier3', NULL, 16830, 0),
(128, 'cocotier3', NULL, 16827, 0),
(129, 'cocotier3', NULL, 16772, 0),
(130, 'cocotier3', NULL, 16641, 0),
(131, 'cocotier3', NULL, 16630, 0),
(132, 'cocotier3', NULL, 16666, 0),
(133, 'cocotier2', NULL, 16837, 0),
(134, 'cocotier2', NULL, 16741, 0),
(135, 'cocotier2', NULL, 16648, 0),
(136, 'cocotier2', NULL, 16627, 0),
(137, 'cocotier2', NULL, 16787, 0),
(138, 'cocotier2', NULL, 16871, 0),
(139, 'cocotier2', NULL, 16860, 0),
(140, 'cocotier2', NULL, 16831, 0),
(141, 'cocotier2', NULL, 16838, 0),
(142, 'cocotier1', NULL, 16863, 0),
(143, 'cocotier1', NULL, 16614, 0),
(144, 'cocotier1', NULL, 16777, 0),
(145, 'cocotier1', NULL, 16678, 0),
(146, 'cocotier1', NULL, 15565, 0),
(147, 'cocotier1', NULL, 16647, 0),
(148, 'cocotier1', NULL, 16762, 0),
(149, 'cocotier1', NULL, 16749, 0),
(150, 'cocotier1', NULL, 16653, 0),
(151, 'cocotier1', NULL, 16744, 0),
(152, 'cocotier1', NULL, 16622, 0),
(153, 'cocotier1', NULL, 16637, 0),
(154, 'pierre1', NULL, 16670, 0),
(155, 'pierre1', NULL, 16808, 0),
(156, 'pierre2', NULL, 16721, 0),
(157, 'pierre1', NULL, 19784, 0),
(158, 'pierre1', NULL, 19850, 0),
(159, 'pierre1', NULL, 19818, 0),
(160, 'pierre1', NULL, 19819, 0),
(161, 'cocotier3', NULL, 16843, 0),
(162, 'cocotier3', NULL, 16651, 0),
(163, 'cocotier3', NULL, 16681, 0),
(164, 'cocotier3', NULL, 16783, 0),
(165, 'mur_pierre_bleue', NULL, 35138, 0),
(166, 'mur_pierre_bleue', NULL, 35137, 0),
(167, 'mur_pierre_bleue', NULL, 35136, 0),
(168, 'mur_pierre_bleue', NULL, 35135, 0),
(169, 'mur_pierre_bleue', NULL, 35134, 0),
(170, 'mur_pierre_bleue', NULL, 35133, 0),
(171, 'mur_pierre_bleue', NULL, 35132, 0),
(172, 'mur_pierre_bleue', NULL, 35131, 0),
(173, 'mur_pierre_bleue', NULL, 35130, 0),
(174, 'mur_pierre_bleue', NULL, 35129, 0),
(175, 'mur_pierre_bleue', NULL, 35128, 0),
(176, 'mur_pierre_bleue', NULL, 35127, 0),
(177, 'mur_pierre_bleue', NULL, 35126, 0),
(178, 'mur_pierre_bleue', NULL, 35125, 0),
(179, 'mur_pierre_bleue', NULL, 35124, 0),
(180, 'mur_pierre_bleue', NULL, 35123, 0),
(181, 'mur_pierre_bleue', NULL, 35122, 0),
(182, 'mur_pierre_bleue', NULL, 35121, 0),
(183, 'statues6', NULL, 35114, 0),
(184, 'statue_heroique', NULL, 35118, 0),
(185, 'statue_heroique', NULL, 35117, 0),
(186, 'pierre_noire2', NULL, 35043, 0),
(187, 'pierre_noire2', NULL, 35036, 0),
(188, 'pierre_noire2', NULL, 35052, 0),
(189, 'pierre_noire2', NULL, 35058, 0),
(190, 'pierre_noire2', NULL, 35077, 0),
(191, 'pierre_noire2', NULL, 35076, 0),
(192, 'pierre_noire2', NULL, 35105, 0),
(193, 'pierre1', NULL, 35014, 0),
(194, 'pierre1', NULL, 35042, 0),
(195, 'pierre1', NULL, 35091, 0),
(196, 'pierre1', NULL, 35092, 0),
(197, 'pierre2', NULL, 35067, 0),
(198, 'pierre2', NULL, 35022, 0),
(199, 'pierre2', NULL, 35040, 0),
(200, 'pierre2', NULL, 35062, 0),
(201, 'mur_pierre', NULL, 35139, 0),
(202, 'mur_pierre', NULL, 35140, 0),
(203, 'mur_pierre', NULL, 35141, 0),
(204, 'mur_pierre', NULL, 35142, 0),
(205, 'mur_pierre', NULL, 35143, 0),
(206, 'mur_pierre', NULL, 35144, 0),
(207, 'mur_pierre', NULL, 35145, 0),
(208, 'mur_pierre', NULL, 35146, 0),
(209, 'mur_pierre', NULL, 35147, 0),
(210, 'mur_pierre', NULL, 35148, 0),
(211, 'mur_pierre', NULL, 35149, 0),
(212, 'mur_pierre', NULL, 35150, 0),
(213, 'mur_pierre', NULL, 35119, 0),
(214, 'mur_pierre', NULL, 35120, 0),
(215, 'statues6', NULL, 35112, 0),
(216, 'pierre1', NULL, 35030, 0),
(217, 'pierre3', NULL, 35066, 0),
(218, 'pierre3', NULL, 35098, 0),
(219, 'statues5', NULL, 37488, 0),
(220, 'statues5', NULL, 37482, 0),
(221, 'pilier', NULL, 37485, 0),
(222, 'pilier', NULL, 37487, 0),
(223, 'pilier', NULL, 37483, 0),
(224, 'pilier', NULL, 37504, 0),
(225, 'pilier', NULL, 37495, 0),
(226, 'pilier', NULL, 37502, 0),
(227, 'statue_heroique', NULL, 37491, 0),
(228, 'statue_heroique', NULL, 37501, 0),
(229, 'table_bois', NULL, 38901, 0),
(230, 'coffre_bois', NULL, 38897, 0),
(231, 'mur_pierre_bleue', NULL, 17022, 0),
(232, 'mur_pierre_bleue', NULL, 17023, 0),
(233, 'mur_pierre_bleue', NULL, 17024, 0),
(234, 'mur_pierre_bleue', NULL, 17025, 0),
(235, 'mur_pierre_bleue', NULL, 17026, 0),
(236, 'mur_pierre_bleue', NULL, 17027, 0),
(237, 'mur_pierre_bleue', NULL, 17028, 0),
(238, 'mur_pierre_bleue', NULL, 17029, 0),
(239, 'mur_pierre_bleue', NULL, 17030, 0),
(240, 'mur_pierre_bleue', NULL, 17031, 0),
(241, 'mur_pierre_bleue', NULL, 17032, 0),
(242, 'mur_pierre_bleue', NULL, 17033, 0),
(243, 'mur_pierre_bleue', NULL, 17034, 0),
(244, 'mur_pierre_bleue', NULL, 17035, 0),
(245, 'mur_pierre_bleue', NULL, 17036, 0),
(246, 'mur_pierre_bleue', NULL, 17038, 0),
(247, 'mur_pierre_bleue', NULL, 17039, 0),
(248, 'mur_pierre_bleue', NULL, 17040, 0),
(249, 'mur_pierre_bleue', NULL, 17041, 0),
(250, 'mur_pierre_bleue', NULL, 17042, 0),
(251, 'mur_pierre_bleue', NULL, 17043, 0),
(252, 'mur_pierre_bleue', NULL, 17044, 0),
(253, 'mur_pierre_bleue', NULL, 17045, 0),
(254, 'table_bois', NULL, 17013, 0),
(255, 'mur_pierre_bleue', NULL, 17046, 0),
(256, 'mur_pierre_bleue', NULL, 17047, 0),
(257, 'mur_pierre_bleue', NULL, 17048, 0),
(258, 'mur_pierre_bleue', NULL, 17049, 0),
(259, 'mur_pierre_bleue', NULL, 17050, 0),
(260, 'mur_pierre_bleue', NULL, 17051, 0),
(261, 'mur_pierre_bleue', NULL, 17052, 0),
(262, 'mur_pierre_bleue', NULL, 17053, 0),
(263, 'mur_pierre_bleue', NULL, 17054, 0),
(264, 'mur_pierre_bleue', NULL, 17055, 0),
(265, 'mur_pierre_bleue', NULL, 17056, 0),
(266, 'mur_pierre_bleue', NULL, 17057, 0),
(267, 'mur_pierre_bleue', NULL, 17058, 0),
(268, 'mur_pierre_bleue', NULL, 17059, 0),
(269, 'mur_pierre_bleue', NULL, 17060, 0),
(270, 'mur_pierre_bleue', NULL, 17061, 0),
(271, 'mur_pierre_bleue', NULL, 17062, 0),
(272, 'mur_pierre_bleue', NULL, 17063, 0),
(273, 'mur_pierre_bleue', NULL, 17064, 0),
(274, 'mur_pierre_bleue', NULL, 17065, 0),
(275, 'mur_pierre_bleue', NULL, 17066, 0),
(276, 'mur_pierre_bleue', NULL, 17067, 0),
(277, 'mur_pierre_bleue', NULL, 17068, 0),
(278, 'mur_pierre_bleue', NULL, 17069, 0),
(279, 'mur_pierre_bleue', NULL, 17070, 0),
(280, 'mur_pierre_bleue', NULL, 17071, 0),
(281, 'mur_pierre_bleue', NULL, 17072, 0),
(282, 'mur_pierre_bleue', NULL, 17073, 0),
(283, 'mur_pierre_bleue', NULL, 17074, 0),
(284, 'mur_pierre_bleue', NULL, 17075, 0),
(285, 'mur_pierre_bleue', NULL, 17076, 0),
(286, 'mur_pierre_bleue', NULL, 17077, 0),
(287, 'mur_pierre_bleue', NULL, 17078, 0),
(288, 'mur_pierre_bleue', NULL, 17079, 0),
(289, 'mur_pierre_bleue', NULL, 17080, 0),
(290, 'mur_pierre_bleue', NULL, 17081, 0),
(291, 'mur_pierre_bleue', NULL, 17082, 0),
(292, 'mur_pierre_bleue', NULL, 17083, 0),
(293, 'mur_pierre_bleue', NULL, 17084, 0),
(294, 'mur_pierre_bleue', NULL, 17085, 0),
(295, 'mur_pierre_bleue', NULL, 17086, 0),
(296, 'mur_pierre_bleue', NULL, 17087, 0),
(297, 'mur_pierre_bleue', NULL, 17088, 0),
(298, 'mur_pierre_bleue', NULL, 17089, 0),
(299, 'mur_pierre_bleue', NULL, 17090, 0),
(300, 'mur_pierre_bleue', NULL, 17091, 0),
(301, 'mur_pierre_bleue', NULL, 17092, 0),
(302, 'mur_pierre_bleue', NULL, 17093, 0),
(303, 'mur_pierre_bleue', NULL, 17094, 0),
(304, 'mur_pierre_bleue', NULL, 17095, 0),
(305, 'mur_pierre_bleue', NULL, 17096, 0),
(306, 'mur_pierre_bleue', NULL, 17097, 0),
(307, 'mur_pierre_bleue', NULL, 17137, 0),
(308, 'mur_pierre_bleue', NULL, 17138, 0),
(309, 'mur_pierre_bleue', NULL, 17140, 0),
(310, 'mur_pierre_bleue', NULL, 17141, 0),
(311, 'mur_pierre_bleue', NULL, 17142, 0),
(312, 'coffre_metal', NULL, 17198, 0),
(313, 'coffre_metal', NULL, 17201, 0),
(314, 'coffre_metal', NULL, 17202, 0),
(315, 'tonneau', NULL, 17203, 0),
(316, 'coffre_bois', NULL, 17200, 0),
(317, 'coffre_humain', NULL, 17207, 0),
(318, 'pilier', NULL, 17119, 0),
(319, 'pilier', NULL, 17107, 0),
(320, 'pilier', NULL, 17217, 0),
(321, 'pilier', NULL, 17218, 0),
(322, 'pilier', NULL, 17136, 0),
(323, 'pilier', NULL, 17147, 0),
(324, 'statue_heroique', NULL, 17114, 0),
(325, 'statue_heroique', NULL, 17102, 0),
(326, 'statues1', NULL, 17219, 0),
(327, 'statues1', NULL, 17220, 0),
(328, 'arbre2', NULL, 17339, 0),
(329, 'arbre2', NULL, 17336, 0),
(330, 'arbre1', NULL, 17370, 0),
(331, 'arbre3', NULL, 17365, 0),
(332, 'arbre3', NULL, 17238, 0),
(333, 'arbre3', NULL, 17240, 0),
(334, 'arbre3', NULL, 17222, 0),
(335, 'arbre2', NULL, 17221, 0),
(336, 'arbre2', NULL, 17239, 0),
(337, 'arbre1', NULL, 17228, 0),
(338, 'pierre1', NULL, 17275, 0),
(339, 'pierre2', NULL, 17277, 0),
(340, 'pierre3', NULL, 17372, 0),
(341, 'pancarte', NULL, 17346, 0),
(342, 'pancarte', NULL, 17352, 0),
(343, 'mur_bois', NULL, 17121, 0),
(344, 'mur_bois', NULL, 17122, 0),
(345, 'mur_bois', NULL, 17123, 0),
(346, 'mur_pierre_bleue', NULL, 17139, 0),
(347, 'mur_bois_petrifie', NULL, 17143, 0),
(348, 'mur_bois_petrifie', NULL, 17148, 0),
(349, 'arbre1', NULL, 17418, 0),
(350, 'arbre1', NULL, 17436, 0),
(351, 'arbre1', NULL, 17420, 0),
(352, 'arbre2', NULL, 17419, 0),
(353, 'arbre2', NULL, 17435, 0),
(354, 'arbre3', NULL, 17437, 0);

-- --------------------------------------------------------

--
-- Structure de la table `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `psw` varchar(255) NOT NULL DEFAULT '',
  `mail` varchar(255) NOT NULL DEFAULT '',
  `coords_id` int(11) NOT NULL DEFAULT 0,
  `race` varchar(255) NOT NULL DEFAULT '',
  `xp` int(11) NOT NULL DEFAULT 0,
  `pi` int(11) NOT NULL DEFAULT 0,
  `pr` int(11) NOT NULL DEFAULT 0,
  `malus` int(11) NOT NULL DEFAULT 0,
  `fatigue` int(11) NOT NULL DEFAULT 0,
  `godId` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `rank` int(11) NOT NULL DEFAULT 1,
  `avatar` varchar(255) NOT NULL DEFAULT '',
  `portrait` varchar(255) NOT NULL DEFAULT '',
  `text` text NOT NULL DEFAULT 'Je suis nouveau, frappez-moi!',
  `story` text NOT NULL DEFAULT 'Je préfère garder cela pour moi.',
  `quest` varchar(255) DEFAULT 'gaia',
  `faction` varchar(255) NOT NULL DEFAULT '',
  `factionRole` int(11) NOT NULL DEFAULT 0,
  `secretFaction` varchar(255) NOT NULL DEFAULT '',
  `secretFactionRole` int(11) NOT NULL DEFAULT 0,
  `nextTurnTime` int(11) NOT NULL DEFAULT 0,
  `registerTime` int(11) NOT NULL DEFAULT 0,
  `lastActionTime` int(11) NOT NULL DEFAULT 0,
  `lastLoginTime` int(11) NOT NULL DEFAULT 0,
  `antiBerserkTime` int(11) NOT NULL DEFAULT 0,
  `lastTravelTime` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players`
--

INSERT INTO `players` (`id`, `name`, `psw`, `mail`, `coords_id`, `race`, `xp`, `pi`, `pr`, `malus`, `fatigue`, `godId`, `pf`, `rank`, `avatar`, `portrait`, `text`, `story`, `quest`, `faction`, `factionRole`, `secretFaction`, `secretFactionRole`, `nextTurnTime`, `registerTime`, `lastActionTime`, `lastLoginTime`, `antiBerserkTime`, `lastTravelTime`) VALUES
(-1, 'Gaïa', '', '', 15, 'lutin', 0, 0, 0, 0, 0, 0, 0, 1, 'img/avatars/ame/lutin.webp', 'img/portraits/ame/1.jpeg', 'Je suis nouveau, frappez-moi!', 'Je préfère garder cela pour moi.', 'gaia', 'saruta_et_freres', 0, '', 0, 0, 0, 0, 0, 0, 0),
(1, 'Cradek', '$2y$10$m35XbOC9buOw7ZH/gB2k.ubYl7vEDYYjgTmDyLcGUNt15Q9LaBILe', '$2y$10$hkduB0wnA8nfn2C.ck6UA.b6jr56K9WeBDel33IokN/rtogNXQ8C2', 15318, 'nain', 6, 6, 0, 0, 1, 0, 0, 1, 'img/avatars/nain/5.png', 'img/portraits/nain/45.jpeg', 'Je suis nouveau, frappez-moi!', 'Je préfère garder cela pour moi.', 'gaia', 'forge_sacree', 0, '', 0, 1736181320, 1736117307, 1736120127, 1736121625, 16200, 1736117842),
(2, 'Dorna', '$2y$10$XJm1A0RZWGRbhvDlUyOP8e/O0hhDLLUwU.VJM00GbmWjydKqeoczy', '$2y$10$pVJivan0Lhqg.x0OSWQzaulIWVr.BPJ.c3Q992jtWsy61FXH84wNS', 17004, 'nain', 6, 6, 0, 0, 1, 0, 0, 1, 'img/avatars/nain/73.png', 'img/portraits/nain/44.jpeg', 'Je suis nouveau, frappez-moi!', 'Je préfère garder cela pour moi.', 'gaia', 'forge_sacree', 0, '', 0, 1736181749, 1736118099, 1736120060, 1736121647, 16200, 1736118462),
(3, 'Thyrias', '$2y$10$SzsgPLFIpn11Rg/TDubHj.fvFLGZdgY.Vwx9VD9GlYYhPu5MR3SeG', '$2y$10$1iltdhoPMNdCc9hBNMbdkuVpkb5/Qf7s2CIM0.KgIFwkQmVKXj7p6', 15472, 'elfe', 10, 10, 0, 0, 1, 0, 0, 1, 'img/avatars/elfe/70.png', 'img/portraits/elfe/33.jpeg', 'Je suis nouveau, frappez-moi!', 'Je préfère garder cela pour moi.', 'gaia', 'eryn_dolen', 0, '', 0, 1736184980, 1736120180, 1736120472, 1736120194, 16200, 0);

-- --------------------------------------------------------

--
-- Structure de la table `players_actions`
--

CREATE TABLE `players_actions` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `charges` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_actions`
--

INSERT INTO `players_actions` (`player_id`, `name`, `type`, `charges`) VALUES
(1, 'attaquer', '', 0),
(1, 'courir', '', 0),
(1, 'dmg1/pic_de_pierre', 'sort', 0),
(1, 'entrainement', '', 0),
(1, 'fouiller', '', 0),
(1, 'prier', '', 0),
(1, 'repos', '', 0),
(1, 'soins/barbier', 'sort', 0),
(2, 'attaquer', '', 0),
(2, 'courir', '', 0),
(2, 'dmg1/pic_de_pierre', 'sort', 0),
(2, 'entrainement', '', 0),
(2, 'fouiller', '', 0),
(2, 'prier', '', 0),
(2, 'repos', '', 0),
(2, 'soins/barbier', 'sort', 0),
(3, 'attaquer', '', 0),
(3, 'courir', '', 0),
(3, 'dmg1/fleche_aquatique', 'sort', 0),
(3, 'entrainement', '', 0),
(3, 'fouiller', '', 0),
(3, 'prier', '', 0),
(3, 'repos', '', 0),
(3, 'soins/lien_de_vie', 'sort', 0);

-- --------------------------------------------------------

--
-- Structure de la table `players_assists`
--

CREATE TABLE `players_assists` (
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_rank` int(11) NOT NULL DEFAULT 1,
  `damages` int(11) NOT NULL DEFAULT 1,
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_banned`
--

CREATE TABLE `players_banned` (
  `player_id` int(11) NOT NULL,
  `ips` text NOT NULL,
  `text` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_bonus`
--

CREATE TABLE `players_bonus` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `n` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_bonus`
--

INSERT INTO `players_bonus` (`player_id`, `name`, `n`) VALUES
(1, 'a', -1),
(1, 'mvt', -1),
(2, 'a', -1),
(2, 'mvt', -1),
(3, 'a', -1),
(3, 'mvt', -2);

-- --------------------------------------------------------

--
-- Structure de la table `players_connections`
--

CREATE TABLE `players_connections` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `ip` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `footprint` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_connections`
--

INSERT INTO `players_connections` (`id`, `player_id`, `ip`, `time`, `footprint`) VALUES
(1, 1, '0b4160b356e71f5df4c73233a8a686d9', 1736117543, '186f3c809ebe9df2f58f8f8c97c5a370'),
(2, 2, '0b4160b356e71f5df4c73233a8a686d9', 1736118255, '186f3c809ebe9df2f58f8f8c97c5a370'),
(3, 1, '0b4160b356e71f5df4c73233a8a686d9', 1736118434, '186f3c809ebe9df2f58f8f8c97c5a370'),
(4, 2, '0b4160b356e71f5df4c73233a8a686d9', 1736118488, '186f3c809ebe9df2f58f8f8c97c5a370'),
(5, 1, '0b4160b356e71f5df4c73233a8a686d9', 1736118520, '186f3c809ebe9df2f58f8f8c97c5a370'),
(6, 2, '0b4160b356e71f5df4c73233a8a686d9', 1736118569, '186f3c809ebe9df2f58f8f8c97c5a370'),
(7, 1, '0b4160b356e71f5df4c73233a8a686d9', 1736120123, '186f3c809ebe9df2f58f8f8c97c5a370'),
(8, 3, '0b4160b356e71f5df4c73233a8a686d9', 1736120194, '186f3c809ebe9df2f58f8f8c97c5a370'),
(9, 1, '0b4160b356e71f5df4c73233a8a686d9', 1736121625, '186f3c809ebe9df2f58f8f8c97c5a370'),
(10, 2, '0b4160b356e71f5df4c73233a8a686d9', 1736121647, '186f3c809ebe9df2f58f8f8c97c5a370');

-- --------------------------------------------------------

--
-- Structure de la table `players_effects`
--

CREATE TABLE `players_effects` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `endTime` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_followers`
--

CREATE TABLE `players_followers` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `foreground_id` int(11) NOT NULL,
  `params` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_forum_missives`
--

CREATE TABLE `players_forum_missives` (
  `player_id` int(11) NOT NULL,
  `name` bigint(20) NOT NULL,
  `viewed` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_forum_missives`
--

INSERT INTO `players_forum_missives` (`player_id`, `name`, `viewed`) VALUES
(1, 1724908803, 1),
(2, 1724908803, 1),
(3, 1724908803, 1);

-- --------------------------------------------------------

--
-- Structure de la table `players_forum_rewards`
--

CREATE TABLE `players_forum_rewards` (
  `id` int(11) NOT NULL,
  `from_player_id` int(11) NOT NULL,
  `to_player_id` int(11) NOT NULL,
  `postName` varchar(255) NOT NULL DEFAULT '',
  `topName` varchar(255) NOT NULL DEFAULT '',
  `img` varchar(255) NOT NULL DEFAULT '',
  `pr` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_ips`
--

CREATE TABLE `players_ips` (
  `id` int(11) NOT NULL,
  `ip` varchar(255) NOT NULL DEFAULT '',
  `expTime` int(11) NOT NULL DEFAULT 0,
  `failed` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_ips`
--

INSERT INTO `players_ips` (`id`, `ip`, `expTime`, `failed`) VALUES
(1, '172.20.0.1', 1736121946, 1);

-- --------------------------------------------------------

--
-- Structure de la table `players_items`
--

CREATE TABLE `players_items` (
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0,
  `equiped` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_items`
--

INSERT INTO `players_items` (`player_id`, `item_id`, `n`, `equiped`) VALUES
(1, 1, 40, ''),
(1, 8, 1, ''),
(2, 1, 20, ''),
(2, 8, 1, ''),
(3, 1, 40, ''),
(3, 8, 1, '');

-- --------------------------------------------------------

--
-- Structure de la table `players_items_bank`
--

CREATE TABLE `players_items_bank` (
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_items_exchanges`
--

CREATE TABLE `players_items_exchanges` (
  `exchange_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `n` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_kills`
--

CREATE TABLE `players_kills` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `player_rank` int(11) NOT NULL DEFAULT 1,
  `target_rank` int(11) NOT NULL DEFAULT 1,
  `xp` int(11) NOT NULL DEFAULT 0,
  `assist` int(11) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL DEFAULT 0,
  `plan` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_logs`
--

CREATE TABLE `players_logs` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `text` varchar(255) NOT NULL DEFAULT '',
  `hiddenText` text NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `plan` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `coords_id` int(11) DEFAULT 0,
  `coords_computed` varchar(35) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_logs`
--

INSERT INTO `players_logs` (`id`, `player_id`, `target_id`, `text`, `hiddenText`, `type`, `plan`, `time`, `coords_id`, `coords_computed`) VALUES
(1, 1, 1, 'Cradek s\'est déplacé en 1,0,0', '', 'move', 'gaia', 1736117555, 3, '1_0_0_gaia'),
(2, 1, 1, 'Cradek s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736117577, 4, '1_-1_0_gaia'),
(3, 1, 1, 'Cradek s\'est déplacé en 2,-2,0', '', 'move', 'gaia', 1736117578, 8, '2_-2_0_gaia'),
(4, 1, 1, 'Cradek s\'est déplacé en 3,-3,0', '', 'move', 'gaia', 1736117579, 13, '3_-3_0_gaia'),
(5, 1, 1, 'Cradek s\'est déplacé en 4,-4,0', '', 'move', 'gaia', 1736117580, 15, '4_-4_0_gaia'),
(6, 1, 1, 'Cradek s\'est déplacé en 5,-5,0', '', 'move', 'gaia', 1736117581, 20, '5_-5_0_gaia'),
(7, 1, 1, 'Cradek s\'est déplacé en 6,-6,0', '', 'move', 'gaia', 1736117582, 30, '6_-6_0_gaia'),
(8, 1, 1, 'Cradek s\'est déplacé en 7,-7,0', '', 'move', 'gaia', 1736117583, 97, '7_-7_0_gaia'),
(9, 1, 1, 'Cradek s\'est déplacé en 8,-8,0', '', 'move', 'gaia', 1736117584, 100, '8_-8_0_gaia'),
(10, 1, 1, 'Cradek s\'est déplacé en 7,-8,0', '', 'move', 'gaia', 1736117585, 99, '7_-8_0_gaia'),
(11, 1, 1, 'Cradek s\'est déplacé en 6,-8,0', '', 'move', 'gaia', 1736117602, 51587, '6_-8_0_gaia'),
(12, 1, 1, 'Cradek s\'est déplacé en 5,-7,0', '', 'move', 'gaia', 1736117603, 31, '5_-7_0_gaia'),
(13, 1, 1, 'Cradek s\'est déplacé en 5,-6,0', '', 'move', 'gaia', 1736117604, 29, '5_-6_0_gaia'),
(14, 1, 1, 'Cradek s\'est déplacé en 5,-5,0', '', 'move', 'gaia', 1736117605, 20, '5_-5_0_gaia'),
(15, 1, 1, 'Cradek s\'est déplacé en 4,-4,0', '', 'move', 'gaia', 1736117607, 15, '4_-4_0_gaia'),
(16, 1, 1, 'Cradek s\'est déplacé en 3,-3,0', '', 'move', 'gaia', 1736117608, 13, '3_-3_0_gaia'),
(17, 1, 1, 'Cradek s\'est déplacé en 2,-2,0', '', 'move', 'gaia', 1736117608, 8, '2_-2_0_gaia'),
(18, 1, 1, 'Cradek s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736117609, 4, '1_-1_0_gaia'),
(19, 1, 1, 'Cradek s\'est déplacé en 0,-1,0', '', 'move', 'gaia', 1736117610, 2, '0_-1_0_gaia'),
(20, 1, 1, 'Cradek s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736117630, 4, '1_-1_0_gaia'),
(21, 1, 1, 'Cradek s\'est déplacé en 2,-2,0', '', 'move', 'gaia', 1736117631, 8, '2_-2_0_gaia'),
(22, 1, 1, 'Cradek s\'est déplacé en 3,-3,0', '', 'move', 'gaia', 1736117632, 13, '3_-3_0_gaia'),
(23, 1, 1, 'Cradek s\'est déplacé en 4,-4,0', '', 'move', 'gaia', 1736117632, 15, '4_-4_0_gaia'),
(24, 1, 1, 'Cradek s\'est déplacé en 5,-5,0', '', 'move', 'gaia', 1736117633, 20, '5_-5_0_gaia'),
(25, 1, 1, 'Cradek s\'est déplacé en 6,-6,0', '', 'move', 'gaia', 1736117634, 30, '6_-6_0_gaia'),
(26, 1, 1, 'Cradek s\'est déplacé en 7,-7,0', '', 'move', 'gaia', 1736117635, 97, '7_-7_0_gaia'),
(27, 1, 1, 'Cradek s\'est déplacé en 8,-8,0', '', 'move', 'gaia', 1736117636, 100, '8_-8_0_gaia'),
(28, 1, 1, 'Cradek s\'est déplacé en 9,-9,0', '', 'move', 'gaia', 1736117637, 40, '9_-9_0_gaia'),
(29, 1, 1, 'Cradek s\'est déplacé en 8,-8,0', '', 'move', 'gaia', 1736117659, 100, '8_-8_0_gaia'),
(30, 1, 1, 'Cradek s\'est déplacé en 7,-7,0', '', 'move', 'gaia', 1736117659, 97, '7_-7_0_gaia'),
(31, 1, 1, 'Cradek s\'est déplacé en 6,-6,0', '', 'move', 'gaia', 1736117660, 30, '6_-6_0_gaia'),
(32, 1, 1, 'Cradek s\'est déplacé en 5,-5,0', '', 'move', 'gaia', 1736117661, 20, '5_-5_0_gaia'),
(33, 1, 1, 'Cradek s\'est déplacé en 4,-4,0', '', 'move', 'gaia', 1736117661, 15, '4_-4_0_gaia'),
(34, 1, 1, 'Cradek s\'est déplacé en 3,-3,0', '', 'move', 'gaia', 1736117662, 13, '3_-3_0_gaia'),
(35, 1, 1, 'Cradek s\'est déplacé en 2,-2,0', '', 'move', 'gaia', 1736117663, 8, '2_-2_0_gaia'),
(36, 1, 1, 'Cradek s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736117664, 4, '1_-1_0_gaia'),
(37, 1, 1, 'Cradek s\'est déplacé en 1,-2,0', '', 'move', 'gaia', 1736117803, 7, '1_-2_0_gaia'),
(38, 1, 1, 'Cradek s\'est déplacé en 0,0,0', '', 'move', 'banque_des_lutins', 1736117842, 15318, '0_0_0_banque_des_lutins'),
(39, 1, 1, 'Cradek s\'est déplacé en 0,1,0', '', 'move', 'banque_des_lutins', 1736117850, 17005, '0_1_0_banque_des_lutins'),
(40, 1, 1, 'Cradek s\'est déplacé en 0,2,0', '', 'move', 'banque_des_lutins', 1736117852, 17000, '0_2_0_banque_des_lutins'),
(41, 1, 1, 'Cradek s\'est déplacé en 1,2,0', '', 'move', 'banque_des_lutins', 1736117855, 17001, '1_2_0_banque_des_lutins'),
(42, 1, 1, 'Cradek s\'est déplacé en 1,1,0', '', 'move', 'banque_des_lutins', 1736117861, 17004, '1_1_0_banque_des_lutins'),
(43, 1, 1, 'Cradek s\'est déplacé en 0,0,0', '', 'move', 'banque_des_lutins', 1736117862, 15318, '0_0_0_banque_des_lutins'),
(44, 1, 1, 'Cradek s\'est déplacé en 0,-1,0', '', 'move', 'banque_des_lutins', 1736117873, 17014, '0_-1_0_banque_des_lutins'),
(45, 1, 1, 'Cradek s\'est déplacé en 0,-2,0', '', 'move', 'banque_des_lutins', 1736117874, 17018, '0_-2_0_banque_des_lutins'),
(46, 1, 1, 'Cradek s\'est déplacé en 0,-3,0', '', 'move', 'banque_des_lutins', 1736117875, 17037, '0_-3_0_banque_des_lutins'),
(47, 1, 1, 'Cradek s\'est déplacé en 0,-4,0', '', 'move', 'banque_des_lutins', 1736117876, 17098, '0_-4_0_banque_des_lutins'),
(48, 1, 1, 'Cradek s\'est déplacé en 0,-5,0', '', 'move', 'banque_des_lutins', 1736117877, 17099, '0_-5_0_banque_des_lutins'),
(49, 1, 1, 'Cradek s\'est déplacé en 0,-6,0', '', 'move', 'banque_des_lutins', 1736117878, 17110, '0_-6_0_banque_des_lutins'),
(50, 1, 1, 'Cradek s\'est déplacé en 0,-5,0', '', 'move', 'banque_des_lutins', 1736117882, 17099, '0_-5_0_banque_des_lutins'),
(51, 1, 1, 'Cradek s\'est déplacé en 0,-4,0', '', 'move', 'banque_des_lutins', 1736117883, 17098, '0_-4_0_banque_des_lutins'),
(52, 1, 1, 'Cradek s\'est déplacé en 0,-3,0', '', 'move', 'banque_des_lutins', 1736117884, 17037, '0_-3_0_banque_des_lutins'),
(53, 1, 1, 'Cradek s\'est déplacé en 0,-2,0', '', 'move', 'banque_des_lutins', 1736117885, 17018, '0_-2_0_banque_des_lutins'),
(54, 1, 1, 'Cradek s\'est déplacé en 0,-1,0', '', 'move', 'banque_des_lutins', 1736117885, 17014, '0_-1_0_banque_des_lutins'),
(55, 1, 1, 'Cradek s\'est déplacé en 0,0,0', '', 'move', 'banque_des_lutins', 1736117886, 15318, '0_0_0_banque_des_lutins'),
(56, 2, 2, 'Dorna s\'est déplacé en 1,0,0', '', 'move', 'gaia', 1736118265, 3, '1_0_0_gaia'),
(57, 2, 2, 'Dorna s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736118278, 4, '1_-1_0_gaia'),
(58, 2, 2, 'Dorna s\'est déplacé en 2,-2,0', '', 'move', 'gaia', 1736118287, 8, '2_-2_0_gaia'),
(59, 2, 2, 'Dorna s\'est déplacé en 3,-3,0', '', 'move', 'gaia', 1736118288, 13, '3_-3_0_gaia'),
(60, 2, 2, 'Dorna s\'est déplacé en 4,-4,0', '', 'move', 'gaia', 1736118289, 15, '4_-4_0_gaia'),
(61, 2, 2, 'Dorna s\'est déplacé en 5,-5,0', '', 'move', 'gaia', 1736118290, 20, '5_-5_0_gaia'),
(62, 2, 2, 'Dorna s\'est déplacé en 6,-6,0', '', 'move', 'gaia', 1736118291, 30, '6_-6_0_gaia'),
(63, 2, 2, 'Dorna s\'est déplacé en 7,-7,0', '', 'move', 'gaia', 1736118292, 97, '7_-7_0_gaia'),
(64, 2, 2, 'Dorna s\'est déplacé en 7,-8,0', '', 'move', 'gaia', 1736118292, 99, '7_-8_0_gaia'),
(65, 2, 2, 'Dorna s\'est déplacé en 8,-8,0', '', 'move', 'gaia', 1736118293, 100, '8_-8_0_gaia'),
(66, 2, 2, 'Dorna s\'est déplacé en 8,-7,0', '', 'move', 'gaia', 1736118294, 98, '8_-7_0_gaia'),
(67, 2, 2, 'Dorna s\'est déplacé en 7,-7,0', '', 'move', 'gaia', 1736118296, 97, '7_-7_0_gaia'),
(68, 2, 2, 'Dorna s\'est déplacé en 6,-6,0', '', 'move', 'gaia', 1736118297, 30, '6_-6_0_gaia'),
(69, 2, 2, 'Dorna s\'est déplacé en 5,-5,0', '', 'move', 'gaia', 1736118298, 20, '5_-5_0_gaia'),
(70, 2, 2, 'Dorna s\'est déplacé en 4,-4,0', '', 'move', 'gaia', 1736118299, 15, '4_-4_0_gaia'),
(71, 2, 2, 'Dorna s\'est déplacé en 3,-3,0', '', 'move', 'gaia', 1736118300, 13, '3_-3_0_gaia'),
(72, 2, 2, 'Dorna s\'est déplacé en 2,-2,0', '', 'move', 'gaia', 1736118301, 8, '2_-2_0_gaia'),
(73, 2, 2, 'Dorna s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736118301, 4, '1_-1_0_gaia'),
(74, 2, 2, 'Dorna s\'est déplacé en 0,-2,0', '', 'move', 'gaia', 1736118373, 6, '0_-2_0_gaia'),
(75, 2, 2, 'Dorna s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736118382, 4, '1_-1_0_gaia'),
(76, 2, 2, 'Dorna s\'est déplacé en -4,2,0', '', 'move', 'banque_des_lutins', 1736118462, 17182, '-4_2_0_banque_des_lutins'),
(77, 2, 2, 'Dorna s\'est déplacé en 1,1,0', '', 'move', 'banque_des_lutins', 1736118541, 17004, '1_1_0_banque_des_lutins'),
(78, 2, 2, 'Dorna s\'est déplacé en 1,1,0', '', 'move', 'gaia2', 1736120060, 181, '1_1_0_gaia2'),
(79, 2, 1, 'Dorna a attaqué Cradek avec Poing.', '<style>.action-details{display: none;}</style><div style=\"color: #66ccff;\">Réussite!</div><div id=\"data\" style=\"display: none;\"> <div id=\"view\"> <div id=\"svg-container\"> <?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?> <svg xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" version=\"1.1\" baseProfile=\"full\" id=\"svg-view\" width=\"450\" height=\"450\" style=\"background: url(\'img/tiles/gaia2.webp\');\" class=\"box-shadow\" > <image id=\"tiles64\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles115\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"100\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles77\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles72\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles65\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles116\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"50\" y=\"350\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles78\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles73\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles66\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles118\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"150\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles60\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles84\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"400\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles74\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles69\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles62\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles85\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"400\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles75\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles70\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles63\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles113\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"50\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles76\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles71\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"150\" y=\"100\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"350\" y=\"350\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"400\" y=\"350\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"400\" y=\"400\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image id=\"players2\" width=\"50\" height=\"50\" x=\"200\" y=\"200\" href=\"img/avatars/nain/73.png\" class=\"avatar-shadow\" /> <image id=\"players2\" width=\"50\" height=\"50\" data-table=\"players\" x=\"200\" y=\"200\" href=\"img/avatars/nain/73.png\" /> <image id=\"walls117\" width=\"50\" height=\"50\" data-table=\"walls\" x=\"250\" y=\"300\" href=\"img/walls/gaia.png\" /> <image class=\"case \" data-coords=\"-3,5\" x=\"0\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,4\" x=\"0\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,3\" x=\"0\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,2\" x=\"0\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,1\" x=\"0\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,0\" x=\"0\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,-1\" x=\"0\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,-2\" x=\"0\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,-3\" x=\"0\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,5\" x=\"50\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,4\" x=\"50\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,3\" x=\"50\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,2\" x=\"50\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,1\" x=\"50\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,0\" x=\"50\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-1\" x=\"50\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-2\" x=\"50\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-3\" x=\"50\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,5\" x=\"100\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,4\" x=\"100\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,3\" x=\"100\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,2\" x=\"100\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,1\" x=\"100\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,0\" x=\"100\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-1\" x=\"100\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-2\" x=\"100\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-3\" x=\"100\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,5\" x=\"150\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,4\" x=\"150\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,3\" x=\"150\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"0,2\" x=\"150\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"0,1\" x=\"150\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"0,0\" x=\"150\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-1\" x=\"150\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-2\" x=\"150\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-3\" x=\"150\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,5\" x=\"200\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,4\" x=\"200\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,3\" x=\"200\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"1,2\" x=\"200\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"1,1\" x=\"200\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"1,0\" x=\"200\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-1\" x=\"200\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-2\" x=\"200\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-3\" x=\"200\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,5\" x=\"250\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,4\" x=\"250\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,3\" x=\"250\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"2,2\" x=\"250\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"2,1\" x=\"250\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"2,0\" x=\"250\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-1\" x=\"250\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-2\" x=\"250\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-3\" x=\"250\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,5\" x=\"300\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,4\" x=\"300\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,3\" x=\"300\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,2\" x=\"300\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,1\" x=\"300\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,0\" x=\"300\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-1\" x=\"300\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-2\" x=\"300\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-3\" x=\"300\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,5\" x=\"350\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,4\" x=\"350\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,3\" x=\"350\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,2\" x=\"350\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,1\" x=\"350\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,0\" x=\"350\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-1\" x=\"350\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-2\" x=\"350\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-3\" x=\"350\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,5\" x=\"400\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,4\" x=\"400\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,3\" x=\"400\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,2\" x=\"400\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,1\" x=\"400\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,0\" x=\"400\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-1\" x=\"400\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-2\" x=\"400\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-3\" x=\"400\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <rect data-coords=\"\" id=\"go-rect\" x=\"50\" y=\"50\" width=\"50\" height=\"50\" fill=\"green\" style=\"opacity: 0.3; display: none;\" /> <image id=\"go-img\" x=\"50\" y=\"30\" style=\"opacity: 0.8; display: none; pointer-events: none;\" class=\"blink\" href=\"img/ui/view/arrow.webp\" /> <rect data-coords=\"\" id=\"destroy-rect\" x=\"50\" y=\"50\" width=\"50\" height=\"50\" fill=\"red\" style=\"opacity: 0.3; display: none;\" /> <image id=\"destroy-img\" x=\"50\" y=\"30\" style=\"opacity: 0.8; display: none; pointer-events: none; filter: hue-rotate(-100deg); z-index: 100;\" class=\"blink\" href=\"img/ui/view/arrow.webp\" /> </svg> </div> </div> <script> document.addEventListener(\"DOMContentLoaded\", function() { var scrollableDiv = document.getElementById(\"view\"); scrollableDiv.scrollLeft = (scrollableDiv.scrollWidth - scrollableDiv.clientWidth) / 2; }); </script> <div id=\"ajax-data\"></div>    <script src=\"js/view.js\"></script>\n    </div><script>\n$(document).ready(function(){\n\n\n    var data = $(\'#data\').html();\n\n    $(\'#view\').html(\'\').html(data);\n\n\n    $(\'.case\').click(function(e){\n\n        e.preventDefault();\n        e.stopPropagation();\n\n        document.location.reload();\n    });\n\n\n\n    // watch the disapearance of #ui-card to reload view\n\n    var targetNode = document.getElementById(\'ui-card\');\n\n    // Function to check visibility\n    function checkVisibility() {\n        if ($(targetNode).is(\':visible\')) {\n        } else {\n\n            // div is invisible\n            document.location.reload();\n        }\n    }\n\n    // MutationObserver configuration\n    var observer = new MutationObserver(function(mutationsList, observer) {\n        for(var mutation of mutationsList) {\n            if (mutation.attributeName === \'style\' || mutation.attributeName === \'class\') {\n                checkVisibility();\n            }\n        }\n    });\n\n    // Start observing the target node for configured mutations\n    observer.observe(targetNode, { attributes: true, childList: false, subtree: false });\n\n    // Initial check\n    checkVisibility();\n});\n</script>\n', 'action', 'gaia2', 1736120060, 181, '1_1_0_gaia2'),
(80, 2, 2, 'Dorna s\'est déplacé en 1,0,0', '', 'move', 'gaia2', 1736120063, 113, '1_0_0_gaia2'),
(81, 2, 2, 'Dorna s\'est déplacé en 1,-1,0', '', 'move', 'gaia2', 1736120064, 117, '1_-1_0_gaia2'),
(82, 2, 2, 'Dorna s\'est déplacé en 2,-2,0', '', 'move', 'gaia2', 1736120065, 103, '2_-2_0_gaia2'),
(83, 2, 2, 'Dorna s\'est déplacé en 3,-3,0', '', 'move', 'gaia2', 1736120066, 101, '3_-3_0_gaia2'),
(84, 2, 2, 'Dorna s\'est déplacé en 4,-4,0', '', 'move', 'gaia2', 1736120067, 102, '4_-4_0_gaia2'),
(85, 2, 2, 'Dorna s\'est déplacé en 5,-5,0', '', 'move', 'gaia2', 1736120068, 124, '5_-5_0_gaia2'),
(86, 2, 2, 'Dorna s\'est déplacé en 6,-6,0', '', 'move', 'gaia2', 1736120068, 132, '6_-6_0_gaia2'),
(87, 2, 2, 'Dorna est arrivé sur Olympia.', '', 'rez', 'banque_des_lutins', 1736120069, 17065, '6_-6_0_banque_des_lutins'),
(88, 2, 2, 'Dorna s\'est déplacé en 1,0,0', '', 'move', 'banque_des_lutins', 1736120069, 17010, '1_0_0_banque_des_lutins'),
(89, 2, 2, 'Dorna s\'est déplacé en 1,1,0', '', 'move', 'banque_des_lutins', 1736120072, 17004, '1_1_0_banque_des_lutins'),
(90, 1, 1, 'Cradek s\'est déplacé en 0,0,0', '', 'move', 'gaia2', 1736120127, 114, '0_0_0_gaia2'),
(91, 1, 2, 'Cradek a attaqué Dorna avec Poing.', '<style>.action-details{display: none;}</style><div style=\"color: #66ccff;\">Réussite!</div><div id=\"data\" style=\"display: none;\"> <div id=\"view\"> <div id=\"svg-container\"> <?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?> <svg xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" version=\"1.1\" baseProfile=\"full\" id=\"svg-view\" width=\"450\" height=\"450\" style=\"background: url(\'img/tiles/gaia2.webp\');\" class=\"box-shadow\" > <image id=\"tiles75\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles70\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles115\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"50\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles65\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles60\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles76\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles71\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles116\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"100\" y=\"300\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles66\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles61\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles84\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"350\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles72\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles117\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"0\" y=\"200\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles67\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles62\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles85\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"350\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles73\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles118\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"100\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles68\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles113\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"100\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles63\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles74\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles69\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles114\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"0\" y=\"150\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles64\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"250\" y=\"250\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"300\" y=\"400\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"300\" y=\"300\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"0\" y=\"150\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"350\" y=\"350\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"200\" y=\"50\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"400\" y=\"400\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"250\" y=\"150\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_s.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"250\" y=\"200\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_s.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"400\" y=\"300\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image id=\"players1\" width=\"50\" height=\"50\" x=\"200\" y=\"200\" href=\"img/avatars/nain/5.png\" class=\"avatar-shadow\" /> <image id=\"players1\" width=\"50\" height=\"50\" data-table=\"players\" x=\"200\" y=\"200\" href=\"img/avatars/nain/5.png\" /> <image id=\"walls117\" width=\"50\" height=\"50\" data-table=\"walls\" x=\"300\" y=\"250\" href=\"img/walls/gaia.png\" /> <image class=\"case \" data-coords=\"-4,4\" x=\"0\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,3\" x=\"0\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,2\" x=\"0\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,1\" x=\"0\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,0\" x=\"0\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,-1\" x=\"0\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,-2\" x=\"0\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,-3\" x=\"0\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-4,-4\" x=\"0\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,4\" x=\"50\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,3\" x=\"50\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,2\" x=\"50\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,1\" x=\"50\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,0\" x=\"50\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,-1\" x=\"50\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,-2\" x=\"50\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,-3\" x=\"50\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-3,-4\" x=\"50\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,4\" x=\"100\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,3\" x=\"100\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,2\" x=\"100\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,1\" x=\"100\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,0\" x=\"100\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-1\" x=\"100\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-2\" x=\"100\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-3\" x=\"100\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-4\" x=\"100\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,4\" x=\"150\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,3\" x=\"150\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,2\" x=\"150\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"-1,1\" x=\"150\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"-1,0\" x=\"150\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"-1,-1\" x=\"150\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-2\" x=\"150\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-3\" x=\"150\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-4\" x=\"150\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,4\" x=\"200\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,3\" x=\"200\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,2\" x=\"200\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"0,1\" x=\"200\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"0,0\" x=\"200\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"0,-1\" x=\"200\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-2\" x=\"200\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-3\" x=\"200\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-4\" x=\"200\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,4\" x=\"250\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,3\" x=\"250\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,2\" x=\"250\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"1,1\" x=\"250\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"1,0\" x=\"250\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"1,-1\" x=\"250\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-2\" x=\"250\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-3\" x=\"250\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-4\" x=\"250\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,4\" x=\"300\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,3\" x=\"300\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,2\" x=\"300\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,1\" x=\"300\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,0\" x=\"300\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-1\" x=\"300\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-2\" x=\"300\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-3\" x=\"300\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-4\" x=\"300\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,4\" x=\"350\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,3\" x=\"350\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,2\" x=\"350\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,1\" x=\"350\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,0\" x=\"350\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-1\" x=\"350\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-2\" x=\"350\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-3\" x=\"350\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-4\" x=\"350\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,4\" x=\"400\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,3\" x=\"400\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,2\" x=\"400\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,1\" x=\"400\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,0\" x=\"400\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-1\" x=\"400\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-2\" x=\"400\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-3\" x=\"400\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-4\" x=\"400\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <rect data-coords=\"\" id=\"go-rect\" x=\"50\" y=\"50\" width=\"50\" height=\"50\" fill=\"green\" style=\"opacity: 0.3; display: none;\" /> <image id=\"go-img\" x=\"50\" y=\"30\" style=\"opacity: 0.8; display: none; pointer-events: none;\" class=\"blink\" href=\"img/ui/view/arrow.webp\" /> <rect data-coords=\"\" id=\"destroy-rect\" x=\"50\" y=\"50\" width=\"50\" height=\"50\" fill=\"red\" style=\"opacity: 0.3; display: none;\" /> <image id=\"destroy-img\" x=\"50\" y=\"30\" style=\"opacity: 0.8; display: none; pointer-events: none; filter: hue-rotate(-100deg); z-index: 100;\" class=\"blink\" href=\"img/ui/view/arrow.webp\" /> </svg> </div> </div> <script> document.addEventListener(\"DOMContentLoaded\", function() { var scrollableDiv = document.getElementById(\"view\"); scrollableDiv.scrollLeft = (scrollableDiv.scrollWidth - scrollableDiv.clientWidth) / 2; }); </script> <div id=\"ajax-data\"></div>    <script src=\"js/view.js\"></script>\n    </div><script>\n$(document).ready(function(){\n\n\n    var data = $(\'#data\').html();\n\n    $(\'#view\').html(\'\').html(data);\n\n\n    $(\'.case\').click(function(e){\n\n        e.preventDefault();\n        e.stopPropagation();\n\n        document.location.reload();\n    });\n\n\n\n    // watch the disapearance of #ui-card to reload view\n\n    var targetNode = document.getElementById(\'ui-card\');\n\n    // Function to check visibility\n    function checkVisibility() {\n        if ($(targetNode).is(\':visible\')) {\n        } else {\n\n            // div is invisible\n            document.location.reload();\n        }\n    }\n\n    // MutationObserver configuration\n    var observer = new MutationObserver(function(mutationsList, observer) {\n        for(var mutation of mutationsList) {\n            if (mutation.attributeName === \'style\' || mutation.attributeName === \'class\') {\n                checkVisibility();\n            }\n        }\n    });\n\n    // Start observing the target node for configured mutations\n    observer.observe(targetNode, { attributes: true, childList: false, subtree: false });\n\n    // Initial check\n    checkVisibility();\n});\n</script>\n', 'action', 'gaia2', 1736120127, 114, '0_0_0_gaia2'),
(92, 1, 1, 'Cradek s\'est déplacé en 1,-1,0', '', 'move', 'gaia2', 1736120130, 117, '1_-1_0_gaia2'),
(93, 1, 1, 'Cradek s\'est déplacé en 2,-2,0', '', 'move', 'gaia2', 1736120131, 103, '2_-2_0_gaia2'),
(94, 1, 1, 'Cradek s\'est déplacé en 3,-3,0', '', 'move', 'gaia2', 1736120131, 101, '3_-3_0_gaia2'),
(95, 1, 1, 'Cradek s\'est déplacé en 4,-4,0', '', 'move', 'gaia2', 1736120132, 102, '4_-4_0_gaia2'),
(96, 1, 1, 'Cradek s\'est déplacé en 5,-5,0', '', 'move', 'gaia2', 1736120133, 124, '5_-5_0_gaia2'),
(97, 1, 1, 'Cradek s\'est déplacé en 6,-6,0', '', 'move', 'gaia2', 1736120133, 132, '6_-6_0_gaia2'),
(98, 1, 1, 'Cradek est arrivé sur Olympia.', '', 'rez', 'banque_des_lutins', 1736120134, 17065, '6_-6_0_banque_des_lutins'),
(99, 1, 1, 'Cradek s\'est déplacé en -1,-1,0', '', 'move', 'banque_des_lutins', 1736120134, 17015, '-1_-1_0_banque_des_lutins'),
(100, 1, 1, 'Cradek s\'est déplacé en 0,0,0', '', 'move', 'banque_des_lutins', 1736120136, 15318, '0_0_0_banque_des_lutins'),
(101, 3, 3, 'Thyrias s\'est déplacé en 1,-1,0', '', 'move', 'gaia', 1736120219, 4, '1_-1_0_gaia'),
(102, 3, 3, 'Thyrias s\'est déplacé en 2,-2,0', '', 'move', 'gaia', 1736120220, 8, '2_-2_0_gaia'),
(103, 3, 3, 'Thyrias s\'est déplacé en 3,-3,0', '', 'move', 'gaia', 1736120221, 13, '3_-3_0_gaia'),
(104, 3, 3, 'Thyrias s\'est déplacé en 3,-3,0', '', 'move', 'gaia2', 1736120472, 101, '3_-3_0_gaia2');
INSERT INTO `players_logs` (`id`, `player_id`, `target_id`, `text`, `hiddenText`, `type`, `plan`, `time`, `coords_id`, `coords_computed`) VALUES
(105, 3, -1, 'Thyrias a attaqué Gaïa avec Poing.', '<style>.action-details{display: none;}</style><div style=\"color: #66ccff;\">Réussite!</div><div id=\"data\" style=\"display: none;\"> <div id=\"view\"> <div id=\"svg-container\"> <?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?> <svg xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" version=\"1.1\" baseProfile=\"full\" id=\"svg-view\" width=\"550\" height=\"550\" style=\"background: url(\'img/tiles/gaia2.webp\');\" class=\"box-shadow\" > <image id=\"tiles77\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles93\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"450\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles61\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles72\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"100\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles88\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"400\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles83\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles113\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"0\" y=\"150\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles67\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles78\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles94\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"450\" y=\"400\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles62\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles73\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"100\" y=\"100\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles89\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles84\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"100\" y=\"250\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles116\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"0\" y=\"200\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles68\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles79\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"300\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles95\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"450\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles63\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles74\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"100\" y=\"150\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles90\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles85\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"250\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles118\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"100\" y=\"0\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles69\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles80\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles96\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"500\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles64\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles75\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"100\" y=\"200\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles91\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"400\" y=\"400\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles86\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"400\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles70\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"150\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles81\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles104\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"350\" y=\"500\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles65\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles76\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"150\" y=\"150\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles92\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"450\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles60\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles87\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"250\" y=\"400\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles71\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"100\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles82\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"300\" y=\"350\" href=\"img/tiles/carreaux.png\" /> <image id=\"tiles107\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"500\" y=\"400\" href=\"img/tiles/falaise.png\" /> <image id=\"tiles66\" width=\"50\" height=\"50\" data-table=\"tiles\" x=\"200\" y=\"250\" href=\"img/tiles/carreaux.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"300\" y=\"350\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"350\" y=\"200\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"300\" y=\"300\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"150\" y=\"50\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_s.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"350\" y=\"250\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"350\" y=\"350\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"150\" y=\"100\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_s.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"350\" y=\"300\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"400\" y=\"400\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"150\" y=\"150\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"200\" y=\"350\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"300\" y=\"200\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"100\" y=\"100\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"200\" y=\"200\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"250\" y=\"350\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"200\" y=\"300\" style=\"opacity: 1;\" href=\"img/elements/diamant.png\" /> <image width=\"50\" height=\"50\" data-table=\"elements\" x=\"250\" y=\"250\" style=\"opacity: 0.5;\" href=\"img/elements/trace_pas_se.webp\" /> <image id=\"players3\" width=\"50\" height=\"50\" x=\"250\" y=\"250\" href=\"img/avatars/ame/elfe.webp\" class=\"avatar-shadow\" /> <image id=\"players3\" width=\"50\" height=\"50\" data-table=\"players\" x=\"250\" y=\"250\" href=\"img/avatars/ame/elfe.webp\" /> <image id=\"walls117\" width=\"50\" height=\"50\" data-table=\"walls\" x=\"200\" y=\"150\" href=\"img/walls/gaia.png\" /> <image id=\"foregrounds5\" width=\"50\" height=\"50\" data-table=\"foregrounds\" x=\"450\" y=\"450\" href=\"img/foregrounds/olympia-00.png\" /> <image id=\"foregrounds6\" width=\"50\" height=\"50\" data-table=\"foregrounds\" x=\"500\" y=\"450\" href=\"img/foregrounds/olympia-01.png\" /> <image id=\"foregrounds7\" width=\"50\" height=\"50\" data-table=\"foregrounds\" x=\"450\" y=\"500\" href=\"img/foregrounds/olympia-02.png\" /> <image id=\"foregrounds8\" width=\"50\" height=\"50\" data-table=\"foregrounds\" x=\"500\" y=\"500\" href=\"img/foregrounds/olympia-03.png\" /> <use xlink:href=\"#foregrounds5\" /><use xlink:href=\"#foregrounds6\" /><use xlink:href=\"#foregrounds7\" /><use xlink:href=\"#foregrounds8\" /> <image class=\"case \" data-coords=\"-2,2\" x=\"0\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,1\" x=\"0\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,0\" x=\"0\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-1\" x=\"0\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-2\" x=\"0\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-3\" x=\"0\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-4\" x=\"0\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-5\" x=\"0\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-6\" x=\"0\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-7\" x=\"0\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-2,-8\" x=\"0\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,2\" x=\"50\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,1\" x=\"50\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,0\" x=\"50\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-1\" x=\"50\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-2\" x=\"50\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-3\" x=\"50\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-4\" x=\"50\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-5\" x=\"50\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-6\" x=\"50\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-7\" x=\"50\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"-1,-8\" x=\"50\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,2\" x=\"100\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,1\" x=\"100\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,0\" x=\"100\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-1\" x=\"100\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-2\" x=\"100\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-3\" x=\"100\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-4\" x=\"100\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-5\" x=\"100\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-6\" x=\"100\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-7\" x=\"100\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"0,-8\" x=\"100\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,2\" x=\"150\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,1\" x=\"150\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,0\" x=\"150\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-1\" x=\"150\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-2\" x=\"150\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-3\" x=\"150\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-4\" x=\"150\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-5\" x=\"150\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-6\" x=\"150\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-7\" x=\"150\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"1,-8\" x=\"150\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,2\" x=\"200\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,1\" x=\"200\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,0\" x=\"200\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-1\" x=\"200\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"2,-2\" x=\"200\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"2,-3\" x=\"200\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"2,-4\" x=\"200\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-5\" x=\"200\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-6\" x=\"200\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-7\" x=\"200\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"2,-8\" x=\"200\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,2\" x=\"250\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,1\" x=\"250\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,0\" x=\"250\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-1\" x=\"250\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"3,-2\" x=\"250\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"3,-3\" x=\"250\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"3,-4\" x=\"250\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-5\" x=\"250\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-6\" x=\"250\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-7\" x=\"250\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"3,-8\" x=\"250\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,2\" x=\"300\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,1\" x=\"300\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,0\" x=\"300\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-1\" x=\"300\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"4,-2\" x=\"300\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"4,-3\" x=\"300\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case go\" data-coords=\"4,-4\" x=\"300\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-5\" x=\"300\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-6\" x=\"300\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-7\" x=\"300\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"4,-8\" x=\"300\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,2\" x=\"350\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,1\" x=\"350\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,0\" x=\"350\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-1\" x=\"350\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-2\" x=\"350\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-3\" x=\"350\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-4\" x=\"350\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-5\" x=\"350\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-6\" x=\"350\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-7\" x=\"350\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"5,-8\" x=\"350\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,2\" x=\"400\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,1\" x=\"400\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,0\" x=\"400\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-1\" x=\"400\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-2\" x=\"400\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-3\" x=\"400\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-4\" x=\"400\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-5\" x=\"400\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-6\" x=\"400\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-7\" x=\"400\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"6,-8\" x=\"400\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,2\" x=\"450\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,1\" x=\"450\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,0\" x=\"450\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-1\" x=\"450\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-2\" x=\"450\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-3\" x=\"450\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-4\" x=\"450\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-5\" x=\"450\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-6\" x=\"450\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-7\" x=\"450\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"7,-8\" x=\"450\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,2\" x=\"500\" y=\"0\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,1\" x=\"500\" y=\"50\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,0\" x=\"500\" y=\"100\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-1\" x=\"500\" y=\"150\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-2\" x=\"500\" y=\"200\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-3\" x=\"500\" y=\"250\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-4\" x=\"500\" y=\"300\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-5\" x=\"500\" y=\"350\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-6\" x=\"500\" y=\"400\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-7\" x=\"500\" y=\"450\" href=\"img/ui/view/grid.webp\" /> <image class=\"case \" data-coords=\"8,-8\" x=\"500\" y=\"500\" href=\"img/ui/view/grid.webp\" /> <rect data-coords=\"\" id=\"go-rect\" x=\"50\" y=\"50\" width=\"50\" height=\"50\" fill=\"green\" style=\"opacity: 0.3; display: none;\" /> <image id=\"go-img\" x=\"50\" y=\"30\" style=\"opacity: 0.8; display: none; pointer-events: none;\" class=\"blink\" href=\"img/ui/view/arrow.webp\" /> <rect data-coords=\"\" id=\"destroy-rect\" x=\"50\" y=\"50\" width=\"50\" height=\"50\" fill=\"red\" style=\"opacity: 0.3; display: none;\" /> <image id=\"destroy-img\" x=\"50\" y=\"30\" style=\"opacity: 0.8; display: none; pointer-events: none; filter: hue-rotate(-100deg); z-index: 100;\" class=\"blink\" href=\"img/ui/view/arrow.webp\" /> </svg> </div> </div> <script> document.addEventListener(\"DOMContentLoaded\", function() { var scrollableDiv = document.getElementById(\"view\"); scrollableDiv.scrollLeft = (scrollableDiv.scrollWidth - scrollableDiv.clientWidth) / 2; }); </script> <div id=\"ajax-data\"></div>    <script src=\"js/view.js\"></script>\n    </div><script>\n$(document).ready(function(){\n\n\n    var data = $(\'#data\').html();\n\n    $(\'#view\').html(\'\').html(data);\n\n\n    $(\'.case\').click(function(e){\n\n        e.preventDefault();\n        e.stopPropagation();\n\n        document.location.reload();\n    });\n\n\n\n    // watch the disapearance of #ui-card to reload view\n\n    var targetNode = document.getElementById(\'ui-card\');\n\n    // Function to check visibility\n    function checkVisibility() {\n        if ($(targetNode).is(\':visible\')) {\n        } else {\n\n            // div is invisible\n            document.location.reload();\n        }\n    }\n\n    // MutationObserver configuration\n    var observer = new MutationObserver(function(mutationsList, observer) {\n        for(var mutation of mutationsList) {\n            if (mutation.attributeName === \'style\' || mutation.attributeName === \'class\') {\n                checkVisibility();\n            }\n        }\n    });\n\n    // Start observing the target node for configured mutations\n    observer.observe(targetNode, { attributes: true, childList: false, subtree: false });\n\n    // Initial check\n    checkVisibility();\n});\n</script>\n', 'action', 'gaia2', 1736120472, 101, '3_-3_0_gaia2'),
(106, 3, 3, 'Thyrias s\'est déplacé en 4,-4,0', '', 'move', 'gaia2', 1736120475, 102, '4_-4_0_gaia2'),
(107, 3, 3, 'Thyrias s\'est déplacé en 5,-5,0', '', 'move', 'gaia2', 1736120476, 124, '5_-5_0_gaia2'),
(108, 3, 3, 'Thyrias s\'est déplacé en 6,-6,0', '', 'move', 'gaia2', 1736120477, 132, '6_-6_0_gaia2'),
(109, 3, 3, 'Thyrias est arrivé sur Olympia.', '', 'rez', 'arcadia', 1736120478, 16728, '6_-6_0_arcadia'),
(110, 3, 3, 'Thyrias s\'est déplacé en -1,0,0', '', 'move', 'arcadia', 1736120478, 15472, '-1_0_0_arcadia'),
(111, 3, 3, 'Thyrias s\'est déplacé en 0,0,0', '', 'move', 'arcadia', 1736120551, 15457, '0_0_0_arcadia'),
(112, 3, 3, 'Thyrias s\'est déplacé en -1,0,0', '', 'move', 'arcadia', 1736120555, 15472, '-1_0_0_arcadia');

-- --------------------------------------------------------

--
-- Structure de la table `players_logs_archives`
--

CREATE TABLE `players_logs_archives` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `text` varchar(255) NOT NULL DEFAULT '',
  `hiddenText` text NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `plan` varchar(255) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0,
  `coords_id` int(11) DEFAULT 0,
  `coords_computed` varchar(35) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_options`
--

CREATE TABLE `players_options` (
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `players_options`
--

INSERT INTO `players_options` (`player_id`, `name`) VALUES
(1, 'isAdmin');

-- --------------------------------------------------------

--
-- Structure de la table `players_pnjs`
--

CREATE TABLE `players_pnjs` (
  `player_id` int(11) NOT NULL,
  `pnj_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_psw`
--

CREATE TABLE `players_psw` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL DEFAULT 0,
  `uniqid` varchar(255) NOT NULL DEFAULT '',
  `sentTime` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_quests`
--

CREATE TABLE `players_quests` (
  `player_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `startTime` int(11) NOT NULL DEFAULT 0,
  `endTime` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_quests_steps`
--

CREATE TABLE `players_quests_steps` (
  `player_id` int(11) NOT NULL,
  `quest_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `endTime` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `players_upgrades`
--

CREATE TABLE `players_upgrades` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `cost` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `quests`
--

CREATE TABLE `quests` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `text` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `races`
--

CREATE TABLE `races` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `playable` tinyint(1) DEFAULT NULL,
  `hidden` tinyint(1) DEFAULT NULL,
  `portraitNextNumber` int(11) DEFAULT NULL,
  `avatarNextNumber` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `coords`
--
ALTER TABLE `coords`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `forums_keywords`
--
ALTER TABLE `forums_keywords`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blessed_by_id` (`blessed_by_id`);

--
-- Index pour la table `items_asks`
--
ALTER TABLE `items_asks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `items_bids`
--
ALTER TABLE `items_bids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `items_exchanges`
--
ALTER TABLE `items_exchanges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `items_exchanges_fk_1` (`player_id`),
  ADD KEY `items_exchanges_fk_2` (`target_id`);

--
-- Index pour la table `map_dialogs`
--
ALTER TABLE `map_dialogs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `map_elements`
--
ALTER TABLE `map_elements`
  ADD PRIMARY KEY (`name`,`coords_id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `map_foregrounds`
--
ALTER TABLE `map_foregrounds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `map_items`
--
ALTER TABLE `map_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `map_plants`
--
ALTER TABLE `map_plants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `map_tiles`
--
ALTER TABLE `map_tiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coords_id` (`coords_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `map_triggers`
--
ALTER TABLE `map_triggers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `map_walls`
--
ALTER TABLE `map_walls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coords_id` (`coords_id`);

--
-- Index pour la table `players_actions`
--
ALTER TABLE `players_actions`
  ADD PRIMARY KEY (`player_id`,`name`);

--
-- Index pour la table `players_assists`
--
ALTER TABLE `players_assists`
  ADD PRIMARY KEY (`player_id`,`target_id`),
  ADD KEY `target_id` (`target_id`);

--
-- Index pour la table `players_banned`
--
ALTER TABLE `players_banned`
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `players_bonus`
--
ALTER TABLE `players_bonus`
  ADD PRIMARY KEY (`player_id`,`name`);

--
-- Index pour la table `players_connections`
--
ALTER TABLE `players_connections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `players_effects`
--
ALTER TABLE `players_effects`
  ADD PRIMARY KEY (`player_id`,`name`);

--
-- Index pour la table `players_followers`
--
ALTER TABLE `players_followers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `foreground_id` (`foreground_id`);

--
-- Index pour la table `players_forum_missives`
--
ALTER TABLE `players_forum_missives`
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `players_forum_rewards`
--
ALTER TABLE `players_forum_rewards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `from_player_id` (`from_player_id`),
  ADD KEY `to_player_id` (`to_player_id`);

--
-- Index pour la table `players_ips`
--
ALTER TABLE `players_ips`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `players_items`
--
ALTER TABLE `players_items`
  ADD PRIMARY KEY (`player_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Index pour la table `players_items_bank`
--
ALTER TABLE `players_items_bank`
  ADD PRIMARY KEY (`player_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Index pour la table `players_items_exchanges`
--
ALTER TABLE `players_items_exchanges`
  ADD KEY `players_items_exchanges_fk_1` (`exchange_id`),
  ADD KEY `players_items_exchanges_fk_2` (`item_id`),
  ADD KEY `players_items_exchanges_fk_3` (`player_id`),
  ADD KEY `players_items_exchanges_fk_4` (`target_id`);

--
-- Index pour la table `players_kills`
--
ALTER TABLE `players_kills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `target_id` (`target_id`);

--
-- Index pour la table `players_logs`
--
ALTER TABLE `players_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `target_id` (`target_id`),
  ADD KEY `players_logs_coords_fk_1` (`coords_id`);

--
-- Index pour la table `players_logs_archives`
--
ALTER TABLE `players_logs_archives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`),
  ADD KEY `target_id` (`target_id`),
  ADD KEY `players_logs_archives_coords_fk_1` (`coords_id`);

--
-- Index pour la table `players_options`
--
ALTER TABLE `players_options`
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `players_pnjs`
--
ALTER TABLE `players_pnjs`
  ADD KEY `player_id` (`player_id`),
  ADD KEY `pnj_id` (`pnj_id`);

--
-- Index pour la table `players_psw`
--
ALTER TABLE `players_psw`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `players_quests`
--
ALTER TABLE `players_quests`
  ADD PRIMARY KEY (`player_id`,`quest_id`),
  ADD KEY `quest_id` (`quest_id`);

--
-- Index pour la table `players_quests_steps`
--
ALTER TABLE `players_quests_steps`
  ADD PRIMARY KEY (`player_id`,`quest_id`,`name`),
  ADD KEY `quest_id` (`quest_id`);

--
-- Index pour la table `players_upgrades`
--
ALTER TABLE `players_upgrades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `player_id` (`player_id`);

--
-- Index pour la table `quests`
--
ALTER TABLE `quests`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `races`
--
ALTER TABLE `races`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `coords`
--
ALTER TABLE `coords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51588;

--
-- AUTO_INCREMENT pour la table `forums_keywords`
--
ALTER TABLE `forums_keywords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT pour la table `items_asks`
--
ALTER TABLE `items_asks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `items_bids`
--
ALTER TABLE `items_bids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `items_exchanges`
--
ALTER TABLE `items_exchanges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `map_dialogs`
--
ALTER TABLE `map_dialogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `map_elements`
--
ALTER TABLE `map_elements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=596;

--
-- AUTO_INCREMENT pour la table `map_foregrounds`
--
ALTER TABLE `map_foregrounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT pour la table `map_items`
--
ALTER TABLE `map_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `map_plants`
--
ALTER TABLE `map_plants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT pour la table `map_tiles`
--
ALTER TABLE `map_tiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1041;

--
-- AUTO_INCREMENT pour la table `map_triggers`
--
ALTER TABLE `map_triggers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=180;

--
-- AUTO_INCREMENT pour la table `map_walls`
--
ALTER TABLE `map_walls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=355;

--
-- AUTO_INCREMENT pour la table `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `players_connections`
--
ALTER TABLE `players_connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `players_followers`
--
ALTER TABLE `players_followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `players_forum_rewards`
--
ALTER TABLE `players_forum_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `players_ips`
--
ALTER TABLE `players_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `players_kills`
--
ALTER TABLE `players_kills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `players_logs`
--
ALTER TABLE `players_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT pour la table `players_logs_archives`
--
ALTER TABLE `players_logs_archives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `players_psw`
--
ALTER TABLE `players_psw`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `players_upgrades`
--
ALTER TABLE `players_upgrades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `quests`
--
ALTER TABLE `quests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `races`
--
ALTER TABLE `races`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`blessed_by_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `items_asks`
--
ALTER TABLE `items_asks`
  ADD CONSTRAINT `items_asks_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `items_asks_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `items_bids`
--
ALTER TABLE `items_bids`
  ADD CONSTRAINT `items_bids_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `items_bids_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `items_exchanges`
--
ALTER TABLE `items_exchanges`
  ADD CONSTRAINT `items_exchanges_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `items_exchanges_fk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `map_dialogs`
--
ALTER TABLE `map_dialogs`
  ADD CONSTRAINT `map_dialogs_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `map_elements`
--
ALTER TABLE `map_elements`
  ADD CONSTRAINT `map_elements_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `map_foregrounds`
--
ALTER TABLE `map_foregrounds`
  ADD CONSTRAINT `map_foregrounds_ibfk_3` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `map_items`
--
ALTER TABLE `map_items`
  ADD CONSTRAINT `map_items_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `map_items_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `map_tiles`
--
ALTER TABLE `map_tiles`
  ADD CONSTRAINT `map_tiles_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`),
  ADD CONSTRAINT `map_tiles_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `map_triggers`
--
ALTER TABLE `map_triggers`
  ADD CONSTRAINT `map_triggers_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `map_walls`
--
ALTER TABLE `map_walls`
  ADD CONSTRAINT `map_walls_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `map_walls_ibfk_2` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `players`
--
ALTER TABLE `players`
  ADD CONSTRAINT `players_ibfk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `players_actions`
--
ALTER TABLE `players_actions`
  ADD CONSTRAINT `players_actions_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_assists`
--
ALTER TABLE `players_assists`
  ADD CONSTRAINT `players_assists_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_assists_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_banned`
--
ALTER TABLE `players_banned`
  ADD CONSTRAINT `players_banned_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_bonus`
--
ALTER TABLE `players_bonus`
  ADD CONSTRAINT `players_bonus_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_connections`
--
ALTER TABLE `players_connections`
  ADD CONSTRAINT `players_connections_fk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_effects`
--
ALTER TABLE `players_effects`
  ADD CONSTRAINT `players_effects_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_followers`
--
ALTER TABLE `players_followers`
  ADD CONSTRAINT `players_followers_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_followers_ibfk_3` FOREIGN KEY (`foreground_id`) REFERENCES `map_foregrounds` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `players_forum_missives`
--
ALTER TABLE `players_forum_missives`
  ADD CONSTRAINT `players_forum_missives_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_forum_rewards`
--
ALTER TABLE `players_forum_rewards`
  ADD CONSTRAINT `players_forum_rewards_ibfk_1` FOREIGN KEY (`from_player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_forum_rewards_ibfk_2` FOREIGN KEY (`to_player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_items`
--
ALTER TABLE `players_items`
  ADD CONSTRAINT `players_items_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Contraintes pour la table `players_items_exchanges`
--
ALTER TABLE `players_items_exchanges`
  ADD CONSTRAINT `players_items_exchanges_fk_1` FOREIGN KEY (`exchange_id`) REFERENCES `items_exchanges` (`id`),
  ADD CONSTRAINT `players_items_exchanges_fk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `players_items_exchanges_fk_3` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_items_exchanges_fk_4` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_kills`
--
ALTER TABLE `players_kills`
  ADD CONSTRAINT `players_kills_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_kills_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_logs`
--
ALTER TABLE `players_logs`
  ADD CONSTRAINT `players_logs_coords_fk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`),
  ADD CONSTRAINT `players_logs_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_logs_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_logs_archives`
--
ALTER TABLE `players_logs_archives`
  ADD CONSTRAINT `players_logs_archives_coords_fk_1` FOREIGN KEY (`coords_id`) REFERENCES `coords` (`id`);

--
-- Contraintes pour la table `players_options`
--
ALTER TABLE `players_options`
  ADD CONSTRAINT `players_options_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_pnjs`
--
ALTER TABLE `players_pnjs`
  ADD CONSTRAINT `players_pnjs_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_pnjs_ibfk_2` FOREIGN KEY (`pnj_id`) REFERENCES `players` (`id`);

--
-- Contraintes pour la table `players_quests`
--
ALTER TABLE `players_quests`
  ADD CONSTRAINT `players_quests_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_quests_ibfk_2` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`);

--
-- Contraintes pour la table `players_quests_steps`
--
ALTER TABLE `players_quests_steps`
  ADD CONSTRAINT `players_quests_steps_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  ADD CONSTRAINT `players_quests_steps_ibfk_2` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`);

--
-- Contraintes pour la table `players_upgrades`
--
ALTER TABLE `players_upgrades`
  ADD CONSTRAINT `players_upgrades_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

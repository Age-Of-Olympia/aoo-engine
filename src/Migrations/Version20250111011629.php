<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250111011629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE map_walls DROP FOREIGN KEY map_walls_ibfk_1');
        $this->addSql('ALTER TABLE map_walls DROP FOREIGN KEY map_walls_ibfk_2');
        $this->addSql('ALTER TABLE map_triggers DROP FOREIGN KEY map_triggers_ibfk_2');
        $this->addSql('ALTER TABLE map_tiles DROP FOREIGN KEY map_tiles_ibfk_1');
        $this->addSql('ALTER TABLE map_tiles DROP FOREIGN KEY map_tiles_ibfk_2');
        $this->addSql('ALTER TABLE items_bids DROP FOREIGN KEY items_bids_ibfk_1');
        $this->addSql('ALTER TABLE items_bids DROP FOREIGN KEY items_bids_ibfk_2');
        $this->addSql('ALTER TABLE players_effects DROP FOREIGN KEY players_effects_ibfk_1');
        $this->addSql('ALTER TABLE players_followers DROP FOREIGN KEY players_followers_ibfk_1');
        $this->addSql('ALTER TABLE players_followers DROP FOREIGN KEY players_followers_ibfk_3');
        $this->addSql('ALTER TABLE items_asks DROP FOREIGN KEY items_asks_ibfk_2');
        $this->addSql('ALTER TABLE items_asks DROP FOREIGN KEY items_asks_ibfk_1');
        $this->addSql('ALTER TABLE players_banned DROP FOREIGN KEY players_banned_ibfk_1');
        $this->addSql('ALTER TABLE players_logs DROP FOREIGN KEY players_logs_ibfk_1');
        $this->addSql('ALTER TABLE players_logs DROP FOREIGN KEY players_logs_ibfk_2');
        $this->addSql('ALTER TABLE players_logs DROP FOREIGN KEY players_logs_coords_fk_1');
        $this->addSql('ALTER TABLE map_dialogs DROP FOREIGN KEY map_dialogs_ibfk_1');
        $this->addSql('ALTER TABLE map_items DROP FOREIGN KEY map_items_ibfk_1');
        $this->addSql('ALTER TABLE map_items DROP FOREIGN KEY map_items_ibfk_2');
        $this->addSql('ALTER TABLE map_elements DROP FOREIGN KEY map_elements_ibfk_1');
        $this->addSql('ALTER TABLE players_actions DROP FOREIGN KEY players_actions_ibfk_1');
        $this->addSql('ALTER TABLE players_connections DROP FOREIGN KEY players_connections_fk_1');
        $this->addSql('ALTER TABLE players_forum_missives DROP FOREIGN KEY players_forum_missives_ibfk_1');
        $this->addSql('ALTER TABLE players_assists DROP FOREIGN KEY players_assists_ibfk_1');
        $this->addSql('ALTER TABLE players_assists DROP FOREIGN KEY players_assists_ibfk_2');
        $this->addSql('ALTER TABLE map_foregrounds DROP FOREIGN KEY map_foregrounds_ibfk_3');
        $this->addSql('ALTER TABLE players_upgrades DROP FOREIGN KEY players_upgrades_ibfk_1');
        $this->addSql('ALTER TABLE players_logs_archives DROP FOREIGN KEY players_logs_archives_coords_fk_1');
        $this->addSql('ALTER TABLE players_bonus DROP FOREIGN KEY players_bonus_ibfk_1');
        $this->addSql('ALTER TABLE players_kills DROP FOREIGN KEY players_kills_ibfk_1');
        $this->addSql('ALTER TABLE players_kills DROP FOREIGN KEY players_kills_ibfk_2');
        $this->addSql('ALTER TABLE players_quests_steps DROP FOREIGN KEY players_quests_steps_ibfk_1');
        $this->addSql('ALTER TABLE players_quests_steps DROP FOREIGN KEY players_quests_steps_ibfk_2');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY items_ibfk_1');
        $this->addSql('ALTER TABLE players_pnjs DROP FOREIGN KEY players_pnjs_ibfk_1');
        $this->addSql('ALTER TABLE players_pnjs DROP FOREIGN KEY players_pnjs_ibfk_2');
        $this->addSql('ALTER TABLE players_items DROP FOREIGN KEY players_items_ibfk_2');
        $this->addSql('ALTER TABLE players_items DROP FOREIGN KEY players_items_ibfk_1');
        $this->addSql('ALTER TABLE players_options DROP FOREIGN KEY players_options_ibfk_1');
        $this->addSql('ALTER TABLE players_items_exchanges DROP FOREIGN KEY players_items_exchanges_fk_2');
        $this->addSql('ALTER TABLE players_items_exchanges DROP FOREIGN KEY players_items_exchanges_fk_3');
        $this->addSql('ALTER TABLE players_items_exchanges DROP FOREIGN KEY players_items_exchanges_fk_1');
        $this->addSql('ALTER TABLE players_items_exchanges DROP FOREIGN KEY players_items_exchanges_fk_4');
        $this->addSql('ALTER TABLE players_forum_rewards DROP FOREIGN KEY players_forum_rewards_ibfk_1');
        $this->addSql('ALTER TABLE players_forum_rewards DROP FOREIGN KEY players_forum_rewards_ibfk_2');
        $this->addSql('ALTER TABLE players DROP FOREIGN KEY players_ibfk_1');
        $this->addSql('ALTER TABLE items_exchanges DROP FOREIGN KEY items_exchanges_fk_1');
        $this->addSql('ALTER TABLE items_exchanges DROP FOREIGN KEY items_exchanges_fk_2');
        $this->addSql('ALTER TABLE players_quests DROP FOREIGN KEY players_quests_ibfk_2');
        $this->addSql('ALTER TABLE players_quests DROP FOREIGN KEY players_quests_ibfk_1');
        $this->addSql('DROP TABLE map_walls');
        $this->addSql('DROP TABLE map_triggers');
        $this->addSql('DROP TABLE map_tiles');
        $this->addSql('DROP TABLE coords');
        $this->addSql('DROP TABLE items_bids');
        $this->addSql('DROP TABLE races');
        $this->addSql('DROP TABLE players_items_bank');
        $this->addSql('DROP TABLE players_effects');
        $this->addSql('DROP TABLE players_followers');
        $this->addSql('DROP TABLE items_asks');
        $this->addSql('DROP TABLE players_banned');
        $this->addSql('DROP TABLE players_logs');
        $this->addSql('DROP TABLE quests');
        $this->addSql('DROP TABLE map_dialogs');
        $this->addSql('DROP TABLE map_items');
        $this->addSql('DROP TABLE map_elements');
        $this->addSql('DROP TABLE players_actions');
        $this->addSql('DROP TABLE players_connections');
        $this->addSql('DROP TABLE players_forum_missives');
        $this->addSql('DROP TABLE players_assists');
        $this->addSql('DROP TABLE map_foregrounds');
        $this->addSql('DROP TABLE players_upgrades');
        $this->addSql('DROP TABLE players_psw');
        $this->addSql('DROP TABLE players_logs_archives');
        $this->addSql('DROP TABLE players_bonus');
        $this->addSql('DROP TABLE players_kills');
        $this->addSql('DROP TABLE players_quests_steps');
        $this->addSql('DROP TABLE forums_keywords');
        $this->addSql('DROP TABLE items');
        $this->addSql('DROP TABLE players_pnjs');
        $this->addSql('DROP TABLE players_items');
        $this->addSql('DROP TABLE players_options');
        $this->addSql('DROP TABLE players_items_exchanges');
        $this->addSql('DROP TABLE map_plants');
        $this->addSql('DROP TABLE players_forum_rewards');
        $this->addSql('DROP TABLE players');
        $this->addSql('DROP TABLE players_ips');
        $this->addSql('DROP TABLE items_exchanges');
        $this->addSql('DROP TABLE players_quests');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE map_walls (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, player_id INT DEFAULT NULL, coords_id INT NOT NULL, damages INT DEFAULT 0 NOT NULL, INDEX player_id (player_id), INDEX coords_id (coords_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_triggers (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, coords_id INT NOT NULL, params VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX coords_id (coords_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_tiles (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, coords_id INT NOT NULL, foreground INT DEFAULT 0 NOT NULL, player_id INT DEFAULT NULL, INDEX player_id (player_id), INDEX coords_id (coords_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE coords (id INT AUTO_INCREMENT NOT NULL, x INT DEFAULT 0 NOT NULL, y INT DEFAULT 0 NOT NULL, z INT DEFAULT 0 NOT NULL, plan VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE items_bids (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, player_id INT NOT NULL, price INT NOT NULL, n INT NOT NULL, stock INT NOT NULL, INDEX item_id (item_id), INDEX player_id (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE races (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, code VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_uca1400_ai_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_uca1400_ai_ci`, playable TINYINT(1) DEFAULT NULL, hidden TINYINT(1) DEFAULT NULL, portraitNextNumber INT DEFAULT NULL, avatarNextNumber INT DEFAULT NULL, UNIQUE INDEX code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_items_bank (player_id INT NOT NULL, item_id INT NOT NULL, n INT DEFAULT 0 NOT NULL, INDEX item_id (item_id), PRIMARY KEY(player_id, item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_effects (player_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, endTime INT DEFAULT NULL, INDEX IDX_5E51ED8099E6F5DF (player_id), PRIMARY KEY(player_id, name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_followers (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, foreground_id INT NOT NULL, params VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX player_id (player_id), INDEX foreground_id (foreground_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE items_asks (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, player_id INT NOT NULL, price INT NOT NULL, n INT NOT NULL, stock INT NOT NULL, INDEX item_id (item_id), INDEX player_id (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_banned (player_id INT NOT NULL, ips TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, text VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, INDEX player_id (player_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_logs (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, target_id INT NOT NULL, text VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, hiddenText TEXT CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, plan VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, time INT DEFAULT 0 NOT NULL, coords_id INT DEFAULT 0, coords_computed VARCHAR(35) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX players_logs_coords_fk_1 (coords_id), INDEX player_id (player_id), INDEX target_id (target_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE quests (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, text VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_dialogs (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, coords_id INT NOT NULL, params VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX coords_id (coords_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_items (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, coords_id INT NOT NULL, n INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX coords_id (coords_id), INDEX item_id (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_elements (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, coords_id INT NOT NULL, endTime INT DEFAULT 0 NOT NULL, INDEX coords_id (coords_id), UNIQUE INDEX id (id), PRIMARY KEY(name, coords_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_actions (player_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, charges INT DEFAULT 0 NOT NULL, INDEX IDX_131220C599E6F5DF (player_id), PRIMARY KEY(player_id, name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_connections (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, ip VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, time INT DEFAULT 0 NOT NULL, footprint VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX player_id (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_forum_missives (player_id INT NOT NULL, name BIGINT NOT NULL, viewed INT DEFAULT 0 NOT NULL, INDEX player_id (player_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_assists (player_id INT NOT NULL, target_id INT NOT NULL, player_rank INT DEFAULT 1 NOT NULL, damages INT DEFAULT 1 NOT NULL, time INT DEFAULT 0 NOT NULL, INDEX target_id (target_id), INDEX IDX_7F55E8199E6F5DF (player_id), PRIMARY KEY(player_id, target_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_foregrounds (id INT AUTO_INCREMENT NOT NULL, coords_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX coords_id (coords_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_upgrades (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, cost INT DEFAULT 0 NOT NULL, INDEX player_id (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_psw (id INT AUTO_INCREMENT NOT NULL, player_id INT DEFAULT 0 NOT NULL, uniqid VARCHAR(255) CHARACTER SET latin1 DEFAULT \'\' NOT NULL COLLATE `latin1_swedish_ci`, sentTime INT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET latin1 COLLATE `latin1_swedish_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_logs_archives (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, target_id INT NOT NULL, text VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, hiddenText TEXT CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, type VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, plan VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, time INT DEFAULT 0 NOT NULL, coords_id INT DEFAULT 0, coords_computed VARCHAR(35) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX target_id (target_id), INDEX players_logs_archives_coords_fk_1 (coords_id), INDEX player_id (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_bonus (player_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, n INT DEFAULT 0 NOT NULL, INDEX IDX_82BD56D099E6F5DF (player_id), PRIMARY KEY(player_id, name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_kills (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, target_id INT NOT NULL, player_rank INT DEFAULT 1 NOT NULL, target_rank INT DEFAULT 1 NOT NULL, xp INT DEFAULT 0 NOT NULL, assist INT DEFAULT 0 NOT NULL, time INT DEFAULT 0 NOT NULL, plan VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX target_id (target_id), INDEX player_id (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_quests_steps (player_id INT NOT NULL, quest_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'pending\' NOT NULL COLLATE `utf8mb4_general_ci`, endTime INT DEFAULT 0 NOT NULL, INDEX quest_id (quest_id), INDEX IDX_27C7201899E6F5DF (player_id), PRIMARY KEY(player_id, quest_id, name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE forums_keywords (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, postName BIGINT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE items (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, private INT DEFAULT 0 NOT NULL, enchanted INT DEFAULT 0 NOT NULL, vorpal INT DEFAULT 0 NOT NULL, cursed INT DEFAULT 0 NOT NULL, element VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, blessed_by_id INT DEFAULT NULL, spell VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX blessed_by_id (blessed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_pnjs (player_id INT NOT NULL, pnj_id INT NOT NULL, INDEX player_id (player_id), INDEX pnj_id (pnj_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_items (player_id INT NOT NULL, item_id INT NOT NULL, n INT DEFAULT 0 NOT NULL, equiped VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX item_id (item_id), INDEX IDX_FC3BC0E799E6F5DF (player_id), PRIMARY KEY(player_id, item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_options (player_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, INDEX player_id (player_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_items_exchanges (exchange_id INT NOT NULL, item_id INT NOT NULL, n INT NOT NULL, player_id INT NOT NULL, target_id INT NOT NULL, INDEX players_items_exchanges_fk_3 (player_id), INDEX players_items_exchanges_fk_4 (target_id), INDEX players_items_exchanges_fk_1 (exchange_id), INDEX players_items_exchanges_fk_2 (item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE map_plants (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, coords_id INT NOT NULL, params VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX coords_id (coords_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_forum_rewards (id INT AUTO_INCREMENT NOT NULL, from_player_id INT NOT NULL, to_player_id INT NOT NULL, postName VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, topName VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, img VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, pr INT DEFAULT 0 NOT NULL, INDEX to_player_id (to_player_id), INDEX from_player_id (from_player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, psw VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, mail VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, coords_id INT DEFAULT 0 NOT NULL, race VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, xp INT DEFAULT 0 NOT NULL, pi INT DEFAULT 0 NOT NULL, pr INT DEFAULT 0 NOT NULL, malus INT DEFAULT 0 NOT NULL, fatigue INT DEFAULT 0 NOT NULL, godId INT DEFAULT 0 NOT NULL, pf INT DEFAULT 0 NOT NULL, rank INT DEFAULT 1 NOT NULL, avatar VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, portrait VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, text TEXT CHARACTER SET utf8mb4 DEFAULT \'Je suis nouveau, frappez-moi!\' NOT NULL COLLATE `utf8mb4_general_ci`, story TEXT CHARACTER SET utf8mb4 DEFAULT \'Je préfère garder cela pour moi.\' NOT NULL COLLATE `utf8mb4_general_ci`, quest VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'gaia\' COLLATE `utf8mb4_general_ci`, faction VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, factionRole INT DEFAULT 0 NOT NULL, secretFaction VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, secretFactionRole INT DEFAULT 0 NOT NULL, nextTurnTime INT DEFAULT 0 NOT NULL, registerTime INT DEFAULT 0 NOT NULL, lastActionTime INT DEFAULT 0 NOT NULL, lastLoginTime INT DEFAULT 0 NOT NULL, antiBerserkTime INT DEFAULT 0 NOT NULL, lastTravelTime INT DEFAULT 0 NOT NULL, INDEX coords_id (coords_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_ips (id INT AUTO_INCREMENT NOT NULL, ip VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'\' NOT NULL COLLATE `utf8mb4_general_ci`, expTime INT DEFAULT 0 NOT NULL, failed INT DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE items_exchanges (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, target_id INT NOT NULL, player_ok TINYINT(1) DEFAULT 0 NOT NULL, target_ok TINYINT(1) DEFAULT 0 NOT NULL, update_time INT NOT NULL, INDEX items_exchanges_fk_1 (player_id), INDEX items_exchanges_fk_2 (target_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE players_quests (player_id INT NOT NULL, quest_id INT NOT NULL, status VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'pending\' NOT NULL COLLATE `utf8mb4_general_ci`, startTime INT DEFAULT 0 NOT NULL, endTime INT DEFAULT 0 NOT NULL, INDEX quest_id (quest_id), INDEX IDX_AE8032EB99E6F5DF (player_id), PRIMARY KEY(player_id, quest_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE map_walls ADD CONSTRAINT map_walls_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE map_walls ADD CONSTRAINT map_walls_ibfk_2 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE map_triggers ADD CONSTRAINT map_triggers_ibfk_2 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE map_tiles ADD CONSTRAINT map_tiles_ibfk_1 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE map_tiles ADD CONSTRAINT map_tiles_ibfk_2 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE items_bids ADD CONSTRAINT items_bids_ibfk_1 FOREIGN KEY (item_id) REFERENCES items (id)');
        $this->addSql('ALTER TABLE items_bids ADD CONSTRAINT items_bids_ibfk_2 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_effects ADD CONSTRAINT players_effects_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_followers ADD CONSTRAINT players_followers_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_followers ADD CONSTRAINT players_followers_ibfk_3 FOREIGN KEY (foreground_id) REFERENCES map_foregrounds (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE items_asks ADD CONSTRAINT items_asks_ibfk_2 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE items_asks ADD CONSTRAINT items_asks_ibfk_1 FOREIGN KEY (item_id) REFERENCES items (id)');
        $this->addSql('ALTER TABLE players_banned ADD CONSTRAINT players_banned_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_logs ADD CONSTRAINT players_logs_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_logs ADD CONSTRAINT players_logs_ibfk_2 FOREIGN KEY (target_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_logs ADD CONSTRAINT players_logs_coords_fk_1 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE map_dialogs ADD CONSTRAINT map_dialogs_ibfk_1 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE map_items ADD CONSTRAINT map_items_ibfk_1 FOREIGN KEY (item_id) REFERENCES items (id)');
        $this->addSql('ALTER TABLE map_items ADD CONSTRAINT map_items_ibfk_2 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE map_elements ADD CONSTRAINT map_elements_ibfk_1 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE players_actions ADD CONSTRAINT players_actions_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_connections ADD CONSTRAINT players_connections_fk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_forum_missives ADD CONSTRAINT players_forum_missives_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_assists ADD CONSTRAINT players_assists_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_assists ADD CONSTRAINT players_assists_ibfk_2 FOREIGN KEY (target_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE map_foregrounds ADD CONSTRAINT map_foregrounds_ibfk_3 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE players_upgrades ADD CONSTRAINT players_upgrades_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_logs_archives ADD CONSTRAINT players_logs_archives_coords_fk_1 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE players_bonus ADD CONSTRAINT players_bonus_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_kills ADD CONSTRAINT players_kills_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_kills ADD CONSTRAINT players_kills_ibfk_2 FOREIGN KEY (target_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_quests_steps ADD CONSTRAINT players_quests_steps_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_quests_steps ADD CONSTRAINT players_quests_steps_ibfk_2 FOREIGN KEY (quest_id) REFERENCES quests (id)');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT items_ibfk_1 FOREIGN KEY (blessed_by_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_pnjs ADD CONSTRAINT players_pnjs_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_pnjs ADD CONSTRAINT players_pnjs_ibfk_2 FOREIGN KEY (pnj_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_items ADD CONSTRAINT players_items_ibfk_2 FOREIGN KEY (item_id) REFERENCES items (id)');
        $this->addSql('ALTER TABLE players_items ADD CONSTRAINT players_items_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_options ADD CONSTRAINT players_options_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_items_exchanges ADD CONSTRAINT players_items_exchanges_fk_2 FOREIGN KEY (item_id) REFERENCES items (id)');
        $this->addSql('ALTER TABLE players_items_exchanges ADD CONSTRAINT players_items_exchanges_fk_3 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_items_exchanges ADD CONSTRAINT players_items_exchanges_fk_1 FOREIGN KEY (exchange_id) REFERENCES items_exchanges (id)');
        $this->addSql('ALTER TABLE players_items_exchanges ADD CONSTRAINT players_items_exchanges_fk_4 FOREIGN KEY (target_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_forum_rewards ADD CONSTRAINT players_forum_rewards_ibfk_1 FOREIGN KEY (from_player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_forum_rewards ADD CONSTRAINT players_forum_rewards_ibfk_2 FOREIGN KEY (to_player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players ADD CONSTRAINT players_ibfk_1 FOREIGN KEY (coords_id) REFERENCES coords (id)');
        $this->addSql('ALTER TABLE items_exchanges ADD CONSTRAINT items_exchanges_fk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE items_exchanges ADD CONSTRAINT items_exchanges_fk_2 FOREIGN KEY (target_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE players_quests ADD CONSTRAINT players_quests_ibfk_2 FOREIGN KEY (quest_id) REFERENCES quests (id)');
        $this->addSql('ALTER TABLE players_quests ADD CONSTRAINT players_quests_ibfk_1 FOREIGN KEY (player_id) REFERENCES players (id)');
    }
}

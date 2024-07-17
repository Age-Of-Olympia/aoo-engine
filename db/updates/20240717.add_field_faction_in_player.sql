ALTER TABLE `players`
    ADD `factionRole` int(11) NOT NULL DEFAULT 0 AFTER `faction`,
    ADD `secretFactionRole` int(11)  NOT NULL DEFAULT 0 AFTER `secretFaction`;
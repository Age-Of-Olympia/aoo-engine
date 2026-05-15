-- Remove the legacy `tuto/attaquer` action and all rows that hung off it.
--
-- The action was the tutorial's first-attack seed: its outcome chain (id 19)
-- removed itself, ran an `addraceactions` instruction, and teleported the
-- player to gaia2. The new tutorial system handles initialisation via
-- register.php and the api/tutorial/{skip,cancel,complete}.php endpoints,
-- and Classes/Player::put_player() now grants the race's starter pack
-- directly — so this action no longer has any caller. Leaving it around
-- meant any player who still carried it crashed at first attack.
--
-- Delete in FK-safe order: outcome_instructions → action_outcomes →
-- action_conditions → players_actions → actions. Targeting by name
-- (not hard-coded ids) so the cleanup is idempotent even if a future
-- dump renumbers things.

DELETE oi FROM outcome_instructions oi
    JOIN action_outcomes ao ON oi.outcome_id = ao.id
    JOIN actions a ON ao.action_id = a.id
    WHERE a.name = 'tuto/attaquer';

DELETE ao FROM action_outcomes ao
    JOIN actions a ON ao.action_id = a.id
    WHERE a.name = 'tuto/attaquer';

DELETE ac FROM action_conditions ac
    JOIN actions a ON ac.action_id = a.id
    WHERE a.name = 'tuto/attaquer';

DELETE FROM players_actions WHERE name = 'tuto/attaquer';

DELETE FROM actions WHERE name = 'tuto/attaquer';

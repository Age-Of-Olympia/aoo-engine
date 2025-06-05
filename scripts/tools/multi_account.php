<?php
use Classes\Db;

$db = new Db();

$sql = "SELECT group_concat(players_connections.player_id) as mats, group_concat(players.name) as names, connection_date
FROM (select player_id, ip, FROM_UNIXTIME(time, '%d/%m/%Y') as connection_date
      from players_connections
      group by ip, player_id, FROM_UNIXTIME(time, '%d/%m/%Y')) as players_connections
         inner join players on players_connections.player_id = players.id
group by ip, connection_date
having count(player_id)>1
order by connection_date desc";

$res = $db->exe($sql);

echo '<h2>Players sharing same IP</h2>';
while($row = $res->fetch_object()){
    echo 'Matricules '.$row->mats. ' noms : '.$row->names. ' le '.$row->connection_date.' <br/>';
}


$sql = "SELECT group_concat(players_connections.player_id) as mats, group_concat(players.name) as names, connection_date
FROM (select player_id, footprint, FROM_UNIXTIME(time, '%d/%m/%Y') as connection_date
      from players_connections
      group by footprint, player_id, FROM_UNIXTIME(time, '%d/%m/%Y')) as players_connections
         inner join players on players_connections.player_id = players.id
group by footprint, connection_date
having count(player_id)>1
order by connection_date desc";

$res = $db->exe($sql);

echo '<h2>Players sharing same FootPrint </h2>';
while($row = $res->fetch_object()){
    echo 'Matricules '.$row->mats. ' noms : '.$row->names. ' le '.$row->connection_date.' <br/>';
}



$sql = 'SELECT player1Infos.id as player1_id,
       player1Infos.name as player1_name,
       player2Infos.id as player2_id,
       player2Infos.name as player2_name,
       COUNT(*) as pair_count
FROM (SELECT pl1.player_id AS player1,
             pl2.player_id AS player2,
             pl1.time      AS time1,
             pl2.time      AS time2
      FROM players_logs pl1
               INNER JOIN
           players_logs pl2
           ON pl1.player_id < pl2.player_id
               AND ABS(pl1.time - pl2.time) <= 2700 -- 45 minutes = 2700 seconds
      WHERE DATE(FROM_UNIXTIME(pl1.time)) = DATE(FROM_UNIXTIME(pl2.time))
        AND pl1.time >= UNIX_TIMESTAMP(NOW() - INTERVAL 10 DAY)
        AND pl2.time >= UNIX_TIMESTAMP(NOW() - INTERVAL 10 DAY)) AS pairs
         INNER JOIN players player1Infos on pairs.player1 = player1Infos.id
         INNER JOIN players player2Infos on pairs.player2 = player2Infos.id
GROUP BY player1, player2
ORDER BY pair_count DESC
LIMIT 5 
';

$res = $db->exe($sql);

echo '<h2>Players having logs in the same time slot (top 5)</h2>';
while($row = $res->fetch_object()){
    echo 'Matricules '.$row->player1_id. ','.$row->player2_id. ' noms : '.$row->player1_name. ','.$row->player2_name.' , '.$row->pair_count.' times<br/>';
}



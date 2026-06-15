<?php
require('../../config/db.php');
$season_id=(int)($_GET['season_id']??0);
if(!$season_id){ $season_id=$conn->query("SELECT id FROM seasons ORDER BY data_inicio DESC LIMIT 1")->fetch_assoc()['id']; }

$res=$conn->query("
SELECT p.id, CONCAT(p.primeiro_nome,' ',p.ultimo_nome) AS nome, pos.code,
       SUM(s.goals) AS golos, SUM(s.assists) AS assist,
       SUM(s.yellow_cards) AS amarelos, SUM(s.red_cards) AS vermelhos,
       COUNT(s.id) AS jogos, SUM(s.started) AS titular, COALESCE(SUM(s.minutes_played),0) AS minutos
FROM player_match_stats s
JOIN players p ON p.id=s.player_id
JOIN matches m ON m.id=s.match_id
LEFT JOIN positions pos ON pos.id=p.position_id
WHERE m.season_id=$season_id
GROUP BY p.id
ORDER BY golos DESC, assist DESC
");
?>
<h2>Estatísticas da Época</h2>
<table class="tabela">
<tr><th>Jogador</th><th>Pos</th><th>Jogos</th><th>Titular</th><th>Min</th><th>Golos</th><th>Assist</th><th>Am.</th><th>Vm.</th></tr>
<?php while($r=$res->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($r['nome']) ?></td>
  <td><?= $r['code'] ?></td>
  <td><?= $r['jogos'] ?></td>
  <td><?= $r['titular'] ?></td>
  <td><?= $r['minutos'] ?></td>
  <td><?= $r['golos'] ?></td>
  <td><?= $r['assist'] ?></td>
  <td><?= $r['amarelos'] ?></td>
  <td><?= $r['vermelhos'] ?></td>
</tr>
<?php endwhile; ?>
</table>

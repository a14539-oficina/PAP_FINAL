<?php
require('../../config/db.php');
$match_id=(int)($_GET['match_id']??0);

$match=$conn->query("
  SELECT m.*, t.nome AS equipa 
  FROM matches m JOIN teams t ON t.id=m.team_id WHERE m.id=$match_id
")->fetch_assoc();

$stats=$conn->query("
  SELECT p.id, CONCAT(p.primeiro_nome,' ',p.ultimo_nome) AS nome,
         s.started, s.minutes_played, s.goals, s.assists, s.yellow_cards, s.red_cards
  FROM player_match_stats s
  JOIN players p ON p.id=s.player_id
  WHERE s.match_id=$match_id
  ORDER BY p.ultimo_nome
");
?>
<h2>Estatísticas do Jogo — <?= htmlspecialchars($match['equipa']) ?> vs <?= htmlspecialchars($match['adversario']) ?></h2>
<table class="tabela">
<tr><th>Jogador</th><th>Titular</th><th>Minutos</th><th>Golos</th><th>Assist.</th><th>Am.</th><th>Vm.</th></tr>
<?php while($r=$stats->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($r['nome']) ?></td>
  <td><?= $r['started']?'Sim':'Não' ?></td>
  <td><?= $r['minutes_played'] ?></td>
  <td><?= $r['goals'] ?></td>
  <td><?= $r['assists'] ?></td>
  <td><?= $r['yellow_cards'] ?></td>
  <td><?= $r['red_cards'] ?></td>
</tr>
<?php endwhile; ?>
</table>

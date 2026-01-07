<?php
// approvals.php — CLOSED scoping per rol (amp_id) + dorp/gemeente
// Priester(6):  net sy gemeente;   target: [8 Koorleier, 7 Onderdiaken, 9 Hulpkrag, 10 Broer/Suster]
// Oudste(5):    net sy dorp;       target: [6 Priester, 5 Oudste]
// Opsiener(2), Evangelis(3), Profeet(4): net sy dorp; target: [5,2,3,4]
// Apostel(1):   net apostels landwyd

if (!isset($pdo)) require_once $_SERVER['DOCUMENT_ROOT'].'/app/db_connect.php';
require_once __DIR__ . '/../../includes/languages.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pageLang = $_SESSION['language'] ?? 'af';

// Huidige gebruiker
$meAmpId = 0; $meCongId = null; $meTownId = null;
try {
  $q = $pdo->prepare("SELECT amp_id, congregation_id, town_id FROM users WHERE id=? LIMIT 1");
  $q->execute([$_SESSION['user_id'] ?? 0]);
  if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    $meAmpId  = (int)($r['amp_id'] ?? 0);
    $meCongId = $r['congregation_id'] ?? null;
    $meTownId = $r['town_id'] ?? null;
  }
} catch (Throwable $e) {}

// Roltoets
$isApostle  = ($meAmpId === 1);
$isOverseer = ($meAmpId === 2);
$isEvangel  = ($meAmpId === 3);
$isProphet  = ($meAmpId === 4);
$isElder    = ($meAmpId === 5);
$isPriest   = ($meAmpId === 6);

// Scope- en rolfilters
$roleFilterSql = ' AND 1=0 '; // default = niks
$scopeSql      = ''; $scopeParams = [];

if ($isPriest) {
  $roleFilterSql = ' AND u.amp_id IN (8,7,9,10) ';           // laer rolle
  if ($meCongId) { $scopeSql = ' AND u.congregation_id = :cid '; $scopeParams[':cid'] = (int)$meCongId; }
  else           { $scopeSql = ' AND 1=0 '; }
}
elseif ($isElder) {
  $roleFilterSql = ' AND u.amp_id IN (6,5) ';                // Priester, Oudste
  if ($meTownId) { $scopeSql = ' AND u.town_id = :tid '; $scopeParams[':tid'] = (int)$meTownId; }
  else           { $scopeSql = ' AND 1=0 '; }
}
elseif ($isOverseer || $isEvangel || $isProphet) {
  $roleFilterSql = ' AND u.amp_id IN (5,2,3,4) ';            // Vierfout + Oudste
  if ($meTownId) { $scopeSql = ' AND u.town_id = :tid '; $scopeParams[':tid'] = (int)$meTownId; }
  else           { $scopeSql = ' AND 1=0 '; }
}
elseif ($isApostle) {
  $roleFilterSql = ' AND u.amp_id = 1 ';
}

// Pending lys
$pending = [];
try {
  $sql = "
    SELECT
      u.id, u.name, u.surname, u.email, u.gender, u.amp_id,
      c.name AS congregation
    FROM users u
    LEFT JOIN congregations c ON c.id = u.congregation_id
    WHERE u.status = 'pending'
      {$roleFilterSql}
      {$scopeSql}
    ORDER BY u.surname, u.name
  ";
  $st = $pdo->prepare($sql);
  $st->execute($scopeParams);
  $pending = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Translate office names
  foreach ($pending as &$p) {
    $ampId = (int)($p['amp_id'] ?? 10);
    $gender = $p['gender'] ?? 'man';
    $p['amp'] = get_translated_office($ampId, $gender, $pageLang);
  }
  unset($p);
} catch (Throwable $e) { $pending = []; }
?>
<div class="panel stack">
  <h2>Goedkeurings</h2>

<?php if ($roleFilterSql === ' AND 1=0 '): ?>
  <p class="muted">Jy het nie toestemming om hierdie blad te gebruik nie.</p>

<?php elseif (empty($pending)): ?>
  <p class="muted">Geen pendende registrasies nie.</p>

<?php else: ?>
  <div class="table-wrap" style="overflow:auto">
    <table class="pending-table" role="table" style="width:100%;border-collapse:collapse">
      <thead>
        <tr>
          <th>Naam</th>
          <th>Amp</th>
          <th>Geme.</th>
          <th>Han.</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $p): ?>
        <tr id="row-<?= (int)$p['id'] ?>">
          <td><?= h(trim(($p['name']??'').' '.($p['surname']??''))) ?></td>
          <td><?= h($p['amp'] ?? '') ?></td>
          <td><?= h($p['congregation'] ?? '') ?></td>
          <td class="actions">
            <button class="btn-icon approve-btn" data-id="<?= (int)$p['id'] ?>" type="button">✔</button>
            <button class="btn-icon reject-btn"  data-id="<?= (int)$p['id'] ?>" type="button">✖</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <script>
  (function(){
    function postForm(url, data){
      const f = new FormData(); Object.keys(data||{}).forEach(k=>f.append(k,data[k]));
      return fetch(url,{method:'POST',body:f}).then(r=>r.json());
    }
    document.querySelectorAll('.approve-btn').forEach(b=>{
      b.addEventListener('click', ()=>{
        const id=b.getAttribute('data-id'); b.disabled=true;
        postForm('/api/admin/approve_user.php',{user_id:id}).then(j=>{
          if(j && j.success){ const tr=document.getElementById('row-'+id); if(tr) tr.remove(); }
          else{ alert((j&&j.error)||'Server error'); b.disabled=false; }
        }).catch(()=>{ alert('Network error'); b.disabled=false; });
      });
    });
    document.querySelectorAll('.reject-btn').forEach(b=>{
      b.addEventListener('click', ()=>{
        const id=b.getAttribute('data-id'); b.disabled=true;
        postForm('/api/admin/reject_user.php',{user_id:id}).then(j=>{
          if(j && j.success){ const tr=document.getElementById('row-'+id); if(tr) tr.remove(); }
          else{ alert((j&&j.error)||'Server error'); b.disabled=false; }
        }).catch(()=>{ alert('Network error'); b.disabled=false; });
      });
    });
  })();
  </script>
<?php endif; ?>
</div>

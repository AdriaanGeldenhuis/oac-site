<?php
$myAmpId = (int)($currentUser['amp_id'] ?? 0);
if ($myAmpId < 1 || $myAmpId > 10) {
  echo '<p>'.T('Jy het nie toegang tot hierdie blad nie.','You do not have access to this page.').'</p>';
  return;
}

$myTownId = $currentUser['town_id'] ?? null;
$myCongId = $currentUser['congregation_id'] ?? null;

$canSee = [];
$canEdit = [];
$scopeFilter = '';
$scopeParams = [];

if ($myAmpId === 1) {
    $canSee = [2,3,4];
    $canEdit = [1,2,3,4];
    $scopeFilter = '';
} 
elseif (in_array($myAmpId, [2,3,4])) {
    $canSee = [1,2,3,4,5,6,7,8,9,10];
    $canEdit = [2,3,4,5,6,7,8,9,10];
    if ($myTownId) {
        $scopeFilter = 'AND u.town_id = :town_id';
        $scopeParams[':town_id'] = $myTownId;
    }
} 
elseif ($myAmpId === 5) {
    $canSee = [2,3,4,5,6,7,8,9,10];
    $canEdit = [5,6,7,8,9,10];
    if ($myTownId) {
        $scopeFilter = 'AND u.town_id = :town_id';
        $scopeParams[':town_id'] = $myTownId;
    }
} 
elseif ($myAmpId === 6) {
    $canSee = [5,6,7,8,9,10];
    $canEdit = [7,8,9,10];
    $scopeFilter = 'AND ((u.amp_id = 5 AND u.town_id = :town_id) OR (u.amp_id IN (6,7,8,9,10) AND u.congregation_id = :cong_id))';
    $scopeParams[':town_id'] = $myTownId;
    $scopeParams[':cong_id'] = $myCongId;
} 
elseif ($myAmpId === 7) {
    $canSee = [6,7,8,9,10];
    $canEdit = [8,9,10];
    if ($myCongId) {
        $scopeFilter = 'AND u.congregation_id = :cong_id';
        $scopeParams[':cong_id'] = $myCongId;
    }
} 
elseif ($myAmpId === 8) {
    $canSee = [6,7,8,9,10];
    $canEdit = [9,10];
    if ($myCongId) {
        $scopeFilter = 'AND u.congregation_id = :cong_id';
        $scopeParams[':cong_id'] = $myCongId;
    }
} 
elseif ($myAmpId === 9) {
    $canSee = [6,7,8,9,10];
    $canEdit = [10];
    if ($myCongId) {
        $scopeFilter = 'AND u.congregation_id = :cong_id';
        $scopeParams[':cong_id'] = $myCongId;
    }
} 
elseif ($myAmpId === 10) {
    $canSee = [6,7,8,9,10];
    $canEdit = [];
    if ($myCongId) {
        $scopeFilter = 'AND u.congregation_id = :cong_id';
        $scopeParams[':cong_id'] = $myCongId;
    }
}

$amptes = [];
if (!empty($canSee)) {
    try {
        $ampList = implode(',', $canSee);
        $sql = "SELECT u.id, u.name, u.surname, u.amp_id, u.email, u.phone, u.photo, u.gender,
                CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name,
                c.name AS congregation
                FROM users u
                LEFT JOIN amptes a ON a.id = u.amp_id
                LEFT JOIN congregations c ON c.id = u.congregation_id
                WHERE u.amp_id IN ($ampList) 
                AND u.status = 'approved'
                $scopeFilter
                ORDER BY u.amp_id ASC, u.gender ASC, u.surname ASC, u.name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($scopeParams);
        $amptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $amptes = [];
    }
}

$groupedAmptes = [];
foreach ($amptes as $amp) {
    $ampId = (int)$amp['amp_id'];
    if (!isset($groupedAmptes[$ampId])) {
        $groupedAmptes[$ampId] = [];
    }
    $groupedAmptes[$ampId][] = $amp;
}
krsort($groupedAmptes);
?>

<div class="ampte-section">
  <div class="admin-section-header">
    <div class="admin-icon-wrapper">
      <svg class="admin-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2"/>
      </svg>
    </div>
    <h2 class="admin-section-title"><?= T('Ampte', 'Offices') ?></h2>
  </div>

  <div class="ampte-search-wrapper">
    <input type="text" id="ampteSearch" class="ampte-search-input" placeholder="<?= T('Soek ampte...', 'Search offices...') ?>">
    <svg class="ampte-search-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
      <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2"/>
    </svg>
  </div>

  <?php if (empty($groupedAmptes)): ?>
  <p class="admin-muted"><?= T('Geen ampte gevind nie.', 'No office holders found.') ?></p>
  <?php else: ?>
  <div class="ampte-container" id="ampteContainer">
    <?php foreach ($groupedAmptes as $ampId => $ampList): ?>
    <div class="ampte-group">
      <h4 class="ampte-group-title"><?= htmlspecialchars($ampList[0]['amp_name'] ?? '') ?></h4>
      <div class="ampte-grid">
        <?php foreach ($ampList as $amp): ?>
        <?php
        $targetAmpId = (int)$amp['amp_id'];
        $canEditThis = in_array($targetAmpId, $canEdit, true);
        $avatar = !empty($amp['photo']) ? htmlspecialchars($amp['photo']) : '/assets/img/avatar-default.png';
        ?>
        <div class="ampte-card" data-id="<?= (int)$amp['id'] ?>" data-name="<?= htmlspecialchars(strtolower($amp['name'] . ' ' . $amp['surname'])) ?>">
          <div class="ampte-avatar">
            <img src="<?= $avatar ?>" alt="<?= htmlspecialchars($amp['name']) ?>">
          </div>
          <div class="ampte-info">
            <h4><?= htmlspecialchars($amp['name'] . ' ' . $amp['surname']) ?></h4>
            <p class="ampte-role"><?= htmlspecialchars($amp['amp_name'] ?? '') ?></p>
            <p class="ampte-congregation"><?= htmlspecialchars($amp['congregation'] ?? '') ?></p>
          </div>
          <div class="ampte-actions">
            <a href="/profile/?u=<?= (int)$amp['id'] ?>" class="ampte-btn ampte-btn-view" title="<?= T('Profiel', 'Profile') ?>">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </a>
            <a href="/calendar/view.php?u=<?= (int)$amp['id'] ?>" class="ampte-btn ampte-btn-calendar" title="<?= T('Kyk Kalender', 'View Calendar') ?>">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
              </svg>
            </a>
            <?php if ($canEditThis): ?>
            <button type="button" class="ampte-btn ampte-btn-edit btn-change-role" data-id="<?= (int)$amp['id'] ?>" data-name="<?= htmlspecialchars($amp['name'] . ' ' . $amp['surname']) ?>" data-current-amp="<?= $targetAmpId ?>" title="<?= T('Verander Amp', 'Change Role') ?>">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <button type="button" class="ampte-btn ampte-btn-delete btn-delete-user" data-id="<?= (int)$amp['id'] ?>" data-name="<?= htmlspecialchars($amp['name'] . ' ' . $amp['surname']) ?>" title="<?= T('Verwyder', 'Delete') ?>">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" stroke="currentColor" stroke-width="2"/>
              </svg>
            </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div id="roleChangeModal" class="ampte-modal" style="display:none;">
  <div class="ampte-modal-overlay"></div>
  <div class="ampte-modal-content">
    <div class="ampte-modal-header">
      <h3><?= T('Verander Amp', 'Change Role') ?></h3>
      <button type="button" class="ampte-modal-close" onclick="closeRoleModal()">&times;</button>
    </div>
    <div class="ampte-modal-body">
      <form id="roleChangeForm">
        <input type="hidden" id="roleTargetId" name="target_user_id">
        
        <div class="ampte-form-group">
          <label><?= T('Gebruiker', 'User') ?>:</label>
          <div id="roleTargetName" class="ampte-display-field"></div>
        </div>
        
        <div class="ampte-form-group">
          <label for="newAmpId"><?= T('Nuwe Amp', 'New Role') ?></label>
          <select id="newAmpId" name="new_amp_id" class="ampte-form-control" required>
            <option value=""><?= T('Kies...', 'Select...') ?></option>
            <?php
            try {
                $ampteStmt = $pdo->query("SELECT id, male_name, female_name FROM amptes ORDER BY id ASC");
                $availableAmptes = $ampteStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($availableAmptes as $ampte) {
                    $ampteId = (int)$ampte['id'];
                    if (in_array($ampteId, $canEdit, true)) {
                        $displayName = $ampte['male_name'] . ' / ' . $ampte['female_name'];
                        echo '<option value="' . $ampteId . '">' . htmlspecialchars($displayName) . '</option>';
                    }
                }
            } catch (Throwable $e) {}
            ?>
          </select>
        </div>
        
        <div class="ampte-modal-actions">
          <button type="button" class="ampte-btn ampte-btn-secondary" onclick="closeRoleModal()"><?= T('Kanselleer', 'Cancel') ?></button>
          <button type="submit" class="ampte-btn ampte-btn-primary"><?= T('Stoor', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<link rel="stylesheet" href="/admin/css/amptes.css?v=<?= $VER ?>">
<script src="/admin/js/amptes.js?v=<?= $VER ?>"></script>
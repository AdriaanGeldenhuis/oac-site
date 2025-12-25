<?php
declare(strict_types=1);
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/csrf.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $errors[] = 'Ongeldige sessie. Probeer weer.';
    }

    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $marital = $_POST['marital_status'] ?? '';
    $amp = (int)($_POST['amp_id'] ?? 0);
    $country = (int)($_POST['country_id'] ?? 0);
    $prov = (int)($_POST['province_id'] ?? 0);
    $town = (int)($_POST['town_id'] ?? 0);
    $cong = (int)($_POST['congregation_id'] ?? 0);
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (empty($email) || empty($name) || empty($surname) || empty($pass)) {
        $errors[] = 'Vul asseblief alles in.';
    }
    if ($pass !== $pass2) {
        $errors[] = 'Wagwoorde stem nie ooreen nie.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'Wagwoord moet minstens 8 karakters wees.';
    }

    if (!$errors) {
        $chk = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'E-pos bestaan reeds.';
        }
    }

    if (!$errors) {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare(
            "INSERT INTO users 
             (email, password_hash, name, surname, phone, birthdate, gender, marital_status, 
              amp_id, status, province_id, town_id, congregation_id, language, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, 'af', NOW())"
        );
        $stmt->execute([
            $email, $hash, $name, $surname, $phone, $birthdate ?: null, $gender, $marital,
            $amp ?: null, $prov ?: null, $town ?: null, $cong ?: null
        ]);
        $success = true;
    }
}

$csrf = csrf_token();

// Haal ampte, lande, provinsies, dorpe, gemeentes
$amptes = $pdo->query("SELECT * FROM amptes ORDER BY id")->fetchAll();
$countries = $pdo->query("SELECT * FROM countries ORDER BY name")->fetchAll();
$provinces = $pdo->query("SELECT * FROM provinces ORDER BY name")->fetchAll();
$towns = $pdo->query("SELECT * FROM towns ORDER BY name")->fetchAll();
$congregations = $pdo->query("SELECT * FROM congregations ORDER BY name")->fetchAll();
?>
<!doctype html>
<html lang="af">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Registreer</title>
<link rel="stylesheet" href="/assets/css/register.css">
</head>
<body>
<div class="register-container">
  <!-- Logo -->
  <div class="logo-container">
    <img src="/assets/logo/bible2.webp" alt="Logo">
  </div>
  
  <h1>Skep Rekening</h1>
  
  <?php if ($success): ?>
    <div class="success">Registrasie suksesvol! Wag vir goedkeuring. <a href="/login.php">Login</a></div>
  <?php elseif ($errors): ?>
    <div class="errors">
      <?php foreach ($errors as $e): ?>
        <p><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    
    <div class="full-width">
      <label>E-pos<br><input type="email" name="email" required></label>
    </div>
    
    <div>
      <label>Naam<br><input type="text" name="name" required></label>
    </div>
    
    <div>
      <label>Van<br><input type="text" name="surname" required></label>
    </div>
    
    <div>
      <label>Foon<br><input type="tel" name="phone"></label>
    </div>
    
    <div>
      <label>Geboortedatum<br><input type="date" name="birthdate"></label>
    </div>
    
    <div>
      <label>Geslag<br>
        <select name="gender" id="gender" onchange="updateAmptes()">
          <option value="">Kies...</option>
          <option value="man">Man</option>
          <option value="vrou">Vrou</option>
        </select>
      </label>
    </div>
    
    <div>
      <label>Huwelikstatus<br>
        <select name="marital_status">
          <option value="">Kies...</option>
          <option value="getroud">Getroud</option>
          <option value="ongetroud">Ongetroud</option>
        </select>
      </label>
    </div>
    
    <div>
      <label>Amp<br>
        <select name="amp_id" id="amp_id" disabled>
          <option value="">Kies eers geslag...</option>
        </select>
      </label>
    </div>
    
    <div>
      <label>Land<br>
        <select name="country_id" id="country_id" onchange="updateProvinces()">
          <option value="">Kies...</option>
          <?php foreach ($countries as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    
    <div>
      <label>Provinsie<br>
        <select name="province_id" id="province_id" onchange="updateTowns()" disabled>
          <option value="">Kies eers land...</option>
        </select>
      </label>
    </div>
    
    <div>
      <label>Dorp<br>
        <select name="town_id" id="town_id" onchange="updateCongregations()" disabled>
          <option value="">Kies eers provinsie...</option>
        </select>
      </label>
    </div>
    
    <div>
      <label>Gemeente<br>
        <select name="congregation_id" id="congregation_id" disabled>
          <option value="">Kies eers dorp...</option>
        </select>
      </label>
    </div>
    
    <div class="password-wrapper">
      <label>
        Wagwoord
        <span class="toggle-pw" onclick="togglePw('pw')">👁️</span>
      </label>
      <input type="password" id="pw" name="password" required>
    </div>
    
    <div class="password-wrapper">
      <label>
        Bevestig Wagwoord
        <span class="toggle-pw" onclick="togglePw('pw2')">👁️</span>
      </label>
      <input type="password" id="pw2" name="password2" required>
    </div>
    
    <button type="submit" class="full-width">Registreer</button>
  </form>
  
  <p>Het al 'n rekening? <a href="/login.php">Login</a></p>
</div>

<script>
// Data from PHP
const amptes = <?= json_encode($amptes) ?>;
const provinces = <?= json_encode($provinces) ?>;
const towns = <?= json_encode($towns) ?>;
const congregations = <?= json_encode($congregations) ?>;

// Update amptes based on gender
function updateAmptes() {
  const gender = document.getElementById('gender').value;
  const ampSelect = document.getElementById('amp_id');
  
  ampSelect.innerHTML = '<option value="">Kies...</option>';
  
  if (gender) {
    ampSelect.disabled = false;
    amptes.forEach(amp => {
      const option = document.createElement('option');
      option.value = amp.id;
      option.textContent = gender === 'man' ? amp.male_name : amp.female_name;
      ampSelect.appendChild(option);
    });
  } else {
    ampSelect.disabled = true;
    ampSelect.innerHTML = '<option value="">Kies eers geslag...</option>';
  }
}

// Update provinces based on country
function updateProvinces() {
  const countryId = parseInt(document.getElementById('country_id').value);
  const provSelect = document.getElementById('province_id');
  const townSelect = document.getElementById('town_id');
  const congSelect = document.getElementById('congregation_id');
  
  // Reset dependent dropdowns
  provSelect.innerHTML = '<option value="">Kies...</option>';
  townSelect.innerHTML = '<option value="">Kies eers provinsie...</option>';
  townSelect.disabled = true;
  congSelect.innerHTML = '<option value="">Kies eers dorp...</option>';
  congSelect.disabled = true;
  
  if (countryId) {
    provSelect.disabled = false;
    const filtered = provinces.filter(p => p.country_id === countryId);
    filtered.forEach(prov => {
      const option = document.createElement('option');
      option.value = prov.id;
      option.textContent = prov.name;
      provSelect.appendChild(option);
    });
  } else {
    provSelect.disabled = true;
    provSelect.innerHTML = '<option value="">Kies eers land...</option>';
  }
}

// Update towns based on province
function updateTowns() {
  const provId = parseInt(document.getElementById('province_id').value);
  const townSelect = document.getElementById('town_id');
  const congSelect = document.getElementById('congregation_id');
  
  // Reset dependent dropdowns
  townSelect.innerHTML = '<option value="">Kies...</option>';
  congSelect.innerHTML = '<option value="">Kies eers dorp...</option>';
  congSelect.disabled = true;
  
  if (provId) {
    townSelect.disabled = false;
    const filtered = towns.filter(t => t.province_id === provId);
    filtered.forEach(town => {
      const option = document.createElement('option');
      option.value = town.id;
      option.textContent = town.name;
      townSelect.appendChild(option);
    });
  } else {
    townSelect.disabled = true;
    townSelect.innerHTML = '<option value="">Kies eers provinsie...</option>';
  }
}

// Update congregations based on town
function updateCongregations() {
  const townId = parseInt(document.getElementById('town_id').value);
  const congSelect = document.getElementById('congregation_id');
  
  congSelect.innerHTML = '<option value="">Kies...</option>';
  
  if (townId) {
    congSelect.disabled = false;
    const filtered = congregations.filter(c => c.town_id === townId);
    filtered.forEach(cong => {
      const option = document.createElement('option');
      option.value = cong.id;
      option.textContent = cong.name;
      congSelect.appendChild(option);
    });
  } else {
    congSelect.disabled = true;
    congSelect.innerHTML = '<option value="">Kies eers dorp...</option>';
  }
}

function togglePw(id) {
  const pw = document.getElementById(id);
  pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
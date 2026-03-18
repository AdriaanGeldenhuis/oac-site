<?php
// =====================================================================
// /policy/index.php — Public Privacy Policy (no auth required)
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/../security/headers.php';
require_once __DIR__ . '/../includes/languages.php';

// Language handling — session-free, uses query param or defaults to English
$pageLang = 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $pageLang = $_GET['lang'];
} elseif (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['language'])
    && in_array($_SESSION['language'], SUPPORTED_LANGS, true)) {
    $pageLang = $_SESSION['language'];
}

function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}
?>
<!doctype html>
<html lang="<?= $pageLang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars(t('privacy_policy'), ENT_QUOTES, 'UTF-8') ?> — Old Apostolic Church</title>
<?php require_once __DIR__ . '/../header_footer/header.php'; ?>
<style>
body {
  background-color: var(--ghf-bg);
  color: var(--ghf-text);
}
.policy-wrap {
  max-width: 800px;
  margin: 30px auto;
  padding: 25px 20px;
  color: var(--ghf-text);
  font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  line-height: 1.7;
}
.policy-wrap h1 {
  font-family: 'Parisienne', cursive;
  color: var(--ghf-text-rose);
  text-shadow: 0 0 15px rgba(183, 110, 121, 0.4);
  font-size: clamp(1.8rem, 5vw, 2.5rem);
  text-align: center;
  margin-bottom: 10px;
}
.policy-wrap h2 {
  color: var(--ghf-text-rose);
  font-size: 1.15rem;
  margin-top: 28px;
  margin-bottom: 8px;
  letter-spacing: 0.5px;
}
.policy-wrap h3 {
  color: var(--ghf-silver);
  font-size: 1rem;
  margin-top: 18px;
  margin-bottom: 6px;
}
.policy-wrap p,
.policy-wrap ul {
  font-size: 0.95rem;
  margin: 8px 0;
}
.policy-wrap ul {
  padding-left: 22px;
}
.policy-wrap a {
  color: var(--ghf-text-rose);
}
.policy-date {
  text-align: center;
  font-size: 0.85rem;
  color: var(--ghf-silver-dark);
  margin-bottom: 20px;
}
.policy-app-name {
  text-align: center;
  font-size: 0.9rem;
  color: var(--ghf-silver);
  margin-bottom: 25px;
}
.policy-table {
  width: 100%;
  border-collapse: collapse;
  margin: 12px 0;
  font-size: 0.9rem;
}
.policy-table th,
.policy-table td {
  border: 1px solid var(--ghf-border);
  padding: 10px 12px;
  text-align: left;
}
.policy-table th {
  background: rgba(183, 110, 121, 0.1);
  color: var(--ghf-text-rose);
  font-weight: 600;
}
</style>
</head>
<body>
<div class="policy-wrap">
  <h1><?= htmlspecialchars(t('privacy_policy'), ENT_QUOTES, 'UTF-8') ?></h1>
  <p class="policy-app-name">Old Apostolic Church (OAC) App &mdash; oacapp.co.za</p>
  <p class="policy-date">Effective date: 1 January 2025 &nbsp;|&nbsp; Last updated: 18 March 2026</p>

  <p>The Old Apostolic Church (&ldquo;we&rdquo;, &ldquo;us&rdquo;, &ldquo;our&rdquo;) operates the OAC App (the &ldquo;App&rdquo;) available on the web at <strong>oacapp.co.za</strong> and on Android via Google Play. This Privacy Policy explains what personal data we collect, how we use it, who we share it with, and your rights regarding that data.</p>
  <p>By creating an account or using the App you agree to the practices described in this policy.</p>

  <!-- ============================================================ -->
  <h2>1. Information We Collect</h2>

  <h3>1.1 Account &amp; Profile Information (provided by you)</h3>
  <ul>
    <li>Full name and surname</li>
    <li>Email address</li>
    <li>Phone number</li>
    <li>Date of birth</li>
    <li>Gender and marital status</li>
    <li>Password (stored as a bcrypt hash &mdash; we never store your plain-text password)</li>
    <li>Profile photo (optional)</li>
    <li>Language preference</li>
  </ul>

  <h3>1.2 Church-Related Information</h3>
  <ul>
    <li>Province, town, and congregation</li>
    <li>Church office / position (<em>amp</em>)</li>
    <li>Spouse link (if you choose to link your account to your spouse&rsquo;s account)</li>
  </ul>

  <h3>1.3 User-Generated Content</h3>
  <ul>
    <li><strong>Diary entries</strong> &mdash; title, body text, date, time, mood, weather, tags</li>
    <li><strong>Prayer requests &amp; testimonies</strong> &mdash; text content and optional photo uploads</li>
    <li><strong>Bible notes, highlights &amp; bookmarks</strong> &mdash; your personal Bible study data</li>
    <li><strong>Gospel media posts</strong> &mdash; text, event details, and image/file attachments</li>
    <li><strong>Calendar events</strong> &mdash; titles, descriptions, dates, and shared-event links</li>
    <li><strong>Comments &amp; reactions</strong> &mdash; on prayers and gospel media posts</li>
    <li><strong>Appointment requests</strong> &mdash; date, time, location text, and notes</li>
  </ul>

  <h3>1.4 Automatically Collected Data</h3>
  <ul>
    <li><strong>Session data</strong> &mdash; a session identifier stored in a cookie to keep you logged in</li>
    <li><strong>Device token</strong> &mdash; a Firebase Cloud Messaging (FCM) token used to deliver push notifications to your device</li>
    <li><strong>IP address</strong> &mdash; used temporarily for rate-limiting login attempts (not stored long-term)</li>
  </ul>

  <h3>1.5 Data We Do NOT Collect</h3>
  <ul>
    <li>GPS / precise location (geolocation is explicitly disabled)</li>
    <li>Contacts, call logs, or SMS</li>
    <li>Microphone or camera access (beyond photo uploads you initiate)</li>
    <li>Advertising identifiers or analytics tracking</li>
  </ul>

  <!-- ============================================================ -->
  <h2>2. How We Use Your Information</h2>
  <table class="policy-table">
    <tr><th>Purpose</th><th>Data used</th></tr>
    <tr><td>Create and manage your account</td><td>Name, email, password hash, church details</td></tr>
    <tr><td>Display your profile to other church members</td><td>Name, surname, photo, congregation, office</td></tr>
    <tr><td>Deliver push notifications (reminders, approvals, birthdays)</td><td>FCM device token</td></tr>
    <tr><td>Show prayer requests to relevant members</td><td>Prayer text, photo, visibility setting, town</td></tr>
    <tr><td>AI-enhanced diary writing</td><td>Diary title and body text (sent to OpenAI &mdash; see Section 4)</td></tr>
    <tr><td>AI Bible commentary</td><td>Bible verse text and language (sent to OpenAI &mdash; see Section 4)</td></tr>
    <tr><td>AI translation of elder teachings</td><td>Teaching text (sent to OpenAI &mdash; see Section 4)</td></tr>
    <tr><td>Prevent abuse</td><td>IP address for login rate-limiting; CSRF tokens</td></tr>
    <tr><td>Personalise language</td><td>Language preference</td></tr>
  </table>

  <!-- ============================================================ -->
  <h2>3. Data Sharing Between Users</h2>
  <p>Parts of your data are visible to other App users depending on context:</p>
  <ul>
    <li><strong>Prayer posts</strong> &mdash; visible to members in your town, your church office group, or all users, depending on the visibility level you choose when posting.</li>
    <li><strong>Gospel media posts &amp; comments</strong> &mdash; visible to all members of the media room you post in.</li>
    <li><strong>Reactions &amp; comments</strong> &mdash; your name is shown when you react to or comment on a post.</li>
    <li><strong>Profile information</strong> &mdash; your name, surname, congregation, and office are visible to members in your area.</li>
    <li><strong>Spouse link</strong> &mdash; if you link accounts, your spouse can see shared calendar events.</li>
    <li><strong>Church elders/officers</strong> &mdash; officers with administrative roles can view member profiles within their jurisdiction (town or congregation) for pastoral purposes.</li>
  </ul>

  <!-- ============================================================ -->
  <h2>4. Third-Party Services</h2>
  <p>We use the following third-party services. We do <strong>not</strong> sell your data to any third party.</p>

  <h3>4.1 OpenAI (GPT-4o)</h3>
  <ul>
    <li><strong>What is sent:</strong> Diary text (for AI enhancement), Bible verse text (for AI commentary), and teaching content (for AI translation).</li>
    <li><strong>Why:</strong> To provide AI-powered writing assistance and Bible study features.</li>
    <li><strong>Retention:</strong> OpenAI&rsquo;s API data usage policy applies. We send data via their API with no long-term training opt-in. See <a href="https://openai.com/policies/privacy-policy" target="_blank" rel="noopener">OpenAI&rsquo;s Privacy Policy</a>.</li>
  </ul>

  <h3>4.2 Firebase Cloud Messaging (Google)</h3>
  <ul>
    <li><strong>What is sent:</strong> A device token and notification payload (title, message, deep link).</li>
    <li><strong>Why:</strong> To deliver push notifications to your Android device.</li>
    <li><strong>Retention:</strong> Tokens are stored until you log out or uninstall the app. See <a href="https://firebase.google.com/support/privacy" target="_blank" rel="noopener">Firebase Privacy Information</a>.</li>
  </ul>

  <h3>4.3 Google Fonts</h3>
  <ul>
    <li><strong>What is sent:</strong> Standard HTTP request headers (IP address, user agent) when fonts load.</li>
    <li><strong>Why:</strong> To display custom typography in the App.</li>
    <li>See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google&rsquo;s Privacy Policy</a>.</li>
  </ul>

  <!-- ============================================================ -->
  <h2>5. Cookies &amp; Sessions</h2>
  <p>We use a single <strong>essential session cookie</strong> (<code>OACSESS</code>) to keep you logged in. This cookie:</p>
  <ul>
    <li>Expires after <strong>30 days</strong> of inactivity</li>
    <li>Is marked <strong>HttpOnly</strong> (not accessible to JavaScript) and <strong>Secure</strong> (sent only over HTTPS)</li>
    <li>Is set with <strong>SameSite=Lax</strong> to prevent cross-site request forgery</li>
  </ul>
  <p>We do <strong>not</strong> use advertising cookies, analytics cookies, or any other tracking cookies.</p>

  <!-- ============================================================ -->
  <h2>6. Data Storage &amp; Security</h2>
  <ul>
    <li>Your data is stored on secured servers with HTTPS encryption in transit.</li>
    <li>Passwords are hashed with <strong>bcrypt</strong> (cost factor 12) and are never stored or transmitted in plain text.</li>
    <li>Sessions are stored in a database (not file-based) and automatically expire.</li>
    <li>All forms use <strong>CSRF tokens</strong> to prevent cross-site request forgery.</li>
    <li>Login attempts are <strong>rate-limited</strong> (5 attempts per 15 minutes per IP address).</li>
    <li>Security headers are enforced: Content Security Policy, X-Frame-Options (DENY), HSTS, X-Content-Type-Options.</li>
    <li>Uploaded photos are processed server-side (resized, converted to WebP) and stored in protected directories.</li>
  </ul>

  <!-- ============================================================ -->
  <h2>7. Data Retention</h2>
  <ul>
    <li><strong>Account data</strong> is retained as long as your account is active.</li>
    <li><strong>Session data</strong> is automatically deleted after 30 days of inactivity.</li>
    <li><strong>Rate-limit records</strong> are temporary and cleared automatically.</li>
    <li><strong>User-generated content</strong> (diary entries, prayers, Bible notes, etc.) is retained until you delete it or delete your account.</li>
    <li><strong>FCM tokens</strong> are retained until you log out, uninstall the app, or delete your account.</li>
  </ul>

  <!-- ============================================================ -->
  <h2>8. Your Rights</h2>
  <p>In accordance with the Protection of Personal Information Act (POPIA) of South Africa and applicable data-protection laws, you have the right to:</p>
  <ul>
    <li><strong>Access</strong> &mdash; request a copy of the personal data we hold about you.</li>
    <li><strong>Correction</strong> &mdash; request correction of inaccurate or incomplete data.</li>
    <li><strong>Deletion</strong> &mdash; request deletion of your account and all associated data. You can delete your account from within the App under your profile settings, which permanently removes all your data including diary entries, prayers, Bible notes, calendar events, notifications, FCM tokens, and uploaded files.</li>
    <li><strong>Object</strong> &mdash; object to the processing of your personal data for specific purposes.</li>
    <li><strong>Withdraw consent</strong> &mdash; you may stop using the App at any time.</li>
  </ul>

  <!-- ============================================================ -->
  <h2>9. Account Deletion</h2>
  <p>You can delete your account at any time through the App. When you delete your account, the following data is <strong>permanently removed</strong>:</p>
  <ul>
    <li>Your user profile, email, and all personal information</li>
    <li>All diary entries and calendar events</li>
    <li>All prayer posts, comments, and reactions</li>
    <li>All Bible notes, highlights, and bookmarks</li>
    <li>All gospel media posts, comments, and attachments</li>
    <li>All notifications and FCM device tokens</li>
    <li>Spouse links and appointment records</li>
    <li>All uploaded photos and files</li>
  </ul>
  <p>This action is <strong>irreversible</strong>.</p>

  <!-- ============================================================ -->
  <h2>10. Children&rsquo;s Privacy</h2>
  <p>The App is not directed at children under the age of 13. We do not knowingly collect personal information from children under 13. If we become aware that a child under 13 has provided us with personal data, we will take steps to delete that data promptly. If you believe a child has provided us with their data, please contact us.</p>

  <!-- ============================================================ -->
  <h2>11. Changes to This Policy</h2>
  <p>We may update this Privacy Policy from time to time. The &ldquo;Last updated&rdquo; date at the top of this page reflects the most recent revision. Continued use of the App after changes constitutes acceptance of the updated policy.</p>

  <!-- ============================================================ -->
  <h2>12. Contact Us</h2>
  <p>If you have questions about this Privacy Policy, wish to exercise your data rights, or need to report a concern, please contact the church administration:</p>
  <ul>
    <li><strong>App:</strong> oacapp.co.za</li>
    <li><strong>Email:</strong> admin@oacapp.co.za</li>
  </ul>
</div>
<?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>

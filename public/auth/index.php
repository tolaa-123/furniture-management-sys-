<?php
session_start();
require_once __DIR__ . '/../../config/config.php';

$auth = isset($_GET['auth']) ? strtolower(trim($_GET['auth'])) : 'login';
if (!in_array($auth, ['login','register','forgot'], true)) $auth = 'login';
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION[CSRF_TOKEN_NAME];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SmartWorkshop — Authentication</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles-auth.css">
</head>
<body>
<header class="site-header">
  <nav class="nav">
    <div class="nav-inner">
      <a class="brand" href="<?php echo BASE_URL; ?>/public/">
        <span class="brand-icon">🪵</span> <span>Smart<span class="accent">Workshop</span></span>
      </a>
      <ul class="links">
        <li><a href="<?php echo BASE_URL; ?>/public/">Home</a></li>
        <li><a href="<?php echo BASE_URL; ?>/public/about">About</a></li>
        <li><a href="<?php echo BASE_URL; ?>/public/furniture">Furniture</a></li>
        <li><a href="<?php echo BASE_URL; ?>/public/#how-it-works">How It Works</a></li>
        <li><a href="<?php echo BASE_URL; ?>/public/contact">Contact</a></li>
      </ul>
      <div class="actions">
        <a class="btn-outline" href="<?php echo BASE_URL; ?>/public/auth/?auth=login">Login</a>
        <a class="btn-primary" href="<?php echo BASE_URL; ?>/public/auth/?auth=register">Register</a>
      </div>
    </div>
  </nav>
  </header>

<main class="page">
  <div class="bg-texture"></div>
  <section class="auth-wrap">
    <article class="auth-card fade-in">
      <?php switch ($auth): case 'login': ?>
        <header class="auth-header">
          <h1>Sign In to Your Account</h1>
          <p>Welcome back! Please login to your account</p>
        </header>
        <form method="post" action="<?php echo BASE_URL; ?>/public/login" class="auth-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
          <label class="field">
            <span class="label">Email Address</span>
            <span class="input">
              <span class="icon">✉️</span>
              <input type="email" name="email" placeholder="admin@furniture.com" required autocomplete="email">
            </span>
          </label>
          <label class="field">
            <span class="label">Password</span>
            <span class="input">
              <span class="icon">🔒</span>
              <input type="password" id="login_password" name="password" placeholder="Enter your password" required autocomplete="current-password">
              <button type="button" class="peek" data-target="#login_password">👁️</button>
            </span>
          </label>
          <div class="meta">
            <label class="check">
              <input type="checkbox" name="remember_me"> Remember me
            </label>
            <a href="<?php echo BASE_URL; ?>/public/auth/?auth=forgot" class="link">Forgot Password?</a>
          </div>
          <button class="btn-primary btn-block" type="submit">Sign In</button>
        </form>
        <footer class="auth-footer">
          <small>Don’t have an account?
            <a href="<?php echo BASE_URL; ?>/public/auth/?auth=register">Register Now</a>
          </small>
        </footer>
      <?php break; case 'register': ?>
        <header class="auth-header">
          <h1>Create Your Account</h1>
          <p>Join our modern furniture management journey.</p>
        </header>
        <form id="registerForm" method="post" action="<?php echo BASE_URL; ?>/public/register" class="auth-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
          <input type="hidden" name="full_name" id="full_name_hidden">
          <div class="grid-2">
            <label class="field">
              <span class="label">First Name</span>
              <span class="input">
                <span class="icon">👤</span>
                <input type="text" id="first_name" placeholder="John" required>
              </span>
            </label>
            <label class="field">
              <span class="label">Last Name</span>
              <span class="input">
                <span class="icon">👤</span>
                <input type="text" id="last_name" placeholder="Doe" required>
              </span>
            </label>
          </div>
          <div class="grid-2">
            <label class="field">
              <span class="label">Phone Number</span>
              <span class="input">
                <span class="icon">📞</span>
                <input type="tel" name="phone" placeholder="+251 XXX XXX XXX">
              </span>
            </label>
            <label class="field">
              <span class="label">Email Address</span>
              <span class="input">
                <span class="icon">✉️</span>
                <input type="email" name="email" placeholder="newuser@furniture.com" required autocomplete="email">
              </span>
            </label>
          </div>
          <div class="grid-2">
            <label class="field">
              <span class="label">Password</span>
              <span class="input">
                <span class="icon">🔒</span>
                <input type="password" id="reg_password" name="password" placeholder="Create a strong password" required>
                <button type="button" class="peek" data-target="#reg_password">👁️</button>
              </span>
            </label>
            <label class="field">
              <span class="label">Confirm Password</span>
              <span class="input">
                <span class="icon">🔒</span>
                <input type="password" id="reg_confirm" name="confirm_password" placeholder="Re-enter your password" required>
                <button type="button" class="peek" data-target="#reg_confirm">👁️</button>
              </span>
            </label>
          </div>
          <label class="check">
            <input type="checkbox" required> I agree to the <a href="#" class="link">Terms &amp; Conditions</a>
          </label>
          <button class="btn-primary btn-block" type="submit">Create Your Account</button>
        </form>
        <footer class="auth-footer">
          <small>Already have an account?
            <a href="<?php echo BASE_URL; ?>/public/auth/?auth=login">Sign In</a>
          </small>
        </footer>
      <?php break; case 'forgot': ?>
        <header class="auth-header">
          <h1>Forgot Your Password?</h1>
          <p>Enter your registered email and we will send you a reset link.</p>
        </header>
        <form method="post" action="<?php echo BASE_URL; ?>/public/forgot-password" class="auth-body">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
          <label class="field">
            <span class="label">Email Address</span>
            <span class="input">
              <span class="icon">✉️</span>
              <input type="email" name="email" placeholder="admin@furniture.com" required autocomplete="email">
            </span>
          </label>
          <div class="helper">
            <small>Remembered your password?
              <a href="<?php echo BASE_URL; ?>/public/auth/?auth=login">Login Now</a>
            </small>
          </div>
          <button class="btn-primary btn-block" type="submit">Send Reset Link</button>
        </form>
        <footer class="auth-footer">
          <small>Don’t have an account?
            <a href="<?php echo BASE_URL; ?>/public/auth/?auth=register">Register Now</a>
          </small>
        </footer>
      <?php break; endswitch; ?>
    </article>
  </section>
</main>

<script>
document.querySelectorAll('.peek').forEach(function(btn){
  btn.addEventListener('click', function(){
    var t = document.querySelector(btn.getAttribute('data-target'));
    if (!t) return;
    t.type = t.type === 'password' ? 'text' : 'password';
  });
});
var rf = document.getElementById('registerForm');
if (rf) {
  rf.addEventListener('submit', function(){
    var fn = document.getElementById('first_name')?.value?.trim() || '';
    var ln = document.getElementById('last_name')?.value?.trim() || '';
    var full = (fn + ' ' + ln).trim();
    var hidden = document.getElementById('full_name_hidden');
    if (hidden) hidden.value = full;
  });
}
</script>
</body>
</html>

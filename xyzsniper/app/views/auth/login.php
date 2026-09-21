<main class="login-page">
  <div class="card login-card">
    <header class="login-header">
      <div class="login-icon">
        <span class="material-symbols-outlined" aria-hidden="true">lock</span>
      </div>
      <div class="login-copy">
        <h1 class="login-title">Selamat datang</h1>
        <p class="login-description">Masuk untuk melanjutkan ke <?= app_name; ?></p>
      </div>
    </header>

    <section>
      <form action="<?= burl; ?>/ProcAuth/login" method="POST" class="login-form" id="loginForm">
        <div role="group" class="fieldset">
          <div role="group" class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="nama@contoh.com" autocomplete="email" required />
          </div>

          <div role="group" class="field">
            <label for="password">Password</label>
            <div class="input-group">
              <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required />
              <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" onclick="togglePassword('password', 'toggleIconPassword')" aria-label="Tampilkan password">
                <span id="toggleIconPassword" class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
              </button>
            </div>
          </div>
        </div>
      </form>
    </section>

    <footer class="login-actions">
      <button type="submit" form="loginForm" class="btn" data-variant="primary">Masuk</button>
      <div class="login-divider" aria-hidden="true">
        <span>atau</span>
      </div>
      <a href="<?= burl; ?>/ProcAuth/google" class="btn" data-variant="outline">
        <img
          class="login-google-icon"
          src="https://thumb.wikimedia.org/wikipedia/commons/thumb/3/3c/Google_Favicon_2025.svg/960px-Google_Favicon_2025.svg.png"
          alt=""
          aria-hidden="true"
        />
        Masuk dengan Google
      </a>
    </footer>
  </div>
</main>
<script>
  function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
      input.type = 'text';
      icon.textContent = 'visibility_off';
    } else {
      input.type = 'password';
      icon.textContent = 'visibility';
    }
  }
</script>

<main class="panel-main" aria-label="Konten pengaturan akun">
  <div class="panel-page-header">
    <div>
      <h1 style="margin: 0;">Pengaturan Akun</h1>
      <p style="margin-top: 4px;">Kelola preferensi dan keamanan akun Anda di sini.</p>
    </div>
  </div>

  <div style="display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
    <!-- Ubah Email -->
    <article class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%;">
      <header class="dashboard-card-header" style="padding: 24px 24px 0 24px; margin-bottom: 0;">
        <div>
          <h2>Ubah Email</h2>
          <p>Perbarui alamat email yang terhubung dengan akun Anda.</p>
        </div>
      </header>
      <section style="padding: 24px; flex-grow: 1;">
        <form id="formUpdateEmail" action="<?= burl; ?>/ProcAccounts/updateEmail" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ProcAuth::csrfToken(), ENT_QUOTES); ?>" />
          <div role="group" class="fieldset">
            <div role="group" class="field">
              <label for="currentEmail">Email Saat Ini</label>
              <input type="email" id="currentEmail" value="<?= htmlspecialchars($_SESSION['account']['email'] ?? ''); ?>" disabled style="background-color: rgba(128,128,128,0.1); color: var(--color-muted-foreground);" />
            </div>
            <div role="group" class="field">
              <label for="newEmail">Email Baru</label>
              <input type="email" id="newEmail" name="new_email" placeholder="Masukkan email baru..." required />
            </div>
          </div>
        </form>
      </section>
      <footer style="padding: 16px 24px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; align-items: center;">
        <button type="submit" form="formUpdateEmail" id="btnSubmitEmail" class="btn" data-variant="primary" disabled>Simpan Email</button>
      </footer>
    </article>

    <!-- Ubah Password -->
    <article class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%;">
      <header class="dashboard-card-header" style="padding: 24px 24px 0 24px; margin-bottom: 0;">
        <div>
          <h2>Ubah Kata Sandi</h2>
          <?php if (!empty($_SESSION['account']['password'])): ?>
            <p>Perbarui kata sandi akun Anda.</p>
          <?php else: ?>
            <p style="color: var(--color-destructive, #ef4444); font-weight: 500;">Akun Anda belum memiliki kata sandi</p>
          <?php endif; ?>
        </div>
      </header>
      <section style="padding: 24px; flex-grow: 1;">
        <form id="formUpdatePassword" action="<?= burl; ?>/ProcAccounts/updatePassword" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ProcAuth::csrfToken(), ENT_QUOTES); ?>" />
          <div role="group" class="fieldset">
            <div role="group" class="field">
              <label for="newPassword">Kata Sandi Baru</label>
              <div class="input-group">
                <input type="password" id="newPassword" name="new_password" placeholder="Masukkan kata sandi baru..." required />
                <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" onclick="togglePassword('newPassword', 'toggleIconNewPassword')" aria-label="Tampilkan password">
                  <span id="toggleIconNewPassword" class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                </button>
              </div>
            </div>
            <div role="group" class="field" id="confirmPasswordField" style="position: relative;">
              <label for="confirmPassword">Konfirmasi Kata Sandi Baru</label>
              <div class="input-group">
                <input type="password" id="confirmPassword" name="confirm_password" placeholder="Ulangi kata sandi baru..." required />
                <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" onclick="togglePassword('confirmPassword', 'toggleIconConfirmPassword')" aria-label="Tampilkan password">
                  <span id="toggleIconConfirmPassword" class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                </button>
              </div>
              <span id="passwordValidationMsg" style="font-size: 0.75rem; position: absolute; bottom: -20px; left: 0; display: none;"></span>
            </div>
          </div>
        </form>
      </section>
      <footer style="padding: 16px 24px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; align-items: center;">
        <button type="submit" form="formUpdatePassword" id="btnSubmitPassword" class="btn" data-variant="primary" disabled>Perbarui Kata Sandi</button>
      </footer>
    </article>

    <!-- Card Dummy -->
    <article class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 100%;">
      <header class="dashboard-card-header" style="padding: 24px 24px 0 24px; margin-bottom: 0;">
        <div>
          <h2 style="display: flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined" style="color: #eab308; font-size: 20px;">bolt</span>
            Kode Pas Kilat
          </h2>
          <p>Kode untuk menghubungkan Ekstensi ke akun Anda.</p>
        </div>
      </header>
      <section style="padding: 24px; flex-grow: 1; display: flex; align-items: center; justify-content: center; gap: 16px;">
        <?php $passCode = Passcode::generate($_SESSION['account']['id']); ?>
        <div id="passcodeDisplay" style="font-size: 3rem; font-weight: 800; letter-spacing: 0.1em; color: var(--color-foreground); font-family: monospace;">
          <?= $passCode; ?>
        </div>
        <svg width="40" height="40" viewBox="0 0 40 40" style="transform: rotate(-90deg);">
          <defs>
            <linearGradient id="shimmerYellow" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#eab308" />
              <stop offset="50%" stop-color="#fef08a" />
              <stop offset="100%" stop-color="#eab308" />
              <animateTransform attributeName="gradientTransform" type="rotate" from="360 0.5 0.5" to="0 0.5 0.5" dur="3s" repeatCount="indefinite" />
            </linearGradient>
            <linearGradient id="shimmerRed" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#ef4444" />
              <stop offset="50%" stop-color="#fca5a5" />
              <stop offset="100%" stop-color="#ef4444" />
              <animateTransform attributeName="gradientTransform" type="rotate" from="360 0.5 0.5" to="0 0.5 0.5" dur="1.5s" repeatCount="indefinite" />
            </linearGradient>
          </defs>
          <path
            d="M20 4.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            fill="none"
            stroke="var(--color-border)"
            stroke-width="8"
          />
          <path
            id="passcodeTimer"
            d="M20 4.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
            fill="none"
            stroke="url(#shimmerYellow)"
            stroke-width="8"
            stroke-dasharray="100, 100"
            stroke-dashoffset="0"
            style="transition: stroke-dashoffset 1s linear, stroke 0.3s ease;"
          />
        </svg>
      </section>
      <footer style="padding: 16px 24px; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; align-items: center;">
        <button type="button" id="btnCopyPasscode" class="btn" data-variant="secondary" onclick="copyToClipboard('<?= $passCode; ?>', this)">
          <span class="material-symbols-outlined" style="font-size: 18px; margin-right: 4px;">content_copy</span>
          Salin Kode
        </button>
      </footer>
    </article>
  </div>

  <!-- Link Google Account -->
  <?php $is_google_linked = !empty($_SESSION['account']['google_id']); ?>
  <article class="card" style="padding: 0; overflow: hidden; margin-top: 24px;">
    <section style="padding: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
      <div>
        <h2 style="margin: 0; font-size: 1.125rem;">Akun <span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC05">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span></h2>
        <?php if ($is_google_linked): ?>
          <p style="margin: 4px 0 0 0; color: var(--color-muted-foreground); font-size: 0.875rem;">
            <?php if (!empty($_SESSION['account']['google_email'])): ?>
              Akun Anda sudah ditautkan dengan <strong><?= htmlspecialchars($_SESSION['account']['google_email']); ?></strong>.
            <?php else: ?>
              Akun Anda sudah ditautkan.
            <?php endif; ?>
          </p>
        <?php else: ?>
          <p style="margin: 4px 0 0 0; color: var(--color-muted-foreground); font-size: 0.875rem;">Silahkan klik tautkan untuk login menggunakan akun <span style="color:#4285F4">G</span><span style="color:#EA4335">o</span><span style="color:#FBBC05">o</span><span style="color:#4285F4">g</span><span style="color:#34A853">l</span><span style="color:#EA4335">e</span> Anda.</p>
        <?php endif; ?>
      </div>
      <?php if ($is_google_linked): ?>
        <button type="button" class="btn" data-variant="destructive" onclick="confirmAction('Putuskan Tautan Google', 'Apakah Anda yakin ingin memutuskan tautan akun Google ini?', '<?= burl; ?>/ProcAuth/unlinkGoogle', 'POST', 'Putuskan', 'destructive', 'link_off');">
          Putuskan
        </button>
      <?php else: ?>
        <a href="<?= burl; ?>/ProcAuth/google" class="btn" data-variant="outline" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18px" height="18px">
            <path fill="#FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/>
            <path fill="#FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/>
            <path fill="#4CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/>
            <path fill="#1976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/>
          </svg>
          Tautkan
        </a>
      <?php endif; ?>
    </section>
  </article>

  <script>
    function copyToClipboard(text, btn) {
      const showSuccess = () => {
        if (!btn) return;
        const originalHtml = btn.innerHTML;
        const originalStyle = btn.getAttribute('style') || '';
        
        btn.disabled = true;
        btn.style.backgroundColor = '#22c55e';
        btn.style.color = '#ffffff';
        btn.style.borderColor = '#22c55e';
        btn.innerHTML = '<span class="material-symbols-outlined" style="font-size: 18px; margin-right: 4px;">check</span>Tersalin!';
        
        setTimeout(() => {
          btn.disabled = false;
          btn.setAttribute('style', originalStyle);
          btn.innerHTML = originalHtml;
        }, 2000);
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
          showSuccess();
        }).catch(err => {
          console.error("Gagal menyalin teks: ", err);
        });
      } else {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        
        textArea.style.position = "absolute";
        textArea.style.left = "-999999px";
        
        document.body.prepend(textArea);
        textArea.select();

        try {
          document.execCommand('copy');
          showSuccess();
        } catch (error) {
          console.error("Gagal menyalin teks: ", error);
        } finally {
          textArea.remove();
        }
      }
    }

    // Auto-update Passcode without refresh
    window.addEventListener('DOMContentLoaded', () => {
      const serverTime = <?= time() * 1000; ?>;
      const localTime = Date.now();
      const timeOffset = localTime - serverTime;

      setInterval(() => {
        const currentServerTime = new Date(Date.now() - timeOffset);
        
        // Progress circle update
        const secondsRemaining = 60 - currentServerTime.getSeconds();
        const percentage = (secondsRemaining / 60) * 100;
        const timerElement = document.getElementById('passcodeTimer');
        
        if (timerElement) {
          timerElement.style.strokeDashoffset = 100 - percentage;
          if (secondsRemaining <= 10) {
            timerElement.style.stroke = 'url(#shimmerRed)'; // Red shimmer when <= 10s
          } else {
            timerElement.style.stroke = 'url(#shimmerYellow)'; // Default yellow shimmer
          }
        }

        // Only fetch at exactly the 0th second of the minute
        if (currentServerTime.getSeconds() === 0) {
          fetch('<?= burl; ?>/ProcAccounts/getPasscode')
            .then(res => res.json())
            .then(data => {
              if (data.passcode) {
                const display = document.getElementById('passcodeDisplay');
                const btn = document.getElementById('btnCopyPasscode');
                if (display && display.innerText !== data.passcode) {
                  display.innerText = data.passcode;
                  if (btn) {
                    btn.setAttribute('onclick', `copyToClipboard('${data.passcode}', this)`);
                  }
                }
              }
            })
            .catch(err => console.error('Gagal memperbarui kode pas:', err));
        }
      }, 1000);
    });

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

    document.addEventListener('DOMContentLoaded', () => {
      const emailInput = document.getElementById('newEmail');
      const btnSubmitEmail = document.getElementById('btnSubmitEmail');

      const newPassword = document.getElementById('newPassword');
      const confirmPassword = document.getElementById('confirmPassword');
      const btnSubmitPassword = document.getElementById('btnSubmitPassword');
      const passwordValidationMsg = document.getElementById('passwordValidationMsg');
      const confirmPasswordField = document.getElementById('confirmPasswordField');

      function validateEmailForm() {
        const email = emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        btnSubmitEmail.disabled = !emailRegex.test(email);
      }

      function validatePasswordForm() {
        const isFilled = newPassword.value.length >= 6 && confirmPassword.value.length > 0;
        const isMatch = newPassword.value === confirmPassword.value;
        btnSubmitPassword.disabled = !(isFilled && isMatch);
        
        if (confirmPassword.value.length > 0) {
          passwordValidationMsg.style.display = 'block';
          if (!isMatch) {
            passwordValidationMsg.textContent = 'Kata sandi tidak cocok.';
            passwordValidationMsg.style.color = 'var(--color-destructive, #ef4444)';
            confirmPasswordField.setAttribute('data-invalid', 'true');
          } else {
            passwordValidationMsg.textContent = 'Kata sandi cocok.';
            passwordValidationMsg.style.color = '#22c55e';
            confirmPasswordField.removeAttribute('data-invalid');
          }
        } else {
          passwordValidationMsg.style.display = 'none';
          confirmPasswordField.removeAttribute('data-invalid');
        }
      }

      emailInput.addEventListener('input', validateEmailForm);
      newPassword.addEventListener('input', validatePasswordForm);
      confirmPassword.addEventListener('input', validatePasswordForm);

    });
  </script>
</main>

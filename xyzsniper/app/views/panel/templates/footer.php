    <footer class="app-footer">
      <p>&copy; <?= date('Y'); ?> <?= app_name; ?>. Dibuat oleh Ryad XYZ.</p>
    </footer>
  </main>

  <script>
    window.addEventListener('DOMContentLoaded', () => {
      const themeButton = document.querySelector('[data-theme-toggle]');

      if (!themeButton || !window.basecoat?.theme) {
        return;
      }

      const updateThemeButton = () => {
        const isDark = window.basecoat.theme.get() === 'dark';
        const label = isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';

        themeButton.dataset.tooltip = label;
        themeButton.setAttribute('aria-label', label);
        themeButton.querySelector('.material-symbols-outlined').textContent = isDark ? 'light_mode' : 'dark_mode';
      };

      themeButton.addEventListener('click', () => {
        window.basecoat.theme.toggle();
        updateThemeButton();
      });

      updateThemeButton();
    });
  </script>
  
  <?php Dialog::renderGlobalConfirm(); ?>
  <?php Flasher::flash(); ?>
</body>
</html>

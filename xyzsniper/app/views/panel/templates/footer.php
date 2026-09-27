    <footer class="app-footer">
      <p>&copy; <?= date('Y'); ?> <?= app_name; ?>. Dibuat oleh Ryad XYZ.</p>
    </footer>
  </main>

  <?php Dialog::renderGlobalConfirm(); ?>
  <?php Flasher::flash(); ?>
</body>
</html>

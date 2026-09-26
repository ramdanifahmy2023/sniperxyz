<?php

class Dialog {
  public static function renderGlobalConfirm() {
    echo '
    <script>
      window.confirmAction = function(title, message, actionUrl, method = \'POST\', submitText = \'Ya, Lanjutkan\', submitVariant = \'destructive\', icon = \'warning\') {
        const dialog = document.getElementById(\'globalConfirmDialog\');
        document.getElementById(\'globalConfirmDialogTitle\').textContent = title;
        document.getElementById(\'globalConfirmDialogDesc\').textContent = message;
        document.getElementById(\'globalConfirmDialogIcon\').textContent = icon;
        
        const form = document.getElementById(\'globalConfirmForm\');
        form.action = actionUrl;
        form.method = method;
        
        const submitBtn = document.getElementById(\'globalConfirmSubmitBtn\');
        submitBtn.textContent = submitText;
        submitBtn.setAttribute(\'data-variant\', submitVariant);
        
        dialog.showModal();
      };
    </script>
    
    <dialog id="globalConfirmDialog" class="dialog" aria-labelledby="globalConfirmDialogTitle" aria-describedby="globalConfirmDialogDesc" onclick="if (event.target === this) this.close()">
      <div style="max-width: 320px; width: 100%; margin: 0 auto;">
        <header style="text-align: center;">
          <div style="margin-bottom: 8px;">
            <span id="globalConfirmDialogIcon" class="material-symbols-outlined" style="font-size: 40px; color: var(--color-destructive);">warning</span>
          </div>
          <h2 id="globalConfirmDialogTitle">Konfirmasi</h2>
          <p id="globalConfirmDialogDesc">Apakah Anda yakin?</p>
        </header>
        <form id="globalConfirmForm" method="POST" style="display: none;"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(ProcAuth::csrfToken(), ENT_QUOTES, 'UTF-8') . '" /></form>
        <footer style="display: flex; gap: 8px; width: 100%; margin-top: 16px;">
          <button type="button" class="btn" data-variant="outline" onclick="this.closest(\'dialog\').close()" style="flex: 1; justify-content: center;">Tutup</button>
          <button type="submit" form="globalConfirmForm" id="globalConfirmSubmitBtn" class="btn" data-variant="destructive" style="flex: 1; justify-content: center;">Ya, Lanjutkan</button>
        </footer>
      </div>
    </dialog>
    ';
  }
}

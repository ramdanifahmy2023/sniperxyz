<?php

class Flasher {
  public static function setFlash($title, $message, $type) {
    $_SESSION['flash'] = [
      'title' => $title,
      'message' => $message,
      'type' => $type
    ];
  }

  public static function flash() {
    if (isset($_SESSION['flash'])) {
      $flash = $_SESSION['flash'];
      $icon = '';
      
      if ($flash['type'] === 'success') {
        $icon = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
        $titleStyle = 'color: #22c55e;';
      } else if ($flash['type'] === 'error') {
        $icon = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
        $titleStyle = 'color: #ef4444;';
      } else if ($flash['type'] === 'warning') {
        $icon = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        $titleStyle = 'color: #eab308;';
      } else {
        $icon = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
        $titleStyle = 'color: #3b82f6;';
      }

      echo '
      <div class="toaster" style="position: fixed !important; top: 16px !important; bottom: auto !important; left: 50% !important; transform: translateX(-50%) !important; z-index: 999999 !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: flex-start !important; height: auto !important; pointer-events: none;">
        <div class="toast" role="status" aria-atomic="true" aria-hidden="false" data-category="'.$flash['type'].'" id="flasherToast" style="pointer-events: auto;">
          <div class="toast-content" style="background-color: var(--color-surface, #fff); border: 1px solid var(--color-border, #e5e7eb); border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            '.$icon.'
            <section>
              <h2 style="'.$titleStyle.'">'.htmlspecialchars($flash['title']).'</h2>
              <p style="word-break: normal; overflow-wrap: break-word; white-space: normal;">'.htmlspecialchars($flash['message']).'</p>
            </section>
            <footer>
              <button type="button" class="btn" data-variant="ghost" data-size="icon-sm" data-toast-cancel onclick="document.getElementById(\'flasherToast\').setAttribute(\'aria-hidden\', \'true\'); setTimeout(() => document.getElementById(\'flasherToast\').remove(), 300)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
              </button>
            </footer>
          </div>
        </div>
      </div>
      <script>
        setTimeout(() => {
          let toast = document.getElementById("flasherToast");
          if(toast) {
            toast.setAttribute("aria-hidden", "true");
            setTimeout(() => toast.remove(), 300);
          }
        }, 5000);
      </script>
      ';
      
      unset($_SESSION['flash']);
    }
  }
}

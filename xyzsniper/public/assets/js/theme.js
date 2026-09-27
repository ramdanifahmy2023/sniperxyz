(() => {
  'use strict';

  const STORAGE_KEY = 'themeMode';
  const MODES = ['light', 'dark', 'system'];
  const mediaQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  const readMode = () => {
    try {
      const stored = window.localStorage.getItem(STORAGE_KEY);
      return MODES.includes(stored) ? stored : 'system';
    } catch (_) {
      return 'system';
    }
  };

  const isDarkMode = (mode) => mode === 'dark' || (mode === 'system' && Boolean(mediaQuery?.matches));

  const apply = (mode, persist = true) => {
    const nextMode = MODES.includes(mode) ? mode : 'system';
    const dark = isDarkMode(nextMode);
    const root = document.documentElement;

    root.classList.toggle('dark', dark);
    root.dataset.theme = nextMode;
    root.style.colorScheme = dark ? 'dark' : 'light';

    if (persist) {
      try {
        window.localStorage.setItem(STORAGE_KEY, nextMode);
      } catch (_) {}
    }

    document.dispatchEvent(new CustomEvent('sniper:themechange', {
      detail: { mode: nextMode, resolved: dark ? 'dark' : 'light' },
    }));
  };

  const bind = () => {
    const mode = readMode();
    apply(mode, false);

    document.querySelectorAll('[data-theme-select]').forEach((select) => {
      select.value = mode;
      select.addEventListener('change', () => apply(select.value));
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.addEventListener('click', () => apply(isDarkMode(readMode()) ? 'light' : 'dark'));
    });

    const updateLabels = () => {
      const currentMode = readMode();
      const resolved = isDarkMode(currentMode) ? 'dark' : 'light';
      document.querySelectorAll('[data-theme-select]').forEach((select) => {
        select.value = currentMode;
        select.setAttribute('aria-label', `Tema tampilan: ${currentMode}`);
      });
      document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const icon = button.querySelector('.material-symbols-outlined');
        const label = resolved === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
        button.setAttribute('aria-label', label);
        button.dataset.tooltip = label;
        if (icon) icon.textContent = resolved === 'dark' ? 'light_mode' : 'dark_mode';
      });
    };

    document.addEventListener('sniper:themechange', updateLabels);
    updateLabels();
  };

  if (mediaQuery) {
    const onSystemChange = () => {
      if (readMode() === 'system') apply('system', false);
    };
    if (typeof mediaQuery.addEventListener === 'function') mediaQuery.addEventListener('change', onSystemChange);
    else if (typeof mediaQuery.addListener === 'function') mediaQuery.addListener(onSystemChange);
  }

  window.SniperTheme = { apply, get: readMode, isDark: () => isDarkMode(readMode()) };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind, { once: true });
  else bind();
})();

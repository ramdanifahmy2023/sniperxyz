// Theme preference is presentation-only and intentionally isolated from capture state.
(function () {
  'use strict';

  const STORAGE_KEY = 'themePreference';
  const VALID_THEMES = new Set(['light', 'dark', 'system']);
  const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  let preference = 'system';

  function normalize(value) {
    return VALID_THEMES.has(value) ? value : 'system';
  }

  function apply(value) {
    preference = normalize(value);
    document.documentElement.dataset.theme = preference;
    const dark = preference === 'dark' || (preference === 'system' && media && media.matches);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-select]').forEach((select) => {
      select.value = preference;
    });
  }

  function load() {
    if (!window.chrome || !chrome.storage || !chrome.storage.local) {
      apply('system');
      return;
    }
    chrome.storage.local.get([STORAGE_KEY], (result) => apply(result[STORAGE_KEY]));
  }

  function set(value) {
    apply(value);
    if (window.chrome && chrome.storage && chrome.storage.local) {
      chrome.storage.local.set({ [STORAGE_KEY]: preference });
    }
  }

  function mount() {
    document.querySelectorAll('[data-theme-select]').forEach((select) => {
      select.addEventListener('change', () => set(select.value));
    });
    load();
  }

  if (media) {
    const handleSystemChange = () => {
      if (preference === 'system') apply('system');
    };
    if (media.addEventListener) media.addEventListener('change', handleSystemChange);
    else if (media.addListener) media.addListener(handleSystemChange);
  }

  window.SniperTheme = { get: () => preference, set, apply };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
  else mount();
}());

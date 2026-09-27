<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $data['judul'] ?? app_name; ?></title>
  <link rel="icon" type="image/png" sizes="64x64" href="<?= assets; ?>/images/app_sources/favicon.png">

  <script>
    (() => {
      try {
        const storedTheme = localStorage.getItem('themeMode');
        const mode = ['light', 'dark', 'system'].includes(storedTheme) ? storedTheme : 'system';
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const dark = mode === 'dark' || (mode === 'system' && prefersDark);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.dataset.theme = mode;
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
      } catch (_) {}
    })();
  </script>
  
  <link rel="stylesheet" href="<?= assets; ?>/css/basecoat/basecoat-vega.cdn.min.css">
  <link rel="stylesheet" href="<?= assets; ?>/css/app.css">

  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
  <script src="<?= assets; ?>/js/theme.js" defer></script>
</head>
<body class="app-shell">

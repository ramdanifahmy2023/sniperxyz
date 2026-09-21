<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $data['judul'] ?? app_name; ?></title>
  <link rel="icon" type="image/png" href="<?= assets; ?>/images/app_sources/logo.png">

  <script>
    (() => {
      try {
        const storedTheme = localStorage.getItem('themeMode');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (storedTheme ? storedTheme === 'dark' : prefersDark) {
          document.documentElement.classList.add('dark');
        }
      } catch (_) {}
    })();
  </script>

  <link rel="stylesheet" href="<?= assets; ?>/css/basecoat/basecoat-vega.cdn.min.css">
  <link rel="stylesheet" href="<?= assets; ?>/css/app.css">
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
  <script src="<?= assets; ?>/css/basecoat/js/all.min.js" defer></script>
</head>
<body class="app-shell">
  <?php $this->v('panel/templates/sidebar'); ?>

  <main id="content" class="panel-shell">
    <?php $this->v('panel/templates/navbar'); ?>

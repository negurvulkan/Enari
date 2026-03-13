<!DOCTYPE html>
<html lang="de" data-admin-theme-resolved="{$adminTheme}" data-theme-resolved="{$adminTheme}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title} Login</title>
    {foreach $stylesheets as $stylesheet}
        <link rel="stylesheet" href="{$stylesheet}">
    {/foreach}
</head>
<body class="admin-auth-page" data-admin-theme-resolved="{$adminTheme}" data-theme-resolved="{$adminTheme}">
    <main class="admin-auth">
        <section class="admin-auth__panel">
            <p class="admin-auth__eyebrow">Maintainer Access</p>
            <h1 class="admin-auth__title">{$title}</h1>
            {if $requiresCredentials}
                <p class="admin-auth__hint">Melde dich mit dem konfigurierten Maintainer-Account an.</p>
            {else}
                <p class="admin-auth__hint">Kopiere die Sample-Konfiguration nach <code>site.config.php</code> und hinterlege dort gueltige Admin-Zugangsdaten oder nutze einen vertrauenswuerdigen lokalen Zugriff.</p>
            {/if}
            {if $errorMessage ne ''}
                <p class="admin-auth__error">{$errorMessage}</p>
            {/if}
            {if $requiresCredentials}
                <form method="post" action="{$loginActionUrl}" class="admin-auth__form">
                    <input type="hidden" name="csrf" value="{$csrfToken}">
                    <label class="admin-auth__field">
                        <span>Benutzername</span>
                        <input type="text" name="username" autocomplete="username" required>
                    </label>
                    <label class="admin-auth__field">
                        <span>Passwort</span>
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>
                    <button type="submit" class="admin-button admin-button--primary">Anmelden</button>
                </form>
            {/if}
        </section>
    </main>
</body>
</html>

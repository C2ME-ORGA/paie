<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="referrer" content="no-referrer">
        <meta name="robots" content="noindex, nofollow">

        <title>Invitation indisponible — {{ config('app.name', 'BatiPaie') }}</title>

        <style>
            :root {
                color-scheme: light;
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                color: #1f2937;
                background: #f3f4f6;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 1.5rem;
            }

            main {
                width: 100%;
                max-width: 34rem;
                padding: 2rem;
                border: 1px solid #d1d5db;
                border-radius: 0.75rem;
                background: #ffffff;
            }

            h1 {
                margin: 0 0 1rem;
                font-size: 1.5rem;
            }

            p {
                line-height: 1.55;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>Invitation indisponible</h1>

            <p>
                Ce lien d’invitation n’est pas valide ou n’est plus disponible.
            </p>

            <p>
                Contactez l’administrateur technique de
                {{ config('app.name', 'BatiPaie') }} pour obtenir une nouvelle invitation.
            </p>
        </main>
    </body>
</html>

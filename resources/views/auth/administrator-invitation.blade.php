<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="referrer" content="no-referrer">
        <meta name="robots" content="noindex, nofollow">

        <title>Créer le compte administrateur — {{ config('app.name', 'BatiPaie') }}</title>

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
                box-shadow: 0 12px 30px rgb(15 23 42 / 8%);
            }

            h1 {
                margin: 0 0 1rem;
                font-size: 1.5rem;
            }

            p {
                line-height: 1.55;
            }

            .muted {
                color: #4b5563;
                font-size: 0.9375rem;
            }

            .errors {
                margin: 1rem 0;
                padding: 0.75rem 1rem;
                border: 1px solid #fecaca;
                border-radius: 0.5rem;
                background: #fef2f2;
                color: #991b1b;
            }

            .errors ul {
                margin: 0;
                padding-left: 1.25rem;
            }

            form {
                display: grid;
                gap: 0.75rem;
                margin-top: 1.5rem;
            }

            label {
                font-weight: 600;
            }

            input {
                width: 100%;
                padding: 0.65rem 0.75rem;
                border: 1px solid #9ca3af;
                border-radius: 0.4rem;
                font: inherit;
            }

            input:focus {
                outline: 2px solid #b45309;
                outline-offset: 1px;
            }

            button {
                margin-top: 0.5rem;
                padding: 0.75rem 1rem;
                border: 0;
                border-radius: 0.4rem;
                background: #92400e;
                color: #ffffff;
                font: inherit;
                font-weight: 600;
                cursor: pointer;
            }

            button:hover {
                background: #78350f;
            }

            .hint {
                margin: -0.25rem 0 0;
                color: #4b5563;
                font-size: 0.875rem;
            }
        </style>
    </head>
    <body>
        <main>
            <h1>Créer le compte administrateur</h1>

            <p>
                Bonjour {{ $invitation->name }},
            </p>

            <p>
                Vous êtes invité(e) à créer le compte administrateur de
                <strong>{{ config('app.name', 'BatiPaie') }}</strong>
                pour l’adresse {{ $invitation->email }}.
            </p>

            <p class="muted">
                Ce lien est valable jusqu’au
                {{ $invitation->expires_at->translatedFormat('d/m/Y à H:i') }}
                et ne peut être utilisé qu’une seule fois.
            </p>

            @if ($errors->any())
                <div class="errors" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('administrator-invitations.accept', ['token' => $token], false) }}">
                @csrf

                <label for="password">Mot de passe</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    autofocus
                >
                <p class="hint">
                    Au moins 12 caractères, avec majuscules, minuscules, chiffres et symboles.
                </p>

                <label for="password_confirmation">Confirmation du mot de passe</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                >

                <button type="submit">Créer mon compte</button>
            </form>

            <p class="muted">
                Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail
                et informez l’équipe technique.
            </p>
        </main>
    </body>
</html>

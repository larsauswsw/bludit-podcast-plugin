// ESLint für das Admin-Skript des Plugins (Roadmap 036). Das mitgelieferte
// Podlove-Paket (player/) ist Fremdcode und wird nicht geprüft.
import js from '@eslint/js';
import globals from 'globals';

export default [
    js.configs.recommended,
    {
        files: ['bl-plugins/podcast/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2017,
            sourceType: 'script',
            globals: {
                ...globals.browser,
                // Von Bludit (bl-kernel/js/variables.php) bzw. adminView() gesetzt
                tokenCSRF: 'readonly',
                PODCAST_API_URL: 'readonly',
                PODCAST_TEXTS: 'readonly',
            },
        },
        rules: {
            // CLAUDE.md Regel 4: Daten nur per textContent ins DOM
            'no-restricted-properties': ['error',
                { property: 'innerHTML', message: 'Nur textContent verwenden (CLAUDE.md Regel 4).' },
                { property: 'outerHTML', message: 'Nur textContent verwenden (CLAUDE.md Regel 4).' },
                { property: 'insertAdjacentHTML', message: 'Nur textContent verwenden (CLAUDE.md Regel 4).' },
                { object: 'document', property: 'write', message: 'Kein document.write.' },
            ],
            // catch (e) ohne Verwendung ist hier gewollt (Fehler bewusst ignoriert)
            'no-unused-vars': ['error', { caughtErrors: 'none' }],
            'no-eval': 'error',
            'no-implied-eval': 'error',
            'no-new-func': 'error',
        },
    },
];

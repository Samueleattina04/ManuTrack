#!/usr/bin/env bash
# =====================================================================
#  ManuTrack — prepara il pacchetto di rilascio (Linux / macOS)
#
#  Uso:  scripts/release.sh            (dal commit corrente)
#        scripts/release.sh --dirty    (permette modifiche non salvate: solo per prove)
#        scripts/release.sh --demo     (pacchetto DEMO separato, con i dati dimostrativi)
#
#  Requisiti sul PC: git, PHP 8.3 con zip, Composer, Node 20+ con npm.
#  Risultato: dist/manutrack-<versione>.zip (+ .sha256), pronto da caricare
#  via FTP o Gestione file di DirectAdmin ed estrarre nella cartella dell'app.
# =====================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DIRTY=0
EDITION=cliente
for arg in "$@"; do
    case "$arg" in
        --dirty) DIRTY=1 ;;
        --demo) EDITION=demo ;;
        *) echo "Opzione sconosciuta: $arg" >&2; exit 2 ;;
    esac
done

VERSION="$(tr -d '[:space:]' < VERSION)"
SUFFIX=""
[[ "$EDITION" == "demo" ]] && SUFFIX="-demo"
BUILD="$ROOT/build/manutrack$SUFFIX-$VERSION"
DIST="$ROOT/dist"

echo "ManuTrack $VERSION · preparazione del pacchetto ($EDITION)"

if [[ "$DIRTY" -eq 0 ]] && [[ -n "$(git status --porcelain)" ]]; then
    echo "Ci sono modifiche non salvate in git. Fai un commit oppure usa --dirty per una prova." >&2
    exit 1
fi

if ! grep -q "^## \[$VERSION\]" CHANGELOG.md; then
    echo "CHANGELOG.md non ha una sezione per la versione $VERSION." >&2
    exit 1
fi

rm -rf "$BUILD"
mkdir -p "$BUILD"

echo "→ Copia dei sorgenti"
if [[ "$DIRTY" -eq 1 ]]; then
    # Copia l'albero di lavoro rispettando .gitignore
    git ls-files -z --cached --others --exclude-standard | tar --null -T - -cf - | tar -xf - -C "$BUILD"
else
    git archive --format=tar HEAD | tar -xf - -C "$BUILD"
fi

cd "$BUILD"

# Seeder e factory creano dati e account dimostrativi: non vanno ai clienti.
# Si tolgono prima di Composer, così non finiscono nemmeno nell'autoload.
# Il pacchetto demo tiene i seeder (le factory servono solo ai test).
if [[ "$EDITION" == "demo" ]]; then
    rm -rf database/factories
else
    rm -rf database/seeders database/factories
fi

echo "→ Dipendenze PHP di produzione"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --prefer-dist

echo "→ Compilazione del frontend"
npm ci --no-audit --no-fund
npm run build

echo "→ Pulizia"
rm -rf node_modules tests scripts .github docker docker-compose.yml \
       resources/js resources/css \
       phpunit.xml phpstan.neon pint.json package.json package-lock.json vite.config.js \
       .editorconfig .gitattributes .gitignore public/hot
find storage -type f ! -name '.gitignore' -delete
# Cartelle .git lasciate da composer quando scarica i pacchetti con git
find vendor -name .git -type d -prune -exec rm -rf {} +
rm -f bootstrap/cache/*.php

cd "$ROOT"
echo "→ Pacchetto"
php scripts/release/package.php "$BUILD" "$DIST" "--edition=$EDITION"
echo "Fatto."

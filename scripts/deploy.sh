#!/bin/bash

set -e

APP_DIR="/var/www/spendo"
BRANCH="main"

cd "$APP_DIR"

export NVM_DIR="$HOME/.nvm"

if [ -s "$NVM_DIR/nvm.sh" ]; then
    . "$NVM_DIR/nvm.sh"
fi

echo "==> Verificando entorno..."

if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: No existe APP_DIR: $APP_DIR"
    exit 1
fi

if [ ! -d ".git" ]; then
    echo "ERROR: $APP_DIR no parece ser un repositorio git."
    exit 1
fi

for cmd in git php composer; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "ERROR: No se encontró el comando: $cmd"
        exit 1
    fi
done

if ! command -v npm >/dev/null 2>&1; then
    echo "ERROR: No se encontró npm. Revisar NVM/PATH."
    exit 1
fi

if [ ! -w "$APP_DIR" ]; then
    echo "ERROR: El usuario actual no tiene permiso de escritura en $APP_DIR"
    exit 1
fi

if [ ! -w "$APP_DIR/storage" ]; then
    echo "ERROR: Sin permiso de escritura en storage/"
    exit 1
fi

if [ ! -w "$APP_DIR/bootstrap/cache" ]; then
    echo "ERROR: Sin permiso de escritura en bootstrap/cache/"
    exit 1
fi

if [ ! -f "artisan" ]; then
    echo "ERROR: No se encontró artisan. ¿Es un proyecto Laravel?"
    exit 1
fi

echo "==> Entorno OK."

echo "==> Commit actual..."
OLD_COMMIT=$(git rev-parse HEAD)

echo "==> Actualizando desde origin/$BRANCH..."
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

NEW_COMMIT=$(git rev-parse HEAD)

if [ "$OLD_COMMIT" = "$NEW_COMMIT" ]; then
    echo "==> No hay cambios nuevos."
    exit 0
fi

CHANGED_FILES=$(git diff --name-only "$OLD_COMMIT" "$NEW_COMMIT")

echo "==> Archivos modificados:"
echo "$CHANGED_FILES"

if echo "$CHANGED_FILES" | grep -qE '^(composer\.json|composer\.lock)$'; then
    echo "==> Cambios en Composer. Ejecutando composer install..."
    composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "==> Sin cambios en Composer."
fi

if echo "$CHANGED_FILES" | grep -qE '^(package\.json|package-lock\.json|vite\.config\.(js|ts)|resources/)'; then
    echo "==> Cambios de frontend detectados."

    if [ ! -d "node_modules" ] || echo "$CHANGED_FILES" | grep -qE '^(package\.json|package-lock\.json)$'; then
        echo "==> Cambios en dependencias npm. Ejecutando npm ci..."
        npm ci
    fi

    echo "==> Ejecutando npm run build..."
    npm run build
else
    echo "==> Sin cambios de frontend."
fi

echo "==> Ejecutando migraciones..."
php artisan migrate --force

echo "==> Deploy finalizado correctamente."

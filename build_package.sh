#!/bin/bash
# Script para crear paquete de distribución para DoliStore
# FileManager Pro v1.0.0

MODULE_NAME="filemanager"
VERSION="1.0.0"
PACKAGE_NAME="filemanager-pro-v${VERSION}"

echo "=========================================="
echo "  FileManager Pro - Build Package"
echo "  Version: ${VERSION}"
echo "=========================================="

# Directorio actual
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Crear directorio temporal
TEMP_DIR="/tmp/${PACKAGE_NAME}"
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR/${MODULE_NAME}"

echo "[1/6] Copiando archivos del módulo..."

# Copiar archivos principales
cp -r admin "$TEMP_DIR/${MODULE_NAME}/"
cp -r class "$TEMP_DIR/${MODULE_NAME}/" 2>/dev/null || mkdir -p "$TEMP_DIR/${MODULE_NAME}/class"
cp -r core "$TEMP_DIR/${MODULE_NAME}/"
cp -r css "$TEMP_DIR/${MODULE_NAME}/"
cp -r doc "$TEMP_DIR/${MODULE_NAME}/" 2>/dev/null || mkdir -p "$TEMP_DIR/${MODULE_NAME}/doc"
cp -r img "$TEMP_DIR/${MODULE_NAME}/"
cp -r js "$TEMP_DIR/${MODULE_NAME}/"
cp -r langs "$TEMP_DIR/${MODULE_NAME}/"
cp -r lib "$TEMP_DIR/${MODULE_NAME}/"
cp -r scripts "$TEMP_DIR/${MODULE_NAME}/"
cp -r sql "$TEMP_DIR/${MODULE_NAME}/"

# Copiar archivos raíz
cp action.php "$TEMP_DIR/${MODULE_NAME}/"
cp config.php "$TEMP_DIR/${MODULE_NAME}/"
cp index.php "$TEMP_DIR/${MODULE_NAME}/"
cp README.md "$TEMP_DIR/${MODULE_NAME}/"
cp CHANGELOG.md "$TEMP_DIR/${MODULE_NAME}/"
cp LICENSE "$TEMP_DIR/${MODULE_NAME}/"
cp INSTALL.md "$TEMP_DIR/${MODULE_NAME}/"

# Crear directorios vacíos necesarios
mkdir -p "$TEMP_DIR/${MODULE_NAME}/backups"
mkdir -p "$TEMP_DIR/${MODULE_NAME}/logs"
mkdir -p "$TEMP_DIR/${MODULE_NAME}/cache"
mkdir -p "$TEMP_DIR/${MODULE_NAME}/deletedfiles"
mkdir -p "$TEMP_DIR/${MODULE_NAME}/Papelera"

# Crear archivo .gitkeep en directorios vacíos
touch "$TEMP_DIR/${MODULE_NAME}/backups/.gitkeep"
touch "$TEMP_DIR/${MODULE_NAME}/logs/.gitkeep"
touch "$TEMP_DIR/${MODULE_NAME}/cache/.gitkeep"
touch "$TEMP_DIR/${MODULE_NAME}/deletedfiles/.gitkeep"
touch "$TEMP_DIR/${MODULE_NAME}/Papelera/.gitkeep"

echo "[2/6] Limpiando archivos innecesarios..."

# Eliminar archivos de desarrollo y temporales
find "$TEMP_DIR" -name "*.log" -delete
find "$TEMP_DIR" -name "*.tmp" -delete
find "$TEMP_DIR" -name ".DS_Store" -delete
find "$TEMP_DIR" -name "Thumbs.db" -delete
find "$TEMP_DIR" -name "*.bak" -delete
find "$TEMP_DIR" -name "*~" -delete

# Eliminar archivos de backups existentes
rm -rf "$TEMP_DIR/${MODULE_NAME}/backups/"*.zip
rm -rf "$TEMP_DIR/${MODULE_NAME}/backups/"*.json
rm -rf "$TEMP_DIR/${MODULE_NAME}/backups/"*.txt
rm -rf "$TEMP_DIR/${MODULE_NAME}/backups/"*.lock

# Eliminar archivos de logs
rm -rf "$TEMP_DIR/${MODULE_NAME}/logs/"*.log

# Eliminar cache
rm -rf "$TEMP_DIR/${MODULE_NAME}/cache/"*.php
rm -rf "$TEMP_DIR/${MODULE_NAME}/cache/"*.json
rm -rf "$TEMP_DIR/${MODULE_NAME}/lib/cache/"*.json

# Eliminar archivos de papelera de ejemplo
rm -rf "$TEMP_DIR/${MODULE_NAME}/deletedfiles/"*
rm -rf "$TEMP_DIR/${MODULE_NAME}/Papelera/"*.json

# Eliminar este script del paquete
rm -f "$TEMP_DIR/${MODULE_NAME}/build_package.sh"

echo "[3/6] Estableciendo permisos..."

# Permisos para directorios
find "$TEMP_DIR" -type d -exec chmod 755 {} \;

# Permisos para archivos
find "$TEMP_DIR" -type f -exec chmod 644 {} \;

# Permisos especiales para scripts
find "$TEMP_DIR/${MODULE_NAME}/scripts" -name "*.php" -exec chmod 644 {} \;

echo "[4/6] Generando archivo ZIP..."

cd /tmp
rm -f "${PACKAGE_NAME}.zip"
zip -r "${PACKAGE_NAME}.zip" "${PACKAGE_NAME}"

echo "[5/6] Moviendo paquete..."

mv "${PACKAGE_NAME}.zip" "$SCRIPT_DIR/"

echo "[6/6] Limpiando archivos temporales..."

rm -rf "$TEMP_DIR"

echo ""
echo "=========================================="
echo "  ✅ Paquete creado exitosamente!"
echo "=========================================="
echo ""
echo "  Archivo: ${PACKAGE_NAME}.zip"
echo "  Ubicación: $SCRIPT_DIR/"
echo "  Tamaño: $(du -h "$SCRIPT_DIR/${PACKAGE_NAME}.zip" | cut -f1)"
echo ""
echo "  Listo para subir a DoliStore"
echo "=========================================="

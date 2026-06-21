#!/bin/bash
# BambuJab — installation des dépendances Python dans un venv dédié.
# Crée resources/venv et y installe paho-mqtt + requests (versions pinnées).

PROGRESS_FILE=$1
BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
VENV_DIR="$BASE_DIR/venv"
REQ_FILE="$BASE_DIR/requirements.txt"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')][install_dep.sh] $1"; }

echo 0 > "$PROGRESS_FILE"

# 1. Python 3
if ! command -v python3 >/dev/null 2>&1; then
  log "ERREUR : python3 introuvable"
  echo "error" > "$PROGRESS_FILE"
  exit 1
fi
PY_VER=$(python3 -c 'import sys;print("%d.%d"%sys.version_info[:2])')
log "python3 détecté ($PY_VER)"
echo 10 > "$PROGRESS_FILE"

# 2. Module venv disponible ?
if ! python3 -c "import venv" >/dev/null 2>&1; then
  log "Module venv absent — tentative d'installation (python3-venv)..."
  SUDO=""
  [ "$(id -u)" -ne 0 ] && command -v sudo >/dev/null 2>&1 && SUDO="sudo"
  if command -v apt-get >/dev/null 2>&1; then
    $SUDO apt-get update -qq >/dev/null 2>&1
    $SUDO apt-get install -y -qq python3-venv python3-pip >/dev/null 2>&1
  fi
fi
echo 25 > "$PROGRESS_FILE"

# 3. Création / réutilisation du venv
if [ ! -f "$VENV_DIR/bin/python3" ]; then
  log "Création du venv dans $VENV_DIR"
  if ! python3 -m venv "$VENV_DIR"; then
    log "ERREUR : création du venv échouée"
    echo "error" > "$PROGRESS_FILE"
    exit 1
  fi
else
  log "venv déjà présent — réutilisation"
fi
echo 45 > "$PROGRESS_FILE"

# 4. requirements
if [ ! -f "$REQ_FILE" ]; then
  log "ERREUR : $REQ_FILE introuvable"
  echo "error" > "$PROGRESS_FILE"
  exit 1
fi

log "Mise à jour de pip..."
"$VENV_DIR/bin/python3" -m pip install --quiet --upgrade pip >/dev/null 2>&1
echo 60 > "$PROGRESS_FILE"

log "Installation des dépendances (paho-mqtt, requests)..."
if ! "$VENV_DIR/bin/python3" -m pip install --quiet -r "$REQ_FILE"; then
  log "ERREUR : pip install a échoué"
  echo "error" > "$PROGRESS_FILE"
  exit 1
fi
echo 85 > "$PROGRESS_FILE"

# 5. Vérification finale
if ! "$VENV_DIR/bin/python3" -c "import paho.mqtt.client" >/dev/null 2>&1; then
  log "ERREUR : paho-mqtt introuvable après installation"
  echo "error" > "$PROGRESS_FILE"
  exit 1
fi

log "Installation terminée avec succès"
echo 100 > "$PROGRESS_FILE"
rm -f "$PROGRESS_FILE"

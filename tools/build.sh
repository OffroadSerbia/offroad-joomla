#!/bin/bash

#
# Build skripta za OffRoad Serbia Joomla komponente
# Kreira ZIP fajlove za plugin-e, module i template override-e
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$PROJECT_ROOT/build"
JOOMLA_DIR="$PROJECT_ROOT/joomla"

echo "🚀 OffRoad Serbia Build Script"
echo "==============================="
echo ""

# Obriši postojeći build folder
if [ -d "$BUILD_DIR" ]; then
    echo "🧹 Čišćenje postojećeg build foldera..."
    rm -rf "$BUILD_DIR"
fi

# Kreiraj build folder
mkdir -p "$BUILD_DIR"

echo "📦 Kreiranje ZIP paketa..."
echo ""

# Build plugin-ova
if [ -d "$JOOMLA_DIR/plugins" ]; then
    for plugin_group in "$JOOMLA_DIR/plugins"/*; do
        if [ -d "$plugin_group" ]; then
            group_name=$(basename "$plugin_group")
            
            for plugin_dir in "$plugin_group"/*; do
                if [ -d "$plugin_dir" ]; then
                    plugin_name=$(basename "$plugin_dir")
                    zip_name="plg_${group_name}_${plugin_name}.zip"
                    
                    echo "  📦 Kreiram $zip_name..."
                    
                    cd "$plugin_dir"
                    zip -r "$BUILD_DIR/$zip_name" . -x "*.git*" "*.DS_Store*" "Thumbs.db*"
                    cd "$PROJECT_ROOT"
                fi
            done
        fi
    done
fi

# Build modula
if [ -d "$JOOMLA_DIR/modules" ]; then
    for module_dir in "$JOOMLA_DIR/modules"/*; do
        if [ -d "$module_dir" ]; then
            module_name=$(basename "$module_dir")
            zip_name="mod_${module_name}.zip"
            
            echo "  📦 Kreiram $zip_name..."
            
            cd "$module_dir"
            zip -r "$BUILD_DIR/$zip_name" . -x "*.git*" "*.DS_Store*" "Thumbs.db*"
            cd "$PROJECT_ROOT"
        fi
    done
fi

# Build template override-a
if [ -d "$JOOMLA_DIR/templates" ]; then
    for template_dir in "$JOOMLA_DIR/templates"/*; do
        if [ -d "$template_dir" ]; then
            template_name=$(basename "$template_dir")
            zip_name="tpl_${template_name}_overrides.zip"
            
            echo "  📦 Kreiram $zip_name..."
            
            cd "$template_dir"
            zip -r "$BUILD_DIR/$zip_name" . -x "*.git*" "*.DS_Store*" "Thumbs.db*"
            cd "$PROJECT_ROOT"
        fi
    done
fi

echo ""
echo "✅ Build završen!"
echo "📁 Paketi su dostupni u: $BUILD_DIR"
echo ""

# Lista kreiranh paketa
if [ -d "$BUILD_DIR" ] && [ -n "$(ls -A "$BUILD_DIR")" ]; then
    echo "📦 Kreirani paketi:"
    for zip_file in "$BUILD_DIR"/*.zip; do
        if [ -f "$zip_file" ]; then
            file_size=$(du -h "$zip_file" | cut -f1)
            echo "  - $(basename "$zip_file") ($file_size)"
        fi
    done
else
    echo "⚠️  Nema kreiranh paketa."
fi

echo ""
echo "🎯 Sledeći koraci:"
echo "1. Testirati plugin-e na staging sajtu"
echo "2. Instalirati kroz Joomla admin (Extensions → Install)"
echo "3. Aktivirati plugin-e u Plugin Manager"
echo ""
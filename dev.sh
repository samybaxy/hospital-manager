#!/bin/bash

# Script for Hospital Manager Development
# Usage: ./dev.sh [command]

PLUGIN_DIR="$(pwd)"

# Functions
function show_help() {
    echo "Hospital Manager Development Script"
    echo "-----------------------------------"
    echo "Usage: ./dev.sh [command]"
    echo ""
    echo "Commands:"
    echo "  install      Install all dependencies"
    echo "  build        Build the React application for production"
    echo "  dev          Start the development server"
    echo "  watch        Build and watch for changes"
    echo "  help         Show this help message"
    echo ""
}

function install_deps() {
    echo "Installing dependencies..."
    yarn install
    echo "Dependencies installed successfully!"
}

function build_app() {
    echo "Building the React application..."
    yarn build
    echo "Build completed!"
}

function dev_server() {
    echo "Starting development server..."
    yarn dev
}

function watch_changes() {
    echo "Building and watching for changes..."
    yarn watch
}

# Main script
case "$1" in
    "install")
        install_deps
        ;;
    "build")
        build_app
        ;;
    "dev")
        dev_server
        ;;
    "watch")
        watch_changes
        ;;
    "help" | *)
        show_help
        ;;
esac

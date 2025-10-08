#!/bin/bash

# Setup server management aliases for Cursor terminal

echo "🔧 Setting up server management aliases..."

# Add aliases to shell profile
SHELL_PROFILE=""

if [ -f "$HOME/.zshrc" ]; then
    SHELL_PROFILE="$HOME/.zshrc"
elif [ -f "$HOME/.bashrc" ]; then
    SHELL_PROFILE="$HOME/.bashrc"
elif [ -f "$HOME/.bash_profile" ]; then
    SHELL_PROFILE="$HOME/.bash_profile"
fi

if [ -n "$SHELL_PROFILE" ]; then
    echo "" >> "$SHELL_PROFILE"
    echo "# Laravel Server Management Aliases" >> "$SHELL_PROFILE"
    echo "alias server-status='php scripts/server-manager.php status'" >> "$SHELL_PROFILE"
    echo "alias server-setup='php scripts/server-manager.php setup-counter'" >> "$SHELL_PROFILE"
    echo "alias server-cache='php scripts/server-manager.php clear-cache'" >> "$SHELL_PROFILE"
    echo "alias server-migrate='php scripts/server-manager.php migrate'" >> "$SHELL_PROFILE"
    echo "alias server-zamzar='php scripts/server-manager.php check-zamzar'" >> "$SHELL_PROFILE"
    echo "alias server-exec='php scripts/server-manager.php execute'" >> "$SHELL_PROFILE"
    
    echo "✅ Aliases added to $SHELL_PROFILE"
    echo "📝 Reload your terminal or run: source $SHELL_PROFILE"
    echo ""
    echo "Available commands:"
    echo "  server-status   - Check server status"
    echo "  server-setup    - Setup contract counter"
    echo "  server-cache    - Clear all caches"
    echo "  server-migrate  - Run migrations"
    echo "  server-zamzar   - Check Zamzar jobs"
    echo "  server-exec     - Execute custom command"
else
    echo "❌ Could not find shell profile file"
    echo "Please manually add these aliases to your shell configuration:"
    echo ""
    echo "alias server-status='php scripts/server-manager.php status'"
    echo "alias server-setup='php scripts/server-manager.php setup-counter'"
    echo "alias server-cache='php scripts/server-manager.php clear-cache'"
    echo "alias server-migrate='php scripts/server-manager.php migrate'"
    echo "alias server-zamzar='php scripts/server-manager.php check-zamzar'"
    echo "alias server-exec='php scripts/server-manager.php execute'"
fi


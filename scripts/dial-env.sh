#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   source ./scripts/dial-env.example.sh
# Then run:
#   php ./app.php
#
# Notes:
# - This script must be *sourced* (not executed) so the variables persist in your shell.
# - Copy to ./scripts/dial-env.sh and edit values, or export them in your shell profile.

# Required
export DIAL_BASE_URI=""
export DIAL_DEPLOYMENT=""

# Optional (if your DIAL is secured)
export DIAL_API_KEY=""

# Optional (TLS): path to CA bundle (PEM) if you get cURL error 60 on Windows / behind a corporate proxy
export DIAL_CA_BUNDLE="./scripts/cacert.pem"

# Optional: disable xdebug step-debug noise when running CLI tools
export XDEBUG_MODE=off

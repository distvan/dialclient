# PowerShell environment variables for DialClient
#
# Usage (PowerShell):
#   . .\scripts\dial-env.ps1
# Then run:
#   php .\app.php
#
# Notes:
# - This script must be dot-sourced (leading ". ") so the variables persist in your session.
# - Keep secrets out of git; prefer storing tokens in your user profile.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# Required
$env:DIAL_BASE_URI = ''
$env:DIAL_DEPLOYMENT = ''

# Optional (if your DIAL is secured)
$env:DIAL_API_KEY = ''

# Optional (TLS): path to CA bundle (PEM) if you get cURL error 60 on Windows / behind a corporate proxy
$env:DIAL_CA_BUNDLE = (Resolve-Path .\scripts\cacert.pem).Path

# Optional: disable xdebug step-debug noise when running CLI tools
$env:XDEBUG_MODE = 'off'
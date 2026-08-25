---
name: joti-remote-operations
description: >-
  Runbook for executing remote server commands, inspecting databases, and managing files over SSH from Windows without interactive prompt blocks.
---

# Jotify Remote SSH Operations Guide

## 1. SSH Execution from Windows (Askpass Pattern)
To run commands against the production server (`86.92.200.173:2222`) non-interactively from Windows PowerShell:
```powershell
$env:SSH_ASKPASS = "C:\Users\NMaarleveld\.gemini\antigravity\brain\1ac60be5-381e-492a-9a9f-b2ac118b98f7\scratch\askpass.bat"
$env:SSH_ASKPASS_REQUIRE = "force"
$env:DISPLAY = "dummy:0"
```

## 2. Safe Script Piping (Avoiding Quote Escaping Bugs)
Instead of nesting complex double quotes inside PowerShell strings, pass scripts via stdin or Base64:
```powershell
# Method A: Direct stdin pipe
Get-Content "scratch/script.py" -Raw | ssh -p 2222 -o StrictHostKeyChecking=accept-new -o BatchMode=no root@86.92.200.173 "python3"

# Method B: Base64 pipe for bash commands
$b64 = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($cmd))
ssh -p 2222 -o StrictHostKeyChecking=accept-new -o BatchMode=no root@86.92.200.173 "echo '$b64' | base64 -d | bash"
```

## 3. Server Permissions Maintenance
Always ensure web directory permissions remain intact:
```bash
chown -R www-data:www-data /var/www/Joti/media/
chmod -R 775 /var/www/Joti/media/
```

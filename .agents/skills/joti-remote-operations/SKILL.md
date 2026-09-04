---
name: joti-remote-operations
description: >-
  Runbook for executing remote server commands, inspecting databases, and managing files over SSH across production and test environments from Windows without interactive prompt blocks.
---

# Jotify Remote SSH Operations Guide

## 1. Environment Connection Matrix

| Environment | Hostname | Public IP / Port | User | Web URL | Project Root | Default Branch |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Production** | `LAMP` | `86.92.200.173:2222` | `root` | `https://joti.maarleveld.app/` | `/var/www/Joti/` | `dev` / `main` |
| **Test (LXC)** | `debian` | `86.92.200.173:2223` | `root` | `https://test.joti.maarleveld.app/` | `/var/www/Joti/` | `autoinstall` / `dev` |

- Password for both: `@VoermanStraat11FAY!`
- MySQL/MariaDB credentials: User `adminer`, Password `@VoermanStraat11FAY!`, Database `jotihunt`

---

## 2. Remote Script Execution Patterns

### Pattern A: Python Paramiko (Recommended for AI Agents)
Execute remote commands or transfer files without relying on PowerShell terminal askpass wrappers:
```python
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
# Connect to Production (2222) or Test (2223)
ssh.connect('86.92.200.173', port=2222, username='root', password='@VoermanStraat11FAY!')

stdin, stdout, stderr = ssh.exec_command('git status')
print(stdout.read().decode())
ssh.close()
```

### Pattern B: PowerShell SSH Askpass (CLI Terminal)
To run commands directly from Windows PowerShell:
```powershell
# Method A: Direct stdin pipe
Get-Content "scratch/script.py" -Raw | ssh -p 2222 -o StrictHostKeyChecking=accept-new -o BatchMode=no root@86.92.200.173 "python3"

# Method B: Base64 pipe for bash commands (avoids escaping issues)
$b64 = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($cmd))
ssh -p 2222 -o StrictHostKeyChecking=accept-new -o BatchMode=no root@86.92.200.173 "echo '$b64' | base64 -d | bash"
```

---

## 3. Server Permissions & Web Ownership
Always ensure web directory permissions remain intact after file operations or git updates:
```bash
chown -R www-data:www-data /var/www/Joti/
chmod -R 775 /var/www/Joti/media/ /var/www/Joti/DB/
chmod 640 /var/www/Joti/dblogin.php
```

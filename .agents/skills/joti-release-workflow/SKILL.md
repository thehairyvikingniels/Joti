---
name: joti-release-workflow
description: >-
  Step-by-step procedure for preparing releases, maintaining CHANGELOG.md, synchronizing dev and main branches, and pushing annotated tags.
---

# Jotify Release & Versioning Workflow

## 1. Versioning Standards
- Version format: `vYYYY.MM.PATCH` (e.g. `v2026.08.1`).
- Development branch: `dev`
- Production / Release branch: `main`

## 2. Release Steps
1. **Update `CHANGELOG.md`**:
   - Follow [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format.
   - Group changes under `Highlights`, `Added`, `Fixed`, `Changed`.
   - Link relevant GitHub issue numbers (`#29`, `#16`, etc.).
2. **Commit and Push on `dev`**:
   ```bash
   git add CHANGELOG.md
   git commit -m "docs: add CHANGELOG.md for release vYYYY.MM.PATCH"
   git push origin dev
   ```
3. **Merge into `main` and Tag**:
   ```bash
   git checkout main
   git pull origin main
   git merge dev -m "Merge branch 'dev' into main for release vYYYY.MM.PATCH"
   git tag -a vYYYY.MM.PATCH -m "Release vYYYY.MM.PATCH - <Summary of changes>"
   ```
4. **Push Release to GitHub**:
   ```bash
   git push origin main
   git push origin vYYYY.MM.PATCH
   ```
5. **Switch Back to `dev`**:
   ```bash
   git checkout dev
   git status
   ```

#!/usr/bin/env bash
#
# Release the plugin: commit (optional), tag vX.Y.Z, push, create GitHub Release.
#
# Usage:
#   ./scripts/release-github.sh [X.Y.Z]
#   release-github [X.Y.Z]   # if symlinked onto PATH (see repo README or comments below)
#
# Before running:
#   - Bump Version in your main plugin PHP (and readme/changelog if you use them), OR
#   - Set MAIN_PLUGIN to auto-patch the header (see below).
#
# Token: you will be prompted to paste a PAT with `repo` scope (input is hidden).
# Do not commit tokens or put them in this file.

set -euo pipefail

# Resolve symlinks so PLUGIN_ROOT is correct when this script is on PATH (e.g. ~/bin → …/scripts/release-github.sh).
SCRIPT_SOURCE="${BASH_SOURCE[0]}"
while [[ -h "$SCRIPT_SOURCE" ]]; do
  _dir="$(cd -P "$(dirname "$SCRIPT_SOURCE")" && pwd)"
  SCRIPT_SOURCE="$(readlink "$SCRIPT_SOURCE")"
  [[ "$SCRIPT_SOURCE" != /* ]] && SCRIPT_SOURCE="${_dir}/${SCRIPT_SOURCE}"
done
SCRIPT_DIR="$(cd -P "$(dirname "$SCRIPT_SOURCE")" && pwd)"
# Directory that contains this script’s sibling files (the WordPress plugin root).
PLUGIN_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Git repo root: plugin folder may not have .git (ZIP copy, submodule, or monorepo).
GIT_ROOT=""
_search="$PLUGIN_ROOT"
while [[ -n "$_search" && "$_search" != "/" ]]; do
  if [[ -e "$_search/.git" ]]; then
    GIT_ROOT="$(git -C "$_search" rev-parse --show-toplevel)"
    break
  fi
  _search="$(dirname "$_search")"
done
unset _search

if [[ -z "${GIT_ROOT:-}" ]]; then
  echo "No Git repository found above: $PLUGIN_ROOT" >&2
  echo "Fix: cd to your plugin folder and run \`git init\` and \`git remote add origin …\`, or clone from GitHub so .git exists." >&2
  exit 1
fi

cd "$GIT_ROOT"

# Optional: absolute or repo-relative path to main plugin file for automatic Version bump.
# Example: export MAIN_PLUGIN="the-events-calendar-pro.php"
# Leave unset to skip editing files (you bump version yourself).
: "${MAIN_PLUGIN:=}"

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
  read -r -p "Release version (X.Y.Z, no v prefix): " VERSION
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][a-zA-Z0-9.-]+)?$ ]]; then
  echo "Invalid version: ${VERSION:-empty} — use something like 1.2.3 or 1.2.3-beta.1" >&2
  exit 1
fi

TAG="v${VERSION}"

ORIGIN_URL="$(git remote get-url origin 2>/dev/null || true)"
if [[ -z "$ORIGIN_URL" ]]; then
  echo "No git remote 'origin' configured." >&2
  exit 1
fi

# Parse owner/repo from origin (HTTPS or SSH).
OWNER=""
REPO=""
if [[ "$ORIGIN_URL" =~ github\.com[:/]([^/]+)/([^/.]+)(\.git)?$ ]]; then
  OWNER="${BASH_REMATCH[1]}"
  REPO="${BASH_REMATCH[2]}"
fi

if [[ -z "$OWNER" || -z "$REPO" ]]; then
  read -r -p "Could not parse repo from origin. Enter GitHub owner/repo (e.g. acme/my-plugin): " MANUAL
  OWNER="${MANUAL%%/*}"
  REPO="${MANUAL##*/}"
  REPO="${REPO%.git}"
fi

BRANCH="$(git branch --show-current)"
if [[ -z "$BRANCH" ]]; then
  echo "Detached HEAD — checkout a branch before releasing." >&2
  exit 1
fi

echo "Plugin dir: $PLUGIN_ROOT"
echo "Git root:   $GIT_ROOT"
echo "Repository: ${OWNER}/${REPO}"
echo "Branch:     $BRANCH"
echo "Tag:        $TAG"
echo

if [[ -n "$MAIN_PLUGIN" ]]; then
  PF="$MAIN_PLUGIN"
  [[ "$PF" != /* ]] && PF="$PLUGIN_ROOT/$PF"
  if [[ ! -f "$PF" ]]; then
    echo "MAIN_PLUGIN not found: $PF" >&2
    exit 1
  fi
  # WordPress plugin header: * Version: x.y.z
  if grep -qE '^\s*\*\s*Version:\s*' "$PF"; then
    if sed --version >/dev/null 2>&1; then
      sed -i -E "s/^([[:space:]]*\*[[:space:]]*Version:)[[:space:]].*/\1 ${VERSION}/" "$PF"
    else
      sed -i '' -E "s/^([[:space:]]*\*[[:space:]]*Version:)[[:space:]].*/\1 ${VERSION}/" "$PF"
    fi
    echo "Updated Version in $PF"
    git add -- "$PF"
    git commit -m "Bump version to ${VERSION}" || {
      echo "Nothing to commit or commit failed — fix and re-run." >&2
      exit 1
    }
  else
    echo "No '* Version:' line found in $PF — set version manually and re-run without MAIN_PLUGIN." >&2
    exit 1
  fi
fi

if [[ -n "$(git status --porcelain)" ]]; then
  echo "Working tree has uncommitted changes:" >&2
  git status -s >&2
  read -r -p "Commit all with message \"Release ${VERSION}\"? [y/N] " ANS
  if [[ "${ANS,,}" == "y" ]]; then
    git add -A
    git commit -m "Release ${VERSION}"
  else
    echo "Commit or stash changes, then run again." >&2
    exit 1
  fi
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "Tag $TAG already exists locally." >&2
  exit 1
fi

git tag -a "$TAG" -m "Release ${VERSION}"

echo "Pushing $BRANCH and $TAG to origin..."
echo "(Uses your normal git credentials — SSH or credential helper.)"
git push origin "$BRANCH"
git push origin "$TAG"

BODY="${RELEASE_NOTES:-Release ${VERSION}.}"
if command -v jq >/dev/null 2>&1; then
  JSON="$(jq -n --arg tag "$TAG" --arg body "$BODY" '{tag_name:$tag, name:$tag, body:$body}')"
else
  JSON="$(printf '%s' "$BODY" | python3 -c 'import json,sys; print(json.dumps({"tag_name":sys.argv[1],"name":sys.argv[1],"body":sys.stdin.read()}))' "$TAG")" || {
    echo "Install jq or python3 to build the release JSON payload." >&2
    exit 1
  }
fi

read -r -s -p "Paste GitHub token (repo scope; hidden): " GITHUB_TOKEN
echo
GITHUB_TOKEN="${GITHUB_TOKEN//$'\r'/}"
GITHUB_TOKEN="${GITHUB_TOKEN//[[:space:]]/}"

if [[ -z "$GITHUB_TOKEN" ]]; then
  echo "No token entered — create the release manually on GitHub for tag ${TAG}." >&2
  exit 1
fi

RESP="$(mktemp)"
trap 'rm -f "$RESP"' EXIT

echo "Creating GitHub release..."
HTTP_CODE="$(curl -sS -o "$RESP" -w '%{http_code}' -X POST \
  -H "Accept: application/vnd.github+json" \
  -H "Authorization: Bearer ${GITHUB_TOKEN}" \
  -H "X-GitHub-Api-Version: 2022-11-28" \
  "https://api.github.com/repos/${OWNER}/${REPO}/releases" \
  -d "$JSON")"

if [[ "$HTTP_CODE" != "201" ]]; then
  echo "GitHub API returned HTTP $HTTP_CODE" >&2
  cat "$RESP" >&2 || true
  exit 1
fi

echo "Done: release ${TAG} created on ${OWNER}/${REPO}"

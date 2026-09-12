#!/bin/sh
#
# Build the distributable plugin ZIP.
#
#   sh scripts/build-plugin.sh            -> dist/woocommerce-customer-portal.zip
#   sh scripts/build-plugin.sh --check    -> build, then fail if anything is off
#
# The archive is built from an explicit ALLOWLIST of runtime files, never from
# "everything except". A new development file can therefore never leak into a
# release by accident: it is simply not on the list. The script then audits
# its own output, so a broken allowlist fails the build instead of shipping.
#
# The build is deterministic: staged files get a fixed modification time
# (SOURCE_DATE_EPOCH, defaulting to a constant) and are added in sorted
# order, so the same tree always produces a byte-identical ZIP.
#
# Requires: sh, zip, unzip, php (for the version check). No Composer, no Node.

set -eu

SLUG="woocommerce-customer-portal"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d "${TMPDIR:-/tmp}/wcp-build.XXXXXX")"
ZIP="$DIST/$SLUG.zip"

# Fixed timestamp for reproducible archives (2024-01-01T00:00:00Z).
: "${SOURCE_DATE_EPOCH:=1704067200}"
export SOURCE_DATE_EPOCH

trap 'rm -rf "$STAGE"' EXIT INT TERM

fail() {
	printf 'build: %s\n' "$*" >&2
	exit 1
}

cd "$ROOT"

# ---------------------------------------------------------------------------
# 1. Version consistency. Every place a version is declared must agree.
# ---------------------------------------------------------------------------
header_version="$(sed -n 's/^ \* Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' "$SLUG.php" | head -n 1)"
const_version="$(sed -n "s/.*define( 'WCP_VERSION', '\([0-9][0-9.]*\)' ).*/\1/p" "$SLUG.php" | head -n 1)"
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' readme.txt | head -n 1)"
changelog_version="$(sed -n 's/^## \[\([0-9][0-9.]*\)\].*/\1/p' CHANGELOG.md | head -n 1)"

[ -n "$header_version" ] || fail "could not read the Version header from $SLUG.php"
[ "$header_version" = "$const_version" ] || fail "Version header ($header_version) != WCP_VERSION ($const_version)"
[ "$header_version" = "$stable_tag" ] || fail "Version header ($header_version) != readme.txt Stable tag ($stable_tag)"
[ "$header_version" = "$changelog_version" ] || fail "Version header ($header_version) != latest CHANGELOG.md entry ($changelog_version)"

VERSION="$header_version"

# ---------------------------------------------------------------------------
# 2. Stage the runtime allowlist.
# ---------------------------------------------------------------------------
RUNTIME_FILES="
$SLUG.php
uninstall.php
readme.txt
license.txt
"

RUNTIME_DIRS="
admin
assets
includes
languages
public
rest
templates
"

# Files inside runtime directories that are still not runtime.
STAGE_EXCLUDE_NAMES="README.md .gitkeep .DS_Store Thumbs.db"

mkdir -p "$STAGE/$SLUG"

for f in $RUNTIME_FILES; do
	[ -f "$f" ] || fail "required runtime file missing: $f"
	cp "$f" "$STAGE/$SLUG/$f"
done

for d in $RUNTIME_DIRS; do
	[ -d "$d" ] || fail "required runtime directory missing: $d"
	# Only regular files are copied; empty directories are not shipped.
	find "$d" -type f | LC_ALL=C sort | while IFS= read -r path; do
		base="$(basename "$path")"
		skip=0
		for name in $STAGE_EXCLUDE_NAMES; do
			[ "$base" = "$name" ] && skip=1
		done
		[ "$skip" -eq 1 ] && continue
		mkdir -p "$STAGE/$SLUG/$(dirname "$path")"
		cp "$path" "$STAGE/$SLUG/$path"
	done
done

# Normalise permissions and timestamps so the archive is reproducible.
find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +
if touch -d "@$SOURCE_DATE_EPOCH" "$STAGE" 2>/dev/null; then
	find "$STAGE" -exec touch -d "@$SOURCE_DATE_EPOCH" {} +
else
	# BSD touch (macOS) has no @epoch form.
	stamp="$(date -u -r "$SOURCE_DATE_EPOCH" '+%Y%m%d%H%M.%S')"
	find "$STAGE" -exec touch -t "$stamp" {} +
fi

# ---------------------------------------------------------------------------
# 3. Archive. Sorted input, no extra attributes, top-level folder = slug.
# ---------------------------------------------------------------------------
mkdir -p "$DIST"
rm -f "$ZIP"

(
	cd "$STAGE"
	find "$SLUG" -type f | LC_ALL=C sort > "$STAGE/.manifest"
	zip -q -X -D "$ZIP" -@ < "$STAGE/.manifest"
)

# ---------------------------------------------------------------------------
# 4. Audit the archive that was actually written.
# ---------------------------------------------------------------------------
listing="$(unzip -Z1 "$ZIP")"

# Exactly one top-level directory, named after the slug, no double nesting.
top_levels="$(printf '%s\n' "$listing" | cut -d/ -f1 | LC_ALL=C sort -u)"
[ "$top_levels" = "$SLUG" ] || fail "archive must contain only '$SLUG/' at the top level, found: $(printf '%s' "$top_levels" | tr '\n' ' ')"
printf '%s\n' "$listing" | grep -q "^$SLUG/$SLUG.php$" || fail "main plugin file is not directly under $SLUG/"
if printf '%s\n' "$listing" | grep -q "^$SLUG/$SLUG/"; then
	fail "double-nested directory detected"
fi

# Nothing that belongs to development may be present.
forbidden='(^|/)(\.git|\.github|tests?|node_modules|vendor|docker|scripts|docs|dist|\.vscode|\.idea)(/|$)|(^|/)(composer\.(json|lock)|package(-lock)?\.json|phpunit.*\.xml(\.dist)?|phpcs\.xml(\.dist)?|phpstan\.neon(\.dist)?|\.phpunit\.result\.cache|\.gitignore|\.gitattributes|\.editorconfig|\.env.*|Dockerfile|docker-compose.*|.*\.log|\.DS_Store|Thumbs\.db|.*\.map|.*\.sh|README\.md|CHANGELOG\.md|.*\.pem|.*\.key|wp-config.*)$'
if printf '%s\n' "$listing" | grep -E -q "$forbidden"; then
	printf '%s\n' "$listing" | grep -E "$forbidden" >&2
	fail "development files found in the archive (listed above)"
fi

# Every shipped file is a type the plugin actually uses.
if printf '%s\n' "$listing" | grep -v '/$' | grep -E -v '\.(php|css|js|txt|pot)$' >/dev/null; then
	printf '%s\n' "$listing" | grep -v '/$' | grep -E -v '\.(php|css|js|txt|pot)$' >&2
	fail "unexpected file types in the archive (listed above)"
fi

# Every PHP file in the archive parses.
if command -v php >/dev/null 2>&1; then
	find "$STAGE/$SLUG" -name '*.php' | while IFS= read -r file; do
		php -l "$file" >/dev/null 2>&1 || fail "syntax error in $file"
	done
fi

count="$(printf '%s\n' "$listing" | grep -v '/$' | wc -l | tr -d ' ')"
size="$(wc -c < "$ZIP" | tr -d ' ')"

if command -v shasum >/dev/null 2>&1; then
	sha="$(shasum -a 256 "$ZIP" | cut -d' ' -f1)"
else
	sha="$(sha256sum "$ZIP" | cut -d' ' -f1)"
fi

printf 'build: %s %s\n' "$SLUG" "$VERSION"
printf 'build: %s files, %s bytes\n' "$count" "$size"
printf 'build: sha256 %s\n' "$sha"
printf 'build: %s\n' "$ZIP"

# --check prints the full manifest for review as well.
if [ "${1:-}" = "--check" ]; then
	printf '\n'
	printf '%s\n' "$listing" | grep -v '/$'
fi

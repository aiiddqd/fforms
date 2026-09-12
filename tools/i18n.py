#!/usr/bin/env python3
"""Translation toolchain for the FForms plugin.

Source strings are English; `languages/fforms-ru_RU.po` carries the Russian
translation. The tool rebuilds every generated artifact from the sources:

    languages/fforms.pot              template with all translatable strings
    languages/fforms-ru_RU.po         existing translations kept, new msgids added
    languages/fforms-ru_RU.mo         compiled catalog for PHP (needs msgfmt)
    languages/fforms-ru_RU-<md5>.json per-script catalogs for wp.i18n in JS

Usage: python3 tools/i18n.py   (or `make i18n`)
"""

from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
from datetime import datetime, timezone

DOMAIN = 'fforms'
LOCALE = 'ru_RU'
PLURAL_FORMS = (
    'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : '
    '((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);'
)

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LANG_DIR = os.path.join(ROOT, 'languages')

# Directories scanned for source strings. `build/` holds the compiled block
# scripts WordPress actually enqueues, so its strings need catalogs too.
SOURCE_DIRS = ('includes', 'assets', 'src', 'build')

# Scripts that get their own JSON catalog, keyed by the path WordPress resolves
# from the script URL (that path is what load_script_textdomain() hashes).
JSON_SCRIPT_GLOBS = ('assets/*.js', 'build/blocks/*/*.js')

# Single-argument text functions plus their escaping variants.
SINGLE_KEYWORDS = (
    '__', '_e', '_x', '_n_noop',
    'esc_html__', 'esc_html_e', 'esc_html_x',
    'esc_attr__', 'esc_attr_e', 'esc_attr_x',
)
PLURAL_KEYWORDS = ('_n', '_nx')

STRING_RE = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
# `\)?` also matches the minified `(0, i.__)( 'x', 'fforms' )` form webpack
# emits into build/, where the same strings have to be found again.
CALL = r'(?:\b|\.)(?:%s)\s*\)?\s*\(\s*'
SINGLE_RE = re.compile(CALL % '|'.join(SINGLE_KEYWORDS) + STRING_RE, re.S)
PLURAL_RE = re.compile(
    CALL % '|'.join(PLURAL_KEYWORDS) + STRING_RE + r'\s*,\s*' + STRING_RE,
    re.S,
)


def unescape(raw: str, quote: str) -> str:
    """Turn a PHP/JS string literal body into its runtime value."""
    out, i = [], 0
    while i < len(raw):
        ch = raw[i]
        if ch == '\\' and i + 1 < len(raw):
            nxt = raw[i + 1]
            if nxt in ('\\', quote):
                out.append(nxt)
                i += 2
                continue
            if quote == '"' and nxt == 'n':
                out.append('\n')
                i += 2
                continue
            out.append(ch)
            i += 1
            continue
        out.append(ch)
        i += 1
    return ''.join(out)


def literal(match: re.Match, group: int) -> str:
    single, double = match.group(group), match.group(group + 1)
    return unescape(single, "'") if single is not None else unescape(double, '"')


def source_files() -> list[str]:
    files = []
    for base in SOURCE_DIRS:
        for dirpath, _dirnames, filenames in os.walk(os.path.join(ROOT, base)):
            for name in sorted(filenames):
                if name.endswith(('.php', '.js')) or name == 'block.json':
                    files.append(os.path.relpath(os.path.join(dirpath, name), ROOT))
    return sorted(files)


def extract(path: str) -> list[tuple[str, str | None, int]]:
    """Return (msgid, msgid_plural, line) found in one file."""
    text = open(os.path.join(ROOT, path), encoding='utf-8').read()
    found = []
    if path.endswith('block.json'):
        meta = json.loads(text)
        if meta.get('textdomain') == DOMAIN:
            for key in ('title', 'description'):
                if meta.get(key):
                    found.append((meta[key], None, 1))
            for keyword in meta.get('keywords', []):
                found.append((keyword, None, 1))
        return found

    line_of = lambda pos: text.count('\n', 0, pos) + 1  # noqa: E731
    for match in PLURAL_RE.finditer(text):
        found.append((literal(match, 1), literal(match, 3), line_of(match.start())))
    plural_spans = [m.span() for m in PLURAL_RE.finditer(text)]
    for match in SINGLE_RE.finditer(text):
        if any(start <= match.start() < end for start, end in plural_spans):
            continue
        found.append((literal(match, 1), None, line_of(match.start())))
    return found


def collect() -> dict[tuple[str, str | None], list[str]]:
    """Map (msgid, msgid_plural) to sorted "file:line" references."""
    entries: dict[tuple[str, str | None], list[str]] = {}
    for path in source_files():
        for msgid, plural, line in extract(path):
            if msgid == '':
                continue
            entries.setdefault((msgid, plural), []).append(f'{path}:{line}')
    return entries


def po_escape(value: str) -> str:
    return value.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')


def parse_po(path: str) -> dict[tuple[str, str | None], list[str]]:
    """Read translations out of an existing .po file."""
    if not os.path.exists(path):
        return {}
    translations: dict[tuple[str, str | None], list[str]] = {}
    msgid = plural = None
    msgstrs: dict[int, str] = {}
    target: list[str] | None = None
    buffer: dict[str, str] = {}

    def flush():
        if msgid is None:
            return
        values = [msgstrs[key] for key in sorted(msgstrs)]
        if any(values):
            translations[(msgid, plural)] = values

    for raw in open(path, encoding='utf-8'):
        line = raw.strip()
        if line.startswith('#') or line == '':
            continue
        if line.startswith('msgid_plural '):
            plural = unescape_po(line[len('msgid_plural '):])
            buffer['plural'] = plural
            target = None
            continue
        if line.startswith('msgid '):
            flush()
            msgid, plural, msgstrs, buffer = unescape_po(line[len('msgid '):]), None, {}, {}
            target = 'msgid'
            continue
        if line.startswith('msgstr['):
            index = int(line[len('msgstr['):line.index(']')])
            msgstrs[index] = unescape_po(line[line.index(']') + 2:])
            target = f'msgstr{index}'
            continue
        if line.startswith('msgstr '):
            msgstrs[0] = unescape_po(line[len('msgstr '):])
            target = 'msgstr0'
            continue
        if line.startswith('"') and target:
            chunk = unescape_po(line)
            if target == 'msgid':
                msgid += chunk
            elif target.startswith('msgstr'):
                msgstrs[int(target[len('msgstr'):])] += chunk
    flush()
    return translations


def unescape_po(value: str) -> str:
    value = value.strip()
    if value.startswith('"'):
        value = value[1:]
    if value.endswith('"'):
        value = value[:-1]
    return value.replace('\\n', '\n').replace('\\"', '"').replace('\\\\', '\\')


def write_catalog(path: str, entries, translations, header: str) -> None:
    lines = [header]
    for (msgid, plural), refs in sorted(entries.items()):
        lines.append('#: ' + ' '.join(refs))
        lines.append(f'msgid "{po_escape(msgid)}"')
        values = translations.get((msgid, plural), [])
        if plural is None:
            lines.append(f'msgstr "{po_escape(values[0]) if values else ""}"')
        else:
            lines.append(f'msgid_plural "{po_escape(plural)}"')
            for index in range(3):
                value = values[index] if index < len(values) else ''
                lines.append(f'msgstr[{index}] "{po_escape(value)}"')
        lines.append('')
    open(path, 'w', encoding='utf-8').write('\n'.join(lines))


POT_HEADER = f'''# Copyright (C) FForms
# This file is distributed under the same license as the FForms plugin.
msgid ""
msgstr ""
"Project-Id-Version: FForms\\n"
"Report-Msgid-Bugs-To: https://github.com/aiiddqd/fforms\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Generator: tools/i18n.py\\n"
"X-Domain: {DOMAIN}\\n"
'''

PO_HEADER = f'''# Russian translation of the FForms plugin.
msgid ""
msgstr ""
"Project-Id-Version: FForms\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Language: {LOCALE}\\n"
"Plural-Forms: {PLURAL_FORMS}\\n"
"X-Generator: tools/i18n.py\\n"
"X-Domain: {DOMAIN}\\n"
'''


def write_json_catalogs(translations) -> list[str]:
    """One catalog per enqueued script, named the way WordPress looks it up."""
    import glob

    written = []
    for pattern in JSON_SCRIPT_GLOBS:
        for absolute in sorted(glob.glob(os.path.join(ROOT, pattern))):
            relative = os.path.relpath(absolute, ROOT)
            messages = {}
            for msgid, plural, _line in extract(relative):
                values = translations.get((msgid, plural))
                if values:
                    messages[msgid] = list(values)
            if not messages:
                continue
            payload = {
                'translation-revision-date': datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S+0000'),
                'generator': 'tools/i18n.py',
                'source': relative,
                'domain': 'messages',
                'locale_data': {
                    'messages': dict(
                        {'': {'domain': 'messages', 'lang': LOCALE, 'plural-forms': PLURAL_FORMS}},
                        **messages,
                    )
                },
            }
            name = f'{DOMAIN}-{LOCALE}-{hashlib.md5(relative.encode()).hexdigest()}.json'
            with open(os.path.join(LANG_DIR, name), 'w', encoding='utf-8') as handle:
                json.dump(payload, handle, ensure_ascii=False, separators=(',', ':'))
            written.append(f'{name}  ({relative})')
    return written


def main() -> int:
    os.makedirs(LANG_DIR, exist_ok=True)
    entries = collect()
    po_path = os.path.join(LANG_DIR, f'{DOMAIN}-{LOCALE}.po')
    translations = parse_po(po_path)

    write_catalog(os.path.join(LANG_DIR, f'{DOMAIN}.pot'), entries, {}, POT_HEADER)
    write_catalog(po_path, entries, translations, PO_HEADER)

    # Stale JSON catalogs would keep shipping strings that no longer exist.
    for name in os.listdir(LANG_DIR):
        if name.endswith('.json'):
            os.remove(os.path.join(LANG_DIR, name))
    catalogs = write_json_catalogs(translations)

    mo_path = os.path.join(LANG_DIR, f'{DOMAIN}-{LOCALE}.mo')
    try:
        subprocess.run(['msgfmt', '-o', mo_path, po_path], check=True)
    except FileNotFoundError:
        print('msgfmt not found — install gettext to compile the .mo file', file=sys.stderr)
        return 1

    missing = [key[0] for key in entries if key not in translations]
    print(f'strings: {len(entries)}  translated: {len(entries) - len(missing)}  json: {len(catalogs)}')
    for name in catalogs:
        print(f'  {name}')
    if missing:
        print('untranslated:')
        for msgid in sorted(missing):
            print(f'  {msgid}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())

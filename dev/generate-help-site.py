#!/usr/bin/env python3
"""Prepare the in-administrator Help articles from the project Markdown guides."""

from __future__ import annotations

import argparse
import html
import json
import re
import shutil
from pathlib import Path
from urllib.parse import quote, urlparse

ROOT = Path(__file__).resolve().parents[1]
DOCS = ROOT / "docs"

GROUPS = [
    (
        "COM_DHARMASHOP_HELP_GROUP_USER",
        [
            "HELP",
            "SETTINGS_REFERENCE",
            "FIELDS",
            "FILES_FIELD",
            "VARIANTS",
            "BATCH_PROCESSING",
            "FILTER",
            "SEARCH",
            "LAYOUTS",
            "MODULE_CATEGORIES",
            "MODULE_PRODUCTS",
            "CHECKOUT",
            "SHIPPING",
            "PAYMENTS",
            "ORDERS",
            "CUSTOMER_IDENTITY",
            "NOTIFICATIONS",
            "WAREHOUSES",
            "IMAGE_RESIZE",
            "SEO",
            "RELATED_PRODUCTS",
            "LANDING",
            "SECURITY",
            "SETTINGS_SEARCH",
        ],
    ),
    (
        "COM_DHARMASHOP_HELP_GROUP_DEVELOPER",
        [
            "DEVELOPER_GUIDE",
            "ARCHITECTURE",
            "TRIGGERS",
            "SHORTCODES",
            "DEVELOPMENT",
            "DECISIONS",
            "ROADMAP",
            "FILTER_IMPLEMENTATION",
            "BENCHMARKS_NOTES",
        ],
    ),
]


def slugify_heading(value: str) -> str:
    value = re.sub(r"<[^>]*>", "", value).strip().lower()
    value = re.sub(r"[^\w\-\s]", "", value, flags=re.UNICODE)
    return re.sub(r"\s+", "-", value)


def render_inline(value: str, source_slug: str, available: set[str]) -> str:
    tokens: dict[str, str] = {}

    def hold(rendered: str) -> str:
        token = f"DHARMAINLINE{len(tokens)}TOKEN"
        tokens[token] = rendered
        return token

    value = re.sub(r"`([^`]+)`", lambda m: hold(f'<code>{html.escape(m.group(1))}</code>'), value)

    def link(match: re.Match[str]) -> str:
        label, target = match.group(1), match.group(2).strip()
        parsed = urlparse(target)
        if parsed.scheme in {"https", "http", "mailto"}:
            safe_url = html.escape(target, quote=True)
            rel = ' rel="noopener noreferrer" target="_blank"' if parsed.scheme in {"https", "http"} else ""
            return hold(f'<a href="{safe_url}"{rel}>{render_inline(label, source_slug, available)}</a>')

        if parsed.scheme:
            return hold(html.escape(label))

        target_path = (DOCS / source_slug).parent / parsed.path
        target_slug = target_path.stem.upper()
        if target_path.suffix.lower() == ".md" and target_slug in available:
            href = f"index.php?option=com_dharmashop&amp;view=help&amp;article={quote(target_slug)}"
            if parsed.fragment:
                href += "#" + quote(parsed.fragment, safe="-_.")
            return hold(f'<a href="{href}">{render_inline(label, source_slug, available)}</a>')

        return hold(html.escape(label))

    value = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", link, value)
    value = html.escape(value)
    value = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", value)
    value = re.sub(r"(?<!\*)\*([^*]+)\*(?!\*)", r"<em>\1</em>", value)
    for token, rendered in tokens.items():
        value = value.replace(token, rendered)
    return value


def split_table_row(line: str) -> list[str]:
    return [cell.strip() for cell in line.strip().strip("|").split("|")]


def render_markdown(markdown: str, slug: str, available: set[str]) -> str:
    lines = markdown.replace("\r\n", "\n").split("\n")
    out: list[str] = []
    i = 0

    while i < len(lines):
        line = lines[i].rstrip()
        if not line.strip():
            i += 1
            continue

        fence = re.match(r"^\s*```", line)
        if fence:
            code: list[str] = []
            i += 1
            while i < len(lines) and not re.match(r"^\s*```", lines[i]):
                code.append(lines[i])
                i += 1
            i += 1
            out.append('<pre class="p-3 bg-body-tertiary border rounded"><code>' + html.escape("\n".join(code)) + "</code></pre>")
            continue

        heading = re.match(r"^(#{1,6})\s+(.+?)\s*#*\s*$", line)
        if heading:
            level = len(heading.group(1))
            title = heading.group(2)
            out.append(f'<h{level} id="{html.escape(slugify_heading(title), quote=True)}">{render_inline(title, slug, available)}</h{level}>')
            i += 1
            continue

        if re.match(r"^\s*(---+|\*\*\*+|___+)\s*$", line):
            out.append("<hr>")
            i += 1
            continue

        if line.lstrip().startswith("|") and i + 1 < len(lines) and re.match(r"^\s*\|?\s*:?-{3,}", lines[i + 1]):
            headers = split_table_row(line)
            i += 2
            rows: list[list[str]] = []
            while i < len(lines) and lines[i].lstrip().startswith("|"):
                rows.append(split_table_row(lines[i]))
                i += 1
            head = "".join(f"<th scope=\"col\">{render_inline(cell, slug, available)}</th>" for cell in headers)
            body = "".join(
                "<tr>" + "".join(f"<td>{render_inline(cell, slug, available)}</td>" for cell in row) + "</tr>"
                for row in rows
            )
            out.append(f'<div class="table-responsive"><table class="table table-striped table-sm"><thead><tr>{head}</tr></thead><tbody>{body}</tbody></table></div>')
            continue

        if re.match(r"^\s*>\s?", line):
            quote_lines: list[str] = []
            while i < len(lines) and re.match(r"^\s*>\s?", lines[i]):
                quote_lines.append(re.sub(r"^\s*>\s?", "", lines[i]).strip())
                i += 1
            out.append('<blockquote class="blockquote border-start ps-3">' + " ".join(render_inline(item, slug, available) for item in quote_lines) + "</blockquote>")
            continue

        list_match = re.match(r"^\s*([-+*]|\d+\.)\s+(.+)$", line)
        if list_match:
            ordered = list_match.group(1)[0].isdigit()
            tag = "ol" if ordered else "ul"
            items: list[str] = []
            while i < len(lines):
                match = re.match(r"^\s*([-+*]|\d+\.)\s+(.+)$", lines[i])
                if not match or match.group(1)[0].isdigit() != ordered:
                    break
                items.append(f"<li>{render_inline(match.group(2).strip(), slug, available)}</li>")
                i += 1
            out.append(f"<{tag}>{''.join(items)}</{tag}>")
            continue

        paragraph = [line.strip()]
        i += 1
        while i < len(lines) and lines[i].strip():
            if re.match(r"^\s*(#{1,6}\s|```|>|([-+*]|\d+\.)\s+|\|)", lines[i]) or re.match(r"^\s*(---+|\*\*\*+|___+)\s*$", lines[i]):
                break
            paragraph.append(lines[i].strip())
            i += 1
        out.append("<p>" + render_inline(" ".join(paragraph), slug, available) + "</p>")

    return "\n".join(out)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("output", type=Path, help="Output help directory inside the staged component")
    args = parser.parse_args()

    articles: dict[str, dict[str, str]] = {}
    for group_key, slugs in GROUPS:
        for slug in slugs:
            path = DOCS / f"{slug}.md"
            if not path.is_file():
                raise SystemExit(f"Missing help source: {path}")
            source = path.read_text(encoding="utf-8")
            title_match = re.search(r"^#\s+(.+?)\s*$", source, re.M)
            articles[slug] = {"title": title_match.group(1) if title_match else slug, "source": source, "group": group_key}

    available = set(articles)
    if args.output.exists():
        shutil.rmtree(args.output)
    article_dir = args.output / "articles"
    article_dir.mkdir(parents=True, exist_ok=True)

    groups_json = []
    for group_key, slugs in GROUPS:
        group_items = []
        for slug in slugs:
            article = articles[slug]
            (article_dir / f"{slug}.html").write_text(
                render_markdown(article["source"], f"{slug}.md", available), encoding="utf-8"
            )
            group_items.append({"slug": slug, "title": article["title"]})
        groups_json.append({"titleKey": group_key, "items": group_items})

    (args.output / "manifest.json").write_text(
        json.dumps({"groups": groups_json}, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )


if __name__ == "__main__":
    main()

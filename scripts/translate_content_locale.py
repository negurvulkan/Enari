from __future__ import annotations

import argparse
import concurrent.futures
import json
import re
import shutil
import threading
import time
import urllib.parse
import urllib.request
from pathlib import Path
from typing import Iterable


ROOT = Path(__file__).resolve().parents[1]
DE_ROOT = ROOT / "content" / "de" / "01_Weltbau"
EN_ROOT = ROOT / "content" / "en" / "01_Worldbuilding"
TRANSLATE_URL = "https://translate.googleapis.com/translate_a/single"
MYMEMORY_URL = "https://api.mymemory.translated.net/get"
MAX_CHARS = 4200
MAX_WORKERS = 6

TEXT_CACHE: dict[str, str] = {}
CACHE_LOCK = threading.Lock()

FENCE_START_RE = re.compile(r"^(\s*)(```|~~~)")
TOKEN_RE = re.compile(r"ZXQ([A-Z]+)(\d+)ZXQ")
MARKDOWN_LINK_RE = re.compile(r"(!?)\[([^\]]*)\]\(([^)]+)\)")
WIKI_LINK_RE = re.compile(r"(!?)\[\[([^|\]]+)(?:\|([^\]]*))?\]\]")
INLINE_CODE_RE = re.compile(r"`[^`]+`")
HTML_COMMENT_RE = re.compile(r"<!--.*?-->", re.DOTALL)
RAW_URL_RE = re.compile(r"https?://[^\s)]+")


class PlaceholderStore:
    def __init__(self) -> None:
        self.items: list[tuple[str, str]] = []

    def add(self, kind: str, value: str) -> str:
        token = f"ZXQ{kind}{len(self.items)}ZXQ"
        self.items.append((kind, value))
        return token

    def restore(self, text: str) -> str:
        def replacer(match: re.Match[str]) -> str:
            index = int(match.group(2))
            try:
                _, value = self.items[index]
            except IndexError:
                return match.group(0)
            return value

        return TOKEN_RE.sub(replacer, text)


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Translate German content into the English locale tree.")
    parser.add_argument("--source", help="Translate a single Markdown file.")
    parser.add_argument("--target", help="Write the translated single file to this target path.")
    return parser.parse_args()


def ensure_parent(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)


def split_frontmatter(text: str) -> tuple[str, str]:
    if not text.startswith("---\n"):
        return "", text
    end = text.find("\n---\n", 4)
    if end == -1:
        return "", text
    return text[: end + 5], text[end + 5 :]


def target_path_for(source_path: Path) -> Path:
    relative = source_path.relative_to(DE_ROOT)
    parts = list(relative.parts)
    if parts and parts[0] == "01_Sprachen":
        parts[0] = "01_Languages"
    if parts and parts[-1] == "00_Uebersicht.md":
        parts[-1] = "00_Overview.md"
    return EN_ROOT.joinpath(*parts)


def rewrite_targets(text: str) -> str:
    text = text.replace("01_Sprachen/", "01_Languages/")
    text = text.replace("01_Sprachen\\", "01_Languages\\")
    text = text.replace("`01_Sprachen`", "`01_Languages`")
    text = text.replace("00_Uebersicht.md", "00_Overview.md")
    return text


def split_large_piece(text: str, max_chars: int) -> list[str]:
    if len(text) <= max_chars:
        return [text]

    pieces: list[str] = []
    remaining = text
    while len(remaining) > max_chars:
        split_at = remaining.rfind("\n", 0, max_chars)
        if split_at <= 0:
            split_at = max_chars
        pieces.append(remaining[:split_at])
        remaining = remaining[split_at:]
    if remaining:
        pieces.append(remaining)
    return pieces


def split_translation_chunks(text: str, max_chars: int = MAX_CHARS) -> list[str]:
    if len(text) <= max_chars:
        return [text]

    parts = re.split(r"(\n\s*\n)", text)
    chunks: list[str] = []
    current = ""

    for part in parts:
        if part == "":
            continue
        if len(part) > max_chars:
            if current:
                chunks.append(current)
                current = ""
            chunks.extend(split_large_piece(part, max_chars))
            continue
        if current and len(current) + len(part) > max_chars:
            chunks.append(current)
            current = part
        else:
            current += part

    if current:
        chunks.append(current)

    return chunks


def request_translation_google(text: str) -> str:
    payload = urllib.parse.urlencode(
        {
            "client": "gtx",
            "sl": "de",
            "tl": "en",
            "dt": "t",
            "q": text,
        }
    ).encode("utf-8")

    request = urllib.request.Request(
        TRANSLATE_URL,
        data=payload,
        headers={"User-Agent": "Mozilla/5.0"},
    )

    with urllib.request.urlopen(request, timeout=120) as response:
        data = json.loads(response.read().decode("utf-8"))

    segments = data[0] if isinstance(data, list) and data else []
    translated_parts: list[str] = []
    for segment in segments:
        if isinstance(segment, list) and segment:
            translated_parts.append(str(segment[0]))
    return "".join(translated_parts)


def request_translation_mymemory(text: str) -> str:
    translated_parts: list[str] = []
    for chunk in split_translation_chunks(text, max_chars=450):
        leading_len = len(chunk) - len(chunk.lstrip())
        trailing_len = len(chunk) - len(chunk.rstrip())
        leading = chunk[:leading_len]
        trailing = chunk[len(chunk) - trailing_len :] if trailing_len else ""
        core = chunk[leading_len : len(chunk) - trailing_len if trailing_len else len(chunk)]

        url = MYMEMORY_URL + "?" + urllib.parse.urlencode({"q": core, "langpair": "de|en"})
        request = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(request, timeout=120) as response:
            data = json.loads(response.read().decode("utf-8"))
        translated_core = str(((data.get("responseData") or {}).get("translatedText")) or core)
        translated_parts.append(f"{leading}{translated_core}{trailing}")
    return "".join(translated_parts)


def request_translation(text: str) -> str:
    try:
        return request_translation_google(text)
    except Exception:
        return request_translation_mymemory(text)


def translate_core_text(text: str) -> str:
    with CACHE_LOCK:
        cached = TEXT_CACHE.get(text)
    if cached is not None:
        return cached

    translated_chunks: list[str] = []
    for chunk in split_translation_chunks(text):
        translated_chunk = chunk
        for attempt in range(4):
            try:
                translated_chunk = request_translation(chunk)
                break
            except Exception:
                if attempt == 3:
                    translated_chunk = chunk
                    break
                time.sleep(2 * (attempt + 1))
        translated_chunks.append(translated_chunk)

    translated = "".join(translated_chunks)
    with CACHE_LOCK:
        TEXT_CACHE[text] = translated
    return translated


def translate_text(text: str) -> str:
    stripped = text.strip()
    if stripped == "":
        return text

    leading_len = len(text) - len(text.lstrip())
    trailing_len = len(text) - len(text.rstrip())
    leading = text[:leading_len]
    trailing = text[len(text) - trailing_len :] if trailing_len else ""
    core = text[leading_len : len(text) - trailing_len if trailing_len else len(text)]
    translated_core = translate_core_text(core)
    return f"{leading}{translated_core}{trailing}"


def mask_fenced_blocks(text: str, store: PlaceholderStore) -> str:
    lines = text.splitlines(keepends=True)
    output: list[str] = []
    in_fence = False
    fence_marker = ""
    fence_buffer: list[str] = []

    for line in lines:
        fence_match = FENCE_START_RE.match(line)
        if not in_fence and fence_match:
            in_fence = True
            fence_marker = fence_match.group(2)
            fence_buffer = [line]
            continue

        if in_fence:
            fence_buffer.append(line)
            if line.lstrip().startswith(fence_marker):
                output.append(store.add("FENCE", "".join(fence_buffer)))
                in_fence = False
                fence_marker = ""
                fence_buffer = []
            continue

        output.append(line)

    if fence_buffer:
        output.append(store.add("FENCE", "".join(fence_buffer)))

    return "".join(output)


def mask_markdown_links(text: str, store: PlaceholderStore) -> str:
    def replacer(match: re.Match[str]) -> str:
        bang, label, target = match.groups()
        normalized_label = label.strip()
        if normalized_label.endswith(".md") or "/" in normalized_label or "\\" in normalized_label:
            return store.add("MDLINK", match.group(0))
        token = store.add("MDTARGET", target)
        return f"{bang}[{label}]({token})"

    return MARKDOWN_LINK_RE.sub(replacer, text)


def mask_wiki_links(text: str, store: PlaceholderStore) -> str:
    def replacer(match: re.Match[str]) -> str:
        bang, target, label = match.groups()
        token = store.add("WIKITARGET", target)
        if label is None:
            return f"{bang}[[{token}]]"
        return f"{bang}[[{token}|{label}]]"

    return WIKI_LINK_RE.sub(replacer, text)


def protect_text(text: str) -> tuple[str, PlaceholderStore]:
    store = PlaceholderStore()
    protected = mask_fenced_blocks(text, store)
    protected = HTML_COMMENT_RE.sub(lambda match: store.add("HTML", match.group(0)), protected)
    protected = INLINE_CODE_RE.sub(lambda match: store.add("CODE", match.group(0)), protected)
    protected = RAW_URL_RE.sub(lambda match: store.add("URL", match.group(0)), protected)
    protected = mask_markdown_links(protected, store)
    protected = mask_wiki_links(protected, store)
    return protected, store


def translate_preserving_markdown(text: str) -> str:
    protected, store = protect_text(text)
    translated = translate_text(protected)
    restored = store.restore(translated)
    return (
        restored.replace(" )", ")")
        .replace("( ", "(")
        .replace(" ]", "]")
        .replace("[ ", "[")
        .replace("] (", "](")
        .replace("! [", "![")
    )


def translate_frontmatter(frontmatter: str) -> str:
    if frontmatter == "":
        return frontmatter

    translatable_keys = {"title", "excerpt"}
    translated_lines: list[str] = []
    current_key = ""

    for line in frontmatter.splitlines():
        if line == "---":
            translated_lines.append(line)
            continue

        match = re.match(r"^([A-Za-z0-9_-]+):(.*)$", line)
        if match:
            current_key = match.group(1)
            value = match.group(2).strip()
            if current_key in translatable_keys and value != "":
                translated_value = translate_preserving_markdown(value)
                translated_lines.append(f"{current_key}: {translated_value}")
            else:
                translated_lines.append(line)
            continue

        if current_key in translatable_keys and re.match(r"^\s+-\s+", line):
            prefix, value = line.split("-", 1)
            translated_lines.append(f"{prefix}- {translate_preserving_markdown(value.strip())}")
            continue

        translated_lines.append(line)

    return "\n".join(translated_lines) + ("\n" if frontmatter.endswith("\n") else "")


def translate_markdown(source_path: Path, target_path: Path) -> None:
    source_text = source_path.read_text(encoding="utf-8")
    frontmatter, body = split_frontmatter(source_text)
    translated_frontmatter = translate_frontmatter(frontmatter)
    translated_body = translate_preserving_markdown(body)
    final_text = rewrite_targets(translated_frontmatter + translated_body)
    ensure_parent(target_path)
    target_path.write_text(final_text, encoding="utf-8", newline="\n")


def copy_asset(source_path: Path, target_path: Path) -> None:
    ensure_parent(target_path)
    shutil.copy2(source_path, target_path)


def iter_source_files() -> Iterable[Path]:
    for path in sorted(DE_ROOT.rglob("*")):
        if path.is_file():
            yield path


def translate_one(source_path: Path) -> tuple[str, Path]:
    target_path = target_path_for(source_path)
    translate_markdown(source_path, target_path)
    return "translated", target_path


def run_single(source: str, target: str | None) -> int:
    source_path = Path(source)
    target_path = Path(target) if target else target_path_for(source_path)
    translate_markdown(source_path, target_path)
    print(f"translated_single={target_path}")
    return 0


def main() -> int:
    args = parse_args()
    if args.source:
        return run_single(args.source, args.target)

    markdown_files: list[Path] = []
    copied = 0

    for source_path in iter_source_files():
        target_path = target_path_for(source_path)
        if source_path.suffix.lower() == ".md":
            markdown_files.append(source_path)
        else:
            copy_asset(source_path, target_path)
            copied += 1

    translated = 0
    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = [executor.submit(translate_one, path) for path in markdown_files]
        for future in concurrent.futures.as_completed(futures):
            future.result()
            translated += 1

    print(f"translated_markdown={translated}")
    print(f"copied_assets={copied}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

#!/usr/bin/env python3
"""Spin AI — Google web search + speech-to-text on the client."""

from __future__ import annotations

import json
import os
import re
import urllib.error
import urllib.parse
import urllib.request
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

from concurrent.futures import ThreadPoolExecutor, as_completed

import requests
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parent
HOST = os.environ.get("SPIN_HOST", "127.0.0.1")
PORT = int(os.environ.get("SPIN_PORT", "8765"))

MAX_PAGES = int(os.environ.get("SPIN_MAX_PAGES", "5"))
MAX_CHARS_PER_PAGE = int(os.environ.get("SPIN_MAX_CHARS_PER_PAGE", "3500"))
FETCH_TIMEOUT = float(os.environ.get("SPIN_FETCH_TIMEOUT", "8"))

USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
)


def _http_json(url: str, *, method: str = "GET", headers: dict[str, str] | None = None, body: bytes | None = None) -> dict[str, Any]:
    req = urllib.request.Request(url, data=body, headers=headers or {}, method=method)
    with urllib.request.urlopen(req, timeout=30) as resp:
        return json.loads(resp.read().decode("utf-8"))


def google_cse_search(query: str, api_key: str, cse_id: str, max_results: int = 6) -> list[dict[str, str]]:
    """Official Google Custom Search JSON API."""
    params = urllib.parse.urlencode(
        {
            "key": api_key,
            "cx": cse_id,
            "q": query,
            "num": min(max(max_results, 1), 10),
        }
    )
    data = _http_json(f"https://www.googleapis.com/customsearch/v1?{params}")
    results: list[dict[str, str]] = []
    for item in data.get("items") or []:
        results.append(
            {
                "title": (item.get("title") or "").strip(),
                "snippet": (item.get("snippet") or "").strip(),
                "url": (item.get("link") or "").strip(),
            }
        )
    return results


def google_serper_search(query: str, api_key: str, max_results: int = 6) -> list[dict[str, str]]:
    """Google results via Serper (serper.dev)."""
    payload = json.dumps({"q": query, "num": max_results}).encode("utf-8")
    data = _http_json(
        "https://google.serper.dev/search",
        method="POST",
        headers={
            "Content-Type": "application/json",
            "X-API-KEY": api_key,
        },
        body=payload,
    )
    results: list[dict[str, str]] = []
    for item in (data.get("organic") or [])[:max_results]:
        results.append(
            {
                "title": (item.get("title") or "").strip(),
                "snippet": (item.get("snippet") or "").strip(),
                "url": (item.get("link") or "").strip(),
            }
        )
    return results


def web_search(
    query: str,
    *,
    google_api_key: str = "",
    google_cse_id: str = "",
    serper_api_key: str = "",
    max_results: int = 6,
) -> tuple[list[dict[str, str]], str]:
    """
    Search Google.
    Preference: Google Custom Search → Serper Google API.
    Returns (results, provider_name).
    """
    google_api_key = (
        google_api_key
        or os.environ.get("GOOGLE_API_KEY", "")
        or os.environ.get("GOOGLE_CSE_API_KEY", "")
    ).strip()
    google_cse_id = (google_cse_id or os.environ.get("GOOGLE_CSE_ID", "")).strip()
    serper_api_key = (serper_api_key or os.environ.get("SERPER_API_KEY", "")).strip()

    errors: list[str] = []

    if google_api_key and google_cse_id:
        try:
            return google_cse_search(query, google_api_key, google_cse_id, max_results), "google-cse"
        except Exception as exc:  # noqa: BLE001
            errors.append(f"Google CSE: {exc}")

    if serper_api_key:
        try:
            return google_serper_search(query, serper_api_key, max_results), "google-serper"
        except Exception as exc:  # noqa: BLE001
            errors.append(f"Serper Google: {exc}")

    hint = (
        "Google search needs credentials. Add either:\n"
        "1) Google Custom Search — GOOGLE_API_KEY + GOOGLE_CSE_ID "
        "(https://developers.google.com/custom-search/v1/overview), or\n"
        "2) Serper Google API — SERPER_API_KEY (https://serper.dev).\n"
        "You can also paste them in Spin’s Settings panel."
    )
    if errors:
        hint += "\n\nAttempts:\n- " + "\n- ".join(errors)
    raise RuntimeError(hint)


def format_sources_for_prompt(results: list[dict[str, str]]) -> str:
    lines = []
    for i, r in enumerate(results, 1):
        title = r.get("title") or "Source"
        url = r.get("url") or ""
        snippet = r.get("snippet") or ""
        content = (r.get("content") or "").strip()
        block = [f"[{i}] {title}", f"Snippet: {snippet}", f"URL: {url}"]
        if content:
            block.append(f"Page content:\n{content}")
        lines.append("\n".join(block))
    return "\n\n".join(lines)


def extract_page_text(html: str) -> str:
    """Pull readable main text from HTML."""
    soup = BeautifulSoup(html, "html.parser")
    for tag in soup(["script", "style", "noscript", "svg", "iframe", "nav", "footer", "header", "form", "aside"]):
        tag.decompose()

    root = soup.find("article") or soup.find("main") or soup.body or soup
    text = root.get_text("\n", strip=True) if root else soup.get_text("\n", strip=True)
    lines = [ln.strip() for ln in text.splitlines() if ln.strip()]
    # Drop ultra-short junk / cookie banners.
    cleaned = [ln for ln in lines if len(ln) > 40 or (len(ln) > 12 and " " in ln)]
    joined = "\n".join(cleaned)
    joined = re.sub(r"\n{3,}", "\n\n", joined)
    return joined.strip()


def fetch_page_content(url: str) -> str:
    """Download one page and return truncated readable text."""
    if not url or not url.startswith(("http://", "https://")):
        return ""
    try:
        resp = requests.get(
            url,
            headers={
                "User-Agent": USER_AGENT,
                "Accept": "text/html,application/xhtml+xml;q=0.9,*/*;q=0.8",
                "Accept-Language": "en-US,en;q=0.9",
            },
            timeout=FETCH_TIMEOUT,
            allow_redirects=True,
        )
        ctype = (resp.headers.get("Content-Type") or "").lower()
        if "html" not in ctype and "text/" not in ctype and ctype:
            return ""
        if resp.status_code >= 400:
            return ""
        text = extract_page_text(resp.text)
        if len(text) > MAX_CHARS_PER_PAGE:
            text = text[: MAX_CHARS_PER_PAGE - 1].rstrip() + "…"
        return text
    except Exception:  # noqa: BLE001
        return ""


def enrich_results_with_pages(results: list[dict[str, str]], max_pages: int = MAX_PAGES) -> list[dict[str, str]]:
    """Open top Google links and attach page content for compounding."""
    enriched = [dict(r) for r in results]
    targets = [(i, r.get("url", "")) for i, r in enumerate(enriched) if r.get("url")]
    targets = targets[:max_pages]
    if not targets:
        return enriched

    with ThreadPoolExecutor(max_workers=min(4, len(targets))) as pool:
        futures = {pool.submit(fetch_page_content, url): idx for idx, url in targets}
        for fut in as_completed(futures):
            idx = futures[fut]
            content = fut.result() or ""
            enriched[idx]["content"] = content
            enriched[idx]["fetched"] = bool(content)
    return enriched


def compound_points(results: list[dict[str, str]]) -> list[str]:
    """Merge unique facts from snippets + page excerpts into ranked bullets."""
    points: list[str] = []
    seen: set[str] = set()

    def add(text: str) -> None:
        text = re.sub(r"\s+", " ", (text or "").strip())
        text = strip_urls(text)
        if len(text) < 35:
            return
        key = text.lower()[:100]
        if key in seen:
            return
        seen.add(key)
        if len(text) > 220:
            text = text[:217].rstrip() + "…"
        points.append(text)

    for r in results:
        add(r.get("snippet") or "")
        content = r.get("content") or ""
        if not content:
            continue
        # Prefer denser sentences from the page body.
        sentences = re.split(r"(?<=[.!?])\s+", content)
        for sentence in sentences:
            if len(points) >= 10:
                break
            if 50 <= len(sentence) <= 240:
                add(sentence)
        # Also take a couple of longer paragraph lines.
        for line in content.splitlines():
            if len(points) >= 12:
                break
            if 80 <= len(line) <= 260:
                add(line)

    return points[:8]


def strip_urls(text: str) -> str:
    """Remove raw URLs from the answer body so links only appear in Sources."""
    cleaned = re.sub(r"https?://\S+", "", text)
    cleaned = re.sub(r"[ \t]+\n", "\n", cleaned)
    cleaned = re.sub(r"\n{3,}", "\n\n", cleaned)
    return cleaned.strip()


def format_sources_footer(results: list[dict[str, str]]) -> str:
    """Links-only section placed at the end of every answer."""
    lines = ["Sources"]
    for i, r in enumerate(results, 1):
        title = (r.get("title") or f"Source {i}").strip()
        url = (r.get("url") or "").strip()
        if url:
            lines.append(f"{i}. {title}")
            lines.append(f"   {url}")
        else:
            lines.append(f"{i}. {title}")
    return "\n".join(lines)


def compose_answer(body: str, results: list[dict[str, str]]) -> str:
    body = strip_urls(body)
    if not results:
        return body
    return f"{body}\n\n{format_sources_footer(results)}".strip()


def synthesize_without_llm(question: str, results: list[dict[str, str]], provider: str) -> str:
    """Compound Google results + opened page content into a structured answer."""
    if not results:
        return (
            "I searched Google but couldn’t find solid sources for that yet. "
            "Try rephrasing the question."
        )

    points = compound_points(results)
    fetched_n = sum(1 for r in results if r.get("fetched"))
    overview = points[0] if points else (
        (results[0].get("snippet") or "").strip()
        or "Here’s a clear summary compiled from Google and the linked pages."
    )

    details: list[str] = []
    for r in results:
        content = (r.get("content") or "").strip()
        if not content:
            continue
        # One compact detail block per successfully opened page.
        chunk = " ".join(content.split())
        if len(chunk) > 320:
            chunk = chunk[:317].rstrip() + "…"
        title = (r.get("title") or "Source").strip()
        details.append(f"• From {title}: {chunk}")
        if len(details) >= 3:
            break

    bullets = "\n".join(f"• {p}" for p in points[:7]) or "• Limited detail was available from the opened pages."
    details_block = ("\n\n**Details**\n" + "\n".join(details)) if details else ""

    body = (
        f"**Answer**\n{overview}\n\n"
        f"**Key points**\n{bullets}"
        f"{details_block}\n\n"
        f"**Takeaway**\n"
        f"This result compounds Google search hits"
        f"{f' and {fetched_n} opened source page(s)' if fetched_n else ''} "
        f"about “{question}”."
    )
    return compose_answer(body, results)


def synthesize_with_llm(
    question: str,
    results: list[dict[str, str]],
    api_key: str,
    api_base: str,
    model: str,
    history: list[dict[str, str]] | None = None,
) -> str:
    """Ask an OpenAI-compatible model to compound Google + page evidence."""
    sources = format_sources_for_prompt(results)
    system = (
        "You are Spin, a research AI that compounds information from Google results "
        "AND the full page content opened from those links.\n"
        "FORMAT RULES (strict):\n"
        "1) Write a polished answer with this exact structure:\n"
        "   **Answer** — 2–4 sentence overview that blends the strongest agreed facts\n"
        "   **Key points** — 5–8 concise bullet points starting with •\n"
        "   **Details** — short paragraphs synthesizing nuances across sources\n"
        "   **Takeaway** — one practical closing line\n"
        "2) Compound overlapping facts into one clear narrative. Prefer consensus; "
        "call out conflicts briefly.\n"
        "3) Do NOT include any URLs, links, or a Sources section in your reply. "
        "Links are added separately after your answer.\n"
        "4) Do NOT invent facts that are not supported by the provided evidence.\n"
        "5) Use plain text with **bold** labels and • bullets only — no markdown tables."
    )
    user = (
        f"Question: {question}\n\n"
        f"Evidence from Google + opened pages (do not paste URLs):\n{sources}\n\n"
        "Compound all of this into the best single answer. No links in your response."
    )

    messages: list[dict[str, str]] = [{"role": "system", "content": system}]
    if history:
        for msg in history[-6:]:
            role = msg.get("role")
            content = msg.get("content")
            if role in ("user", "assistant") and content:
                messages.append({"role": role, "content": content})
    messages.append({"role": "user", "content": user})

    base = api_base.rstrip("/")
    payload = json.dumps(
        {
            "model": model or "gpt-4o-mini",
            "messages": messages,
            "temperature": 0.25,
        }
    ).encode("utf-8")

    req = urllib.request.Request(
        f"{base}/chat/completions",
        data=payload,
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {api_key}",
        },
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=90) as resp:
        data = json.loads(resp.read().decode("utf-8"))
    body = (
        data.get("choices", [{}])[0]
        .get("message", {})
        .get("content", "")
        .strip()
        or "I couldn’t form an answer from the compounded sources."
    )
    return compose_answer(body, results)


def answer_question(body: dict[str, Any]) -> dict[str, Any]:
    question = (body.get("question") or "").strip()
    if not question:
        return {"error": "Missing question"}

    api_key = (body.get("api_key") or os.environ.get("OPENAI_API_KEY") or "").strip()
    api_base = (body.get("api_base") or "https://api.openai.com/v1").strip()
    model = (body.get("model") or "gpt-4o-mini").strip()
    history = body.get("history") if isinstance(body.get("history"), list) else []

    google_api_key = (body.get("google_api_key") or "").strip()
    google_cse_id = (body.get("google_cse_id") or "").strip()
    serper_api_key = (body.get("serper_api_key") or "").strip()

    try:
        results, provider = web_search(
            question,
            google_api_key=google_api_key,
            google_cse_id=google_cse_id,
            serper_api_key=serper_api_key,
            max_results=6,
        )
    except Exception as exc:  # noqa: BLE001
        return {"error": str(exc)}

    # Open the linked pages and compound their content.
    results = enrich_results_with_pages(results, max_pages=MAX_PAGES)
    pages_read = sum(1 for r in results if r.get("fetched"))

    if api_key:
        try:
            text = synthesize_with_llm(question, results, api_key, api_base, model, history)
            mode = "google+pages+llm"
        except Exception as exc:  # noqa: BLE001
            text = synthesize_without_llm(question, results, provider)
            text += f"\n\n(Note: LLM synthesis failed — showing compounded page findings. {exc})"
            mode = "google+pages-fallback"
    else:
        text = synthesize_without_llm(question, results, provider)
        mode = "google+pages"

    # Split body / sources for clean UI rendering.
    marker = "\n\nSources\n"
    if marker in text:
        main, _, _ = text.partition(marker)
        main = main.strip()
    else:
        main = strip_urls(text)

    # Do not send large page bodies back to the browser.
    public_sources = [
        {"title": r.get("title", ""), "url": r.get("url", ""), "snippet": r.get("snippet", "")}
        for r in results
    ]

    return {
        "answer": text,
        "body": main,
        "sources": public_sources,
        "mode": mode,
        "provider": provider,
        "pages_read": pages_read,
    }


class SpinHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def log_message(self, fmt: str, *args: Any) -> None:
        print(f"[spin] {self.address_string()} - {fmt % args}")

    def _cors(self) -> None:
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")

    def do_OPTIONS(self) -> None:  # noqa: N802
        self.send_response(204)
        self._cors()
        self.end_headers()

    def do_POST(self) -> None:  # noqa: N802
        path = urlparse(self.path).path
        if path != "/api/ask":
            self.send_error(404, "Not found")
            return

        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length) if length else b"{}"
        try:
            body = json.loads(raw.decode("utf-8") or "{}")
        except json.JSONDecodeError:
            self._json(400, {"error": "Invalid JSON"})
            return

        try:
            payload = answer_question(body)
            status = 400 if "error" in payload else 200
            self._json(status, payload)
        except Exception as exc:  # noqa: BLE001
            self._json(500, {"error": f"Google search failed: {exc}"})

    def _json(self, status: int, payload: dict[str, Any]) -> None:
        data = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self._cors()
        self.end_headers()
        self.wfile.write(data)


def main() -> None:
    server = ThreadingHTTPServer((HOST, PORT), SpinHandler)
    print(f"Spin AI ready at http://{HOST}:{PORT}")
    print("Search: Google → open result pages → compound into best answer")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nSpin stopped.")


if __name__ == "__main__":
    main()

# Spin

AI assistant named **Spin** with:

- **Speech to text** (browser mic)
- **Google search** for every question
- Optional OpenAI-compatible synthesis on top of Google sources

## Run

```bash
cd spin-ai
python3 -m venv .venv
source .venv/bin/activate
python server.py
```

Open http://127.0.0.1:8765

## Google setup (required)

Google blocks anonymous scraping, so Spin needs one of:

### Option A — Google Custom Search (official)
1. Create a [Programmable Search Engine](https://programmablesearchengine.google.com/) (search the entire web)
2. Enable [Custom Search JSON API](https://developers.google.com/custom-search/v1/overview) in Google Cloud
3. Put in Settings (or env):
   - `GOOGLE_API_KEY`
   - `GOOGLE_CSE_ID`

```bash
export GOOGLE_API_KEY="AIza..."
export GOOGLE_CSE_ID="your-cx-id"
python server.py
```

### Option B — Serper (Google results, one key)
1. Get a key at [serper.dev](https://serper.dev)
2. Put in Settings or:

```bash
export SERPER_API_KEY="..."
python server.py
```

## How answers work

1. You ask (type or speak).
2. Spin searches **Google**.
3. Spin **opens the top result pages** and reads their content.
4. It **compounds** snippets + page text into one best-formatted answer.
5. **Sources / links appear only at the end**.

Optional OpenAI-compatible key = smoother synthesis of the compounded evidence.

(() => {
  const messagesEl = document.getElementById("messages");
  const form = document.getElementById("composer");
  const input = document.getElementById("input");
  const micBtn = document.getElementById("mic");
  const statusEl = document.getElementById("status");
  const apiKeyEl = document.getElementById("apiKey");
  const apiBaseEl = document.getElementById("apiBase");
  const apiModelEl = document.getElementById("apiModel");
  const googleApiKeyEl = document.getElementById("googleApiKey");
  const googleCseIdEl = document.getElementById("googleCseId");
  const serperApiKeyEl = document.getElementById("serperApiKey");

  const STORAGE_KEYS = {
    key: "spin_api_key",
    base: "spin_api_base",
    model: "spin_api_model",
    googleKey: "spin_google_api_key",
    googleCse: "spin_google_cse_id",
    serper: "spin_serper_api_key",
  };

  const history = [];

  let recognition = null;
  let listening = false;
  let busy = false;

  apiKeyEl.value = localStorage.getItem(STORAGE_KEYS.key) || "";
  apiBaseEl.value = localStorage.getItem(STORAGE_KEYS.base) || "https://api.openai.com/v1";
  apiModelEl.value = localStorage.getItem(STORAGE_KEYS.model) || "gpt-4o-mini";
  googleApiKeyEl.value = localStorage.getItem(STORAGE_KEYS.googleKey) || "";
  googleCseIdEl.value = localStorage.getItem(STORAGE_KEYS.googleCse) || "";
  serperApiKeyEl.value = localStorage.getItem(STORAGE_KEYS.serper) || "";

  [
    apiKeyEl,
    apiBaseEl,
    apiModelEl,
    googleApiKeyEl,
    googleCseIdEl,
    serperApiKeyEl,
  ].forEach((el) => {
    el.addEventListener("change", persistSettings);
  });

  function persistSettings() {
    localStorage.setItem(STORAGE_KEYS.key, apiKeyEl.value.trim());
    localStorage.setItem(STORAGE_KEYS.base, apiBaseEl.value.trim());
    localStorage.setItem(STORAGE_KEYS.model, apiModelEl.value.trim());
    localStorage.setItem(STORAGE_KEYS.googleKey, googleApiKeyEl.value.trim());
    localStorage.setItem(STORAGE_KEYS.googleCse, googleCseIdEl.value.trim());
    localStorage.setItem(STORAGE_KEYS.serper, serperApiKeyEl.value.trim());
  }

  function setStatus(text) {
    statusEl.textContent = text || "";
  }

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatAnswerHtml(body) {
    const lines = String(body || "").split("\n");
    const html = [];
    let inList = false;

    const closeList = () => {
      if (inList) {
        html.push("</ul>");
        inList = false;
      }
    };

    for (const raw of lines) {
      const line = raw.trimEnd();
      const trimmed = line.trim();

      if (!trimmed) {
        closeList();
        continue;
      }

      const heading = trimmed.match(/^\*\*(.+?)\*\*\s*$/);
      if (heading) {
        closeList();
        html.push(`<h4 class="ans-h">${escapeHtml(heading[1])}</h4>`);
        continue;
      }

      const bullet = trimmed.match(/^[•\-\*]\s+(.+)$/);
      if (bullet) {
        if (!inList) {
          html.push('<ul class="ans-list">');
          inList = true;
        }
        const item = escapeHtml(bullet[1]).replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
        html.push(`<li>${item}</li>`);
        continue;
      }

      closeList();
      const para = escapeHtml(trimmed).replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
      html.push(`<p class="ans-p">${para}</p>`);
    }
    closeList();
    return html.join("");
  }

  function formatSourcesHtml(sources) {
    if (!sources || !sources.length) return "";
    const items = sources
      .filter((s) => s && (s.url || s.title))
      .map((s, i) => {
        const title = escapeHtml(s.title || `Source ${i + 1}`);
        const url = s.url || "";
        if (url) {
          const safe = escapeHtml(url);
          return `<li><a href="${safe}" target="_blank" rel="noopener noreferrer">${title}</a></li>`;
        }
        return `<li>${title}</li>`;
      })
      .join("");
    return `<div class="ans-sources"><h4 class="ans-h">Sources</h4><ol class="ans-links">${items}</ol></div>`;
  }

  function appendBubble(role, text, sources) {
    const bubble = document.createElement("div");
    bubble.className = `bubble bubble--${role === "user" ? "user" : "spin"}`;
    const who = document.createElement("span");
    who.className = "who";
    who.textContent = role === "user" ? "You" : "Spin";
    bubble.appendChild(who);

    if (role === "user") {
      const p = document.createElement("p");
      p.textContent = text;
      bubble.appendChild(p);
    } else {
      const content = document.createElement("div");
      content.className = "ans";
      content.innerHTML = formatAnswerHtml(text) + formatSourcesHtml(sources);
      bubble.appendChild(content);
    }

    messagesEl.appendChild(bubble);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function autoResize() {
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
  }

  input.addEventListener("input", autoResize);

  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  function getSpeechRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return null;
    const rec = new SpeechRecognition();
    rec.continuous = false;
    rec.interimResults = true;
    rec.lang = navigator.language || "en-US";
    return rec;
  }

  function stopListening() {
    if (recognition && listening) {
      try {
        recognition.stop();
      } catch (_) {
        /* ignore */
      }
    }
    listening = false;
    micBtn.classList.remove("is-listening");
    micBtn.setAttribute("aria-label", "Start speech to text");
  }

  function startListening() {
    recognition = getSpeechRecognition();
    if (!recognition) {
      setStatus("Speech to text needs Chrome, Edge, or Safari.");
      return;
    }

    let finalTranscript = input.value.trim();

    recognition.onstart = () => {
      listening = true;
      micBtn.classList.add("is-listening");
      micBtn.setAttribute("aria-label", "Stop listening");
      setStatus("Listening… speak now.");
    };

    recognition.onresult = (event) => {
      let interim = "";
      for (let i = event.resultIndex; i < event.results.length; i++) {
        const piece = event.results[i][0].transcript;
        if (event.results[i].isFinal) {
          finalTranscript = (finalTranscript ? `${finalTranscript} ` : "") + piece.trim();
        } else {
          interim += piece;
        }
      }
      input.value = (finalTranscript + (interim ? ` ${interim}` : "")).trim();
      autoResize();
    };

    recognition.onerror = (event) => {
      stopListening();
      if (event.error === "not-allowed") {
        setStatus("Microphone permission blocked. Allow mic access and try again.");
      } else if (event.error !== "aborted") {
        setStatus(`Speech error: ${event.error}`);
      } else {
        setStatus("");
      }
    };

    recognition.onend = () => {
      listening = false;
      micBtn.classList.remove("is-listening");
      micBtn.setAttribute("aria-label", "Start speech to text");
      if (input.value.trim()) {
        setStatus("Ready — press send or keep talking.");
      } else {
        setStatus("");
      }
    };

    recognition.start();
  }

  micBtn.addEventListener("click", () => {
    if (listening) {
      stopListening();
      setStatus("");
      return;
    }
    startListening();
  });

  async function askSpin(userText) {
    const res = await fetch("/api/ask", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        question: userText,
        api_key: apiKeyEl.value.trim(),
        api_base: apiBaseEl.value.trim() || "https://api.openai.com/v1",
        model: apiModelEl.value.trim() || "gpt-4o-mini",
        google_api_key: googleApiKeyEl.value.trim(),
        google_cse_id: googleCseIdEl.value.trim(),
        serper_api_key: serperApiKeyEl.value.trim(),
        history,
      }),
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw new Error(data.error || `Request failed (${res.status})`);
    }

    const reply = (data.body || data.answer || "").trim() || "No answer returned.";
    const sources = data.sources || [];
    history.push({ role: "user", content: userText });
    history.push({ role: "assistant", content: reply });
    return {
      reply,
      sources,
      mode: data.mode || "google",
      provider: data.provider || "google",
      pagesRead: data.pages_read || 0,
    };
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (busy) return;

    const text = input.value.trim();
    if (!text) return;

    stopListening();
    appendBubble("user", text);
    input.value = "";
    autoResize();
    busy = true;
    setStatus("Searching Google and reading source pages…");

    try {
      const { reply, sources, mode, provider, pagesRead } = await askSpin(text);
      appendBubble("assistant", reply, sources);
      const pageNote = pagesRead ? `, read ${pagesRead} page(s)` : "";
      if (String(mode).includes("llm")) {
        setStatus(`Compounded from Google (${provider}${pageNote}) + AI.`);
      } else {
        setStatus(`Compounded from Google (${provider}${pageNote}).`);
      }
    } catch (err) {
      appendBubble("assistant", err.message || "Google search failed. Check Settings for API keys.");
      setStatus("Add Google credentials in Settings.");
    } finally {
      busy = false;
    }
  });
})();

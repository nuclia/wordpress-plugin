(() => {
  const config = window.progressNucliaSearch || {};
  const restUrl = typeof config.restUrl === "string" ? config.restUrl.replace(/\/$/, "") : "";
  const restNonce = typeof config.restNonce === "string" ? config.restNonce : "";
  const strings = config.strings || {};

  const widgets = Array.from(document.querySelectorAll(".pl-nuclia-search-widget"));
  if (!widgets.length || !restUrl) return;

  const headers = {
    "Content-Type": "application/json",
  };
  if (restNonce) {
    headers["X-WP-Nonce"] = restNonce;
  }

  const stripTrailingCitationDefinitions = (text) => {
    const lines = text.split("\n");
    let end = lines.length;
    while (end > 0) {
      const line = (lines[end - 1] || "").trim();
      if (!line) {
        end -= 1;
        continue;
      }
      if (/^\[\d+\]\s*:\s*/.test(line)) {
        end -= 1;
        continue;
      }
      break;
    }
    return lines.slice(0, end).join("\n").trimEnd();
  };

  const renderAnswerHtml = (answer) => {
    const cleaned = stripTrailingCitationDefinitions(answer || "");
    return cleaned.replace(/\[(\d+)\]/g, (match, index) => {
      return `<button type="button" class="pl-nuclia-footnote" data-citation="${index}" aria-label="Open citation ${index}">${match}</button>`;
    });
  };

  const normalizeCitations = (raw) => {
    if (!raw || typeof raw !== "object") return [];
    const items = [];
    Object.entries(raw).forEach(([key, value]) => {
      let title = "";
      let text = "";
      let sourceUrl = "";
      let language = "";

      if (value && typeof value === "object") {
        const field = value;
        const titleText = field.title?.text || field.title?.readonly?.text;
        const paragraphText = field.paragraph?.text || field.paragraph?.readonly?.text;
        const textField = field.text?.text || field.text?.readonly?.text;
        title = titleText || paragraphText || key;
        text = paragraphText || textField || "";
        sourceUrl = field.metadata?.readonly?.uri || "";
        language = field.metadata?.readonly?.language || "";
      } else if (typeof value === "string") {
        title = value;
        text = value;
      } else {
        title = key;
      }

      items.push({
        id: key,
        title,
        text,
        sourceUrl,
        language,
      });
    });

    return items;
  };

  const parseStreamItems = (items) => {
    const resources = new Map();
    const footnotes = new Map();
    const paragraphs = new Map();
    const citationOrder = [];
    let answerText = "";

    const addCitationOrder = (blockId) => {
      if (!citationOrder.includes(blockId)) {
        citationOrder.push(blockId);
      }
    };

    items.forEach((obj) => {
      const item = obj?.item;
      if (!item || typeof item !== "object") return;

      if (item.type === "answer" && typeof item.text === "string") {
        answerText += item.text;
      }

      if (item.type === "retrieval" && item.results?.resources) {
        Object.entries(item.results.resources).forEach(([resourceId, resource]) => {
          resources.set(resourceId, resource);
          const fields = resource?.fields || {};
          Object.values(fields).forEach((field) => {
            const fieldParagraphs = field?.paragraphs || {};
            Object.entries(fieldParagraphs).forEach(([paragraphId, paragraph]) => {
              paragraphs.set(paragraphId, {
                text: paragraph?.text || "",
                score: paragraph?.score || 0,
                pageNumber: paragraph?.position?.page_number || 0,
                resourceId,
                paragraphId,
              });
            });
          });
        });
      }

      if (item.type === "footnote_citations" && item.footnote_to_context) {
        Object.entries(item.footnote_to_context).forEach(([blockId, paragraphId]) => {
          footnotes.set(blockId, paragraphId);
          addCitationOrder(blockId);
        });
      }
    });

    const citations = [];
    citationOrder.forEach((blockId) => {
      const paragraphId = footnotes.get(blockId);
      if (!paragraphId) return;
      const paragraph = paragraphs.get(paragraphId);
      if (!paragraph) return;
      const resource = resources.get(paragraph.resourceId);
      if (!resource) return;

      citations.push({
        id: blockId,
        title: resource.title || `Citation ${blockId}`,
        text: paragraph.text,
        sourceUrl: resource.thumbnail || resource.url || "",
        language: resource.metadata?.language || "",
      });
    });

    return { answerText, citations };
  };

  const setStatus = (widget, message) => {
    const status = widget.querySelector(".pl-nuclia-search-status");
    if (status) {
      status.textContent = message || "";
    }
  };

  const showResults = (widget, show) => {
    const results = widget.querySelector(".pl-nuclia-search-results");
    if (results) {
      results.hidden = !show;
    }
  };

  const highlightCitation = (list, index) => {
    const card = list.querySelector(`[data-citation-index="${index}"]`);
    if (!card) return;
    card.classList.add("is-active");
    card.scrollIntoView({ behavior: "smooth", block: "center" });
    window.setTimeout(() => card.classList.remove("is-active"), 1600);
  };

  const renderCitations = (widget, citations) => {
    const list = widget.querySelector(".pl-nuclia-citations-list");
    if (!list) return;
    list.innerHTML = "";

    if (!citations.length) {
      const empty = document.createElement("div");
      empty.className = "pl-nuclia-muted";
      empty.textContent = strings.noCitations || "No citations returned.";
      list.appendChild(empty);
      return;
    }

    citations.forEach((citation, idx) => {
      const card = document.createElement("div");
      card.className = "pl-nuclia-citation-card";
      card.dataset.citationIndex = String(idx + 1);

      const title = document.createElement("div");
      title.className = "pl-nuclia-citation-title";
      title.textContent = citation.title || `Citation ${idx + 1}`;

      const meta = document.createElement("div");
      meta.className = "pl-nuclia-citation-meta";
      meta.textContent = citation.language ? `Language: ${citation.language}` : `Citation ${idx + 1}`;

      const snippet = document.createElement("div");
      snippet.className = "pl-nuclia-citation-snippet";
      snippet.textContent = citation.text || "";

      card.appendChild(title);
      card.appendChild(meta);
      if (citation.text) {
        card.appendChild(snippet);
      }

      if (citation.sourceUrl) {
        const link = document.createElement("a");
        link.href = citation.sourceUrl;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.className = "pl-nuclia-citation-link";
        link.textContent = "Open source";
        card.appendChild(link);
      }

      list.appendChild(card);
    });
  };

  const fetchConfigs = async (widget) => {
    const select = widget.querySelector(".pl-nuclia-select");
    const status = widget.querySelector(".pl-nuclia-config-status");
    if (!select) return [];

    select.innerHTML = "";
    if (status) status.textContent = strings.loadingConfigs || "Loading configurations…";

    try {
      const response = await fetch(`${restUrl}/search-configurations`, {
        method: "GET",
        headers,
        credentials: "same-origin",
      });
      const data = await response.json();
      const configs = Array.isArray(data?.configs) ? data.configs : [];
      if (configs.length === 0) {
        const option = document.createElement("option");
        option.value = "";
        option.textContent = strings.noConfigs || "No configurations available";
        select.appendChild(option);
      } else {
        configs.forEach((configItem) => {
          const name = configItem?.name;
          if (!name) return;
          const option = document.createElement("option");
          option.value = name;
          option.textContent = name;
          select.appendChild(option);
        });
      }
    } catch (error) {
      const option = document.createElement("option");
      option.value = "";
      option.textContent = strings.noConfigs || "No configurations available";
      select.appendChild(option);
    } finally {
      if (status) status.textContent = "";
    }
  };

  const runSearch = async (widget) => {
    const input = widget.querySelector(".pl-nuclia-input");
    const select = widget.querySelector(".pl-nuclia-select");
    const answerNode = widget.querySelector(".pl-nuclia-answer-text");
    const clearButton = widget.querySelector(".pl-nuclia-clear");
    const citationsList = widget.querySelector(".pl-nuclia-citations-list");
    const showConfig = widget.dataset.showConfig !== "false";
    const fixedConfig = widget.dataset.searchConfig || "";

    if (!input || !answerNode) return;
    const question = input.value.trim();
    if (!question) return;

    if (clearButton) clearButton.hidden = false;
    setStatus(widget, strings.searching || "Searching…");
    showResults(widget, true);
    answerNode.innerHTML = "";
    if (citationsList) citationsList.innerHTML = "";

    try {
      const configValue = fixedConfig || (showConfig ? select?.value || "" : "");
      const response = await fetch(`${restUrl}/ask`, {
        method: "POST",
        headers,
        credentials: "same-origin",
        body: JSON.stringify({
          question,
          search_configuration: configValue,
        }),
      });

      const data = await response.json();
      if (!response.ok) {
        throw new Error(data?.message || data?.error || "Search failed.");
      }

      let answerText = "";
      let citations = [];
      const streamItems = Array.isArray(data?.stream_items)
        ? data.stream_items
        : data?.stream_items && typeof data.stream_items === "object"
        ? Object.values(data.stream_items)
        : null;

      if (Array.isArray(streamItems)) {
        const parsed = parseStreamItems(streamItems);
        answerText = parsed.answerText;
        citations = parsed.citations;
      } else {
        answerText = typeof data?.answer === "string"
          ? data.answer
          : typeof data?.message === "string"
          ? data.message
          : "";
        citations = normalizeCitations(data?.citations || {});
      }

      answerNode.innerHTML = answerText
        ? renderAnswerHtml(answerText)
        : `<span class="pl-nuclia-muted">${strings.noAnswer || "No answer returned."}</span>`;

      renderCitations(widget, citations);

      const list = widget.querySelector(".pl-nuclia-citations-list");
      answerNode.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        const button = target.closest(".pl-nuclia-footnote");
        if (!button || !list) return;
        const index = Number(button.dataset.citation || "");
        if (Number.isFinite(index)) {
          highlightCitation(list, index);
        }
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : "Search failed.";
      answerNode.innerHTML = `<span class="pl-nuclia-error">${message}</span>`;
      renderCitations(widget, []);
    } finally {
      setStatus(widget, "");
    }
  };

  widgets.forEach((widget) => {
    const input = widget.querySelector(".pl-nuclia-input");
    const button = widget.querySelector(".pl-nuclia-button");
    const clearButton = widget.querySelector(".pl-nuclia-clear");
    const select = widget.querySelector(".pl-nuclia-select");
    const showConfig = widget.dataset.showConfig !== "false";
    const fixedConfig = widget.dataset.searchConfig || "";

    if (showConfig) {
      fetchConfigs(widget).then(() => {
        if (fixedConfig && select) {
          select.value = fixedConfig;
        }
      });
    }

    if (button) {
      button.addEventListener("click", () => runSearch(widget));
    }

    if (input) {
      input.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          runSearch(widget);
        }
      });
    }

    if (clearButton) {
      clearButton.addEventListener("click", () => {
        const answerNode = widget.querySelector(".pl-nuclia-answer-text");
        const citationsList = widget.querySelector(".pl-nuclia-citations-list");
        if (input) input.value = "";
        if (answerNode) answerNode.innerHTML = "";
        if (citationsList) citationsList.innerHTML = "";
        setStatus(widget, "");
        showResults(widget, false);
        clearButton.hidden = true;
      });
    }
  });
})();

<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kroo Admin</title>
  <style>
    :root {
      --bg: #061f18;
      --panel: #0c3328;
      --line: #315749;
      --ink: #f8ead4;
      --muted: #a9bdb4;
      --accent: #d4956b;
      --mint: #57d5a0;
      --danger: #df786f
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      background: var(--bg);
      color: var(--ink);
      font: 14px system-ui, sans-serif
    }

    button,
    input,
    textarea,
    select {
      font: inherit
    }

    .shell {
      display: grid;
      grid-template-columns: 240px 1fr;
      min-height: 100vh
    }

    .side {
      padding: 28px 18px;
      border-right: 1px solid var(--line);
      background: #08271f;
      position: sticky;
      top: 0;
      height: 100vh
    }

    .brand {
      font: 700 27px Georgia;
      color: var(--accent);
      margin: 0 10px 30px
    }

    .nav button {
      display: block;
      width: 100%;
      padding: 12px;
      border: 0;
      border-radius: 9px;
      background: none;
      color: var(--muted);
      text-align: left;
      cursor: pointer
    }

    .nav button.active {
      background: var(--panel);
      color: var(--ink)
    }

    main {
      padding: 32px;
      min-width: 0
    }

    .top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px
    }

    .summarybar {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap
    }

    #summary {
      margin-bottom: 24px
    }

    .city-tools {
      display: grid;
      gap: 10px;
      width: 100%
    }

    .city-search-row,
    .city-pagination {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap
    }

    .sight-pagination label {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      max-width: 100%;
      white-space: nowrap;
    }

    .summarybar .sight-pagination select {
      width: auto;
      min-width: 0;
      max-width: 240px;
    }

    .city-search-row input {
      flex: 1;
      min-width: 220px
    }

    .field-row {
      display: flex;
      align-items: center;
      gap: 8px
    }

    .field-row select {
      flex: 1
    }

    .summarybar select,
    .summarybar input {
      min-width: 220px;
      padding: 7px 34px 7px 10px;
      border: 1px solid var(--line);
      border-radius: 7px;
      background: var(--panel);
      color: var(--ink)
    }

    h1 {
      font: 700 30px Georgia;
      margin: 0
    }

    button {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 9px 13px;
      background: var(--panel);
      color: var(--ink);
      cursor: pointer
    }

    button:disabled {
      cursor: not-allowed;
      opacity: .45
    }

    .primary {
      background: var(--accent);
      border-color: var(--accent);
      color: #10271f;
      font-weight: 700
    }

    .danger {
      color: var(--danger)
    }

    .auth {
      max-width: 460px;
      margin: 12vh auto;
      padding: 28px;
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 14px
    }

    .auth input {
      width: 100%;
      margin: 14px 0
    }

    .table {
      overflow: auto;
      border: 1px solid var(--line);
      border-radius: 12px
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 850px
    }

    th,
    td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid var(--line);
      vertical-align: middle
    }

    th {
      color: var(--muted);
      font-size: 12px;
      text-transform: uppercase
    }

    td img {
      width: 72px;
      height: 48px;
      border-radius: 8px;
      object-fit: cover;
      background: #123f30
    }

    .badge {
      padding: 4px 7px;
      border-radius: 12px;
      background: #173f33;
      color: var(--mint);
      white-space: nowrap
    }

    .actions {
      display: flex;
      gap: 7px;
      white-space: nowrap
    }

    th:last-child,
    td:last-child {
      position: sticky;
      right: 0;
      background: var(--bg);
      box-shadow: -8px 0 12px rgba(0, 0, 0, .12)
    }

    .modal {
      position: fixed;
      inset: 0;
      background: #000a;
      display: grid;
      place-items: center;
      padding: 20px;
      z-index: 5
    }

    .dialog {
      width: min(880px, 100%);
      max-height: 92vh;
      overflow: auto;
      background: var(--panel);
      border: 1px solid var(--accent);
      border-radius: 14px;
      padding: 24px
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px
    }

    .field {
      display: flex;
      flex-direction: column;
      gap: 6px
    }

    .wide {
      grid-column: 1/-1
    }

    label {
      color: var(--muted);
      font-size: 12px
    }

    input,
    textarea,
    select {
      width: 100%;
      padding: 10px;
      border: 1px solid var(--line);
      border-radius: 7px;
      background: #061f18;
      color: var(--ink)
    }

    textarea {
      min-height: 85px;
      resize: vertical
    }

    .check {
      display: flex;
      align-items: center;
      gap: 8px
    }

    .check input {
      width: auto
    }

    .dialogfoot {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px
    }

    .lesson-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; width:100% }
    .lesson-stat, .lesson-help, .question-card { border:1px solid var(--line); border-radius:12px; background:var(--panel) }
    .lesson-stat { padding:14px }
    .lesson-stat strong { display:block; margin-top:4px; color:var(--ink); font-size:18px }
    .lesson-help { grid-column:1/-1; padding:12px 14px; color:var(--muted) }
    .lesson-pill { display:inline-flex; padding:4px 8px; border-radius:999px; background:#173f33; color:var(--mint); font-weight:700; white-space:nowrap }
    .lesson-pill.preview { background:#493528; color:#ffd5b8 }
    .lesson-type-note { grid-column:1/-1; padding:12px 14px; border-left:3px solid var(--accent); border-radius:8px; background:#102f26; color:var(--muted) }
    .ai-draft-tools { display:flex; align-items:center; gap:10px; color:var(--muted); font-size:12px }
    .ai-draft-tools[hidden] { display:none }
    .ai-draft-tools.lesson-action { margin-right:auto; flex-wrap:wrap }
    .ai-draft-tools.field-action { margin-top:2px; flex-wrap:wrap; align-self:start }
    .ai-draft-tools button { flex:0 0 auto }
    .ai-draft-tools .include-images { display:inline-flex; align-items:center; gap:6px; white-space:nowrap }
    .ai-draft-tools .include-images[hidden] { display:none }
    .ai-draft-tools .include-images input { width:auto }
    @@media(max-width:600px) { .dialogfoot { flex-wrap:wrap } .ai-draft-tools.lesson-action { flex-basis:100% } .ai-draft-tools.field-action { grid-column:1/-1 !important } }
    .question-card { grid-column:1/-1; padding:0 14px 14px }
    .question-card summary { padding:14px 0; color:var(--ink); font-weight:700; cursor:pointer }
    .question-fields { display:grid; grid-template-columns:1fr 1fr; gap:14px }


    .notice {
      padding: 10px 12px;
      margin-bottom: 14px;
      border-radius: 8px;
      background: #173f33;
      color: var(--mint)
    }

    .error {
      background: #4b2524;
      color: #ffd4cf
    }

    .hidden {
      display: none !important
    }

    @@media(max-width:760px) {
      .shell {
        display: block
      }

      .side {
        height: auto;
        position: static;
        border-right: 0;
        border-bottom: 1px solid var(--line);
        padding: 16px
      }

      .brand {
        margin: 0 0 12px
      }

      .nav {
        display: flex;
        overflow: auto
      }

      .nav button {
        white-space: nowrap
      }

      .nav .logout {
        margin-left: auto
      }

      main {
        padding: 18px
      }

      .grid {
        grid-template-columns: 1fr
      }

      .lesson-summary, .question-fields { grid-template-columns:1fr }

      .wide {
        grid-column: auto
      }
    }
    body { line-height: 1.5 }
    .side { overflow-y: auto }
    .nav button { margin-bottom: 5px; min-height: 44px }
    .nav button.active { box-shadow: inset 3px 0 var(--mint); background: #174535 }
    button:hover:not(:disabled) { filter: brightness(1.18) }
    :focus-visible { outline: 3px solid var(--mint); outline-offset: 3px }
    .page-description { color: var(--muted); margin: 8px 0 0 }
    .summarybar { padding: 16px; border: 1px solid var(--line); border-radius: 12px; background: var(--panel) }
    th { background: #102f26; letter-spacing: .04em; white-space: nowrap }
    tbody tr:hover td { background-color: #10372b }
    td { max-width: 340px; overflow-wrap: anywhere }
    td img { width: 96px; height: 64px }
    .image-trigger { padding: 0; border: 0; background: transparent; display: inline-flex; cursor: zoom-in }
    .image-trigger img { display: block; object-fit: cover; border-radius: 6px }
    .image-field { padding: 14px; border: 1px dashed var(--line); border-radius: 10px }
    .image-field .image-trigger { align-self: flex-start; margin: 8px 0 }
    .image-field img { width: 180px; height: 120px; object-fit: contain; background: #061f18 }
    .image-field small { color: var(--muted) }
    .dialogfoot { position: sticky; bottom: -24px; padding: 16px 0; background: var(--panel); border-top: 1px solid var(--line) }
    .empty-state { text-align: center; padding: 40px; color: var(--muted) }
    .image-viewer { width: min(1200px, 96vw); max-width: 96vw; padding: 0; border: 1px solid var(--line); border-radius: 16px; background: #08271f; color: var(--ink) }
    .image-viewer::backdrop { background: #000d }
    .viewer-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 16px }
    .viewer-toolbar h2 { margin: 0 auto 0 0; font-size: 18px }
    .viewer-stage { height: 65vh; overflow: auto; background: #041510; padding: 16px }
    .viewer-stage img { display: block; margin: auto; max-width: 100%; max-height: 100%; object-fit: contain }
    .viewer-stage.zoomed img { max-width: none; max-height: none }
    #viewerStatus { margin: 0; padding: 10px 16px; color: var(--muted) }
    .table.ai-workspace { border: 0; overflow: visible }
    .ai-workspace { display: grid; gap: 22px }
    .ai-hero { position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding: 28px 30px; border: 1px solid #39745d; border-radius: 18px; background: radial-gradient(circle at 90% 0%, #20684b 0, transparent 34%), linear-gradient(130deg, #103b2c, #0b2b23 75%) }
    .ai-hero::after { content: '✦'; position: absolute; right: 28px; bottom: -64px; color: #ffffff0a; font: 220px Georgia; pointer-events: none }
    .ai-eyebrow { display: block; margin-bottom: 8px; color: #96e5bb; font-size: 11px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase }
    .ai-hero h2 { position: relative; margin: 0 0 8px; font: 700 clamp(22px, 2.5vw, 32px) Georgia; max-width: 650px }
    .ai-hero p { position: relative; margin: 0; max-width: 640px; color: #c4d7cc; line-height: 1.6 }
    .ai-status { position: relative; display: inline-flex; align-items: center; gap: 8px; flex: none; padding: 8px 12px; border: 1px solid #4b886c; border-radius: 100px; background: #0d3228; color: #bde9cc; font-size: 12px; font-weight: 700 }
    .ai-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--mint) }
    .ai-status.missing { border-color: #8b5548; color: #f5ba9a }
    .ai-status.missing::before { background: var(--danger) }
    .ai-grid { display: grid; grid-template-columns: minmax(290px, .95fr) minmax(340px, 1.05fr); gap: 18px; align-items: stretch }
    .ai-card { min-width: 0; padding: 24px; border: 1px solid var(--line); border-radius: 16px; background: #0b3026 }
    .ai-card-heading { display: flex; align-items: center; gap: 11px; margin-bottom: 8px }
    .ai-step { display: inline-grid; place-items: center; width: 27px; height: 27px; flex: none; border-radius: 8px; background: #285d45; color: #d7f4df; font-size: 12px; font-weight: 800 }
    .ai-card h3, .ai-batches h3 { margin: 0; font-size: 18px }
    .ai-card-intro { margin: 0 0 18px; color: var(--muted); line-height: 1.5 }
    .ai-types { display: grid; gap: 8px }
    .ai-type { width: 100%; display: flex; align-items: center; gap: 12px; padding: 12px 13px; border: 1px solid #315749; border-radius: 10px; background: #0a291f; text-align: left }
    .ai-type:hover:not(:disabled) { border-color: #6ca581 }
    .ai-type.selected { border-color: var(--mint); background: #194735; box-shadow: inset 3px 0 var(--mint) }
    .ai-type-icon { display: grid; place-items: center; width: 36px; height: 36px; flex: none; border-radius: 9px; background: #1e4b39; color: #c1eed2; font-size: 18px }
    .ai-type strong { display: block; font-size: 13px }
    .ai-type small { display: block; margin-top: 2px; color: var(--muted); font-size: 11px }
    .ai-type-check { margin-left: auto; color: var(--mint); font-weight: 800 }
    .ai-config { display: grid; align-content: start; gap: 18px }
    .ai-config .field { gap: 8px; font-weight: 700 }
    .ai-config [hidden], .ai-batch [hidden] { display: none }
    .ai-config input[type=number], .ai-config input[type=file] { width: 100%; padding: 11px 12px; border: 1px solid var(--line); border-radius: 9px; background: #09271e; color: var(--ink); font-weight: 400 }
    .ai-config input[type=file] { padding: 17px; border-style: dashed; border-color: #5e9176 }
    .ai-config small, .ai-help { color: var(--muted); font-size: 12px; font-weight: 400; line-height: 1.5 }
    .ai-config .ai-help { min-height: 48px; padding: 12px 14px; border-radius: 9px; background: #123b2c }
    .ai-submit-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding-top: 6px; border-top: 1px solid var(--line) }
    .ai-submit-row .primary { padding: 12px 20px; font-weight: 800 }
    .ai-batches { padding: 22px 24px; border: 1px solid var(--line); border-radius: 16px; background: #0b3026 }
    .ai-batches-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px }
    .ai-empty { padding: 28px; border: 1px dashed #426a55; border-radius: 12px; color: var(--muted); text-align: center }
    .ai-batch-list { display: grid; gap: 10px }
    .ai-batch-sections { display: grid; gap: 24px }
    .ai-batch-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid var(--line) }
    .ai-batch-tabs button { padding: 8px 12px; color: var(--muted) }
    .ai-batch-tabs button[aria-selected=true] { background: #245942; border-color: #68c398; color: var(--ink) }
    .ai-batch-section[hidden] { display: none }
    .ai-tab-count { margin-left: 6px; padding: 2px 6px; border-radius: 5px; background: #123b2c; color: var(--ink); font-size: 11px }
    .ai-generation-counts { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; margin-bottom: 10px }
    .ai-generation-counts div { padding: 10px 12px; border: 1px solid var(--line); border-radius: 8px; color: var(--muted); font-size: 11px }
    .ai-generation-counts strong { display: block; margin-top: 4px; color: var(--ink); font-size: 20px }
    .ai-count-help { margin: 0 0 14px; color: var(--muted); font-size: 12px; line-height: 1.5 }
    .ai-batch-section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px }
    .ai-batch-section-head h4 { margin: 0; font-size: 16px }
    .ai-batch-section-head small { margin-left: 8px; color: var(--muted); font-size: 12px; font-weight: 400 }
    .ai-section-empty { margin: 0; padding: 12px 0; color: var(--muted); font-size: 12px }
    .ai-batch { padding: 16px; border: 1px solid var(--line); border-radius: 11px; background: #09271f }
    .ai-batch-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px }
    .ai-batch-title { display: flex; align-items: center; gap: 9px; font-weight: 750 }
    .ai-batch-meta { margin: 5px 0 11px; color: var(--muted); font-size: 12px }
    .ai-batch-actions { display: flex; gap: 7px; flex-wrap: wrap; justify-content: flex-end }
    .ai-batch-actions button, .ai-batches-head button { padding: 6px 10px; font-size: 12px }
    .ai-pill { display: inline-block; padding: 3px 8px; border-radius: 100px; background: #245942; color: #b9eccb; font-size: 11px; font-weight: 700 }
    .ai-pill.paused { background: #5c4b2b; color: #f4d99a }
    .ai-pill.complete { background: #244d49; color: #afe4da }
    .ai-progress { height: 7px; overflow: hidden; border-radius: 100px; background: #264637 }
    .ai-progress span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #47ba88, #a8e5a0) }
    .ai-errors { margin-top: 12px; padding: 12px; border-radius: 9px; background: #442a26; color: #ffd8c7; font-size: 12px; line-height: 1.5; overflow-wrap: anywhere }
    @@media(max-width:1100px) { .ai-grid { grid-template-columns: 1fr } }
    @@media(max-width:760px) {
      .top { align-items: flex-start; flex-wrap: wrap }
      .city-search-row input { min-width: 0; flex-basis: 100% }
      .dialog { padding: 16px }
      .dialogfoot { bottom: -16px }
      .viewer-toolbar { gap: 8px; padding: 12px }
      .viewer-toolbar h2 { flex-basis: 100% }
      .viewer-toolbar button { min-height: 44px }
      .ai-grid { grid-template-columns: 1fr }
      .ai-hero { flex-direction: column; padding: 22px }
      .ai-card, .ai-batches { padding: 18px }
      .ai-batch-top { flex-direction: column }
      .ai-batch-actions { justify-content: flex-start }
    }
    .ai-workspace { gap: 16px; }
    .ai-toolbar { padding: 18px; border: 1px solid var(--line); border-radius: 12px; background: var(--panel); }
    .ai-toolbar-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .ai-toolbar-head h2 { margin: 0; font-size: 17px; }
    .ai-toolbar-form { display: flex; align-items: end; gap: 12px; flex-wrap: wrap; }
    .ai-toolbar-form .field { margin: 0; min-width: 190px; flex: 1; }
    .ai-toolbar-form .ai-limit { flex: 0 1 130px; min-width: 100px; }
    .ai-toolbar-form select, .ai-toolbar-form input { width: 100%; height: 40px; background: #09271f; color: var(--ink); border: 1px solid var(--line); border-radius: 7px; padding: 8px 10px; }
    .ai-toolbar-form button { height: 40px; }
    .ai-toolbar .ai-help { margin: 10px 0 0; }
    .ai-toolbar [hidden] { display: none; }
    .ai-batches { padding: 18px; border-radius: 12px; }
    .ai-batch { padding: 12px; }
    .ai-progress { height: 4px; }
    .ai-results { margin-top: 14px; color: var(--ink); }
    .ai-results-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
    .ai-results-heading p { margin: 0; color: var(--muted); font-size: 12px; }
    .ai-city-entry { position: relative; }
    .ai-city-entry .ai-result-group > summary { padding-right: 90px; min-height: 43px; }
    .ai-city-remove { position: absolute; top: 7px; right: 0; padding: 5px 9px; font-size: 12px; }
    .ai-result-group { border-top: 1px solid var(--line); }
    .ai-result-group > summary { cursor: pointer; padding: 11px 0; color: var(--ink); font-weight: 600; }
    .ai-result-group > summary small { margin-left: 8px; color: var(--muted); font-weight: 400; }
    .ai-result-row { display: grid; grid-template-columns: 120px minmax(0, 1fr) auto; align-items: start; gap: 14px; padding: 12px 0; border-top: 1px solid #284a3c; color: var(--ink); background: transparent; }
    .ai-result-row .image-trigger { display: block; width: 120px; height: 80px; margin: 0; border: 1px solid var(--line); border-radius: 7px; overflow: hidden; }
    .ai-result-row .image-trigger img { width: 100%; height: 100%; object-fit: contain; }
    .ai-result-text { min-width: 0; }
    .ai-result-text h4 { margin: 0 0 4px; font-size: 14px; color: #f8ead4; }
    .ai-result-text small, .ai-no-description { color: #b6c9c0; font-size: 12px; }
    .ai-description { margin-top: 8px; font-size: 13px; line-height: 1.55; }
    .ai-description summary { display: block; cursor: pointer; color: #d9e4dc; }
    .ai-description summary span { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .collection-detail-preview { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-width: 340px; line-height: 1.5; max-height: 3em; overflow-wrap: anywhere; }
    .ai-description summary strong { display: block; margin-top: 3px; color: #88c6a6; font-size: 12px; font-weight: 500; }
    .ai-description[open] summary span { display: none; }
    .ai-description[open] summary strong::after { content: ' · Collapse'; }
    .ai-description p { margin: 8px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; color: #f8ead4; }
    .ai-image-error { display: block; padding: 8px; color: var(--danger); font-size: 12px; }
    .ai-result-actions { display: flex; gap: 6px; }
    .ai-result-actions button { padding: 6px 9px; font-size: 12px; }
    .ai-result-row .ai-image-empty { display: grid; place-items: center; width: 120px; height: 80px; padding: 8px; background: #143a2c; border: 1px dashed #426451; color: #b6c9c0; border-radius: 7px; font-size: 12px; }
    .ai-result-pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 12px; margin-top: 14px; color: var(--muted); font-size: 12px; }
    .ai-content-editor { width: min(640px, calc(100vw - 32px)); max-height: 90vh; overflow-y: auto; border: 1px solid var(--line); border-radius: 12px; padding: 22px; background: var(--panel); color: var(--ink); }
    .ai-content-editor::backdrop { background: #0009; }
    .ai-content-editor h2 { margin: 0 0 16px; font-size: 20px; }
    .ai-content-editor .field { margin-bottom: 14px; }
    .ai-content-editor input, .ai-content-editor textarea { width: 100%; background: #09271f; color: var(--ink); border: 1px solid var(--line); border-radius: 7px; padding: 9px; }
    .ai-content-editor textarea { min-height: 180px; resize: vertical; }
    .ai-content-editor .check input { width: auto; }
    .ai-content-editor [hidden] { display: none; }
    .ai-edit-preview { max-width: 180px; max-height: 120px; object-fit: contain; margin-bottom: 10px; }
    @@media(max-width:700px) {
      .ai-result-row { grid-template-columns: 90px minmax(0, 1fr); gap: 10px; }
      .ai-result-row .image-trigger, .ai-result-row .ai-image-empty { width: 90px; height: 60px; }
      .ai-image-error { display: block; padding: 8px; color: var(--danger); font-size: 12px; }
    .ai-result-actions { grid-column: 2; }
      .ai-toolbar-form .field { flex-basis: 100%; }
      .ai-batch-top { flex-wrap: wrap; }
    }
  </style>
</head>

<body>
  <section id="login" class="auth">
    <h1>Kroo Admin</h1>
    <p style="color:var(--muted)">Enter the server ADMIN_API_KEY.</p><input id="key" type="password"
      placeholder="Admin API key" autocomplete="current-password"><button class="primary" onclick="withButtonLoading(this, &quot;Opening...&quot;, () => login())">Open
      dashboard</button>
    <p id="loginError" style="color:var(--danger)"></p>
  </section>
  <div id="app" class="shell hidden">
    <aside class="side">
      <div class="brand">Kroo Admin</div>
      <nav class="nav"><button data-tab="countries" class="active">Country</button><button onclick="withButtonLoading(this, &quot;Loading...&quot;, () => showUsStates(this))">US state images</button><button data-tab="cities">Cities</button><button data-tab="sights">Top sights</button><button
          data-tab="collections">Collection kinds</button><button data-tab="collection-lists">Collection list</button><button data-tab="daily-destinations">Kroo IQ</button><button data-tab="ai">AI automation</button><button class="logout" onclick="logout()">Lock</button></nav>
    </aside>
    <main>
      <div class="top">
        <div><h1 id="title">Country hero images</h1><p class="page-description">Manage your travel content. Select an image to preview and zoom.</p></div>
        <button id="addButton" class="primary" onclick="openEditor()">+ Add new</button>
      </div>
      <div id="summary" style="color:var(--muted)"></div>
      <div id="notice" role="status" aria-live="polite"></div>
      <div id="table" class="table"></div>
    </main>
  </div>
  <div id="modal" class="modal hidden">
    <form id="form" class="dialog">
      <h1 id="formTitle">Add</h1>
      <div id="formNotice" role="alert" aria-live="assertive"></div>
      <div id="fields" class="grid" style="margin-top:18px"></div>
      <div class="dialogfoot"><div id="aiDraftTools" class="ai-draft-tools" hidden><label class="include-images" hidden><input type="checkbox" id="includeLessonImages">Image include</label><button type="button" onclick="generateEditorText(this)">Generate with AI</button><span id="aiDraftHint">Review and edit the draft before saving.</span></div><button type="button" onclick="closeEditor()">Close</button><button class="primary"
          type="submit">Save</button></div>
    </form>
  </div>
  <dialog id="aiContentEditor" class="ai-content-editor" aria-labelledby="aiEditTitle">
    <form id="aiContentForm" onsubmit="saveAiContent(event)">
      <h2 id="aiEditTitle">Edit content</h2>
      <p id="aiEditNotice" class="notice error" role="alert"></p>
      <label class="field">Name<input name="name" required maxlength="150"></label>
      <label class="field">Description<textarea name="description" maxlength="20000"></textarea></label>
      <button id="aiContentDescriptionButton" type="button" onclick="generateAiContentDescription(this)">Generate description with AI</button>
      <img id="aiEditPreview" class="ai-edit-preview" alt="Current image" hidden>
      <label class="field">Image path or URL<input name="image" placeholder="/images/sights/stamp.webp"><small>Clear this field to remove the image.</small></label>
      <label class="field">Replace image<input name="imageFile" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
      <button type="button" onclick="generateAiEditorImage(this)">Generate image with AI</button>
      <label id="aiFeatureField" class="check"><input name="isFeatured" type="checkbox">Approved · Shown in app lists</label>
      <div class="dialogfoot"><button type="button" onclick="document.querySelector('#aiContentEditor').close()">Cancel</button><button class="primary" type="submit">Save changes</button></div>
    </form>
  </dialog>
  <dialog id="imageViewer" class="image-viewer" aria-labelledby="viewerTitle">
    <div class="viewer-toolbar">
      <h2 id="viewerTitle">Image preview</h2>
      <button type="button" id="zoomOut" aria-label="Zoom out" onclick="zoomImage(-.25)">−</button>
      <output id="zoomLevel" aria-live="polite">Fit</output>
      <button type="button" id="zoomIn" aria-label="Zoom in" onclick="zoomImage(.25)">+</button>
      <button type="button" onclick="fitImage()">Fit image</button>
      <button type="button" onclick="imageViewer.close()">Close</button>
    </div>
    <div id="viewerStage" class="viewer-stage"><img id="viewerImage" alt=""></div>
    <p id="viewerStatus" role="status">Loading image…</p>
  </dialog>
  <script>
    const imageViewer = document.querySelector('#imageViewer');
    const viewerImage = document.querySelector('#viewerImage');
    const viewerStage = document.querySelector('#viewerStage');
    let imageScale = 1;
    let viewerTrigger;
    function imagePreview(url, label = 'Image') {
      return `<button type="button" class="image-trigger" data-image-url="${esc(url)}" data-image-label="${esc(label)}" aria-label="${esc('Preview ' + label)}"><img src="${esc(url)}" alt="${esc(label)}" loading="lazy"></button>`;
    }
    function fitImage() {
      viewerStage.classList.remove('zoomed');
      viewerImage.style.width = '';
      imageScale = viewerImage.naturalWidth ? Math.min(1, (viewerStage.clientWidth - 32) / viewerImage.naturalWidth, (viewerStage.clientHeight - 32) / viewerImage.naturalHeight) : 1;
      document.querySelector('#zoomLevel').textContent = 'Fit';
      updateZoomButtons();
    }
    function updateZoomButtons() {
      const unavailable = !viewerImage.naturalWidth;
      document.querySelector('#zoomOut').disabled = unavailable || imageScale <= .1;
      document.querySelector('#zoomIn').disabled = unavailable || imageScale >= 4;
    }
    function zoomImage(delta) {
      if (!viewerImage.naturalWidth) return;
      imageScale = Math.max(.1, Math.min(4, imageScale + delta));
      viewerStage.classList.add('zoomed');
      viewerImage.style.width = `${viewerImage.naturalWidth * imageScale}px`;
      document.querySelector('#zoomLevel').textContent = `${Math.round(imageScale * 100)}%`;
      updateZoomButtons();
    }
    document.addEventListener('click', event => {
      const trigger = event.target.closest('[data-image-url]');
      if (!trigger) return;
      viewerTrigger = trigger;
      viewerImage.removeAttribute('src');
      viewerImage.alt = trigger.dataset.imageLabel;
      document.querySelector('#viewerTitle').textContent = trigger.dataset.imageLabel;
      document.querySelector('#viewerStatus').textContent = 'Loading image…';
      viewerImage.onload = () => { fitImage(); document.querySelector('#viewerStatus').textContent = `${viewerImage.naturalWidth} × ${viewerImage.naturalHeight} pixels · Use + to zoom, then scroll to explore. Escape closes the preview.`; };
      viewerImage.onerror = () => { document.querySelector('#viewerStatus').textContent = 'This image could not be loaded. Close the preview and choose another image or upload a replacement.'; updateZoomButtons(); };
      imageViewer.showModal();
      fitImage();
      viewerImage.src = trigger.dataset.imageUrl;
    });
    imageViewer.addEventListener('click', event => { if (event.target === imageViewer) imageViewer.close(); });
    imageViewer.addEventListener('close', () => { viewerImage.removeAttribute('src'); viewerTrigger?.focus(); });
  </script>
  <script>
    const state = { key: sessionStorage.stampoAdminKey || '', tab: 'countries', rows: [], meta: { countries: [], collectionKinds: [] }, states: {}, cities: {}, filters: { sights: '', 'collection-lists': '', cities: '' }, paging: { currentPage: 1, lastPage: 1, perPage: 50, total: 0 }, sightPaging: { currentPage: 1, lastPage: 1, perPage: 50, total: 0 }, lessonPaging: { currentPage: 1, perPage: 10 }, edit: null };
    const title = document.querySelector('#title'), summary = document.querySelector('#summary'), table = document.querySelector('#table'), notice = document.querySelector('#notice'), modal = document.querySelector('#modal'), form = document.querySelector('#form'), formTitle = document.querySelector('#formTitle'), formNotice = document.querySelector('#formNotice'), fields = document.querySelector('#fields');
    const normalizedFiles = new WeakMap();
    async function withButtonLoading(button, label, action) {
      if (button.disabled) return;
      const original = button.textContent;
      button.disabled = true;
      button.textContent = label;
      button.setAttribute('aria-busy', 'true');
      try { return await action(); }
      finally {
        button.disabled = false;
        button.textContent = original;
        button.removeAttribute('aria-busy');
      }
    }
    const krooIqQuestionFields = Array.from({ length: 10 }, (_, offset) => {
      const number = offset + 1, required = number <= 5 ? 1 : 0;
      return [
        [`q${number}Information`, `Information before question ${number}`, 'textarea', required],
        [`q${number}Prompt`, `Question ${number}`, 'textarea', required],
        [`q${number}Image`, `Information photo for question ${number}`, 'image'],
        [`q${number}Answers`, `Question ${number} answers (one per line)`, 'textarea', required],
        [`q${number}Correct`, `Question ${number} correct answer number (1, 2, 3, 4...)`, 'number', required],
        [`q${number}Explanation`, `Question ${number} explanation`, 'textarea', required],
      ];
    }).flat();
    const schemas = {
      countries: [['name', 'Country name', 'text', 1], ['heroImage', 'Country hero image', 'image', 1], ['description', 'Description', 'textarea']],
      cities: [['name', 'Name', 'text', 1], ['id', 'City ID', 'text'], ['countryId', 'Country', 'country', 1], ['state', 'State / region', 'state'], ['population', 'Population', 'number'], ['latitude', 'Latitude', 'number'], ['longitude', 'Longitude', 'number'], ['imageUrl', 'Image', 'image'], ['description', 'Description', 'textarea']],
      sights: [['name', 'Name', 'text', 1], ['countryId', 'Country', 'country', 1], ['state', 'State / region', 'state', 1], ['cityId', 'City', 'city', 1], ['image', 'Image', 'image'], ['content', 'Content', 'textarea', 1], ['isFeatured', 'Shown in lists', 'check']],
      collections: [['title', 'Title', 'text', 1], ['id', 'ID', 'text'], ['explorerImageUrl', 'Explorer image (3:2 transparent, optional)', 'image', 1], ['heroImageUrl', 'Collection page hero image (optional)', 'image', 1], ['detail', 'Detail', 'textarea', 1], ['access', 'Available to', 'access'], ['isPublished', 'Published', 'check']],
      'collection-lists': [['collectionKindIds', 'Collections', 'kinds', 1], ['title', 'Title', 'text', 1], ['id', 'ID (optional)', 'text'], ['countryId', 'Country', 'country'], ['state', 'State / region', 'state'], ['cityId', 'City / location', 'city'], ['sightId', 'Linked top sight ID (optional)', 'text'], ['location', 'Location label (optional)', 'text'], ['imageUrl', 'Image', 'image'], ['detail', 'Detail', 'textarea'], ['access', 'Access', 'access']],
      'daily-destinations': [['isPreview', 'Public preview (Lesson 0 with 10 questions)', 'check'], ['countryId', 'Country', 'country', 1], ...krooIqQuestionFields, ['isPublished', 'Available in Kroo IQ', 'check']]
    };
    async function call(path, options = {}) { let r; try { r = await fetch(path, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${state.key}`, 'X-Admin-Key': state.key, ...options.headers } }) } catch (error) { throw new Error(`Could not reach the server for ${path}. Check the connection and try again.`) } if (r.status === 204) return null; const type = r.headers.get('content-type') || ''; if (!type.includes('application/json')) throw new Error(`Server returned HTML instead of JSON (${r.status}) for ${path}. Clear the Laravel caches and verify this route is deployed.`); const body = await r.json(); if (!r.ok) throw new Error(body.message || `Request failed (${r.status})`); return body }
    async function login() { state.key = document.querySelector('#key').value.trim(); try { state.meta = await call('/admin/api/meta'); sessionStorage.stampoAdminKey = state.key; document.querySelector('#login').classList.add('hidden'); document.querySelector('#app').classList.remove('hidden'); await load() } catch (e) { document.querySelector('#loginError').textContent = e.message } }
    function logout() { sessionStorage.removeItem('stampoAdminKey'); location.reload() }
    let loadVersion = 0;
    async function load() {
      const version = ++loadVersion;
      const tab = state.tab;
      try {
        if (tab === 'ai') { await loadAi(); return }
        table.classList.remove('ai-workspace');
        summary.style.display = '';
        document.querySelector('.page-description').textContent = 'Manage your travel content. Select an image to preview and zoom.';
        const paging = tab === 'sights' ? state.sightPaging : state.paging;
        const params = new URLSearchParams();
        if (['cities', 'sights'].includes(tab)) {
          params.set('page', paging.currentPage);
          params.set('per_page', paging.perPage);
          params.set(tab === 'sights' ? 'country' : 'query', state.filters[tab] || '');
          if (tab === 'sights') params.set('query', state.sightSearch || '');
        }
        const result = await call('/admin/api/' + tab + (params.size ? '?' + params : ''));
        if (version !== loadVersion || tab !== state.tab) return;
        if (['cities', 'sights'].includes(tab)) {
          state.rows = result.data;
          state[tab === 'sights' ? 'sightPaging' : 'paging'] = result.meta;
        } else state.rows = result;
        render();
        note('');
      } catch (e) { if (version === loadVersion) note(e.message, true) }
    }
    const aiKinds = [
      { id: 'countries', icon: '?', label: 'Countries', detail: 'National stamp images' },
      { id: 'states', icon: '?', label: 'US states', detail: 'All existing US state records' },
      { id: 'cities', icon: '?', label: 'Top 1,000 cities', detail: 'Use the project’s Oxford city list' },
      { id: 'discover-sights', icon: '?', label: 'Top sights', detail: 'Find top attractions and fill missing content for existing sights' }
    ];
    const aiSections = [
      { id: 'discover-sights', label: 'Top sights' },
      { id: 'countries', label: 'Countries' },
      { id: 'cities', label: 'Cities' },
      { id: 'states', label: 'US states' }
    ];
    const aiResultPages = new Map();
    let aiRefreshing = false;
    let aiRunning = false;
    const aiContentCache = new Map();
    const aiOpenDetails = new Map();
    let aiEditing = null;
    let aiMutating = false;
    let aiCategory = 'cities';
    async function loadAi() {
      await call('/admin/api/ai/recover-rate-limits', { method: 'POST' });
      const data = await call('/admin/api/ai');
      title.textContent = 'AI automation';
      document.querySelector('.page-description').textContent = 'Create destination content in batches and track every run.';
      document.querySelector('#addButton').style.display = 'none';
      summary.style.display = 'none';
      table.classList.add('ai-workspace');
      table.innerHTML = `
        <section class="ai-toolbar">
          <div class="ai-toolbar-head"><h2>Generate content</h2><span class="ai-status ${data.configured ? '' : 'missing'}">${data.configured ? 'API ready' : 'API key needed'}</span></div>
          <form id="aiForm" class="ai-toolbar-form" onsubmit="startAi(event)">
            <label class="field">Content type<select name="category" onchange="selectAiCategory(this.value)">${aiKinds.map(x => `<option value="${x.id}" ${x.id === aiCategory ? 'selected' : ''}>${x.label}</option>`).join('')}</select></label>
            <label class="field ai-limit">Maximum items<input name="limit" type="number" min="1" max="10000" value="25"></label>
            <button class="primary" type="submit" ${data.configured ? '' : 'disabled'}>Start batch</button>
          </form>
          <p id="aiCsv" class="ai-help">Oxford 2026 · Project list of 1,000 cities.</p>
          <p id="aiCategoryHelp" class="ai-help"></p>
          <p id="aiRunStatus" class="ai-help" role="status">Keep this page open while batches run.</p>
        </section>
        <section class="ai-batches" aria-labelledby="aiBatchesTitle">
          <div class="ai-batches-head"><div><span class="ai-eyebrow">Activity</span><h3 id="aiBatchesTitle">Recent batches</h3></div><button type="button" onclick="refreshAiBatches();runAiBatches()">Refresh</button></div>
          <div class="ai-batch-tabs" role="tablist" aria-label="Batch content types" onkeydown="aiTabKey(event)">${aiSections.map(section => `<button type="button" role="tab" id="aiTab-${section.id}" data-ai-tab="${section.id}" aria-controls="aiPanel-${section.id}" aria-selected="false" tabindex="-1" onclick="selectAiCategory('${section.id}')">${section.label}<span class="ai-tab-count" data-ai-tab-count aria-hidden="true" title="Saved images in this section’s batches">0</span></button>`).join('')}</div>
          <div id="aiBatches" class="ai-batch-sections">${aiSections.map(section => `<section class="ai-batch-section" role="tabpanel" id="aiPanel-${section.id}" data-ai-section="${section.id}" aria-labelledby="aiTab-${section.id}" hidden><div class="ai-batch-section-head"><h4 id="aiSection-${section.id}">${section.label}<small data-ai-count></small></h4><button type="button" onclick="selectAiCategory('${section.id}');document.querySelector('#aiForm input[name=limit]').focus()">New batch</button></div><div class="ai-generation-counts">${[['images', 'Images saved'], ...(section.id === 'countries' ? [] : [['descriptions', 'Descriptions saved']]), ['completed', 'Batch items completed'], ['pending', 'Pending'], ['failed', 'Failed']].map(([key, label]) => `<div>${label}<strong data-ai-generation-count="${key}">0</strong></div>`).join('')}</div><p class="ai-count-help">Counts cover all batches. Saved content counts each ${section.id === 'discover-sights' ? 'sight' : section.id === 'countries' ? 'country' : section.id === 'states' ? 'US state' : 'city'} once.${section.id === 'discover-sights' ? ' City entries count as one batch item; each sight has its own image and description.' : ''}</p><div class="ai-batch-list" data-ai-batch-list></div><p class="ai-section-empty">No batches in this section yet.</p></section>`).join('')}</div>
        </section>`;
      selectAiCategory(aiCategory);
      renderAiBatches(data.batches, data.sectionCounts);
      note('');
      void runAiBatches();
    }
    function selectAiCategory(category) {
      aiCategory = category;
      const form = document.querySelector('#aiForm');
      if (!form) return;
      form.elements.category.value = category;
      document.querySelectorAll('[data-ai-tab]').forEach(button => {
        const selected = button.dataset.aiTab === category;
        button.setAttribute('aria-selected', String(selected));
        button.tabIndex = selected ? 0 : -1;
      });
      document.querySelectorAll('[data-ai-section]').forEach(section => {
        section.hidden = section.dataset.aiSection !== category;
      });
      const needsCsv = ['cities', 'discover-sights'].includes(category);
      document.querySelector('#aiCsv').hidden = !needsCsv;
      document.querySelector('#aiCategoryHelp').textContent = category === 'discover-sights'
        ? 'Select the next cities not used in a Top sights batch. Find five sights where needed and fill missing images and descriptions. Retry earlier cities from their existing batch.'
        : 'Existing images and descriptions are kept. This run fills missing fields only.';
      form.querySelector('button[type=submit]').textContent = category === 'discover-sights' ? 'Generate top sights' : 'Start batch';
    }
    function aiTabKey(event) {
      const tabs = [...event.currentTarget.querySelectorAll('[role=tab]')];
      const index = tabs.indexOf(event.target);
      if (index < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
        : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
      selectAiCategory(tabs[next].dataset.aiTab);
      tabs[next].focus();
    }
    function renderAiBatches(batches, sectionCounts = {}) {
      const list = document.querySelector('#aiBatches');
      if (!list) return;
      const ids = new Set(batches.map(b => String(b.id)));
      list.querySelectorAll('.ai-batch').forEach(article => {
        if (!ids.has(article.dataset.batchId) && !aiResultPages.has(Number(article.dataset.batchId))) article.remove();
      });
      batches.forEach(b => {
        const label = b.category === 'sights' ? 'Top sights' : aiKinds.find(x => x.id === b.category)?.label || b.category;
        const processed = Math.min(Number(b.total), Number(b.completed) + Number(b.failed));
        const percent = b.total ? Math.round(processed / b.total * 100) : 100;
        const action = b.status === 'running' ? `<button onclick="aiAction(${b.id},'pause')">Pause</button>`
          : (b.status === 'paused' || Number(b.failed) > 0) ? `<button onclick="aiAction(${b.id},'resume')">${b.status === 'paused' ? 'Resume' : 'Retry failed'}</button>` : '';
        const header = `<div class="ai-batch-top"><div><div class="ai-batch-title">${esc(label)} <span class="ai-pill ${esc(b.status)}">${esc(b.status)}</span></div><p class="ai-batch-meta">Batch #${b.id} · ${b.completed} complete · ${b.failed} failed · ${b.total} total</p></div><div class="ai-batch-actions"><button onclick="showAiBatch(${b.id})">View results</button>${action}${b.status === 'complete' ? `<button onclick="aiAction(${b.id},'fill-missing')">Generate missing content</button>` : ''}</div></div><div class="ai-progress" role="progressbar" aria-label="Batch ${b.id} progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}"><span style="width:${percent}%"></span></div>`;
        let article = list.querySelector(`[data-batch-id="${b.id}"]`);
        if (!article) {
          article = document.createElement('article');
          article.className = 'ai-batch';
          article.dataset.batchId = b.id;
          article.innerHTML = `<div class="ai-batch-summary"></div><div id="aiDetails-${b.id}" hidden></div>`;
          const category = b.category === 'sights' ? 'discover-sights' : b.category;
          const section = list.querySelector(`[data-ai-section="${category}"]`);
          if (!section) return;
          const batchList = section.querySelector('[data-ai-batch-list]');
          const next = [...batchList.children].find(existing => Number(existing.dataset.batchId) < Number(b.id));
          batchList.insertBefore(article, next || null);
        }
        if (article.dataset.summary !== header) {
          article.querySelector('.ai-batch-summary').innerHTML = header;
          article.dataset.summary = header;
          const update = article.querySelector('[data-ai-update]');
          if (update) update.textContent = 'Update results';
        }
      });
      list.querySelectorAll('[data-ai-section]').forEach(section => {
        const count = section.querySelectorAll('.ai-batch').length;
        section.querySelector('[data-ai-count]').textContent = `${count} ${count === 1 ? 'batch' : 'batches'}`;
        section.querySelector('.ai-section-empty').hidden = count > 0;
        const category = section.dataset.aiSection;
        const counts = sectionCounts[category] || {};
        section.querySelectorAll('[data-ai-generation-count]').forEach(value => {
          value.textContent = Number(counts[value.dataset.aiGenerationCount] || 0).toLocaleString();
        });
        document.querySelector(`[data-ai-tab="${category}"] [data-ai-tab-count]`).textContent = Number(counts.images || 0).toLocaleString();
      });
    }
    async function runAiBatches() {
      if (aiRunning || state.tab !== 'ai') return;
      aiRunning = true;
      try {
        while (state.tab === 'ai') {
          if (aiMutating || document.querySelector('#aiContentEditor').open) {
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
          }
          const data = await call('/admin/api/ai');
          if (state.tab !== 'ai') break;
          const batch = data.nextBatchId ? { id: data.nextBatchId } : data.batches.filter(b => b.status === 'running').sort((a, b) => a.id - b.id)[0];
          if (!batch) {
            document.querySelector('#aiRunStatus').textContent = 'No batches running. Start or resume a batch to continue.';
            break;
          }
          if (Number(data.retryAfterSeconds) > 0) {
            await waitAiRateLimit(data.retryAfterSeconds);
            continue;
          }
          document.querySelector('#aiRunStatus').textContent = `Processing batch #${batch.id} · ${data.concurrency || 8} parallel generations · Keep this page open.`;
          const result = await call(`/admin/api/ai/${batch.id}/process`, { method: 'POST' });
          if (state.tab !== 'ai') break;
          await refreshAiBatches();
          if (Number(result.retryAfterSeconds) > 0) await waitAiRateLimit(result.retryAfterSeconds);
          else if (!result.processed) await new Promise(resolve => setTimeout(resolve, 3000));
        }
      } catch (error) {
        if (state.tab === 'ai') {
          document.querySelector('#aiRunStatus').textContent = 'Processing stopped. Refresh to continue.';
          note(error.message, true);
        }
      } finally { aiRunning = false }
    }
    async function waitAiRateLimit(seconds) {
      const until = Date.now() + Number(seconds) * 1000;
      while (state.tab === 'ai' && Date.now() < until) {
        document.querySelector('#aiRunStatus').textContent = `OpenAI rate limit · Continuing automatically in ${Math.ceil((until - Date.now()) / 1000)} seconds. Keep this page open.`;
        await new Promise(resolve => setTimeout(resolve, 1000));
      }
    }
    async function refreshAiBatches() {
      if (aiRefreshing || aiMutating || document.querySelector('#aiContentEditor').open) return;
      aiRefreshing = true;
      try {
        const data = await call('/admin/api/ai');
        if (state.tab !== 'ai') return;
        renderAiBatches(data.batches, data.sectionCounts);
      } catch (error) { note(error.message, true) }
      finally { aiRefreshing = false }
    }
    async function startAi(event) {
      event.preventDefault();
      const form = event.target;
      const button = form.querySelector('button[type=submit]');
      const body = new FormData(form);
      button.disabled = true;
      try {
        const response = await fetch('/admin/api/ai', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${state.key}` }, body });
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Could not start batch.');
        await refreshAiBatches();
        note(`Batch #${data.batch.id} started. ${data.batch.total} items ready.`);
        await showAiBatch(data.batch.id);
        void runAiBatches();
      } catch (error) { note(error.message, true) }
      finally { button.disabled = false }
    }
    function aiContentRow(batch, item, content) {
      const key = `${batch.id}:${content.id}`;
      aiContentCache.set(key, { batch, item, content });
      const sight = ['sights', 'discover-sights'].includes(batch.category);
      const disabled = item.status === 'working' || (item.status === 'queued' && batch.status === 'running');
      const actionKey = esc(JSON.stringify(key));
      return `<article class="ai-result-row">
        ${content.image ? imagePreview(content.image, content.name).replace('<img ', '<img onerror="this.parentElement.innerHTML=\'<span class=ai-image-error>Image could not load</span>\'" ') : '<div class="ai-image-empty">No image</div>'}
        <div class="ai-result-text"><h4>${esc(content.name)}</h4><small>${esc(item.location || item.name)} · ${esc(item.status)}${sight ? ` · ${content.isFeatured ? 'Approved' : 'Needs review'}` : ''}</small>
          ${batch.category === 'countries' ? '' : content.description ? `<details class="ai-description" data-description-key="${esc(key)}"><summary><span>${esc(content.description.replace(/\s+/g, ' '))}</span><strong>Full description</strong></summary><p>${esc(content.description)}</p></details>` : '<p class="ai-no-description">No description yet</p>'}
          ${item.error ? `<p class="notice error">${esc(item.error)}</p>` : ''}</div>
        <div class="ai-result-actions"><button onclick="editAiContent(${actionKey})" ${disabled ? 'disabled title="Pause this batch and wait for generation to finish"' : ''}>Edit</button><button class="danger" onclick="removeAiContent(${actionKey})" ${disabled ? 'disabled' : ''}>${sight ? 'Remove' : 'Clear content'}</button></div>
      </article>`;
    }
    async function showAiBatch(id, page = 1) {
      aiResultPages.set(id, page);
      try {
        const data = await call(`/admin/api/ai/${id}/results?page=${page}&per_page=5`);
        const panel = document.querySelector(`#aiDetails-${id}`);
        if (!panel || aiResultPages.get(id) !== page) return;
        const wasOpen = !panel.hidden || aiOpenDetails.has(id);
        const openDetails = panel.hidden ? (aiOpenDetails.get(id) || new Set()) : new Set([...panel.querySelectorAll('details[open]')].map(x => x.dataset.groupKey || x.dataset.descriptionKey));
        panel.hidden = false;
        panel.className = 'ai-results';
        const results = data.results;
        if (page > results.last_page) return showAiBatch(id, results.last_page);
        const discovery = data.batch.category === 'discover-sights';
        panel.innerHTML = `<div class="ai-results-heading"><p>${discovery ? 'Open a city to review its top sights. Results stay in place while batch progress updates.' : data.batch.category === 'countries' ? 'Saved images. Click an image to zoom.' : 'Saved images and descriptions. Click an image to zoom.'}</p><div><button data-ai-update style="font-size:12px;" onclick="showAiBatch(${id},${page})">Refresh results</button> &nbsp; <button style="font-size:12px;" onclick="closeAiResults(${id})">Close results</button></div></div>
          ${results.data.map((item, index) => {
            const rows = item.content.map(content => aiContentRow(data.batch, item, content)).join('') || `<p class="ai-help">${esc(item.error || (discovery ? 'No sights saved yet.' : 'Catalog record removed.'))}</p>`;
            return discovery ? `<div class="ai-city-entry"><details class="ai-result-group" data-group-key="city-${id}-${item.id}" ${openDetails.has(`city-${id}-${item.id}`) || (!wasOpen && index === 0) ? 'open' : ''}><summary>${esc(item.name)}<small>${esc(item.location || '')} · ${item.content.length} sights · ${esc(item.status)}</small></summary>${rows}</details><button class="danger ai-city-remove" title="Remove this city from the batch" onclick="removeAiBatchItem(${id},${item.id},${esc(JSON.stringify(item.name))})" ${item.status === 'working' || (item.status === 'queued' && data.batch.status === 'running') ? 'disabled' : ''}>Remove</button></div>` : rows;
          }).join('') || '<p class="ai-help">No items in this batch.</p>'}
          <div class="ai-result-pagination"><button onclick="showAiBatch(${id},${page - 1})" ${page <= 1 ? 'disabled' : ''}>Previous</button><span>Page ${results.current_page} of ${results.last_page} · ${results.total} items</span><button onclick="showAiBatch(${id},${page + 1})" ${page >= results.last_page ? 'disabled' : ''}>Next</button></div>`;
        panel.querySelectorAll('[data-description-key]').forEach(x => { x.open = openDetails.has(x.dataset.descriptionKey) });
        aiOpenDetails.set(id, openDetails);
      } catch (error) { note(error.message, true) }
    }
    function closeAiResults(id) {
      aiResultPages.delete(id);
      aiOpenDetails.delete(id);
      document.querySelector(`#aiDetails-${id}`).hidden = true;
    }
    function editAiContent(key) {
      aiEditing = aiContentCache.get(key);
      if (!aiEditing) return;
      const editor = document.querySelector('#aiContentEditor');
      const editForm = document.querySelector('#aiContentForm');
      const { content, batch } = aiEditing;
      editForm.reset();
      editForm.elements.name.value = content.name;
      editForm.elements.description.value = content.description || '';
      editForm.elements.description.closest('.field').hidden = batch.category === 'countries';
      document.querySelector('#aiContentDescriptionButton').hidden = batch.category === 'countries';
      editForm.elements.image.value = content.image || '';
      editForm.elements.isFeatured.checked = !!content.isFeatured;
      document.querySelector('#aiFeatureField').hidden = !['sights', 'discover-sights'].includes(batch.category);
      document.querySelector('#aiEditNotice').textContent = '';
      const preview = document.querySelector('#aiEditPreview');
      preview.hidden = !content.image;
      preview.src = content.image || '';
      editor.showModal();
      editForm.elements.name.focus();
    }
    async function generateAiContentDescription(button) {
      if (!aiEditing) return;
      const editForm = document.querySelector('#aiContentForm');
      const category = aiEditing.batch.category;
      const resource = ['sights', 'discover-sights'].includes(category) ? 'sights' : 'cities';
      const name = editForm.elements.name.value.trim();
      if (!name) { document.querySelector('#aiEditNotice').textContent = 'Enter a name first.'; return; }
      await withButtonLoading(button, 'Generating…', async () => {
        try {
          const result = await call('/admin/api/ai/text', { method: 'POST', body: JSON.stringify({ resource, name }) });
          editForm.elements.description.value = result.description;
          editForm.elements.description.focus();
          document.querySelector('#aiEditNotice').textContent = 'Draft ready. Review it before saving.';
        } catch (error) { document.querySelector('#aiEditNotice').textContent = error.message; }
      });
    }
    async function saveAiContent(event) {
      event.preventDefault();
      if (aiMutating || !aiEditing) return;
      const editForm = event.target;
      const { batch, content } = aiEditing;
      const saveButton = editForm.querySelector('button[type=submit]');
      saveButton.disabled = true;
      aiMutating = true;
      try {
        let image = editForm.elements.image.value.trim();
        const file = editForm.elements.imageFile.files[0];
        if (file) {
          const body = new FormData();
          body.append('image', file);
          body.append('folder', batch.category === 'discover-sights' ? 'sights' : batch.category);
          const response = await fetch('/admin/api/images', { method: 'POST', headers: { Accept: 'application/json', 'X-Admin-Key': state.key }, body });
          const uploaded = await response.json();
          if (!response.ok) throw new Error(Object.values(uploaded.errors || {}).flat().join(' ') || uploaded.message || 'Image upload failed.');
          image = uploaded.imageUrl;
          editForm.elements.image.value = image;
          editForm.elements.imageFile.value = '';
        }
        await call(`/admin/api/ai/${batch.id}/content/${encodeURIComponent(content.id)}`, { method: 'PUT', body: JSON.stringify({ name: editForm.elements.name.value.trim(), description: editForm.elements.description.value, image, isFeatured: editForm.elements.isFeatured.checked }) });
        document.querySelector('#aiContentEditor').close();
        await showAiBatch(batch.id, aiResultPages.get(batch.id) || 1);
        note('Content saved.');
      } catch (error) { document.querySelector('#aiEditNotice').textContent = error.message }
      finally { aiMutating = false; saveButton.disabled = false }
    }
    async function removeAiContent(key) {
      const entry = aiContentCache.get(key);
      if (!entry || aiMutating) return;
      const { batch, content } = entry;
      const sight = ['sights', 'discover-sights'].includes(batch.category);
      if (!confirm(sight ? `Remove “${content.name}” from the sights catalog?` : `Clear the image and description for “${content.name}”?`)) return;
      aiMutating = true;
      try {
        await call(`/admin/api/ai/${batch.id}/content/${encodeURIComponent(content.id)}`, { method: 'DELETE' });
        await showAiBatch(batch.id, aiResultPages.get(batch.id) || 1);
        note(sight ? 'Sight removed.' : 'Image and description cleared.');
      } catch (error) { note(error.message, true) }
      finally { aiMutating = false }
    }
    async function removeAiBatchItem(id, itemId, name) {
      if (aiMutating) return;
      if (!confirm(`Remove “${name}” from this batch? Its saved city and sights remain in the catalog.`)) return;
      aiMutating = true;
      try {
        await call(`/admin/api/ai/${id}/items/${itemId}`, { method: 'DELETE' });
        aiOpenDetails.delete(id);
        aiMutating = false;
        await refreshAiBatches();
        await showAiBatch(id, aiResultPages.get(id) || 1);
        note('City removed from this batch.');
      } catch (error) { note(error.message, true) }
      finally { aiMutating = false }
    }
    async function aiAction(id, action) {
      try { await call(`/admin/api/ai/${id}/${action}`, { method: 'POST' }); await refreshAiBatches(); await showAiBatch(id); void runAiBatches() }
      catch (error) { note(error.message, true) }
    }
    setInterval(() => { if (state.tab === 'ai' && document.visibilityState === 'visible') { void refreshAiBatches(); void runAiBatches() } }, 15000);
    function esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])) }
    function render() { const names = { 'countries': 'Country hero images', 'cities': 'Cities', 'sights': 'Top sights', 'collections': 'Collection kinds', 'collection-lists': 'Collection list', 'daily-destinations': 'Kroo IQ lessons and questions' }; title.textContent = names[state.tab]; document.querySelector('#addButton').style.display = ['countries', 'cities'].includes(state.tab) ? 'none' : ''; const filter = state.filters[state.tab] || ''; const rows = state.rows.map((row, index) => ({ row, index })).filter(({ row }) => state.tab === 'collection-lists' ? !filter || row.collectionKindId === filter : true); const options = state.tab === 'sights' ? state.meta.countries.map(x => `<option value="${esc(x.id)}" ${x.id === filter ? 'selected' : ''}>${esc(x.code + ' · ' + x.name)}</option>`).join('') : state.tab === 'collection-lists' ? state.meta.collectionKinds.map(x => `<option value="${esc(x.id)}" ${x.id === filter ? 'selected' : ''}>${esc(x.title)}</option>`).join('') : ''; const label = state.tab === 'sights' ? 'country' : 'collection kind'; const cityTools = state.tab === 'cities' ? `<div class="city-tools"><div class="city-search-row"><input id="citySearch" type="search" value="${esc(filter)}" placeholder="Search city, state, or country" aria-label="Search cities" onkeydown="if(event.key === 'Enter'){event.preventDefault();submitCitySearch()}"><button class="primary" onclick="withButtonLoading(this, &quot;Searching...&quot;, () => submitCitySearch())">Search</button>${filter ? `<button onclick="withButtonLoading(this, &quot;Clearing...&quot;, () => clearCitySearch())">Clear</button>` : ''}<button class="primary" onclick="openEditor()">+ Add new</button></div><div class="city-pagination"><span>${state.paging.total} records</span><button ${state.paging.currentPage <= 1 ? 'disabled' : ''} onclick="withButtonLoading(this, &quot;Loading...&quot;, () => changeCityPage(-1))">Previous</button><span>Page ${state.paging.currentPage} of ${state.paging.lastPage}</span><button ${state.paging.currentPage >= state.paging.lastPage ? 'disabled' : ''} onclick="withButtonLoading(this, &quot;Loading...&quot;, () => changeCityPage(1))">Next</button></div></div>` : ''; const krooIqHelp = state.tab === 'daily-destinations' ? '<small>Each record is one Kroo IQ question. The app uses up to five published records for the selected quiz date.</small>' : ''; summary.innerHTML = `<div class="summarybar">${(state.tab === 'sights' ? sightPaginationTools(options) : cityTools) || `<span>${rows.length}${filter ? ` of ${state.rows.length}` : ''} records ${krooIqHelp}</span>${options ? `<select aria-label="Filter by ${label}" onchange="setTableFilter(this.value)"><option value="">All ${label === 'country' ? 'countries' : 'collection kinds'}</option>${options}</select>` : ''}`}</div>`; const cols = state.tab === 'countries' ? ['heroImage', 'code', 'name'] : state.tab === 'cities' ? ['imageUrl', 'name', 'country', 'state', 'population', 'latitude', 'longitude'] : state.tab === 'sights' ? ['image', 'name', 'country', 'state', 'city'] : state.tab === 'collections' ? ['imageUrl', 'title', 'detail'] : state.tab === 'collection-lists' ? ['imageUrl', 'title', 'collectionKind', 'location', 'detail', 'access'] : ['imageUrl', 'name', 'country', 'city', 'question']; const numberOffset = state.tab === 'sights' ? (state.sightPaging.currentPage - 1) * state.sightPaging.perPage : state.tab === 'cities' ? (state.paging.currentPage - 1) * state.paging.perPage : 0; table.innerHTML = `<table><thead><tr><th>No.</th>${cols.map(x => `<th>${esc(({heroImage:'Image',imageUrl:'Image',collectionKind:'Collection'})[x] || x.replace(/([A-Z])/g, ' $1'))}</th>`).join('')}<th>Actions</th></tr></thead><tbody>${rows.map(({ row: r, index: i }, displayIndex) => `<tr><td>${numberOffset + displayIndex + 1}</td>${cols.map(c => cell(r, c)).join('')}<td><div class="actions"><button onclick="openEditor(${i})">Edit</button>${state.tab === 'countries' ? '' : `<button class="danger" onclick="withButtonLoading(this, &quot;Deleting...&quot;, () => removeRow(${i}))">Delete</button>`}</div></td></tr>`).join('') || '<tr><td colspan="12" class="empty-state">No records found. Try another filter or add content to get started.</td></tr>'}</tbody></table>` }
    const renderDefaultResource = render;
    render = function () {
      renderDefaultResource();
      if (state.tab !== 'daily-destinations') return;
      const previewCount = state.rows.filter(row => Number(row.lessonNumber) === 0).length;
      const publishedCount = state.rows.filter(row => row.isPublished !== false).length;
      summary.innerHTML = `<div class="lesson-summary"><div class="lesson-stat">Total lessons<strong>${state.rows.length}</strong></div><div class="lesson-stat">Published<strong>${publishedCount}</strong></div><div class="lesson-stat">Public preview<strong>${previewCount ? 'Ready' : 'Missing'}</strong></div><div class="lesson-help">Lesson 0 is the one-time public preview with 10 questions. Kroo+ lessons start at Lesson 1, contain 5 questions, and progress in order.</div></div>`;
      const { perPage } = state.lessonPaging;
      const lastPage = Math.max(1, Math.ceil(state.rows.length / perPage));
      state.lessonPaging.currentPage = Math.min(state.lessonPaging.currentPage, lastPage);
      const currentPage = state.lessonPaging.currentPage;
      const start = (currentPage - 1) * perPage;
      summary.insertAdjacentHTML('beforeend', `<div class="city-pagination"><label>Show <select aria-label="Kroo IQ lessons per page" onchange="setLessonPageSize(this.value)">${[10, 20, 50].map(size => `<option value="${size}" ${size === perPage ? 'selected' : ''}>${size}</option>`).join('')}</select> per page</label><span>${state.rows.length ? start + 1 : 0}–${Math.min(start + perPage, state.rows.length)} of ${state.rows.length} lessons</span><button ${currentPage <= 1 ? 'disabled' : ''} onclick="changeLessonPage(-1)">Previous</button><span>Page ${currentPage} of ${lastPage}</span><button ${currentPage >= lastPage ? 'disabled' : ''} onclick="changeLessonPage(1)">Next</button></div>`);
      table.innerHTML = `<table><thead><tr><th>No.</th><th>Lesson</th><th>Country</th><th>Actions</th></tr></thead><tbody>${state.rows.slice(start, start + perPage).map((row, index) => `<tr><td>${start + index + 1}</td><td><span class="lesson-pill ${Number(row.lessonNumber) === 0 ? 'preview' : ''}">${Number(row.lessonNumber) === 0 ? 'Lesson 0 · Preview' : `Lesson ${esc(row.lessonNumber)}`}</span></td>${cell(row, 'country')}<td><div class="actions"><button onclick="openEditor(${start + index})">Edit</button><button class="danger" onclick="withButtonLoading(this, &quot;Deleting...&quot;, () => removeRow(${start + index}))">Delete</button></div></td></tr>`).join('') || '<tr><td colspan="12" class="empty-state">No records found. Try another filter or add content to get started.</td></tr>'}</tbody></table>`;
    };
    function setLessonPageSize(value) { state.lessonPaging.perPage = Number(value); state.lessonPaging.currentPage = 1; render() }
    function changeLessonPage(delta) { state.lessonPaging.currentPage += delta; render() }
    function sightPaginationTools(options) {
      const { currentPage, lastPage, perPage, total } = state.sightPaging;
      const first = total ? (currentPage - 1) * perPage + 1 : 0;
      const last = Math.min(currentPage * perPage, total);
      return `<div class="city-tools"><div class="city-search-row">
        <input id="sightSearch" type="search" maxlength="200" value="${esc(state.sightSearch || '')}" placeholder="Search sight, city, state, or country" aria-label="Search top sights" onkeydown="if(event.key === 'Enter'){event.preventDefault();submitSightSearch()}">
        <button class="primary" onclick="withButtonLoading(this, &quot;Searching...&quot;, () => submitSightSearch())">Search</button>
        ${state.sightSearch ? '<button onclick="withButtonLoading(this, &quot;Clearing...&quot;, () => clearSightSearch())">Clear</button>' : ''}
      </div><div class="city-pagination sight-pagination">
        <label>Country <select aria-label="Filter by country" onchange="setTableFilter(this.value)"><option value="">All countries</option>${options}</select></label>
        <label>Show <select aria-label="Sights per page" onchange="setSightPageSize(this.value)">${[50, 100, 200].map(size => `<option value="${size}" ${size === perPage ? 'selected' : ''}>${size}</option>`).join('')}</select> per page</label>
        <span>${first}–${last} of ${total} sights</span>
        <button ${currentPage <= 1 ? 'disabled' : ''} onclick="withButtonLoading(this, &quot;Loading...&quot;, () => changeSightPage(-1))">Previous</button>
        <span>Page ${currentPage} of ${lastPage}</span>
        <button ${currentPage >= lastPage ? 'disabled' : ''} onclick="withButtonLoading(this, &quot;Loading...&quot;, () => changeSightPage(1))">Next</button>
      </div></div>`;
    }
    async function submitSightSearch() {
      state.sightSearch = document.querySelector('#sightSearch').value.trim();
      state.sightPaging.currentPage = 1;
      await load();
    }
    async function clearSightSearch() {
      state.sightSearch = '';
      state.sightPaging.currentPage = 1;
      await load();
    }
    async function setTableFilter(value) {
      state.filters[state.tab] = value;
      if (state.tab === 'sights') { state.sightPaging.currentPage = 1; await load() }
      else render();
    }
    async function setSightPageSize(value) {
      const size = Number(value);
      if (![50, 100, 200].includes(size)) return;
      state.sightPaging.perPage = size;
      state.sightPaging.currentPage = 1;
      await load();
    }
    async function changeSightPage(offset) {
      state.sightPaging.currentPage = Math.max(1, Math.min(state.sightPaging.lastPage, state.sightPaging.currentPage + offset));
      await load();
    }
    async function submitCitySearch() { state.filters.cities = document.querySelector('#citySearch')?.value.trim() || ''; state.paging.currentPage = 1; await load() }
    async function clearCitySearch() { state.filters.cities = ''; state.paging.currentPage = 1; await load() }
    async function changeCityPage(offset) { state.paging.currentPage += offset; await load() }
    async function countryChanged() { const region = form.elements.state; if (region) { region.value = ''; region.dataset.value = '' } updateLessonCountrySelection(); await renderStates() }
    async function addState() { const countryId = form.elements.countryId?.value; if (!countryId) return note('Select a country before adding a state.', true); const name = prompt('State / region name:')?.trim(); if (!name) return; try { const created = await call('/admin/api/states', { method: 'POST', body: JSON.stringify({ countryId, name }) }); delete state.states[countryId]; form.elements.state.dataset.value = created.name; await renderStates(); note(`State “${created.name}” is ready to use.`) } catch (e) { note(e.message, true) } }
    function detailPreview(value) {
      const text = String(value ?? '').trim();
      const sentences = typeof Intl.Segmenter === 'function'
        ? Array.from(new Intl.Segmenter('en', { granularity: 'sentence' }).segment(text), item => item.segment)
        : text.match(/[^.!?]+(?:[.!?]+(?:\s+|$)|$)/g) || [text];
      return sentences.slice(0, 2).join('').trim() + (sentences.length > 2 ? ' …' : '');
    }
    function cell(r, c) { if (c === 'image' || c === 'imageUrl' || c === 'heroImage') { const u = r.heroImage || r.image || r.imageUrl; return `<td>${u ? imagePreview(u, r.name || r.title || "Image") : '—'}</td>` } if (c === 'access') return `<td><span class="badge">${r.access === 'pro' || r.isPremium ? 'Kroo+ locked' : 'Unlocked'}</span></td>`; if (c === 'detail' && ['collections', 'collection-lists'].includes(state.tab)) return `<td><div class="collection-detail-preview">${esc(detailPreview(r[c]) || '—')}</div></td>`; return `<td>${esc(r[c] || '—')}</td>` }
    function note(message, bad = false) { notice.innerHTML = message ? `<div class="notice ${bad ? 'error' : ''}">${esc(message)}</div>` : '' }
    function fieldHtml(f, row) { const [key, label, type, wide] = f; let value = row?.[key]; if (key === 'image') value = row?.image || row?.imageUrl; if (key === 'content') value = row?.content || row?.description; if (key === 'options' && Array.isArray(value)) value = value.join('\n'); const cls = `field ${wide ? 'wide' : ''}`; if (type === 'check') return `<label class="check ${wide ? 'wide' : ''}"><input name="${key}" type="checkbox" ${value !== false ? 'checked' : ''}> ${label}</label>`; if (type === 'country') return `<label class="${cls}">${label}<select name="${key}" required onchange="countryChanged()"><option value="">Select…</option>${state.meta.countries.map(x => `<option value="${esc(x.id)}" ${x.id === value ? 'selected' : ''}>${esc(x.code + ' · ' + x.name)}</option>`).join('')}</select></label>`; if (type === 'state') { const add = form.dataset.resource === 'cities' ? '<button type="button" onclick="withButtonLoading(this, &quot;Adding...&quot;, () => addState())">+ Add state</button>' : ''; return `<label class="${cls}">${label}<div class="field-row"><select name="${key}" data-value="${esc(value || '')}" onchange="renderCities()"><option value="">Select a country first…</option></select>${add}</div></label>` } if (type === 'city') return `<label class="${cls}">${label}<select name="${key}" data-value="${esc(value || '')}" required><option value="">Select a state first…</option></select></label>`; if (type === 'kind') return `<label class="${cls}">${label}<select name="${key}" required><option value="">Select…</option>${state.meta.collectionKinds.map(x => `<option value="${esc(x.id)}" ${x.id === value ? 'selected' : ''}>${esc(x.title)}</option>`).join('')}</select></label>`; if (type === 'access') return `<label class="${cls}">${label}<select name="${key}" required><option value="free" ${value !== 'pro' ? 'selected' : ''}>Everyone</option><option value="pro" ${value === 'pro' ? 'selected' : ''}>Kroo+ only</option></select></label>`; if (type === 'image') return `<label class="${cls}">${label}${value ? `<img src="${esc(value)}" alt="" style="width:120px;height:80px;object-fit:cover;margin:6px 0;border-radius:8px">` : ''}<input name="${key}" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-current="${esc(value || '')}" onchange="normalizeImage(this)"><small>Images are automatically resized to 1200 × 800 pixels.</small></label>`; if (type === 'textarea') return `<label class="${cls}">${label}<textarea name="${key}" ${f[3] ? 'required' : ''}>${esc(value || '')}</textarea></label>`; const step = type === 'number' && ['latitude', 'longitude'].includes(key) ? 'step="any"' : ''; return `<label class="${cls}">${label}<input name="${key}" type="${type}" ${step} value="${esc(value ?? '')}" ${f[3] ? 'required' : ''} ${key === 'id' && row ? 'disabled' : ''}></label>` }
    async function renderStates() { const country = form.elements.countryId?.value; const select = form.elements.state; if (!select) return renderCities(); const selected = select.dataset.value; const list = document.querySelector('#city-state-options'); if (select.tagName !== 'SELECT') { if (!country) { if (list) list.innerHTML = ''; return } try { state.states[country] ||= await call(`/admin/api/states?country=${encodeURIComponent(country)}`); if (list) list.innerHTML = state.states[country].map(x => `<option value="${esc(x)}"></option>`).join('') } catch (e) { note(e.message, true) } return } if (!country) { select.innerHTML = '<option value="">Select a country first…</option>'; return renderCities() } select.disabled = true; select.innerHTML = '<option value="">Loading states…</option>'; try { state.states[country] ||= await call(`/admin/api/states?country=${encodeURIComponent(country)}`); select.innerHTML = '<option value="">All / no state</option>' + state.states[country].map(x => `<option value="${esc(x)}" ${x === selected ? 'selected' : ''}>${esc(x)}</option>`).join(''); select.dataset.value = ''; } catch (e) { select.innerHTML = '<option value="">Could not load states</option>'; note(e.message, true) } finally { select.disabled = false } await renderCities() }
    async function renderCities() { const country = form.elements.countryId?.value; const region = form.elements.state?.value || ''; const select = form.elements.cityId; if (!select) return; const selected = select.dataset.value; if (!country) { select.innerHTML = '<option value="">Select a country first…</option>'; return } const key = `${country}:${region}`; select.disabled = true; select.innerHTML = '<option value="">Loading cities…</option>'; try { state.cities[key] ||= await call(`/admin/api/cities?country=${encodeURIComponent(country)}&state=${encodeURIComponent(region)}`); select.innerHTML = '<option value="">Select…</option>' + state.cities[key].map(x => `<option value="${esc(x.id)}" ${String(x.id) === String(selected) ? 'selected' : ''}>${esc(x.name)}</option>`).join(''); select.dataset.value = ''; } catch (e) { select.innerHTML = '<option value="">Could not load cities</option>'; note(e.message, true) } finally { select.disabled = false } }
    async function openEditor(i) {
      state.edit = Number.isInteger(i) ? state.rows[i] : null;
      formNotice.innerHTML = '';
      form.dataset.editId = state.edit?.id || '';
      form.dataset.resource = state.tab;
      const resourceName = state.tab === 'daily-destinations' ? 'Kroo IQ lesson' : state.tab.replace('-', ' ');
      formTitle.textContent = state.tab === 'daily-destinations' && state.edit ? `Edit Lesson ${state.edit.lessonNumber}` : `${state.edit ? 'Edit' : 'Add'} ${resourceName}`;
      const draftTools = document.querySelector('#aiDraftTools');
      form.querySelector('.dialogfoot').prepend(draftTools);
      fields.innerHTML = schemas[state.tab].map(field => fieldHtml(field, state.edit)).join('');
      const isLesson = state.tab === 'daily-destinations';
      draftTools.hidden = !['countries', 'cities', 'sights', 'collections', 'collection-lists', 'daily-destinations'].includes(state.tab);
      draftTools.classList.toggle('lesson-action', isLesson);
      draftTools.classList.toggle('field-action', !isLesson);
      draftTools.querySelector('button').textContent = isLesson ? 'Generate lesson and quiz with AI' : 'Generate description with AI';
      draftTools.querySelector('.include-images').hidden = !isLesson;
      document.querySelector('#includeLessonImages').checked = false;
      document.querySelector('#aiDraftHint').textContent = isLesson ? 'Review questions and add images before saving.' : 'Review and edit the draft before saving.';
      if (isLesson) {
        prepareKrooIqEditor();
        updateLessonCountrySelection();
      } else {
        const target = form.elements.description || form.elements.content || form.elements.detail;
        const targetField = target?.closest('.field');
        if (targetField) {
          const group = document.createElement('div');
          group.className = `field ${targetField.classList.contains('wide') ? 'wide' : ''}`;
          targetField.before(group);
          group.append(targetField, draftTools);
        }
      }
      modal.classList.remove('hidden');
      await renderStates();
    }
    function updateLessonCountrySelection() {
      if (form.dataset.resource !== 'daily-destinations') return;
      const countryId = form.elements.countryId?.value || '';
      const country = state.meta.countries.find(item => item.id === countryId)?.name;
      document.querySelector('#aiDraftTools button').disabled = !country;
      document.querySelector('#aiDraftHint').textContent = country ? `Generate questions about ${country}. Review before saving.` : 'Select a country first.';
    }
    async function generateEditorText(button) {
      if (form.dataset.generating === 'true') return;
      const resource = form.dataset.resource;
      const includeImages = resource === 'daily-destinations' && document.querySelector('#includeLessonImages').checked;
      const countryId = form.elements.countryId?.value || '';
      const country = state.meta.countries.find(item => item.id === countryId)?.name || '';
      const title = form.elements.title?.value.trim() || form.elements.name?.value.trim() || '';
      const name = [title, resource === 'daily-destinations' ? '' : country].filter(Boolean).join(', ');
      if (resource === 'daily-destinations' && !countryId) { formNotice.innerHTML = '<div class="notice error">Select a country first.</div>'; return; }
      if (resource !== 'daily-destinations' && !name) { formNotice.innerHTML = '<div class="notice error">Enter a name or title first.</div>'; return; }
      if (resource === 'daily-destinations' && Array.from({ length: 10 }, (_, index) => form.elements[`q${index + 1}Prompt`]?.value).some(Boolean) && !confirm('Replace the current lesson questions with a new AI draft?')) return;
      await withButtonLoading(button, 'Generating…', async () => {
        const controls = Array.from(form.querySelectorAll('input, select, textarea, button')).map(control => [control, control.disabled]);
        form.dataset.generating = 'true';
        controls.forEach(([control]) => { control.disabled = true; });
        try {
          formNotice.innerHTML = '<div class="notice">Generating a draft. This may take a moment.</div>';
          const result = await call('/admin/api/ai/text', { method: 'POST', body: JSON.stringify({ resource, name: name || country, countryId, isPreview: resource === 'daily-destinations' && form.elements.isPreview.checked }) });
          if (resource === 'daily-destinations') {
            result.questions.forEach((question, index) => {
              const number = index + 1;
              form.elements[`q${number}Information`].value = question.information;
              form.elements[`q${number}Prompt`].value = question.prompt;
              form.elements[`q${number}Answers`].value = question.answers.join('\n');
              form.elements[`q${number}Correct`].value = question.correctAnswer + 1;
              form.elements[`q${number}Explanation`].value = question.explanation;
            });
            fields.querySelector('.question-card')?.setAttribute('open', '');
            if (includeImages) {
              const failed = [];
              for (let index = 0; index < result.questions.length; index++) {
                const number = index + 1;
                const input = form.elements[`q${number}Image`];
                // The previous image belongs to the old question, not this new draft.
                input.value = '';
                normalizedFiles.delete(input);
                input.dataset.current = '';
                input.closest('.image-field').querySelector('.field-preview').innerHTML = '<small>No image selected</small>';
                formNotice.innerHTML = `<div class="notice">Generating image ${number} of ${result.questions.length}…</div>`;
                try { await generateImageForInput(input, resource); }
                catch (error) { failed.push({ number, message: error.message }); }
              }
              formNotice.innerHTML = failed.length
                ? `<div class="notice error">Lesson draft ready. Images for questions ${failed.map(item => item.number).join(', ')} could not be generated. ${esc(failed[0].message)} Retry those images individually before saving.</div>`
                : '<div class="notice">Lesson and images ready. Review each question before saving.</div>';
            } else {
              formNotice.innerHTML = '<div class="notice">Draft ready. Review each question and add its information image before saving.</div>';
            }
          } else {
            const target = form.elements.description || form.elements.content || form.elements.detail;
            target.value = result.description;
            target.focus();
            formNotice.innerHTML = '<div class="notice">Description draft ready. Review it before saving.</div>';
          }
        } catch (error) { formNotice.innerHTML = `<div class="notice error">${esc(error.message)}</div>`; }
        finally {
          delete form.dataset.generating;
          controls.forEach(([control, disabled]) => { control.disabled = disabled; });
        }
      });
    }
    function prepareKrooIqEditor() {
      const preview = form.elements.isPreview;
      if (state.edit) {
        preview.disabled = true;
        preview.closest('label').insertAdjacentHTML('afterend', `<div class="lesson-type-note"><strong>${preview.checked ? 'Public preview · 10 questions' : 'Kroo+ lesson · 5 questions'}</strong><br>Lesson type is fixed after creation. Create a new lesson if you need the other type.</div>`);
      } else {
        preview.addEventListener('change', updateKrooIqQuestionVisibility);
        preview.closest('label').insertAdjacentHTML('afterend', '<div class="lesson-type-note">Choose Public preview only when creating the single Lesson 0. Leave it off for a standard 5-question Kroo+ lesson.</div>');
      }
      for (let number = 1; number <= 10; number++) {
        const controls = ['Information', 'Image', 'Prompt', 'Answers', 'Correct', 'Explanation'].map(suffix => form.elements[`q${number}${suffix}`]);
        const first = controls[0].closest('label');
        const card = document.createElement('details');
        card.className = 'question-card'; card.dataset.question = number; card.open = number === 1;
        card.innerHTML = `<summary>Question ${number}${number > 5 ? ' · Preview only' : ''}</summary><div class="question-fields"></div>`;
        first.before(card);
        controls.forEach(control => card.querySelector('.question-fields').append(control.closest('.field')));
      }
      updateKrooIqQuestionVisibility();
    }
    function updateKrooIqQuestionVisibility() {
      const questionCount = form.elements.isPreview?.checked ? 10 : 5;
      fields.querySelectorAll('.question-card').forEach(card => {
        const visible = Number(card.dataset.question) <= questionCount;
        card.classList.toggle('hidden', !visible);
        card.querySelectorAll('textarea, input[type=number]').forEach(control => { control.required = visible && !control.name.endsWith('Image'); });
      });
    }
    function closeEditor() { if (form.dataset.generating === 'true') return; modal.classList.add('hidden'); form.querySelector('.dialogfoot').prepend(document.querySelector('#aiDraftTools')); form.reset(); fields.replaceChildren(); formNotice.innerHTML = ''; state.edit = null; delete form.dataset.editId; delete form.dataset.resource }
    function aiImageSubject(resource, field) {
      const fieldValue = name => form.elements[name]?.value?.trim() || '';
      const country = state.meta.countries.find(item => item.id === fieldValue('countryId'))?.name || '';
      const city = form.elements.cityId?.value ? form.elements.cityId.selectedOptions[0]?.textContent?.trim() || '' : '';
      if (resource === 'daily-destinations') {
        const number = field.match(/^q(10|[1-9])Image$/)?.[1];
        const subject = fieldValue(`q${number}Information`) || fieldValue(`q${number}Prompt`);
        if (!subject) throw new Error('Enter the question information or prompt before generating its image.');
        return [subject, country].filter(Boolean).join(', ').slice(0, 300);
      }
      const title = fieldValue(resource === 'collections' || resource === 'collection-lists' ? 'title' : 'name');
      if (!title) throw new Error('Enter a name or title before generating an image.');
      return [title, resource === 'collection-lists' ? fieldValue('location') || city : resource === 'sights' ? city : '', country].filter(Boolean).join(', ').slice(0, 300);
    }
    async function generateImageForInput(input, resource) {
      const result = await call('/admin/api/ai/image', { method: 'POST', body: JSON.stringify({ resource, field: input.name, name: aiImageSubject(resource, input.name) }) });
      input.value = '';
      normalizedFiles.delete(input);
      input.dataset.current = result.imageUrl;
      input.closest('.image-field').querySelector('.field-preview').innerHTML = imagePreview(result.imageUrl, 'Generated image');
    }
    async function generateFormImage(button) {
      const input = button.closest('.image-field').querySelector('input[type=file]');
      const resource = form.dataset.resource;
      button.disabled = true;
      button.textContent = 'Generating…';
      try {
        await generateImageForInput(input, resource);
        formNotice.innerHTML = '<div class="notice">Image generated. Save this form to use it.</div>';
      } catch (error) {
        formNotice.innerHTML = `<div class="notice error">${esc(error.message)}</div>`;
      } finally {
        button.disabled = false;
        button.textContent = 'Generate image with AI';
      }
    }
    async function generateAiEditorImage(button) {
      const { batch, item } = aiEditing || {};
      if (!batch) return;
      const editForm = document.querySelector('#aiContentForm');
      const resource = batch.category === 'discover-sights' ? 'sights' : batch.category;
      const field = { countries: 'heroImage', states: 'imageUrl', cities: 'imageUrl', sights: 'image' }[resource];
      if (!field) return;
      button.disabled = true;
      button.textContent = 'Generating…';
      try {
        const result = await call('/admin/api/ai/image', { method: 'POST', body: JSON.stringify({ resource, field, name: [editForm.elements.name.value.trim(), item.location].filter(Boolean).join(', ').slice(0, 300) }) });
        editForm.elements.image.value = result.imageUrl;
        editForm.elements.imageFile.value = '';
        document.querySelector('#aiEditPreview').src = result.imageUrl;
        document.querySelector('#aiEditPreview').hidden = false;
        document.querySelector('#aiEditNotice').textContent = 'Image generated. Save changes to use it.';
      } catch (error) {
        document.querySelector('#aiEditNotice').textContent = error.message;
      } finally {
        button.disabled = false;
        button.textContent = 'Generate image with AI';
      }
    }
    async function normalizeImage(input) { const file = input.files?.[0]; if (!file) return; try { const bitmap = await createImageBitmap(file); const canvas = document.createElement('canvas'); canvas.width = 1200; canvas.height = 800; const context = canvas.getContext('2d'); const transparent = input.name === 'explorerImageUrl'; if (!transparent) { context.fillStyle = '#061f18'; context.fillRect(0, 0, 1200, 800) } const scale = transparent ? Math.min(1200 / bitmap.width, 800 / bitmap.height) : Math.max(1200 / bitmap.width, 800 / bitmap.height), width = bitmap.width * scale, height = bitmap.height * scale; context.drawImage(bitmap, (1200 - width) / 2, (800 - height) / 2, width, height); bitmap.close(); const mime = transparent ? 'image/png' : 'image/jpeg'; const extension = transparent ? 'png' : 'jpg'; const blob = await new Promise(resolve => canvas.toBlob(resolve, mime, .9)); if (!blob) throw new Error('The image could not be resized.'); const preview = input.closest('.image-field')?.querySelector('.field-preview'); if (preview) { const reader = new FileReader(); reader.onload = () => { preview.innerHTML = imagePreview(reader.result, input.closest('.image-field').querySelector('label').textContent) }; reader.readAsDataURL(blob) } normalizedFiles.set(input, new File([blob], `${crypto.randomUUID()}.${extension}`, { type: mime })); note(transparent ? 'Explorer image fitted to a transparent 1200 × 800 canvas.' : 'Image center-cropped to 1200 × 800 pixels.') } catch (error) { input.value = ''; normalizedFiles.delete(input); note(error.message || 'The image could not be resized.', true) } }
    async function uploadImage(el) { const file = normalizedFiles.get(el) || el.files?.[0]; if (!file) return el.dataset.current || ''; const folders = { countries: 'countries', cities: 'cities', sights: 'sights', collections: 'collection', 'collection-lists': 'collection', 'daily-destinations': 'daily-destinations' }, body = new FormData(); body.append('image', file); body.append('folder', folders[form.dataset.resource]); const r = await fetch('/admin/api/images', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${state.key}`, 'X-Admin-Key': state.key }, body }); const type = r.headers.get('content-type') || ''; if (!type.includes('application/json')) throw new Error(`Image upload returned an invalid server response (${r.status}).`); const result = await r.json(); if (!r.ok) { const validation = Object.values(result.errors || {}).flat().join(' '); throw new Error(validation || result.message || 'Image upload failed.') } return result.imageUrl }
    form.onsubmit = async e => { e.preventDefault(); const data = {}, resource = form.dataset.resource, editId = form.dataset.editId; try { for (const f of schemas[resource]) { const el = form.elements[f[0]]; if (!el || el.disabled) continue; const value = f[2] === 'image' ? await uploadImage(el) : f[2] === 'check' ? el.checked : f[2] === 'number' ? Number(el.value || 0) : el.value.trim(); if (f[0] !== 'id' || value !== '') data[f[0]] = value } if (resource === 'daily-destinations') data.options = data.options.split('\n').map(x => x.trim()).filter(Boolean); const path = `/admin/api/${resource}${editId ? '/' + encodeURIComponent(editId) : ''}`; await call(path, { method: editId ? 'PUT' : 'POST', body: JSON.stringify(data) }); closeEditor(); if (resource === 'collections') state.meta = await call('/admin/api/meta'); if (resource === 'cities') delete state.states[data.countryId]; await load(); note(editId ? 'Updated successfully.' : 'Created successfully.') } catch (err) { note(err.message, true) } };
    async function removeRow(i) { const row = state.rows[i]; if (!confirm(`Delete “${row.name || row.title}”? This cannot be undone.`)) return; try { await call(`/admin/api/${state.tab}/${encodeURIComponent(row.id)}`, { method: 'DELETE' }); await load(); note('Deleted successfully.') } catch (e) { note(state.tab === 'cities' ? `Could not delete this city. It may still be used by visits or content. ${e.message}` : e.message, true) } }
    async function showUsStates(button) { try { state.tab = 'us-states'; table.classList.remove('ai-workspace'); summary.style.display = ''; document.querySelector('.page-description').textContent = 'Manage images for US states.'; document.querySelectorAll('.nav button').forEach(x => x.classList.remove('active')); button.classList.add('active'); title.textContent = 'US state images'; document.querySelector('#addButton').style.display = 'none'; summary.textContent = 'Upload images for United States state rows in the app.'; state.rows = await call('/admin/api/us-states'); table.innerHTML = `<table><thead><tr><th>No.</th><th>Image</th><th>State</th><th>Upload</th></tr></thead><tbody>${state.rows.map((row, index) => `<tr><td>${index + 1}</td><td>${row.imageUrl ? imagePreview(row.imageUrl, row.name) : '-'}</td><td>${esc(row.name)}</td><td><input id="state-image-${row.id}" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><button class="primary" onclick="withButtonLoading(this, &quot;Uploading...&quot;, () => saveUsStateImage('${row.id}'))">Upload</button><button onclick="generateUsStateImage(this, ${esc(JSON.stringify(String(row.id)))}, ${esc(JSON.stringify(row.name))})">Generate with AI</button></td></tr>`).join('') || '<tr><td colspan="12" class="empty-state">No records found. Try another filter or add content to get started.</td></tr>'}</tbody></table>`; note('') } catch (e) { note(e.message, true) } }
    async function saveUsStateImage(id) { const input = document.querySelector(`#state-image-${CSS.escape(String(id))}`), file = input?.files?.[0]; if (!file) return note('Choose an image first.', true); try { const body = new FormData(); body.append('image', file); body.append('folder', 'states'); const upload = await fetch('/admin/api/images', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${state.key}`, 'X-Admin-Key': state.key }, body }); const result = await upload.json(); if (!upload.ok) throw new Error(result.message || 'Image upload failed.'); await call(`/admin/api/us-states/${encodeURIComponent(id)}`, { method: 'PUT', body: JSON.stringify({ imageUrl: result.imageUrl }) }); await showUsStates(document.querySelector('.nav button[onclick*="showUsStates"]')); note('State image updated successfully.') } catch (e) { note(e.message, true) } }
    async function generateUsStateImage(button, id, name) {
      button.disabled = true;
      button.textContent = 'Generating…';
      try {
        const result = await call('/admin/api/ai/image', { method: 'POST', body: JSON.stringify({ resource: 'states', field: 'imageUrl', name: `${name}, United States` }) });
        await call(`/admin/api/us-states/${encodeURIComponent(id)}`, { method: 'PUT', body: JSON.stringify({ imageUrl: result.imageUrl }) });
        await showUsStates(document.querySelector('.nav button[onclick*="showUsStates"]'));
        note('State image generated and saved.');
      } catch (error) {
        note(error.message, true);
      } finally {
        button.disabled = false;
        button.textContent = 'Generate with AI';
      }
    }
    document.querySelectorAll('[data-tab]').forEach(b => b.onclick = async () => { document.querySelectorAll('.nav button').forEach(x => x.classList.remove('active')); b.classList.add('active'); state.tab = b.dataset.tab; await load() });
    if (state.key) { document.querySelector('#key').value = state.key; login() }
    function fieldHtml(f, row) {
      const [key, label, type, wide] = f; let value = row?.[key];
      const questionMatch = key.match(/^q(10|[1-9])(Information|Prompt|Image|Answers|Correct|Explanation)$/);
      if (questionMatch) {
        const question = row?.questions?.[Number(questionMatch[1]) - 1] || {};
        value = question[{ Information: 'information', Prompt: 'prompt', Image: 'imageUrl', Answers: 'answers', Correct: 'correctAnswer', Explanation: 'explanation' }[questionMatch[2]]];
        if (questionMatch[2] === 'Answers' && Array.isArray(value)) value = value.join('\n');
        if (questionMatch[2] === 'Correct' && value !== undefined && value !== null) value = Number(value) + 1;
      }
      if (key === 'collectionKindIds') value = row?.collectionKindIds || [];
      if (key === 'isPreview') value = row ? Number(row.lessonNumber) === 0 : !state.rows.some(lesson => Number(lesson.lessonNumber) === 0);
      if (key === 'image') value = row?.image || row?.imageUrl;
      if (key === 'content') value = row?.content || row?.description;
      if (key === 'options' && Array.isArray(value)) value = value.join('\n');
      const cls = `field ${wide ? 'wide' : ''}`, required = wide ? 'required' : '';
      if (type === 'check') return `<label class="check ${wide ? 'wide' : ''}"><input name="${key}" type="checkbox" ${value !== false ? 'checked' : ''}> ${label}</label>`;
      if (type === 'country') { const usedCountries = form.dataset.resource === 'daily-destinations' && !row ? new Set(state.rows.map(lesson => lesson.countryId)) : null; return `<label class="${cls}">${label}<select name="${key}" ${required} onchange="countryChanged()"><option value="">Select…</option>${state.meta.countries.filter(x => !usedCountries?.has(x.id)).map(x => `<option value="${esc(x.id)}" ${x.id === value ? 'selected' : ''}>${esc(x.code + ' · ' + x.name)}</option>`).join('')}</select></label>`; }
      if (type === 'state') return `<label class="${cls}">${label}<select name="${key}" data-value="${esc(value || '')}" onchange="renderCities()"><option value="">Select a country first…</option></select></label>`;
      if (type === 'city') return `<label class="${cls}">${label}<select name="${key}" data-value="${esc(value || '')}" ${required}><option value="">Select a country first…</option></select></label>`;
      if (type === 'kinds') return `<label class="${cls}">${label}<select name="${key}" required multiple size="${Math.min(Math.max(state.meta.collectionKinds.length, 2), 6)}">${state.meta.collectionKinds.map(x => `<option value="${esc(x.id)}" ${value.includes(x.id) ? 'selected' : ''}>${esc(x.title)}</option>`).join('')}</select><small>Choose every collection this item belongs to.</small></label>`;
      if (type === 'access') return `<label class="${cls}">${label}<select name="${key}"><option value="free" ${value !== 'pro' ? 'selected' : ''}>Everyone</option><option value="pro" ${value === 'pro' ? 'selected' : ''}>Kroo+ only</option></select></label>`;
      if (type === 'image') return `<div class="${cls} image-field"><label for="image-${key}">${label}</label><div class="field-preview">${value ? imagePreview(value, label) : '<small>No image selected</small>'}</div><input id="image-${key}" name="${key}" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-current="${esc(value || '')}" onchange="normalizeImage(this)"><small>${key === 'explorerImageUrl' ? 'Fitted to a transparent 1200 × 800 canvas.' : 'Center-cropped to 1200 × 800 pixels.'} Choose a file to preview before saving.</small>${key === 'explorerImageUrl' ? '' : '<button type="button" onclick="generateFormImage(this)">Generate image with AI</button>'}</div>`;
      if (type === 'textarea') return `<label class="${cls}">${label}<textarea name="${key}" ${required}>${esc(value || '')}</textarea></label>`;
      const step = type === 'number' && ['latitude', 'longitude'].includes(key) ? 'step="any"' : '';
      const answerRange = questionMatch?.[2] === 'Correct' ? `min="1" max="${Math.max(1, (row?.questions?.[Number(questionMatch[1]) - 1]?.answers?.length || 4))}"` : '';
      return `<label class="${cls}">${label}<input name="${key}" type="${type}" ${step} ${answerRange} value="${esc(value ?? '')}" ${required} ${key === 'id' && row ? 'disabled' : ''}></label>`;
    }
    form.onsubmit = async e => {
      e.preventDefault();
      if (form.dataset.saving === 'true' || form.dataset.generating === 'true') return;
      form.dataset.saving = 'true';
      const saveButton = form.querySelector('button[type="submit"]');
      const cancelButton = form.querySelector('.dialogfoot button[onclick="closeEditor()"]');
      cancelButton.disabled = true;
      await withButtonLoading(saveButton, 'Saving…', async () => {
      const data = {}, resource = form.dataset.resource, editId = form.dataset.editId;
      try {
        for (const f of schemas[resource]) {
          const el = form.elements[f[0]];
          if (!el || el.disabled) continue;
          const value = f[2] === 'image' ? await uploadImage(el)
            : f[2] === 'check' ? el.checked
            : f[2] === 'kinds' ? Array.from(el.selectedOptions).map(option => option.value)
            : f[2] === 'number' ? Number(el.value || 0) : el.value.trim();
          if (f[0] !== 'id' || value !== '') data[f[0]] = value;
        }
        if (resource === 'daily-destinations') {
          const isPreviewLesson = data.isPreview ?? Number(state.edit?.lessonNumber) === 0;
          data.questions = Array.from({ length: isPreviewLesson ? 10 : 5 }, (_, offset) => offset + 1).map(i => ({
            information: data[`q${i}Information`],
            prompt: data[`q${i}Prompt`],
            imageUrl: data[`q${i}Image`],
            answers: data[`q${i}Answers`].split('\n').map(x => x.trim()).filter(Boolean),
            correctAnswer: data[`q${i}Correct`] - 1,
            explanation: data[`q${i}Explanation`],
          }));
          Object.keys(data).filter(key => /^q(10|[1-9])/.test(key)).forEach(key => delete data[key]);
        }
        const path = `/admin/api/${resource}${editId ? '/' + encodeURIComponent(editId) : ''}`;
        await call(path, { method: editId ? 'PUT' : 'POST', body: JSON.stringify(data) });
        closeEditor();
        if (resource === 'collections') state.meta = await call('/admin/api/meta');
        if (resource === 'cities') delete state.states[data.countryId];
        await load(); note(editId ? 'Updated successfully.' : 'Created successfully.');
      } catch (err) { formNotice.innerHTML = `<div class="notice error">${esc(err.message)}</div>`; }
      });
      cancelButton.disabled = false;
      delete form.dataset.saving;
    };
  </script>
</body>

</html>

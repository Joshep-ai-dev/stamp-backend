<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Stampo Admin</title>
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

    .summarybar select {
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
      width: 48px;
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
      gap: 7px
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
      width: min(760px, 100%);
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

    .cropdialog { width:min(760px,96vw) }
    .cropstage { background:#101716; border:1px solid var(--line); border-radius:10px; overflow:hidden; margin-top:14px; touch-action:none }
    .cropstage canvas { display:block; width:100%; max-height:58vh; cursor:grab }
    .cropstage canvas.dragging { cursor:grabbing }
    .croptools { display:grid; grid-template-columns:1fr 2fr; gap:14px; margin-top:14px; align-items:end }
    .croptools input { padding:0 }

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

    @media(max-width:760px) {
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

      .wide {
        grid-column: auto
      }
    }
  </style>
</head>

<body>
  <section id="login" class="auth">
    <h1>Stampo Admin</h1>
    <p style="color:var(--muted)">Enter the server ADMIN_API_KEY.</p><input id="key" type="password"
      placeholder="Admin API key" autocomplete="current-password"><button class="primary" onclick="login()">Open
      dashboard</button>
    <p id="loginError" style="color:var(--danger)"></p>
  </section>
  <div id="app" class="shell hidden">
    <aside class="side">
      <div class="brand">Stampo Admin</div>
      <nav class="nav"><button data-tab="sights" class="active">Top sights</button><button
          data-tab="collections">Collection kinds</button><button data-tab="collection-lists">Collection list</button><button data-tab="daily-destinations">Daily
          destinations</button><button class="logout" onclick="logout()">Lock</button></nav>
    </aside>
    <main>
      <div class="top">
        <div>
          <h1 id="title">Top sights</h1>
          <div id="summary" style="color:var(--muted);margin-top:5px"></div>
        </div><button class="primary" onclick="openEditor()">+ Add new</button>
      </div>
      <div id="notice"></div>
      <div id="table" class="table"></div>
    </main>
  </div>
  <div id="modal" class="modal hidden">
    <form id="form" class="dialog">
      <h1 id="formTitle">Add</h1>
      <div id="fields" class="grid" style="margin-top:18px"></div>
      <div class="dialogfoot"><button type="button" onclick="closeEditor()">Cancel</button><button class="primary"
          type="submit">Save</button></div>
    </form>
  </div>
  <div id="cropModal" class="modal hidden">
    <div class="dialog cropdialog">
      <h1>Crop image</h1>
      <p style="color:var(--muted);margin-top:5px">Drag the image to position it inside the crop.</p>
      <div class="cropstage"><canvas id="cropCanvas" width="1200" height="800"></canvas></div>
      <div class="croptools">
        <label>Aspect ratio<select id="cropRatio"><option value="1.5">3:2</option><option value="1.7777778">16:9</option><option value="1.3333333">4:3</option><option value="1">1:1</option></select></label>
        <label>Zoom<input id="cropZoom" type="range" min="1" max="3" value="1" step="0.01"></label>
      </div>
      <div class="dialogfoot"><button type="button" onclick="cancelCrop()">Cancel</button><button type="button" class="primary" onclick="applyCrop()">Use crop</button></div>
    </div>
  </div>
  <script>
    const state = { key: sessionStorage.stampoAdminKey || '', tab: 'sights', rows: [], meta: { countries: [], collectionKinds: [] }, cities: {}, filters: { sights: '', 'collection-lists': '' }, edit: null };
    const title = document.querySelector('#title'), summary = document.querySelector('#summary'), table = document.querySelector('#table'), notice = document.querySelector('#notice'), modal = document.querySelector('#modal'), form = document.querySelector('#form'), formTitle = document.querySelector('#formTitle'), fields = document.querySelector('#fields');
    const cropModal = document.querySelector('#cropModal'), cropCanvas = document.querySelector('#cropCanvas'), cropRatio = document.querySelector('#cropRatio'), cropZoom = document.querySelector('#cropZoom'), cropCtx = cropCanvas.getContext('2d');
    const croppedFiles = new WeakMap(); let crop = { input: null, image: null, url: '', x: 0, y: 0, dragging: false, px: 0, py: 0 };
    const schemas = {
      sights: [['name', 'Name', 'text', 1], ['countryId', 'Country', 'country', 1], ['cityId', 'City', 'city', 1], ['image', 'Image', 'image'], ['content', 'Content', 'textarea', 1], ['displayOrder', 'Display order', 'number'], ['isFeatured', 'Shown in lists', 'check'], ['access', 'Access', 'access']],
      collections: [['title', 'Title', 'text', 1], ['id', 'ID (optional)', 'text'], ['imageUrl', 'Image', 'image'], ['detail', 'Detail', 'textarea', 1], ['displayOrder', 'Display order', 'number'], ['isPublished', 'Published', 'check']],
      'collection-lists': [['collectionKindId', 'Collection kind', 'kind', 1], ['title', 'Title', 'text', 1], ['id', 'ID (optional)', 'text'], ['countryId', 'Country', 'country', 1], ['cityId', 'City / location', 'city', 1], ['imageUrl', 'Image', 'image'], ['detail', 'Detail', 'textarea', 1], ['access', 'Access', 'access'], ['displayOrder', 'Display order', 'number']],
      'daily-destinations': [['name', 'Name', 'text', 1], ['id', 'ID (optional)', 'text'], ['countryId', 'Country', 'country', 1], ['cityId', 'City', 'city', 1], ['imageUrl', 'Image', 'image'], ['icon', 'Fallback emoji', 'text'], ['content', 'Lesson content', 'textarea', 1], ['question', 'Question', 'textarea', 1], ['options', 'Answer options (one per line)', 'textarea', 1], ['correctAnswer', 'Correct answer index (starts at 0)', 'number', 1], ['publishDate', 'Publish date (blank = every day)', 'date'], ['displayOrder', 'Display order', 'number'], ['isPublished', 'Published', 'check'], ['unlocked', 'Unlocked for free users', 'check']]
    };
    async function call(path, options = {}) { const r = await fetch(path, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${state.key}`, 'X-Admin-Key': state.key, ...options.headers } }); if (r.status === 204) return null; const type = r.headers.get('content-type') || ''; if (!type.includes('application/json')) throw new Error(`Server returned HTML instead of JSON (${r.status}) for ${path}. Clear the Laravel caches and verify this route is deployed.`); const body = await r.json(); if (!r.ok) throw new Error(body.message || `Request failed (${r.status})`); return body }
    async function login() { state.key = document.querySelector('#key').value.trim(); try { state.meta = await call('/admin/api/meta'); sessionStorage.stampoAdminKey = state.key; document.querySelector('#login').classList.add('hidden'); document.querySelector('#app').classList.remove('hidden'); await load() } catch (e) { document.querySelector('#loginError').textContent = e.message } }
    function logout() { sessionStorage.removeItem('stampoAdminKey'); location.reload() }
    async function load() { try { state.rows = await call(`/admin/api/${state.tab}`); render(); note('') } catch (e) { note(e.message, true) } }
    function esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])) }
    function render() { const names = { 'sights': 'Top sights', 'collections': 'Collection kinds', 'collection-lists': 'Collection list', 'daily-destinations': 'Daily destinations' }; title.textContent = names[state.tab]; const filter = state.filters[state.tab] || ''; const rows = state.rows.map((row, index) => ({ row, index })).filter(({ row }) => state.tab === 'sights' ? !filter || row.countryCode === filter : state.tab === 'collection-lists' ? !filter || row.collectionKindId === filter : true); const options = state.tab === 'sights' ? state.meta.countries.map(x => `<option value="${esc(x.id)}" ${x.id === filter ? 'selected' : ''}>${esc(x.code + ' · ' + x.name)}</option>`).join('') : state.tab === 'collection-lists' ? state.meta.collectionKinds.map(x => `<option value="${esc(x.id)}" ${x.id === filter ? 'selected' : ''}>${esc(x.title)}</option>`).join('') : ''; const label = state.tab === 'sights' ? 'country' : 'collection kind'; summary.innerHTML = `<div class="summarybar"><span>${rows.length}${filter ? ` of ${state.rows.length}` : ''} records</span>${options ? `<select aria-label="Filter by ${label}" onchange="setTableFilter(this.value)"><option value="">All ${label === 'country' ? 'countries' : 'collection kinds'}</option>${options}</select>` : ''}</div>`; const cols = state.tab === 'sights' ? ['image', 'name', 'country', 'city', 'access'] : state.tab === 'collections' ? ['imageUrl', 'title', 'detail'] : state.tab === 'collection-lists' ? ['imageUrl', 'title', 'collectionKind', 'location', 'detail', 'access'] : ['imageUrl', 'name', 'country', 'city', 'publishDate', 'access']; table.innerHTML = `<table><thead><tr>${cols.map(x => `<th>${esc(x)}</th>`).join('')}<th>Actions</th></tr></thead><tbody>${rows.map(({ row: r, index: i }) => `<tr>${cols.map(c => cell(r, c)).join('')}<td><div class="actions"><button onclick="openEditor(${i})">Edit</button><button class="danger" onclick="removeRow(${i})">Delete</button></div></td></tr>`).join('')}</tbody></table>` }
    function setTableFilter(value) { state.filters[state.tab] = value; render() }
    function cell(r, c) { if (c === 'image' || c === 'imageUrl') { const u = r.image || r.imageUrl; return `<td>${u ? `<img src="${esc(u)}" alt="">` : '—'}</td>` } if (c === 'access') return `<td><span class="badge">${r.access === 'pro' || r.isPremium ? 'Kroo+ locked' : 'Unlocked'}</span></td>`; return `<td>${esc(r[c] || '—')}</td>` }
    function note(message, bad = false) { notice.innerHTML = message ? `<div class="notice ${bad ? 'error' : ''}">${esc(message)}</div>` : '' }
    function fieldHtml(f, row) { const [key, label, type, wide] = f; let value = row?.[key]; if (key === 'image') value = row?.image || row?.imageUrl; if (key === 'content') value = row?.content || row?.description; if (key === 'unlocked') value = row ? row.isPremium !== true : true; if (key === 'options' && Array.isArray(value)) value = value.join('\n'); const cls = `field ${wide ? 'wide' : ''}`; if (type === 'check') return `<label class="check ${wide ? 'wide' : ''}"><input name="${key}" type="checkbox" ${value !== false ? 'checked' : ''}> ${label}</label>`; if (type === 'country') return `<label class="${cls}">${label}<select name="${key}" required onchange="renderCities()"><option value="">Select…</option>${state.meta.countries.map(x => `<option value="${esc(x.id)}" ${x.id === value ? 'selected' : ''}>${esc(x.code + ' · ' + x.name)}</option>`).join('')}</select></label>`; if (type === 'city') return `<label class="${cls}">${label}<select name="${key}" data-value="${esc(value || '')}" required><option value="">Select a country first…</option></select></label>`; if (type === 'kind') return `<label class="${cls}">${label}<select name="${key}" required><option value="">Select…</option>${state.meta.collectionKinds.map(x => `<option value="${esc(x.id)}" ${x.id === value ? 'selected' : ''}>${esc(x.title)}</option>`).join('')}</select></label>`; if (type === 'access') return `<label class="${cls}">${label}<select name="${key}" required><option value="free" ${value !== 'pro' ? 'selected' : ''}>Free</option><option value="pro" ${value === 'pro' ? 'selected' : ''}>Kroo+</option></select></label>`; if (type === 'image') return `<label class="${cls}">${label}${value ? `<img src="${esc(value)}" alt="" style="width:120px;height:80px;object-fit:cover;margin:6px 0;border-radius:8px">` : ''}<input name="${key}" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-current="${esc(value || '')}" onchange="openCrop(this)"><small>JPG, PNG, WebP or GIF; maximum 10 MB. Images can be cropped before upload.</small></label>`; if (type === 'textarea') return `<label class="${cls}">${label}<textarea name="${key}" ${f[3] ? 'required' : ''}>${esc(value || '')}</textarea></label>`; return `<label class="${cls}">${label}<input name="${key}" type="${type}" value="${esc(value ?? '')}" ${f[3] ? 'required' : ''} ${key === 'id' && row ? 'disabled' : ''}></label>` }
    async function renderCities() { const country = form.elements.countryId?.value; const select = form.elements.cityId; if (!select) return; const selected = select.dataset.value; if (!country) { select.innerHTML = '<option value="">Select a country first…</option>'; return } select.disabled = true; select.innerHTML = '<option value="">Loading cities…</option>'; try { state.cities[country] ||= await call(`/admin/api/cities?country=${encodeURIComponent(country)}`); select.innerHTML = '<option value="">Select…</option>' + state.cities[country].map(x => `<option value="${esc(x.id)}" ${String(x.id) === String(selected) ? 'selected' : ''}>${esc(x.name)}</option>`).join(''); select.dataset.value = ''; } catch (e) { select.innerHTML = '<option value="">Could not load cities</option>'; note(e.message, true) } finally { select.disabled = false } }
    async function openEditor(i) { state.edit = Number.isInteger(i) ? state.rows[i] : null; form.dataset.editId = state.edit?.id || ''; form.dataset.resource = state.tab; formTitle.textContent = `${state.edit ? 'Edit' : 'Add'} ${state.tab.replace('-', ' ')}`; fields.innerHTML = schemas[state.tab].map(f => fieldHtml(f, state.edit)).join(''); modal.classList.remove('hidden'); await renderCities() }
    function closeEditor() { modal.classList.add('hidden'); state.edit = null; delete form.dataset.editId; delete form.dataset.resource }
    function drawCrop() { if (!crop.image) return; const ratio = Number(cropRatio.value); cropCanvas.width = 1200; cropCanvas.height = Math.round(1200 / ratio); const base = Math.max(cropCanvas.width / crop.image.naturalWidth, cropCanvas.height / crop.image.naturalHeight), scale = base * Number(cropZoom.value), w = crop.image.naturalWidth * scale, h = crop.image.naturalHeight * scale, limitX = Math.max(0, (w - cropCanvas.width) / 2), limitY = Math.max(0, (h - cropCanvas.height) / 2); crop.x = Math.max(-limitX, Math.min(limitX, crop.x)); crop.y = Math.max(-limitY, Math.min(limitY, crop.y)); cropCtx.fillStyle = '#fff'; cropCtx.fillRect(0, 0, cropCanvas.width, cropCanvas.height); cropCtx.drawImage(crop.image, (cropCanvas.width - w) / 2 + crop.x, (cropCanvas.height - h) / 2 + crop.y, w, h) }
    function openCrop(input) { const file = input.files?.[0]; if (!file) return; if (crop.url) URL.revokeObjectURL(crop.url); crop = { input, image: new Image(), url: URL.createObjectURL(file), x: 0, y: 0, dragging: false, px: 0, py: 0 }; crop.image.onload = () => { cropZoom.value = 1; cropRatio.value = '1.5'; cropModal.classList.remove('hidden'); drawCrop() }; crop.image.onerror = () => { input.value = ''; note('This image could not be opened.', true) }; crop.image.src = crop.url }
    function cancelCrop() { if (crop.input) crop.input.value = ''; if (crop.url) URL.revokeObjectURL(crop.url); cropModal.classList.add('hidden'); crop = { input: null, image: null, url: '', x: 0, y: 0, dragging: false, px: 0, py: 0 } }
    function applyCrop() { if (!crop.input) return; const input = crop.input; cropCanvas.toBlob(blob => { if (!blob) return note('The crop could not be created.', true); croppedFiles.set(input, new File([blob], `${crypto.randomUUID()}.jpg`, { type: 'image/jpeg' })); input.dataset.cropped = '1'; if (crop.url) URL.revokeObjectURL(crop.url); cropModal.classList.add('hidden'); crop = { input: null, image: null, url: '', x: 0, y: 0, dragging: false, px: 0, py: 0 } }, 'image/jpeg', .9) }
    cropRatio.onchange = () => { crop.x = crop.y = 0; drawCrop() }; cropZoom.oninput = drawCrop;
    cropCanvas.onpointerdown = e => { crop.dragging = true; crop.px = e.clientX; crop.py = e.clientY; cropCanvas.classList.add('dragging'); cropCanvas.setPointerCapture(e.pointerId) };
    cropCanvas.onpointermove = e => { if (!crop.dragging) return; const rect = cropCanvas.getBoundingClientRect(); crop.x += (e.clientX - crop.px) * cropCanvas.width / rect.width; crop.y += (e.clientY - crop.py) * cropCanvas.height / rect.height; crop.px = e.clientX; crop.py = e.clientY; drawCrop() };
    cropCanvas.onpointerup = cropCanvas.onpointercancel = e => { crop.dragging = false; cropCanvas.classList.remove('dragging'); if (cropCanvas.hasPointerCapture(e.pointerId)) cropCanvas.releasePointerCapture(e.pointerId) };
    async function uploadImage(el) { const file = croppedFiles.get(el) || el.files?.[0]; if (!file) return el.dataset.current || ''; const folders = { sights: 'sights', collections: 'collection', 'collection-lists': 'collection', 'daily-destinations': 'daily-destinations' }, body = new FormData(); body.append('image', file); body.append('folder', folders[form.dataset.resource]); const r = await fetch('/admin/api/images', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${state.key}`, 'X-Admin-Key': state.key }, body }); const type = r.headers.get('content-type') || ''; if (!type.includes('application/json')) throw new Error(`Image upload returned an invalid server response (${r.status}).`); const result = await r.json(); if (!r.ok) { const validation = Object.values(result.errors || {}).flat().join(' '); throw new Error(validation || result.message || 'Image upload failed.') } return result.imageUrl }
    form.onsubmit = async e => { e.preventDefault(); const data = {}, resource = form.dataset.resource, editId = form.dataset.editId; try { for (const f of schemas[resource]) { const el = form.elements[f[0]]; if (!el || el.disabled) continue; data[f[0]] = f[2] === 'image' ? await uploadImage(el) : f[2] === 'check' ? el.checked : f[2] === 'number' ? Number(el.value || 0) : el.value.trim() } if (resource === 'daily-destinations') data.options = data.options.split('\n').map(x => x.trim()).filter(Boolean); const path = `/admin/api/${resource}${editId ? '/' + encodeURIComponent(editId) : ''}`; await call(path, { method: editId ? 'PUT' : 'POST', body: JSON.stringify(data) }); closeEditor(); if (resource === 'collections') state.meta = await call('/admin/api/meta'); await load(); note(editId ? 'Updated successfully.' : 'Created successfully.') } catch (err) { note(err.message, true) } };
    async function removeRow(i) { const row = state.rows[i]; if (!confirm(`Delete “${row.name || row.title}”? This cannot be undone.`)) return; try { await call(`/admin/api/${state.tab}/${encodeURIComponent(row.id)}`, { method: 'DELETE' }); await load(); note('Deleted successfully.') } catch (e) { note(e.message, true) } }
    document.querySelectorAll('[data-tab]').forEach(b => b.onclick = async () => { document.querySelectorAll('[data-tab]').forEach(x => x.classList.remove('active')); b.classList.add('active'); state.tab = b.dataset.tab; await load() });
    if (state.key) { document.querySelector('#key').value = state.key; login() }
  </script>
</body>

</html>

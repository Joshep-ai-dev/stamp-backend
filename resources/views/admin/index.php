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

    .city-search-row input {
      flex: 1;
      min-width: 220px
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
      width: 120px;
      height: 80px;
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
    <h1>Kroo Admin</h1>
    <p style="color:var(--muted)">Enter the server ADMIN_API_KEY.</p><input id="key" type="password"
      placeholder="Admin API key" autocomplete="current-password"><button class="primary" onclick="login()">Open
      dashboard</button>
    <p id="loginError" style="color:var(--danger)"></p>
  </section>
  <div id="app" class="shell hidden">
    <aside class="side">
      <div class="brand">Kroo Admin</div>
      <nav class="nav"><button data-tab="countries" class="active">Country heroes</button><button data-tab="cities">Cities</button><button data-tab="sights">Top sights</button><button
          data-tab="collections">Collection kinds</button><button data-tab="collection-lists">Collection list</button><button data-tab="daily-destinations">Daily
          destinations</button><button class="logout" onclick="logout()">Lock</button></nav>
    </aside>
    <main>
      <div class="top">
        <h1 id="title">Country hero images</h1>
        <button id="addButton" class="primary" onclick="openEditor()">+ Add new</button>
      </div>
      <div id="summary" style="color:var(--muted)"></div>
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
  <script>
    const state = { key: sessionStorage.stampoAdminKey || '', tab: 'countries', rows: [], meta: { countries: [], collectionKinds: [] }, states: {}, cities: {}, filters: { sights: '', 'collection-lists': '', cities: '' }, paging: { currentPage: 1, lastPage: 1, perPage: 50, total: 0 }, edit: null };
    const title = document.querySelector('#title'), summary = document.querySelector('#summary'), table = document.querySelector('#table'), notice = document.querySelector('#notice'), modal = document.querySelector('#modal'), form = document.querySelector('#form'), formTitle = document.querySelector('#formTitle'), fields = document.querySelector('#fields');
    const normalizedFiles = new WeakMap();
    const schemas = {
      countries: [['heroImage', 'Country hero image', 'image', 1]],
      cities: [['name', 'Name', 'text', 1], ['id', 'City ID (optional)', 'text'], ['countryId', 'Country', 'country', 1], ['state', 'State / region', 'state-entry'], ['population', 'Population', 'number'], ['latitude', 'Latitude', 'number'], ['longitude', 'Longitude', 'number'], ['imageUrl', 'Image', 'image']],
      sights: [['name', 'Name', 'text', 1], ['countryId', 'Country', 'country', 1], ['state', 'State / region', 'state', 1], ['cityId', 'City', 'city', 1], ['image', 'Image', 'image'], ['content', 'Content', 'textarea', 1], ['isFeatured', 'Shown in lists', 'check']],
      collections: [['title', 'Title', 'text', 1], ['id', 'ID (optional)', 'text'], ['imageUrl', 'Image', 'image'], ['detail', 'Detail', 'textarea', 1], ['isPublished', 'Published', 'check']],
      'collection-lists': [['collectionKindId', 'Collection kind', 'kind', 1], ['title', 'Title', 'text', 1], ['id', 'ID (optional)', 'text'], ['countryId', 'Country', 'country', 1], ['state', 'State / region', 'state', 1], ['cityId', 'City / location', 'city', 1], ['imageUrl', 'Image', 'image'], ['detail', 'Detail', 'textarea', 1], ['access', 'Access', 'access']],
      'daily-destinations': [['name', 'Name', 'text', 1], ['id', 'ID (optional)', 'text'], ['countryId', 'Country', 'country', 1], ['state', 'State / region', 'state', 1], ['cityId', 'City', 'city', 1], ['imageUrl', 'Image', 'image'], ['icon', 'Fallback emoji', 'text'], ['content', 'Lesson content', 'textarea', 1], ['question', 'Question', 'textarea', 1], ['options', 'Answer options (one per line)', 'textarea'], ['correctAnswer', 'Correct answer index (starts at 0)', 'number', 1], ['publishDate', 'Publish date (blank = every day)', 'date'], ['isPublished', 'Published', 'check']]
    };
    async function call(path, options = {}) { let r; try { r = await fetch(path, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${state.key}`, 'X-Admin-Key': state.key, ...options.headers } }) } catch (error) { throw new Error(`Could not reach the server for ${path}. Check the connection and try again.`) } if (r.status === 204) return null; const type = r.headers.get('content-type') || ''; if (!type.includes('application/json')) throw new Error(`Server returned HTML instead of JSON (${r.status}) for ${path}. Clear the Laravel caches and verify this route is deployed.`); const body = await r.json(); if (!r.ok) throw new Error(body.message || `Request failed (${r.status})`); return body }
    async function login() { state.key = document.querySelector('#key').value.trim(); try { state.meta = await call('/admin/api/meta'); sessionStorage.stampoAdminKey = state.key; document.querySelector('#login').classList.add('hidden'); document.querySelector('#app').classList.remove('hidden'); await load() } catch (e) { document.querySelector('#loginError').textContent = e.message } }
    function logout() { sessionStorage.removeItem('stampoAdminKey'); location.reload() }
    async function load() { try { const cityParams = state.tab === 'cities' ? `?page=${state.paging.currentPage}&per_page=${state.paging.perPage}&query=${encodeURIComponent(state.filters.cities || '')}` : ''; const result = await call(`/admin/api/${state.tab}${cityParams}`); if (state.tab === 'cities') { state.rows = result.data; state.paging = result.meta } else state.rows = result; render(); note('') } catch (e) { note(e.message, true) } }
    function esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])) }
    function render() { const names = { 'countries': 'Country hero images', 'cities': 'Cities', 'sights': 'Top sights', 'collections': 'Collection kinds', 'collection-lists': 'Collection list', 'daily-destinations': 'Daily destinations' }; title.textContent = names[state.tab]; document.querySelector('#addButton').style.display = ['countries', 'cities'].includes(state.tab) ? 'none' : ''; const filter = state.filters[state.tab] || ''; const rows = state.rows.map((row, index) => ({ row, index })).filter(({ row }) => state.tab === 'sights' ? !filter || row.countryCode === filter : state.tab === 'collection-lists' ? !filter || row.collectionKindId === filter : true); const options = state.tab === 'sights' ? state.meta.countries.map(x => `<option value="${esc(x.id)}" ${x.id === filter ? 'selected' : ''}>${esc(x.code + ' · ' + x.name)}</option>`).join('') : state.tab === 'collection-lists' ? state.meta.collectionKinds.map(x => `<option value="${esc(x.id)}" ${x.id === filter ? 'selected' : ''}>${esc(x.title)}</option>`).join('') : ''; const label = state.tab === 'sights' ? 'country' : 'collection kind'; const cityTools = state.tab === 'cities' ? `<div class="city-tools"><div class="city-search-row"><input id="citySearch" type="search" value="${esc(filter)}" placeholder="Search city, state, or country" aria-label="Search cities" onkeydown="if(event.key === 'Enter'){event.preventDefault();submitCitySearch()}"><button class="primary" onclick="submitCitySearch()">Search</button>${filter ? `<button onclick="clearCitySearch()">Clear</button>` : ''}<button class="primary" onclick="openEditor()">+ Add new</button></div><div class="city-pagination"><span>${state.paging.total} records</span><button ${state.paging.currentPage <= 1 ? 'disabled' : ''} onclick="changeCityPage(-1)">Previous</button><span>Page ${state.paging.currentPage} of ${state.paging.lastPage}</span><button ${state.paging.currentPage >= state.paging.lastPage ? 'disabled' : ''} onclick="changeCityPage(1)">Next</button></div></div>` : ''; summary.innerHTML = `<div class="summarybar">${cityTools || `<span>${rows.length}${filter ? ` of ${state.rows.length}` : ''} records</span>${options ? `<select aria-label="Filter by ${label}" onchange="setTableFilter(this.value)"><option value="">All ${label === 'country' ? 'countries' : 'collection kinds'}</option>${options}</select>` : ''}`}</div>`; const cols = state.tab === 'countries' ? ['heroImage', 'code', 'name'] : state.tab === 'cities' ? ['imageUrl', 'name', 'country', 'state', 'population', 'latitude', 'longitude'] : state.tab === 'sights' ? ['image', 'name', 'country', 'state', 'city'] : state.tab === 'collections' ? ['imageUrl', 'title', 'detail'] : state.tab === 'collection-lists' ? ['imageUrl', 'title', 'collectionKind', 'location', 'detail', 'access'] : ['imageUrl', 'name', 'country', 'state', 'city', 'publishDate']; const numberOffset = state.tab === 'cities' ? (state.paging.currentPage - 1) * state.paging.perPage : 0; table.innerHTML = `<table><thead><tr><th>No.</th>${cols.map(x => `<th>${esc(x)}</th>`).join('')}<th>Actions</th></tr></thead><tbody>${rows.map(({ row: r, index: i }, displayIndex) => `<tr><td>${numberOffset + displayIndex + 1}</td>${cols.map(c => cell(r, c)).join('')}<td><div class="actions"><button onclick="openEditor(${i})">Edit</button>${state.tab === 'countries' ? '' : `<button class="danger" onclick="removeRow(${i})">Delete</button>`}</div></td></tr>`).join('')}</tbody></table>` }
    function setTableFilter(value) { state.filters[state.tab] = value; render() }
    async function submitCitySearch() { state.filters.cities = document.querySelector('#citySearch')?.value.trim() || ''; state.paging.currentPage = 1; await load() }
    async function clearCitySearch() { state.filters.cities = ''; state.paging.currentPage = 1; await load() }
    async function changeCityPage(offset) { state.paging.currentPage += offset; await load() }
    async function countryChanged() { const region = form.elements.state; if (region) { region.value = ''; region.dataset.value = '' } await renderStates() }
    function cell(r, c) { if (c === 'image' || c === 'imageUrl' || c === 'heroImage') { const u = r.heroImage || r.image || r.imageUrl; return `<td>${u ? `<img src="${esc(u)}" alt="">` : '—'}</td>` } if (c === 'access') return `<td><span class="badge">${r.access === 'pro' || r.isPremium ? 'Kroo+ locked' : 'Unlocked'}</span></td>`; return `<td>${esc(r[c] || '—')}</td>` }
    function note(message, bad = false) { notice.innerHTML = message ? `<div class="notice ${bad ? 'error' : ''}">${esc(message)}</div>` : '' }
    function fieldHtml(f, row) { const [key, label, type, wide] = f; let value = row?.[key]; if (key === 'image') value = row?.image || row?.imageUrl; if (key === 'content') value = row?.content || row?.description; if (key === 'options' && Array.isArray(value)) value = value.join('\n'); const cls = `field ${wide ? 'wide' : ''}`; if (type === 'check') return `<label class="check ${wide ? 'wide' : ''}"><input name="${key}" type="checkbox" ${value !== false ? 'checked' : ''}> ${label}</label>`; if (type === 'country') return `<label class="${cls}">${label}<select name="${key}" required onchange="countryChanged()"><option value="">Select…</option>${state.meta.countries.map(x => `<option value="${esc(x.id)}" ${x.id === value ? 'selected' : ''}>${esc(x.code + ' · ' + x.name)}</option>`).join('')}</select></label>`; if (type === 'state') return `<label class="${cls}">${label}<select name="${key}" data-value="${esc(value || '')}" onchange="renderCities()"><option value="">Select a country first…</option></select></label>`; if (type === 'state-entry') return `<label class="${cls}">${label}<input name="${key}" list="city-state-options" value="${esc(value || '')}" placeholder="Select or type a new state"><datalist id="city-state-options"></datalist><small>Select an existing state or type a new one.</small></label>`; if (type === 'city') return `<label class="${cls}">${label}<select name="${key}" data-value="${esc(value || '')}" required><option value="">Select a state first…</option></select></label>`; if (type === 'kind') return `<label class="${cls}">${label}<select name="${key}" required><option value="">Select…</option>${state.meta.collectionKinds.map(x => `<option value="${esc(x.id)}" ${x.id === value ? 'selected' : ''}>${esc(x.title)}</option>`).join('')}</select></label>`; if (type === 'access') return `<label class="${cls}">${label}<select name="${key}" required><option value="free" ${value !== 'pro' ? 'selected' : ''}>Free</option><option value="pro" ${value === 'pro' ? 'selected' : ''}>Kroo+</option></select></label>`; if (type === 'image') return `<label class="${cls}">${label}${value ? `<img src="${esc(value)}" alt="" style="width:120px;height:80px;object-fit:cover;margin:6px 0;border-radius:8px">` : ''}<input name="${key}" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-current="${esc(value || '')}" onchange="normalizeImage(this)"><small>Images are automatically resized to 1200 × 800 pixels.</small></label>`; if (type === 'textarea') return `<label class="${cls}">${label}<textarea name="${key}" ${f[3] ? 'required' : ''}>${esc(value || '')}</textarea></label>`; const step = type === 'number' && ['latitude', 'longitude'].includes(key) ? 'step="any"' : ''; return `<label class="${cls}">${label}<input name="${key}" type="${type}" ${step} value="${esc(value ?? '')}" ${f[3] ? 'required' : ''} ${key === 'id' && row ? 'disabled' : ''}></label>` }
    async function renderStates() { const country = form.elements.countryId?.value; const select = form.elements.state; if (!select) return renderCities(); const selected = select.dataset.value; const list = document.querySelector('#city-state-options'); if (select.tagName !== 'SELECT') { if (!country) { if (list) list.innerHTML = ''; return } try { state.states[country] ||= await call(`/admin/api/states?country=${encodeURIComponent(country)}`); if (list) list.innerHTML = state.states[country].map(x => `<option value="${esc(x)}"></option>`).join('') } catch (e) { note(e.message, true) } return } if (!country) { select.innerHTML = '<option value="">Select a country first…</option>'; return renderCities() } select.disabled = true; select.innerHTML = '<option value="">Loading states…</option>'; try { state.states[country] ||= await call(`/admin/api/states?country=${encodeURIComponent(country)}`); select.innerHTML = '<option value="">All / no state</option>' + state.states[country].map(x => `<option value="${esc(x)}" ${x === selected ? 'selected' : ''}>${esc(x)}</option>`).join(''); select.dataset.value = ''; } catch (e) { select.innerHTML = '<option value="">Could not load states</option>'; note(e.message, true) } finally { select.disabled = false } await renderCities() }
    async function renderCities() { const country = form.elements.countryId?.value; const region = form.elements.state?.value || ''; const select = form.elements.cityId; if (!select) return; const selected = select.dataset.value; if (!country) { select.innerHTML = '<option value="">Select a country first…</option>'; return } const key = `${country}:${region}`; select.disabled = true; select.innerHTML = '<option value="">Loading cities…</option>'; try { state.cities[key] ||= await call(`/admin/api/cities?country=${encodeURIComponent(country)}&state=${encodeURIComponent(region)}`); select.innerHTML = '<option value="">Select…</option>' + state.cities[key].map(x => `<option value="${esc(x.id)}" ${String(x.id) === String(selected) ? 'selected' : ''}>${esc(x.name)}</option>`).join(''); select.dataset.value = ''; } catch (e) { select.innerHTML = '<option value="">Could not load cities</option>'; note(e.message, true) } finally { select.disabled = false } }
    async function openEditor(i) { state.edit = Number.isInteger(i) ? state.rows[i] : null; form.dataset.editId = state.edit?.id || ''; form.dataset.resource = state.tab; formTitle.textContent = `${state.edit ? 'Edit' : 'Add'} ${state.tab.replace('-', ' ')}`; fields.innerHTML = schemas[state.tab].map(f => fieldHtml(f, state.edit)).join(''); modal.classList.remove('hidden'); await renderStates() }
    function closeEditor() { modal.classList.add('hidden'); state.edit = null; delete form.dataset.editId; delete form.dataset.resource }
    async function normalizeImage(input) { const file = input.files?.[0]; if (!file) return; try { const bitmap = await createImageBitmap(file); const canvas = document.createElement('canvas'); canvas.width = 1200; canvas.height = 800; const context = canvas.getContext('2d'); context.fillStyle = '#061f18'; context.fillRect(0, 0, 1200, 800); const scale = Math.max(1200 / bitmap.width, 800 / bitmap.height), width = bitmap.width * scale, height = bitmap.height * scale; context.drawImage(bitmap, (1200 - width) / 2, (800 - height) / 2, width, height); bitmap.close(); const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .9)); if (!blob) throw new Error('The image could not be resized.'); normalizedFiles.set(input, new File([blob], `${crypto.randomUUID()}.jpg`, { type: 'image/jpeg' })); note('Image center-cropped to 1200 × 800 pixels.') } catch (error) { input.value = ''; note(error.message || 'The image could not be resized.', true) } }
    async function uploadImage(el) { const file = normalizedFiles.get(el) || el.files?.[0]; if (!file) return el.dataset.current || ''; const folders = { countries: 'countries', cities: 'cities', sights: 'sights', collections: 'collection', 'collection-lists': 'collection', 'daily-destinations': 'daily-destinations' }, body = new FormData(); body.append('image', file); body.append('folder', folders[form.dataset.resource]); const r = await fetch('/admin/api/images', { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${state.key}`, 'X-Admin-Key': state.key }, body }); const type = r.headers.get('content-type') || ''; if (!type.includes('application/json')) throw new Error(`Image upload returned an invalid server response (${r.status}).`); const result = await r.json(); if (!r.ok) { const validation = Object.values(result.errors || {}).flat().join(' '); throw new Error(validation || result.message || 'Image upload failed.') } return result.imageUrl }
    form.onsubmit = async e => { e.preventDefault(); const data = {}, resource = form.dataset.resource, editId = form.dataset.editId; try { for (const f of schemas[resource]) { const el = form.elements[f[0]]; if (!el || el.disabled) continue; data[f[0]] = f[2] === 'image' ? await uploadImage(el) : f[2] === 'check' ? el.checked : f[2] === 'number' ? Number(el.value || 0) : el.value.trim() } if (resource === 'daily-destinations') data.options = data.options.split('\n').map(x => x.trim()).filter(Boolean); const path = `/admin/api/${resource}${editId ? '/' + encodeURIComponent(editId) : ''}`; await call(path, { method: editId ? 'PUT' : 'POST', body: JSON.stringify(data) }); closeEditor(); if (resource === 'collections') state.meta = await call('/admin/api/meta'); if (resource === 'cities') delete state.states[data.countryId]; await load(); note(editId ? 'Updated successfully.' : 'Created successfully.') } catch (err) { note(err.message, true) } };
    async function removeRow(i) { const row = state.rows[i]; if (!confirm(`Delete “${row.name || row.title}”? This cannot be undone.`)) return; try { await call(`/admin/api/${state.tab}/${encodeURIComponent(row.id)}`, { method: 'DELETE' }); await load(); note('Deleted successfully.') } catch (e) { note(state.tab === 'cities' ? `Could not delete this city. It may still be used by visits or content. ${e.message}` : e.message, true) } }
    document.querySelectorAll('[data-tab]').forEach(b => b.onclick = async () => { document.querySelectorAll('[data-tab]').forEach(x => x.classList.remove('active')); b.classList.add('active'); state.tab = b.dataset.tab; await load() });
    if (state.key) { document.querySelector('#key').value = state.key; login() }
  </script>
</body>

</html>

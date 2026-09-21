if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('sw.js'));
}

document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm || 'Pokračovat?')) event.preventDefault();
  });
});

const themeButton = document.querySelector('[data-theme-toggle]');
const applyTheme = (isDark) => {
  document.documentElement.dataset.theme = isDark ? 'dark' : '';
  if (!isDark) delete document.documentElement.dataset.theme;
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', isDark ? '#152018' : '#315d3a');
  if (themeButton) {
    themeButton.setAttribute('aria-pressed', String(isDark));
    themeButton.title = isDark ? 'Přepnout na světlý režim' : 'Přepnout na tmavý režim';
    themeButton.querySelector('[data-theme-icon]').textContent = isDark ? '☀' : '☾';
    themeButton.querySelector('[data-theme-label]').textContent = isDark ? 'Světlý režim' : 'Tmavý režim';
  }
};
if (themeButton) {
  applyTheme(document.documentElement.dataset.theme === 'dark');
  themeButton.addEventListener('click', () => {
    const isDark = document.documentElement.dataset.theme !== 'dark';
    applyTheme(isDark);
    try { localStorage.setItem('seminka-theme', isDark ? 'dark' : 'light'); } catch (_) {}
  });
}

const categorySelect = document.querySelector('[data-category-select]');
const subcategoryField = document.querySelector('[data-vegetable-subcategory]');
if (categorySelect && subcategoryField) {
  const subcategorySelect = subcategoryField.querySelector('select');
  const refreshSubcategory = () => {
    const show = categorySelect.value === categorySelect.dataset.vegetableCategoryId;
    subcategoryField.classList.toggle('is-hidden', !show);
    subcategorySelect.disabled = !show;
    if (!show) subcategorySelect.value = '';
  };
  categorySelect.addEventListener('change', refreshSubcategory);
  refreshSubcategory();
}

document.querySelectorAll('[data-sowing-toggle]').forEach((toggle) => {
  const fields = document.querySelector('[data-sowing-fields="' + toggle.dataset.sowingToggle + '"]');
  if (!fields) return;
  const refreshSowingFields = () => {
    fields.classList.toggle('is-hidden', !toggle.checked);
    fields.querySelectorAll('select').forEach((select) => { select.disabled = !toggle.checked; });
  };
  toggle.addEventListener('change', refreshSowingFields);
  refreshSowingFields();
});

const scanButton = document.querySelector('[data-ean-scan]');
const searchButton = document.querySelector('[data-ean-search]');
if (searchButton) searchButton.addEventListener('click', () => {
  const ean = document.querySelector('#ean')?.value.trim();
  if (!ean) return window.alert('Nejdřív napiš nebo naskenuj EAN kód.');
  window.open('https://www.google.com/search?q=' + encodeURIComponent(ean), '_blank', 'noopener');
});
if (scanButton) scanButton.addEventListener('click', async () => {
  const input = document.querySelector('#ean');
  if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) return window.alert('Tento prohlížeč neumí načíst čárový kód kamerou. EAN můžeš opsat ručně.');
  const overlay = document.createElement('div');
  overlay.className = 'scanner-overlay';
  overlay.innerHTML = '<div class="scanner-card"><button type="button" class="text-button scanner-close">Zavřít</button><p>Namiř kameru na čárový kód na sáčku.</p><video playsinline autoplay></video></div>';
  document.body.appendChild(overlay);
  const video = overlay.querySelector('video'); let stream; let stopped = false;
  const stop = () => { stopped = true; stream?.getTracks().forEach((track) => track.stop()); overlay.remove(); };
  overlay.querySelector('.scanner-close').addEventListener('click', stop);
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }); video.srcObject = stream;
    const detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e'] });
    const scan = async () => { if (stopped) return; try { const codes = await detector.detect(video); if (codes[0]?.rawValue) { input.value = codes[0].rawValue; stop(); return; } } catch (_) {} requestAnimationFrame(scan); };
    video.addEventListener('playing', scan, { once: true });
  } catch (_) { stop(); window.alert('Nepodařilo se otevřít kameru. Zkontroluj povolení fotoaparátu.'); }
});

const state = {
  countries: [],
  lang: 'en',
  filteredCountry: null
};

const translations = {
  en: {
    heading: 'Türkiye Visa Checker',
    subheading: 'Select your country to see visa requirements, e-Visa options, and stay limits.',
    searchPlaceholder: 'Select your country',
    lastUpdated: 'Last updated',
    duration: 'Duration',
    source: 'Source',
    visaTypes: {
      no_visa: 'No visa required',
      e_visa: 'e-Visa available',
      consular: 'Visa required'
    },
    labels: {
      language: 'Language',
      share: 'Share',
      copyLink: 'Copy link',
      whatsapp: 'Share on WhatsApp',
      notFound: 'No visa information found for this country yet.',
      selectPrompt: 'Start typing to search for your country...'
    },
    alerts: {
      copied: 'Shareable link copied to clipboard.',
      copyFailed: 'Unable to copy. Please copy the link manually.'
    }
  },
  tr: {
    heading: 'Türkiye Vize Sorgulayıcı',
    subheading: 'Ülkenizi seçerek vize gereksinimlerini, e-Vize seçeneklerini ve kalış sürelerini öğrenin.',
    searchPlaceholder: 'Ülkenizi seçin',
    lastUpdated: 'Son güncelleme',
    duration: 'Kalış süresi',
    source: 'Kaynak',
    visaTypes: {
      no_visa: 'Vize gerekmiyor',
      e_visa: 'e-Vize mevcut',
      consular: 'Vize gerekli'
    },
    labels: {
      language: 'Dil',
      share: 'Paylaş',
      copyLink: 'Bağlantıyı kopyala',
      whatsapp: 'WhatsApp ile paylaş',
      notFound: 'Bu ülkeye ait vize bilgisi henüz eklenmedi.',
      selectPrompt: 'Ülkenizi aramak için yazmaya başlayın...'
    },
    alerts: {
      copied: 'Paylaşım bağlantısı panoya kopyalandı.',
      copyFailed: 'Kopyalanamadı. Lütfen bağlantıyı elle kopyalayın.'
    }
  }
  ,
  de: {
    heading: 'Türkei Visa Checker',
    subheading: 'Wählen Sie Ihr Land, um Visabestimmungen, e‑Visa‑Optionen und Aufenthaltsdauer zu sehen.',
    searchPlaceholder: 'Wählen Sie Ihr Land',
    lastUpdated: 'Zuletzt aktualisiert',
    duration: 'Aufenthaltsdauer',
    source: 'Quelle',
    visaTypes: {
      no_visa: 'Kein Visum erforderlich',
      e_visa: 'e‑Visum verfügbar',
      consular: 'Visum erforderlich'
    },
    labels: {
      language: 'Sprache',
      share: 'Teilen',
      copyLink: 'Link kopieren',
      whatsapp: 'Auf WhatsApp teilen',
      notFound: 'Für dieses Land liegen noch keine Visa‑Informationen vor.',
      selectPrompt: 'Geben Sie den Ländernamen ein, um zu suchen...'
    },
    alerts: {
      copied: 'Teilen‑Link in die Zwischenablage kopiert.',
      copyFailed: 'Kopieren nicht möglich. Bitte kopieren Sie den Link manuell.'
    }
  }
};

const badgeClassMap = {
  no_visa: 'badge badge-no_visa',
  e_visa: 'badge badge-e_visa',
  consular: 'badge badge-consular'
};

document.addEventListener('DOMContentLoaded', () => {
  console.log('🔧 Visa App initialized');
  initLanguage();
  loadCountries().then(() => {
    populateDatalist();
    handleQueryParams();
  });
  attachEventListeners();
});

function initLanguage() {
  const saved = localStorage.getItem('visa_app_lang');
  const paramLang = new URLSearchParams(window.location.search).get('lang');
  state.lang = ['en', 'tr', 'de'].includes(paramLang) ? paramLang : (saved || 'en');
  console.log('💬 Language set to:', state.lang);
  updateLanguageToggle();
  renderTranslations();
}

function updateLanguageToggle() {
  const langBtns = document.querySelectorAll('[data-lang]');
  if (!langBtns || langBtns.length === 0) return;
  langBtns.forEach(btn => {
    btn.classList.toggle('bg-slate-900/80', btn.dataset.lang === state.lang);
    btn.classList.toggle('text-white', btn.dataset.lang === state.lang);
    btn.classList.toggle('bg-transparent', btn.dataset.lang !== state.lang);
  });
}

function renderTranslations() {
  document.querySelectorAll('[data-i18n]').forEach(node => {
    const keys = node.dataset.i18n.split('.');
    const value = keys.reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : null), translations[state.lang]);
    if (value !== null) {
      node.textContent = value;
    }
  });
  const searchInput = document.getElementById('countryInput');
  if (searchInput) {
    searchInput.placeholder = translations[state.lang].searchPlaceholder;
  }
}

async function loadCountries() {
  try {
    if (Array.isArray(window.__VISA_DATA__) && window.__VISA_DATA__.length > 0) {
      state.countries = window.__VISA_DATA__.sort((a, b) => a.country.localeCompare(b.country, 'en', { sensitivity: 'base' }));
      return;
    }
    const response = await fetch(`data/data.json?_=${Date.now()}`);
    if (!response.ok) throw new Error('Failed to load data');
    const countries = await response.json();
    state.countries = countries.sort((a, b) => a.country.localeCompare(b.country, 'en', { sensitivity: 'base' }));
  } catch (err) {
    console.error(err);
    showFeedback(translations[state.lang].labels.notFound, 'error');
  }
}

function populateDatalist() {
  const datalist = document.getElementById('countries');
  if (!datalist) return;
  datalist.innerHTML = '';
  state.countries.forEach(item => {
    const option = document.createElement('option');
    option.value = item.country;
    datalist.appendChild(option);
  });
}

function attachEventListeners() {
  const searchInput = document.getElementById('countryInput');
  const form = document.getElementById('searchForm');
  const langButtons = document.querySelectorAll('[data-lang]');
  const copyBtn = document.getElementById('copyLink');
  const whatsappBtn = document.getElementById('shareWhatsapp');

  if (searchInput && form) {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const value = searchInput.value.trim();
      if (value.length < 1) return;
      selectCountry(value);
    });

    searchInput.addEventListener('change', e => {
      selectCountry(e.target.value.trim());
    });

    searchInput.addEventListener('input', () => {
      clearResult();
    });
  }

  langButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const newLang = btn.dataset.lang;
      console.log('🔘 Lang button clicked:', newLang);
      if (newLang === state.lang) {
        console.log('⚠️ Same language, skipping');
        return;
      }
      state.lang = newLang;
      localStorage.setItem('visa_app_lang', newLang);
      updateLanguageToggle();
      renderTranslations();
      if (state.filteredCountry) {
        renderResult(state.filteredCountry);
      }
      updateUrl();
    });
  });

  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(window.location.href);
        showFeedback(translations[state.lang].alerts.copied, 'success');
      } catch {
        showFeedback(translations[state.lang].alerts.copyFailed, 'error');
      }
    });
  }

  if (whatsappBtn) {
    whatsappBtn.addEventListener('click', () => {
      const text = encodeURIComponent(`${translations[state.lang].heading} – ${window.location.href}`);
      window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank', 'noopener');
    });
  }
}

function selectCountry(query) {
  if (!query) return;
  console.log('🔍 Searching for country:', query);
  const match = state.countries.find(item => item.country.toLowerCase() === query.toLowerCase());
  if (!match) {
    console.log('❌ Country not found');
    showFeedback(translations[state.lang].labels.notFound, 'warning');
    state.filteredCountry = null;
    renderResult(null);
    updateUrl();
    return;
  }
  console.log('✅ Country found:', match.country);
  state.filteredCountry = match;
  renderResult(match);
  updateUrl(match.country);
}

function renderResult(country) {
  const container = document.getElementById('resultCard');
  const placeholder = document.getElementById('placeholderText');
  if (!container || !placeholder) return;

  if (!country) {
    container.innerHTML = '';
    placeholder.classList.remove('hidden');
    return;
  }

  const visaKey = country.visa_type;
  const t = translations[state.lang];
  const description = (country.descriptions && country.descriptions[state.lang]) || (country.descriptions && country.descriptions.en) || '';
  const duration = (country.durations && country.durations[state.lang]) || (country.durations && country.durations.en) || '';

  placeholder.classList.add('hidden');
  container.innerHTML = `
    <article class="fade-in backdrop-card rounded-3xl p-6 md:p-8 text-slate-100 space-y-6">
      <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
          <img src="${country.flag}" alt="${country.country} flag" class="w-14 h-10 object-cover rounded-xl flag-shadow border border-white/10" loading="lazy" />
          <div>
            <h2 class="text-2xl md:text-3xl font-semibold">${country.country}</h2>
            <p class="text-sm text-slate-300 mt-1">
              <span class="${badgeClassMap[visaKey] || 'badge'}">
                ${t.visaTypes[visaKey] || visaKey}
              </span>
            </p>
          </div>
        </div>
        <div class="text-xs uppercase tracking-wide text-slate-400">
          ${t.lastUpdated}: <span class="text-slate-200">${country.last_update}</span>
        </div>
      </header>

      <div class="space-y-4 text-base leading-relaxed text-slate-200">
        <p>${description}</p>
        <div class="grid gap-4 md:grid-cols-2">
          <div class="bg-slate-900/50 rounded-2xl p-4 border border-white/5">
            <h3 class="text-sm uppercase tracking-wide text-slate-400 mb-2">${t.duration}</h3>
            <p class="text-slate-100">${duration}</p>
          </div>
          <div class="bg-slate-900/50 rounded-2xl p-4 border border-white/5">
            <h3 class="text-sm uppercase tracking-wide text-slate-400 mb-2">${t.source}</h3>
            <a href="${country.source}" target="_blank" rel="noopener" class="text-sky-400 hover:text-sky-300 underline decoration-dotted">
              ${country.source}
            </a>
          </div>
        </div>
      </div>
    </article>
  `;
}

function clearResult() {
  const placeholder = document.getElementById('placeholderText');
  if (placeholder) {
    placeholder.classList.remove('hidden');
  }
  const container = document.getElementById('resultCard');
  if (container) {
    container.innerHTML = '';
  }
  state.filteredCountry = null;
}

function showFeedback(message, type = 'info') {
  const banner = document.getElementById('feedbackBanner');
  if (!banner) return;
  banner.textContent = message;
  banner.dataset.type = type;
  banner.classList.remove('hidden', 'opacity-0');
  banner.classList.add('opacity-100');
  setTimeout(() => {
    banner.classList.add('opacity-0');
    banner.classList.remove('opacity-100');
  }, 2800);
}

function updateUrl(countryName) {
  const url = new URL(window.location.href);
  if (countryName) {
    url.searchParams.set('country', countryName);
  } else {
    url.searchParams.delete('country');
  }
  url.searchParams.set('lang', state.lang);
  window.history.replaceState({}, '', url.toString());
}

function handleQueryParams() {
  const params = new URLSearchParams(window.location.search);
  const countryParam = params.get('country');
  const langParam = params.get('lang');
  if (langParam && ['en', 'tr', 'de'].includes(langParam) && langParam !== state.lang) {
    state.lang = langParam;
    updateLanguageToggle();
    renderTranslations();
  }
  if (countryParam) {
    const decoded = decodeURIComponent(countryParam);
    const match = state.countries.find(item => item.country.toLowerCase() === decoded.toLowerCase());
    if (match) {
      const searchInput = document.getElementById('countryInput');
      if (searchInput) {
        searchInput.value = match.country;
      }
      state.filteredCountry = match;
      renderResult(match);
    }
  } else {
    renderResult(null);
  }
}


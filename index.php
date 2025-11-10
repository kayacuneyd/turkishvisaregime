<?php
$data = file_get_contents(__DIR__ . '/data/data.json');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Türkiye Visa Checker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link rel="stylesheet" href="assets/css/custom.css" />
    <link rel="icon" href="https://upload.wikimedia.org/wikipedia/commons/b/b4/Flag_of_Turkey.svg" />
    <meta name="description" 
    content="Search visa requirements, e-Visa eligibility, and stay limits for traveling to Türkiye..." />
  </head>
  <body class="text-slate-100 antialiased">
    <main class="relative px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <div class="mx-auto max-w-5xl space-y-10">
        <header class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
          <div class="max-w-2xl space-y-4">
            <h1 data-i18n="heading" class="text-4xl md:text-5xl font-semibold tracking-tight"></h1>
            <p data-i18n="subheading" class="text-lg text-slate-300"></p>
          </div>
          <div class="flex items-center gap-3">
            <span data-i18n="labels.language" class="uppercase text-xs tracking-[0.3em] text-slate-400 hidden sm:inline"></span>
            <div class="inline-flex rounded-full border border-white/10 bg-slate-900/40 p-1">
              <button type="button" data-lang="en" class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors text-slate-300 hover:text-white">EN</button>
              <button type="button" data-lang="tr" class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors text-slate-300 hover:text-white">TR</button>
            </div>
          </div>
        </header>

        <section class="backdrop-card rounded-3xl p-6 md:p-8">
          <form id="searchForm" class="space-y-6">
            <div>
              <label for="countryInput" class="block text-sm uppercase tracking-wide text-slate-300 mb-3">
                <span data-i18n="searchPlaceholder"></span>
              </label>
              <div class="relative">
                <input
                  id="countryInput"
                  name="country"
                  type="search"
                  list="countries"
                  class="block w-full rounded-2xl border-none bg-slate-900/80 px-5 py-4 text-lg text-slate-100 placeholder-slate-400 ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-sky-500 transition"
                  autocomplete="off"
                  spellcheck="false"
                  aria-describedby="searchHelp"
                  />
                <datalist id="countries"></datalist>
                <p id="searchHelp" data-i18n="labels.selectPrompt" class="mt-3 text-sm text-slate-400"></p>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-sky-500 hover:bg-sky-400 transition px-6 py-3 text-sm font-semibold uppercase tracking-wide text-white"
                >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M18 10.5a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" />
                </svg>
                <span data-i18n="searchPlaceholder"></span>
              </button>
              <div class="flex items-center gap-2 text-xs uppercase tracking-[0.3em] text-slate-500">
                <span data-i18n="labels.share"></span>
              </div>
              <div class="flex flex-wrap gap-3">
                <button id="copyLink" type="button" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-slate-100/10 transition">
                  <span data-i18n="labels.copyLink"></span>
                </button>
                <button id="shareWhatsapp" type="button" class="inline-flex items-center gap-2 rounded-full border border-emerald-300/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300 hover:bg-emerald-500/20 transition">
                  <span data-i18n="labels.whatsapp"></span>
                </button>
              </div>
            </div>
          </form>

          <div id="feedbackBanner" class="mt-6 hidden rounded-2xl bg-slate-900/80 px-4 py-3 text-sm text-slate-200 transition-opacity duration-500" role="status"></div>
        </section>

        <section class="min-h-[220px]">
          <p id="placeholderText" data-i18n="labels.notFound" class="text-center text-slate-400"></p>
          <div id="resultCard" class="mt-6"></div>
        </section>
      </div>
    </main>

    <footer class="relative px-4 sm:px-6 lg:px-8 py-8 border-t border-white/5">
      <div class="mx-auto max-w-5xl text-center text-sm text-slate-400">
        <p>Developed by <a href="https://kayacuneyt.com" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:text-sky-300 transition">Cüneyt Kaya</a></p>
      </div>
    </footer>

    <script>
      window.__VISA_DATA__ = <?php echo $data ?: '[]'; ?>;
    </script>
    <script src="assets/js/app.js" defer></script>
  </body>
</html>
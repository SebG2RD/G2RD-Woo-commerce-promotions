/* global G2RDPromo */
(() => {
  'use strict';
  const root = document.querySelector('#g2rd-promo-root') || document.querySelector('.g2rd-promo');
  if (!root) return;

  const tabs = Array.isArray(G2RDPromo?.tabs) ? G2RDPromo.tabs : ['overview', 'campaigns', 'calendar', 'stats', 'history', 'settings', 'diagnostic', 'help'];
  const defaultTab = G2RDPromo?.defaultTab || 'overview';
  const make = (tag, text, cls) => {
    const el = document.createElement(tag);
    if (text !== undefined) el.textContent = text;
    if (cls) el.className = cls;
    return el;
  };

  /** Active un onglet sans recharger la page (hash #onglet). */
  const showTab = (name, { push = true } = {}) => {
    if (!tabs.includes(name)) name = defaultTab;
    const panels = root.querySelectorAll('[data-panel]');
    // Mode édition : un seul panneau, pas de bascule hash.
    if (panels.length === 1 && panels[0].getAttribute('data-panel') === 'edit') {
      return;
    }
    panels.forEach((panel) => {
      const on = panel.getAttribute('data-panel') === name;
      panel.classList.toggle('is-active', on);
      panel.hidden = !on;
    });
    root.querySelectorAll('.g2rd-promo-nav__link').forEach((link) => {
      const on = link.getAttribute('data-tab') === name;
      link.classList.toggle('is-active', on);
      link.setAttribute('aria-current', on ? 'page' : 'false');
    });
    if (push) {
      const url = new URL(window.location.href);
      url.searchParams.delete('tab');
      url.hash = name;
      history.replaceState(null, '', url.pathname + url.search + url.hash);
    }
  };

  const tabFromLocation = () => {
    const hash = window.location.hash.replace(/^#/, '');
    if (tabs.includes(hash)) return hash;
    const q = new URLSearchParams(window.location.search).get('tab');
    if (q && tabs.includes(q)) return q;
    const initial = root.getAttribute('data-initial-tab');
    if (initial && tabs.includes(initial)) return initial;
    return defaultTab;
  };

  // Pont POST/?tab= → #onglet, puis navigation locale.
  showTab(tabFromLocation(), { push: true });
  root.querySelectorAll('.g2rd-promo-nav__link').forEach((link) => {
    link.addEventListener('click', (event) => {
      const name = link.getAttribute('data-tab');
      if (!name || !tabs.includes(name)) return;
      event.preventDefault();
      showTab(name);
    });
  });
  window.addEventListener('hashchange', () => showTab(tabFromLocation(), { push: false }));

  root.querySelectorAll('[data-confirm]').forEach((button) =>
    button.addEventListener('click', (event) => {
      if (!window.confirm(G2RDPromo.confirm)) event.preventDefault();
    })
  );

  // Delegated: rules added later have their own term search fields.
  root.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.dataset.target) return;
    const select = document.getElementById(input.dataset.target);
    if (!select) return;
    const term = input.value.toLocaleLowerCase();
    [...select.options].forEach((opt) => {
      opt.hidden = !opt.selected && !opt.textContent.toLocaleLowerCase().includes(term);
    });
  });

  const rules = root.querySelector('#g2rd-promo-rules');
  const template = root.querySelector('#g2rd-promo-rule-template');
  const addRule = root.querySelector('#g2rd-promo-add-rule');
  if (rules && template && addRule) {
    let next = Date.now();
    const renumber = () =>
      rules.querySelectorAll('.g2rd-promo-rule-num').forEach((num, i) => {
        num.textContent = String(i + 1);
      });
    addRule.addEventListener('click', () => {
      const index = String(next++);
      const fragment = template.content.cloneNode(true);
      fragment.querySelectorAll('[name], [id], [for], [data-target]').forEach((el) => {
        ['name', 'id', 'for', 'data-target'].forEach((attr) => {
          if (el.hasAttribute(attr)) el.setAttribute(attr, el.getAttribute(attr).replaceAll('__i__', index));
        });
      });
      rules.append(fragment);
      renumber();
      rules.lastElementChild?.querySelector('select, input')?.focus();
      rules.closest('form')?.dispatchEvent(new Event('change'));
    });
    rules.addEventListener('click', (event) => {
      const button = event.target instanceof Element ? event.target.closest('.g2rd-promo-rule-remove') : null;
      if (!button) return;
      if (rules.querySelectorAll('.g2rd-promo-rule').length <= 1) return;
      button.closest('.g2rd-promo-rule')?.remove();
      renumber();
      rules.closest('form')?.dispatchEvent(new Event('change'));
    });
  }

  const search = root.querySelector('#g2rd-campaign-search');
  const status = root.querySelector('#g2rd-campaign-status');
  if (search && status) {
    const filter = () =>
      root.querySelectorAll('#g2rd-campaign-table tbody tr').forEach((row) => {
        row.hidden =
          !row.textContent.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()) ||
          (status.value && row.dataset.status !== status.value);
      });
    search.addEventListener('input', filter);
    status.addEventListener('change', filter);
  }

  const preview = root.querySelector('#g2rd-promo-preview');
  const countButton = root.querySelector('#g2rd-promo-count');
  const result = root.querySelector('#g2rd-promo-preview-result');
  if (!preview || !result) return;
  const form = preview.closest('form');
  let generation = 0;
  const request = async (page, mode) => {
    const data = new FormData(form);
    data.set('action', 'g2rd_promo_preview');
    data.set('nonce', G2RDPromo.nonce);
    data.set('preview_page', page);
    data.set('preview_mode', mode);
    const response = await fetch(G2RDPromo.ajax, { method: 'POST', credentials: 'same-origin', body: data });
    const json = await response.json();
    if (!json.success) throw new Error(json.data?.message || G2RDPromo.error);
    return json.data;
  };
  const busy = (on) => {
    preview.disabled = on;
    if (countButton) countButton.disabled = on;
    if (on) result.setAttribute('aria-busy', 'true');
    else result.removeAttribute('aria-busy');
  };
  /** Scans the whole catalogue page by page; each request stays short. */
  const countAll = async () => {
    if (!form.reportValidity()) return;
    const run = ++generation;
    busy(true);
    const status = make('p', G2RDPromo.working);
    result.replaceChildren(status);
    try {
      let totals = [];
      let page = 1;
      let pages = 1;
      let candidates = 0;
      do {
        const d = await request(page, 'count');
        if (run !== generation) return;
        totals = d.counts.map((n, i) => n + (totals[i] || 0));
        pages = d.pages;
        candidates = d.candidates;
        status.textContent = `Comptage… ${page} / ${pages}`;
        page++;
      } while (page <= pages);
      const list = make('ul');
      totals.forEach((n, i) => list.append(make('li', `Règle ${i + 1} : ${n} article(s)`)));
      const sum = totals.reduce((a, b) => a + b, 0);
      result.replaceChildren(
        make('p', `${sum} article(s) concerné(s) sur ${candidates} produit(s) candidat(s), premier profil ciblé.`),
        list
      );
    } catch (error) {
      if (run === generation) result.replaceChildren(make('p', error.message || G2RDPromo.error, 'g2rd-promo-hint'));
    } finally {
      if (run === generation) busy(false);
    }
  };
  const load = async (page = 1) => {
    if (!form.reportValidity()) return;
    const run = ++generation;
    busy(true);
    result.replaceChildren(make('p', G2RDPromo.working));
    try {
      const d = await request(page, 'rows');
      if (run !== generation) return;
      result.replaceChildren(
        make(
          'p',
          `${d.candidates} produits candidats (avant exclusions). ${d.matched} combinaisons produit/profil éligibles sur cette page.`
        )
      );
      if (!d.rows.length) result.append(make('p', 'Aucun résultat éligible sur cette page.'));
      for (const row of d.rows) {
        const line = make('div', undefined, 'g2rd-promo-preview-row');
        line.append(make('strong', row.product), make('small', `${row.profile} · règle ${row.rule}`));
        const prices = make('div');
        prices.append(make('del', `${row.base} ${row.currency}`), make('ins', `${row.price} ${row.currency}`));
        line.append(prices);
        result.append(line);
      }
      if (d.matched > d.rows.length) result.append(make('small', 'Affichage limité aux 60 premiers résultats de cette page.'));
      if (d.pages > 1) {
        const nav = make('div', undefined, 'g2rd-promo-preview-pagination');
        const prev = make('button', 'Précédent', 'button');
        const next = make('button', 'Suivant', 'button');
        prev.type = next.type = 'button';
        prev.disabled = d.page <= 1;
        next.disabled = d.page >= d.pages;
        prev.addEventListener('click', () => load(d.page - 1));
        next.addEventListener('click', () => load(d.page + 1));
        nav.append(prev, make('small', `${d.page} / ${d.pages}`), next);
        result.append(nav);
      }
    } catch (error) {
      if (run === generation) result.replaceChildren(make('p', error.message || G2RDPromo.error, 'g2rd-promo-hint'));
    } finally {
      if (run === generation) busy(false);
    }
  };
  preview.addEventListener('click', () => load());
  countButton?.addEventListener('click', countAll);
  form.addEventListener('change', () => {
    generation++;
    busy(false);
    result.replaceChildren(make('p', 'Paramètres modifiés. Recalcule la prévisualisation.', 'g2rd-promo-hint'));
  });
})();

/* global G2RDPromo */
(() => {
  'use strict';
  const root = document.querySelector('.g2rd-promo');
  if (!root) return;
  const make = (tag, text, cls) => { const el = document.createElement(tag); if (text !== undefined) el.textContent = text; if (cls) el.className = cls; return el; };
  root.querySelectorAll('[data-confirm]').forEach(button => button.addEventListener('click', event => { if (!window.confirm(G2RDPromo.confirm)) event.preventDefault(); }));
  root.querySelectorAll('[data-target]').forEach(input => input.addEventListener('input', () => {
    const select = document.getElementById(input.dataset.target); if (!select) return;
    const term = input.value.toLocaleLowerCase();
    [...select.options].forEach(opt => { opt.hidden = !opt.selected && !opt.textContent.toLocaleLowerCase().includes(term); });
  }));
  root.querySelectorAll('[data-all]').forEach(check => {
    const select = document.getElementById(check.dataset.all);
    if (!select) return;
    const sync = () => { select.disabled = check.checked; };
    check.addEventListener('change', sync); sync();
  });
  const search = root.querySelector('#g2rd-campaign-search');
  const status = root.querySelector('#g2rd-campaign-status');
  if (search && status) {
    const filter = () => root.querySelectorAll('#g2rd-campaign-table tbody tr').forEach(row => {
      row.hidden = !row.textContent.toLocaleLowerCase().includes(search.value.toLocaleLowerCase()) || (status.value && row.dataset.status !== status.value);
    });
    search.addEventListener('input', filter); status.addEventListener('change', filter);
  }
  const preview = root.querySelector('#g2rd-promo-preview');
  const result = root.querySelector('#g2rd-promo-preview-result');
  if (!preview || !result) return;
  const form = preview.closest('form');
  let generation = 0;
  const load = async (page = 1) => {
    if (!form.reportValidity()) return;
    const run = ++generation; preview.disabled = true; result.replaceChildren(make('p', G2RDPromo.working)); result.setAttribute('aria-busy', 'true');
    const data = new FormData(form); data.set('action', 'g2rd_promo_preview'); data.set('nonce', G2RDPromo.nonce); data.set('preview_page', page);
    try {
      const response = await fetch(G2RDPromo.ajax, {method:'POST', credentials:'same-origin', body:data});
      const json = await response.json(); if (run !== generation) return;
      if (!json.success) throw new Error(json.data?.message || G2RDPromo.error);
      const d = json.data;
      result.replaceChildren(make('p', `${d.candidates} produits candidats (avant exclusions). ${d.matched} combinaisons produit/profil éligibles sur cette page.`));
      if (!d.rows.length) result.append(make('p', 'Aucun résultat éligible sur cette page.'));
      for (const row of d.rows) {
        const line = make('div', undefined, 'g2rd-promo-preview-row');
        line.append(make('strong', row.product), make('small', row.profile));
        const prices = make('div'); prices.append(make('del', `${row.base} ${row.currency}`), make('ins', `${row.price} ${row.currency}`));
        line.append(prices); result.append(line);
      }
      if (d.matched > d.rows.length) result.append(make('small', 'Affichage limité aux 60 premiers résultats de cette page.'));
      if (d.pages > 1) {
        const nav = make('div', undefined, 'g2rd-promo-preview-pagination');
        const prev = make('button', 'Précédent', 'button'), next = make('button', 'Suivant', 'button'); prev.type = next.type = 'button';
        prev.disabled = d.page <= 1; next.disabled = d.page >= d.pages;
        prev.addEventListener('click', () => load(d.page - 1)); next.addEventListener('click', () => load(d.page + 1));
        nav.append(prev, make('small', `${d.page} / ${d.pages}`), next); result.append(nav);
      }
    } catch(error) { if (run === generation) result.replaceChildren(make('p', error.message || G2RDPromo.error, 'g2rd-promo-hint')); }
    finally { if (run === generation) { preview.disabled = false; result.removeAttribute('aria-busy'); } }
  };
  preview.addEventListener('click', () => load());
  form.addEventListener('change', () => { generation++; preview.disabled = false; result.removeAttribute('aria-busy'); result.replaceChildren(make('p', 'Paramètres modifiés. Recalcule la prévisualisation.', 'g2rd-promo-hint')); });
})();

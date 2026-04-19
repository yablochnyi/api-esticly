import './style.css';

const serviceItems = document.querySelectorAll ('.service-item');
const summaryItemsEl = document.getElementById ('summaryItems');
const summaryDurationEl = document.getElementById ('summaryDuration');
const summaryCostEl = document.getElementById ('summaryCost');
let selected = [];

function parseHours (dur) {
  if (dur.includes ('год')) return parseInt (dur) || 1;
  return 0;
}

function updateSummary () {
  summaryItemsEl.innerHTML = '';
  let totalPrice = 0;
  let totalHours = 0;

  selected.forEach (s => {
    totalPrice += s.price;
    totalHours += parseHours (s.duration);

    const el = document.createElement ('div');
    el.className = 'summary-item';
    el.innerHTML = `
          <div class="summary-item-info">
            <span class="summary-item-name">${s.name}</span>
            <span class="summary-item-meta">${s.duration} · ${s.price} UAH</span>
          </div>
          <button class="summary-item-remove" data-name="${s.name}">
            <img src="./public/Group.svg" alt="">
          </button>`;
    summaryItemsEl.appendChild (el);
  });

  summaryDurationEl.textContent = totalHours > 0
    ? `${totalHours} год`
    : '0 год';
  summaryCostEl.textContent = `${totalPrice} UAH`;

  document.querySelectorAll ('.summary-item-remove').forEach (btn => {
    btn.addEventListener ('click', e => {
      e.stopPropagation ();
      const name = btn.dataset.name;
      selected = selected.filter (s => s.name !== name);
      document.querySelectorAll ('.service-item').forEach (item => {
        if (item.dataset.name === name) {
          item.classList.remove ('selected');
          item.querySelector ('.service-checkbox').classList.remove ('checked');
        }
      });
      updateSummary ();
    });
  });
}

serviceItems.forEach (item => {
  item.addEventListener ('click', () => {
    const name = item.dataset.name;
    const duration = item.dataset.duration;
    const price = parseInt (item.dataset.price);
    const isSelected = item.classList.contains ('selected');

    if (isSelected) {
      item.classList.remove ('selected');
      item.querySelector ('.service-checkbox').classList.remove ('checked');
      selected = selected.filter (s => s.name !== name);
    } else {
      item.classList.add ('selected');
      item.querySelector ('.service-checkbox').classList.add ('checked');
      selected.push ({name, duration, price});
    }
    updateSummary ();
  });
});

const summaryBtn = document.querySelector ('.summary-btn');
if (summaryBtn) {
  summaryBtn.addEventListener ('click', () => {
    localStorage.setItem ('selectedServices', JSON.stringify (selected));
    window.location.href = 'booking_date.html';
  });
}

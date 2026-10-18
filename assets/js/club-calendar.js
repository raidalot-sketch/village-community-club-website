(() => {
  const calendar = document.querySelector('[data-club-calendar]');
  if (!calendar) return;
  const setView = view => {
    calendar.querySelectorAll('[data-calendar-view]').forEach(panel => { panel.hidden = panel.dataset.calendarView !== view; });
    calendar.querySelectorAll('[data-calendar-switch]').forEach(link => { link.setAttribute('aria-current', link.dataset.calendarSwitch === view ? 'page' : 'false'); link.classList.toggle('btn--primary', link.dataset.calendarSwitch === view); });
  };
  setView(calendar.dataset.initialView || (matchMedia('(max-width: 700px)').matches ? 'list' : 'month'));
  calendar.querySelectorAll('[data-calendar-switch]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault(); setView(link.dataset.calendarSwitch); history.replaceState(null, '', link.href);
  }));
})();

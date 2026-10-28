document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.getElementById('mobileMenu');
  const sidebar = document.getElementById('sidebar');
  if (menuButton && sidebar) {
    menuButton.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', event => {
      if (window.innerWidth <= 850 && sidebar.classList.contains('open') &&
          !sidebar.contains(event.target) && !menuButton.contains(event.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  const tabs = document.querySelectorAll('.day-tab');
  const rows = document.querySelectorAll('.class-row');
  const emptyState = document.getElementById('emptyState');
  tabs.forEach(tab => tab.addEventListener('click', () => {
    tabs.forEach(item => item.classList.remove('selected'));
    tab.classList.add('selected');
    const selectedDay = tab.dataset.day;
    let visibleRows = 0;
    rows.forEach(row => {
      const show = row.dataset.day === selectedDay;
      row.hidden = !show;
      if (show) visibleRows += 1;
    });
    if (emptyState) emptyState.hidden = visibleRows !== 0;
  }));
});

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

  document.querySelectorAll('[data-toggle-course]').forEach(button => {
    button.addEventListener('click', () => {
      const row = button.closest('[data-course-row]');
      const status = row.querySelector('[data-status]');
      const registered = status.textContent.trim() === 'Registered';
      status.textContent = registered ? 'Available' : 'Registered';
      status.classList.toggle('status-registered', !registered);
      status.classList.toggle('status-available', registered);
      button.textContent = registered ? 'Add course' : 'Remove';
      button.classList.toggle('remove-course', !registered);
      button.classList.toggle('add-course', registered);
    });
  });

  const saveCourses = document.getElementById('saveCourses');
  if (saveCourses) {
    saveCourses.addEventListener('click', () => {
      const message = document.getElementById('courseMessage');
      if (message) message.hidden = false;
    });
  }

  const generateTimetable = document.getElementById('generateTimetable');
  if (generateTimetable) {
    generateTimetable.addEventListener('click', () => {
      const message = document.getElementById('timetableMessage');
      if (message) {
        message.textContent = 'Sample timetable displayed. Real generation and course, lecturer, time and venue conflict validation will be implemented with the scheduling backend.';
      }
    });
  }

});

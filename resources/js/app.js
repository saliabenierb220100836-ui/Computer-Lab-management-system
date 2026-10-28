import '../css/theme.css';

const logoutDialog = document.querySelector('#logout-dialog');
const openLogoutDialog = document.querySelector('[data-open-logout-dialog]');
const cancelLogout = document.querySelector('[data-cancel-logout]');

if (logoutDialog instanceof HTMLDialogElement && openLogoutDialog instanceof HTMLButtonElement) {
    openLogoutDialog.addEventListener('click', () => logoutDialog.showModal());
    cancelLogout?.addEventListener('click', () => logoutDialog.close());
}

document.querySelectorAll('.profile-photo-form').forEach((form) => {
    const fileInput = form.querySelector('.profile-photo-input');
    const chooseButton = form.querySelector('[data-choose-profile-photo]');

    if (fileInput instanceof HTMLInputElement && chooseButton instanceof HTMLButtonElement) {
        chooseButton.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            if (fileInput.files?.length) {
                form.requestSubmit();
            }
        });
    }
});

document.querySelectorAll('.roster-import-form').forEach((form) => {
    const fileInput = form.querySelector('.roster-import-input');
    const uploadButton = form.querySelector('[data-upload-roster]');

    if (fileInput instanceof HTMLInputElement && uploadButton instanceof HTMLButtonElement) {
        uploadButton.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            if (fileInput.files?.length) {
                form.requestSubmit();
            }
        });
    }
});

async function refreshDashboard() {
    if (
        document.visibilityState !== 'visible'
        || document.querySelector('input:focus, select:focus, textarea:focus')
        || document.querySelector('details[open]')
        || document.querySelector('dialog[open]')
    ) {
        return;
    }

    try {
        const response = await fetch(`${window.location.pathname}${window.location.search}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            throw new Error(`Dashboard refresh returned ${response.status}.`);
        }

        const documentSnapshot = new DOMParser().parseFromString(await response.text(), 'text/html');
        const liveMap = documentSnapshot.querySelector('.floor-map');
        const liveStats = documentSnapshot.querySelector('.stats-grid');
        const currentMap = document.querySelector('.floor-map');
        const currentStats = document.querySelector('.stats-grid');
        if (liveMap && currentMap && liveStats && currentStats) {
            currentMap.innerHTML = liveMap.innerHTML;
            currentStats.innerHTML = liveStats.innerHTML;

            const currentSession = document.querySelector('.active-session-banner');
            const liveSession = documentSnapshot.querySelector('.active-session-banner');
            if (currentSession && liveSession) {
                currentSession.outerHTML = liveSession.outerHTML;
            } else if (currentSession) {
                currentSession.remove();
            } else if (liveSession) {
                currentStats.before(liveSession);
            }

            const currentLogs = document.querySelector('.log-list');
            const liveLogs = documentSnapshot.querySelector('.log-list');
            if (currentLogs && liveLogs) {
                currentLogs.innerHTML = liveLogs.innerHTML;
            }
        }
    } catch (error) {
        console.error('Unable to refresh live lab status.', error);
    }
}

window.setInterval(refreshDashboard, document.querySelector('.floor-map') ? 5000 : 15000);

if (window.location.hash === '#schedule-form') {
    const scheduleForm = document.querySelector('#schedule-form');
    if (scheduleForm instanceof HTMLDetailsElement) {
        scheduleForm.open = true;
        scheduleForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

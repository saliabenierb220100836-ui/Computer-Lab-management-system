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
        fileInput.addEventListener('change', async () => {
            const photo = fileInput.files?.[0];
            if (!photo) {
                return;
            }

            const maxPhotoBytes = 2 * 1024 * 1024;
            fileInput.setCustomValidity('');

            if (photo.size > maxPhotoBytes) {
                fileInput.value = '';
                fileInput.setCustomValidity('Choose an image that is no larger than 2 MB.');
                fileInput.reportValidity();
                return;
            }

            try {
                const jpeg = photo.type === 'image/jpeg'
                    ? photo
                    : await convertProfilePhotoToJpeg(photo);
                if (jpeg.size > maxPhotoBytes) {
                    throw new RangeError('The converted image is larger than 2 MB.');
                }

                const transfer = new DataTransfer();
                transfer.items.add(new File([jpeg], 'profile-photo.jpeg', {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                }));
                fileInput.files = transfer.files;
                form.requestSubmit();
            } catch (error) {
                console.error('Unable to convert the profile photo to JPEG.', error);
                fileInput.value = '';
                fileInput.setCustomValidity(error instanceof RangeError
                    ? 'The converted image is larger than 2 MB. Choose a smaller image.'
                    : 'This image could not be converted. Please choose another JPG, PNG, or WebP image.');
                fileInput.reportValidity();
            }
        });
    }
});

async function convertProfilePhotoToJpeg(photo) {
    const bitmap = await createImageBitmap(photo);

    try {
        const canvas = document.createElement('canvas');
        canvas.width = bitmap.width;
        canvas.height = bitmap.height;

        const context = canvas.getContext('2d');
        if (!context) {
            throw new Error('Unable to prepare the profile photo for conversion.');
        }

        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0);

        return await new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('The browser could not convert the profile photo.'));
                }
            }, 'image/jpeg', 0.9);
        });
    } finally {
        bitmap.close();
    }
}

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

document.querySelectorAll('[data-admin-account-form]').forEach((form) => {
    const roleSelect = form.querySelector('[data-admin-account-role]');
    const certificateField = form.querySelector('[data-student-certificate-field]');
    const certificateInput = form.querySelector('[data-student-certificate]');

    if (
        roleSelect instanceof HTMLSelectElement
        && certificateField instanceof HTMLLabelElement
        && certificateInput instanceof HTMLInputElement
    ) {
        const updateCertificateRequirement = () => {
            const isStudent = roleSelect.value === 'student';
            certificateField.hidden = !isStudent;
            certificateInput.required = isStudent;
            certificateInput.disabled = !isStudent;
        };

        roleSelect.addEventListener('change', updateCertificateRequirement);
        updateCertificateRequirement();
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

document.addEventListener('DOMContentLoaded', function () {
    const btnRegenerate = document.getElementById('totp-regenerate');
    const btnDisable = document.getElementById('totp-disable');
    const modal = document.getElementById('totp-modal');
    const modalClose = document.getElementById('totp-modal-close');
    const qrImg = document.getElementById('totp-qr-img');
    const otpUrlEl = document.getElementById('totp-otp-url');
    const codeInput = document.getElementById('totp-code');
    const btnConfirm = document.getElementById('totp-confirm');
    const statusEl = document.getElementById('totp-status');
    const errorEl = document.getElementById('totp-error');

    if (!btnRegenerate || !modal) {
        return;
    }

    let currentProfileHash = '';
    const serviceId = document.body.getAttribute('data-eb-serviceid');
    const username = document.body.getAttribute('data-eb-username');
    const endpoint = window.EB_TOTP_ENDPOINT;

    function showModal() {
        modal.classList.remove('hidden');
    }
    function hideModal() {
        modal.classList.add('hidden');
        codeInput.value = '';
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
    }

    modalClose && modalClose.addEventListener('click', hideModal);

    btnRegenerate.addEventListener('click', function () {
        errorEl.classList.add('hidden');
        statusEl.textContent = 'Requesting new TOTP secret...';
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'regenerate', serviceId, username })
        }).then(r => r.json()).then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to regenerate TOTP');
            }
            currentProfileHash = data.profileHash || '';
            if (qrImg) qrImg.src = data.image || '';
            if (otpUrlEl) {
                otpUrlEl.textContent = data.url || '';
                otpUrlEl.setAttribute('href', data.url || '#');
            }
            statusEl.textContent = 'Scan the QR code, then enter the 6-digit code.';
            showModal();
        }).catch(err => {
            statusEl.textContent = '';
            errorEl.textContent = err.message || 'Unexpected error';
            errorEl.classList.remove('hidden');
        });
    });

    btnConfirm && btnConfirm.addEventListener('click', function () {
        const code = (codeInput.value || '').trim();
        if (code.length < 6) {
            errorEl.textContent = 'Please enter the 6-digit code.';
            errorEl.classList.remove('hidden');
            return;
        }
        errorEl.classList.add('hidden');
        statusEl.textContent = 'Validating TOTP...';
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'validate', serviceId, username, code, profileHash: currentProfileHash })
        }).then(r => r.json()).then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to validate TOTP');
            }
            statusEl.textContent = 'TOTP enabled successfully. Reloading...';
            window.location.reload();
        }).catch(err => {
            statusEl.textContent = '';
            errorEl.textContent = err.message || 'Unexpected error';
            errorEl.classList.remove('hidden');
        });
    });

    btnDisable && btnDisable.addEventListener('click', function () {
        if (!confirm('Disable two-factor authentication (TOTP) for this account?')) {
            return;
        }
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'disable', serviceId, username })
        }).then(r => r.json()).then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'Failed to disable TOTP');
            }
            window.location.reload();
        }).catch(err => {
            errorEl.textContent = err.message || 'Unexpected error';
            errorEl.classList.remove('hidden');
        });
    });
});



(() => {
    const cfg      = window.FileParser;
    const form     = document.getElementById('parse-form');
    const submitBtn = document.getElementById('submit-btn');
    const statusMsg = document.getElementById('status-msg');
    const resultCard = document.getElementById('result-card');
    const resultPre  = document.getElementById('result-pre');
    const copyBtn    = document.getElementById('copy-btn');
    const csrfToken  = document.getElementById('csrf-token');

    function showStatus(text, type) {
        statusMsg.textContent = text;
        statusMsg.className = 'status-msg visible ' + type;
    }

    function hideStatus() {
        statusMsg.className = 'status-msg';
    }

    function showResult(data) {
        resultPre.textContent = JSON.stringify(data, null, 2);
        resultCard.classList.add('visible');
        resultCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideResult() {
        resultCard.classList.remove('visible');
        resultPre.textContent = '';
    }

    /** Maximum PDF size accepted by the server (10 MB), used for early client-side rejection. */
    const MAX_FILE_BYTES = 10485760;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fileInput = document.getElementById('pdf_file');
        const promptInput = document.getElementById('prompt');

        if (!fileInput.files.length || promptInput.value.trim() === '') {
            return;
        }

        // Reject oversized files before uploading to avoid wasting bandwidth.
        if (fileInput.files[0].size > MAX_FILE_BYTES) {
            showStatus(cfg.i18n.errorFileTooLarge, 'error');
            return;
        }

        hideResult();
        showStatus(cfg.i18n.loading, 'loading');
        submitBtn.disabled = true;

        const formData = new FormData();
        formData.append('_csrf_token', csrfToken.value);
        formData.append('pdf_file', fileInput.files[0]);
        formData.append('prompt', promptInput.value.trim());

        try {
            const res = await fetch(cfg.routes.parse, {
                method: 'POST',
                body: formData,
            });

            const json = await res.json();
            hideStatus();

            if (json.data) {
                showResult(json.data);
            } else {
                showStatus(json.error ?? cfg.i18n.errorUnknown, 'error');
            }
        } catch {
            hideStatus();
            showStatus(cfg.i18n.errorUnknown, 'error');
        } finally {
            submitBtn.disabled = false;
        }
    });

    copyBtn.addEventListener('click', () => {
        navigator.clipboard.writeText(resultPre.textContent).then(() => {
            const original = copyBtn.textContent;
            copyBtn.textContent = cfg.i18n.copied;
            setTimeout(() => { copyBtn.textContent = original; }, 1500);
        });
    });
})();

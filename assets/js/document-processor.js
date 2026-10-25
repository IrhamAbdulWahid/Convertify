document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('documentForm');
    if (!form) return;

    const input = document.getElementById('documentFile');
    const fileInfo = document.getElementById('documentFileInfo');
    const targetFormat = document.getElementById('documentTargetFormat');
    const processButton = document.getElementById('documentProcessButton');
    const engineNotice = document.getElementById('documentEngineNotice');
    const officeToPdfAvailable = form.dataset.officeToPdf === 'true';
    const progress = document.getElementById('documentProgress');
    const error = document.getElementById('documentError');
    const results = document.getElementById('documentResults');
    const resultList = document.getElementById('documentResultList');
    const endpoint = form.dataset.documentEndpoint;
    let resultToken = null;

    input.addEventListener('change', function () {
        error.hidden = true;
        fileInfo.replaceChildren();
        const file = input.files[0];
        if (!file) {
            Array.from(targetFormat.options).forEach(function (option) { option.hidden = false; });
            targetFormat.disabled = false;
            targetFormat.value = 'pdf';
            engineNotice.hidden = true;
            processButton.disabled = true;
            return;
        }

        const extension = file.name.split('.').pop().toLowerCase();
        if (!['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(extension)) {
            showError('Pilih file PDF, DOC, DOCX, XLS, XLSX, PPT, atau PPTX.');
            input.value = '';
            processButton.disabled = true;
            return;
        }
        if (file.size < 1 || file.size > 10 * 1024 * 1024) {
            showError('Ukuran file harus lebih dari 0 dan maksimal 10 MB.');
            input.value = '';
            processButton.disabled = true;
            return;
        }

        Array.from(targetFormat.options).forEach(function (option) {
            option.hidden = extension === 'pdf' ? option.value === 'pdf' : option.value !== 'pdf';
        });
        targetFormat.value = extension === 'pdf' ? 'docx' : 'pdf';
        targetFormat.disabled = extension !== 'pdf' && !officeToPdfAvailable;
        engineNotice.hidden = extension === 'pdf' || officeToPdfAvailable;
        const row = document.createElement('div');
        row.className = 'file-row';
        const info = document.createElement('span');
        info.className = 'file-row-info';
        const name = document.createElement('strong');
        name.textContent = file.name;
        const detail = document.createElement('small');
        detail.textContent = extension.toUpperCase() + ' · ' + formatBytes(file.size);
        info.append(name, detail);
        row.appendChild(info);
        fileInfo.appendChild(row);
        processButton.disabled = extension !== 'pdf' && !officeToPdfAvailable;
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!input.files.length) {
            showError('Pilih satu file PDF atau dokumen terlebih dahulu.');
            return;
        }

        processButton.disabled = true;
        progress.hidden = false;
        error.hidden = true;
        results.hidden = true;
        try {
            const response = await fetch(endpoint, { method: 'POST', body: new FormData(form) });
            let payload;
            try {
                payload = await response.json();
            } catch {
                throw new Error('Server mengirim respons yang tidak dapat dibaca. Silakan coba lagi.');
            }
            if (!response.ok) throw new Error(payload.message || 'Dokumen gagal dikonversi.');
            const result = payload.results && payload.results[0];
            if (!result || !result.success) throw new Error((result && result.message) || payload.message || 'Dokumen gagal dikonversi.');
            renderResult(result);
            results.hidden = false;
        } catch (exception) {
            showError(exception.message || 'Tidak dapat menghubungi server. Periksa koneksi Anda lalu coba lagi.');
        } finally {
            progress.hidden = true;
            processButton.disabled = !input.files.length;
        }
    });

    document.querySelector('[data-document-reset]').addEventListener('click', async function () {
        if (resultToken) {
            const data = new FormData();
            data.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
            data.append('token', resultToken);
            try {
                await fetch(endpoint.replace(/process-document\.php$/, 'delete-file.php'), { method: 'POST', body: data });
            } catch {
                showToast('File sementara akan dihapus saat kedaluwarsa.', 'info');
            }
        }
        resultToken = null;
        input.value = '';
        fileInfo.replaceChildren();
        Array.from(targetFormat.options).forEach(function (option) { option.hidden = false; });
        targetFormat.disabled = false;
        engineNotice.hidden = true;
        targetFormat.value = 'pdf';
        processButton.disabled = true;
        results.hidden = true;
        error.hidden = true;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    function renderResult(result) {
        resultToken = result.token;
        resultList.replaceChildren();
        const card = document.createElement('article');
        card.className = 'result-card result-success';
        const heading = document.createElement('div');
        heading.className = 'result-card-heading';
        const icon = document.createElement('span');
        icon.className = 'result-status-icon';
        icon.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>';
        const name = document.createElement('strong');
        name.textContent = result.name;
        const badge = document.createElement('span');
        badge.className = 'result-badge';
        badge.textContent = 'Selesai';
        heading.append(icon, name, badge);
        card.appendChild(heading);

        const detail = document.createElement('p');
        detail.className = 'result-detail';
        detail.textContent = result.sourceFormat + ' → ' + result.targetFormat + ' · ' + result.message;
        card.appendChild(detail);

        const sizes = document.createElement('div');
        sizes.className = 'result-sizes';
        addSize(sizes, 'Sebelum', result.originalSizeLabel);
        addSize(sizes, 'Sesudah', result.outputSizeLabel);
        card.appendChild(sizes);

        const download = document.createElement('a');
        download.className = 'btn btn-primary btn-sm mt-3';
        download.href = endpoint.replace(/process-document\.php$/, 'download.php') + '?token=' + encodeURIComponent(result.token);
        download.setAttribute('download', result.name);
        download.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i> Unduh';
        card.appendChild(download);
        resultList.appendChild(card);
    }

    function addSize(parent, label, value) {
        const item = document.createElement('span');
        item.className = 'result-size';
        const title = document.createElement('small');
        title.textContent = label;
        const amount = document.createElement('strong');
        amount.textContent = value;
        item.append(title, amount);
        parent.appendChild(item);
    }

    function showError(message) {
        error.textContent = message;
        error.hidden = false;
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const shell = document.querySelector('[data-endpoint]');
    const form = document.getElementById('processorForm');
    const input = document.getElementById('imageFiles');
    if (!shell || !form || !input) return;

    const operationMode = shell.dataset.operation;
    const filesList = document.getElementById('fileList');
    const processButton = document.getElementById('processButton');
    const progress = document.getElementById('progressArea');
    const formError = document.getElementById('formError');
    const resultsSection = document.getElementById('results');
    const resultList = document.getElementById('resultList');
    const selectedFiles = [];
    const previewUrls = new WeakMap();
    let lastResults = [];

    function previewUrl(file) {
        if (!previewUrls.has(file)) previewUrls.set(file, URL.createObjectURL(file));
        return previewUrls.get(file);
    }

    function setInputFiles() {
        const transfer = new DataTransfer();
        selectedFiles.forEach(function (file) { transfer.items.add(file); });
        input.files = transfer.files;
    }

    function renderFiles() {
        filesList.replaceChildren();
        selectedFiles.forEach(function (file, index) {
            const row = document.createElement('div');
            row.className = 'file-row';
            const icon = document.createElement('span');
            icon.className = 'file-type-icon';
            const thumbnail = document.createElement('img');
            thumbnail.className = 'file-thumb';
            thumbnail.src = previewUrl(file);
            thumbnail.alt = '';
            icon.appendChild(thumbnail);
            const info = document.createElement('span');
            info.className = 'file-row-info';
            const name = document.createElement('strong');
            name.textContent = file.name;
            const detail = document.createElement('small');
            detail.textContent = file.name.split('.').pop().toUpperCase() + ' · ' + formatBytes(file.size);
            info.append(name, detail);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove-file';
            remove.setAttribute('aria-label', 'Hapus ' + file.name);
            remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
            remove.addEventListener('click', function () {
                const removed = selectedFiles.splice(index, 1)[0];
                if (previewUrls.has(removed)) {
                    URL.revokeObjectURL(previewUrls.get(removed));
                    previewUrls.delete(removed);
                }
                setInputFiles();
                renderFiles();
            });
            row.append(icon, info, remove);
            filesList.appendChild(row);
        });
        const hasFiles = selectedFiles.length > 0;
        document.querySelectorAll('[data-operation-choice]').forEach(function (button) {
            button.disabled = !hasFiles;
        });
        if (processButton) processButton.disabled = !hasFiles;
    }

    input.addEventListener('change', function () {
        formError.hidden = true;
        const accepted = [];
        Array.from(input.files).forEach(function (file) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif'].includes(extension)) {
                showError(file.name + ': format file tidak didukung.');
            } else if (file.size < 1 || file.size > 10 * 1024 * 1024) {
                showError(file.name + ': ukuran file harus lebih dari 0 dan maksimal 10 MB.');
            } else {
                accepted.push(file);
            }
        });
        const unique = accepted.filter(function (file) {
            return !selectedFiles.some(function (existing) {
                return existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified;
            });
        });
        if (selectedFiles.length + unique.length > 10) {
            showError('Maksimal 10 file dapat diproses dalam satu batch.');
            unique.length = Math.max(0, 10 - selectedFiles.length);
        }
        selectedFiles.push.apply(selectedFiles, unique);
        setInputFiles();
        renderFiles();
    });

    const quality = document.getElementById('quality') || document.getElementById('homeQuality');
    const qualityValue = document.getElementById('qualityValue');
    if (quality && qualityValue) {
        quality.addEventListener('input', function () {
            qualityValue.textContent = quality.value + '%';
            const hiddenQuality = form.querySelector('input[name="quality"]');
            if (hiddenQuality && hiddenQuality !== quality) hiddenQuality.value = quality.value;
        });
    }

    const outputFormat = document.getElementById('outputFormat');
    const homeFormat = document.getElementById('homeFormat');
    const formatSelect = outputFormat || homeFormat;
    const formatInput = form.querySelector('input[name="format"]');
    const backgroundGroup = document.getElementById('backgroundGroup');
    const qualityGroup = document.getElementById('qualityGroup');
    function updateFormat() {
        if (formatInput && formatSelect) formatInput.value = formatSelect.value;
        if (backgroundGroup) backgroundGroup.classList.toggle('d-none', !formatSelect || formatSelect.value !== 'jpeg');
        if (qualityGroup && formatSelect) {
            qualityGroup.classList.toggle('d-none', !['jpeg', 'png', 'webp'].includes(formatSelect.value));
        }
    }
    if (formatSelect) formatSelect.addEventListener('change', updateFormat);
    updateFormat();

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!selectedFiles.length) {
            showError('Pilih setidaknya satu file gambar terlebih dahulu.');
            return;
        }
        const submitter = event.submitter;
        const operation = operationMode === 'choose'
            ? (submitter && submitter.dataset.operationChoice)
            : (operationMode === 'convert-pdf' ? 'convert' : operationMode);
        if (!['convert', 'compress'].includes(operation)) {
            showError('Pilih Konversi atau Kompres untuk melanjutkan.');
            return;
        }

        const operationInput = form.querySelector('input[name="operation"]');
        operationInput.value = operation;
        if (submitter) submitter.disabled = true;
        if (processButton) processButton.disabled = true;
        document.querySelectorAll('[data-operation-choice]').forEach(function (button) { button.disabled = true; });
        progress.hidden = false;
        formError.hidden = true;
        formError.textContent = '';
        resultsSection.hidden = true;

        try {
            const response = await fetch(shell.dataset.endpoint, { method: 'POST', body: new FormData(form) });
            let payload;
            try {
                payload = await response.json();
            } catch {
                throw new Error('Server mengirim respons yang tidak dapat dibaca. Silakan coba lagi.');
            }
            if (!response.ok && !Array.isArray(payload.results)) {
                throw new Error(payload.message || 'Permintaan tidak dapat diproses.');
            }
            lastResults = payload.results || [];
            renderResults(lastResults, operation);
            resultsSection.hidden = false;
            if (!lastResults.some(function (result) { return result.success; })) {
                showError(payload.message || 'File tidak dapat diproses.');
            }
        } catch (error) {
            showError(error.message || 'Tidak dapat menghubungi server. Periksa koneksi Anda lalu coba lagi.');
        } finally {
            progress.hidden = true;
            if (submitter) submitter.disabled = selectedFiles.length === 0;
            if (processButton) processButton.disabled = selectedFiles.length === 0;
            document.querySelectorAll('[data-operation-choice]').forEach(function (button) {
                button.disabled = selectedFiles.length === 0;
            });
        }
    });

    document.querySelectorAll('[data-reset]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const csrf = form.querySelector('input[name="csrf_token"]').value;
            await Promise.all(lastResults.filter(function (result) { return result.success; }).map(async function (result) {
                const data = new FormData();
                data.append('csrf_token', csrf);
                data.append('token', result.token);
                try {
                    await fetch(shell.dataset.endpoint.replace(/process-[^/]+\.php$/, 'delete-file.php'), { method: 'POST', body: data });
                } catch (error) {
                    showToast('Sebagian file sementara akan dihapus saat kedaluwarsa.', 'info');
                }
            }));
            lastResults = [];
            selectedFiles.forEach(function (file) {
                if (previewUrls.has(file)) URL.revokeObjectURL(previewUrls.get(file));
            });
            selectedFiles.length = 0;
            setInputFiles();
            renderFiles();
            resultsSection.hidden = true;
            formError.hidden = true;
            formError.textContent = '';
            input.value = '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    function renderResults(results, operation) {
        resultList.replaceChildren();
        results.forEach(function (result) {
            const card = document.createElement('article');
            card.className = 'result-card' + (result.success ? ' result-success' : ' result-failed');
            const heading = document.createElement('div');
            heading.className = 'result-card-heading';
            const icon = document.createElement('span');
            icon.className = 'result-status-icon';
            icon.innerHTML = result.success
                ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>'
                : '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>';
            const name = document.createElement('strong');
            name.textContent = result.name || 'File gambar';
            const badge = document.createElement('span');
            badge.className = 'result-badge';
            badge.textContent = result.success ? 'Selesai' : 'Gagal';
            heading.append(icon, name, badge);
            card.appendChild(heading);

            if (result.success) {
                if (result.targetFormat !== 'PDF') {
                    const preview = document.createElement('img');
                    preview.className = 'result-preview';
                    preview.src = shell.dataset.endpoint.replace(/process-[^/]+\.php$/, 'preview.php') + '?token=' + encodeURIComponent(result.token);
                    preview.alt = 'Pratinjau ' + result.name;
                    preview.loading = 'lazy';
                    card.appendChild(preview);
                }
                const detail = document.createElement('p');
                detail.className = 'result-detail';
                detail.textContent = result.sourceFormat + ' → ' + result.targetFormat + ' · ' + result.message;
                card.appendChild(detail);
                const sizes = document.createElement('div');
                sizes.className = 'result-sizes';
                addSize(sizes, 'Sebelum', result.originalSizeLabel);
                addSize(sizes, 'Sesudah', result.outputSizeLabel);
                if (operation === 'compress') addSize(sizes, 'Pengurangan', result.reduction + '%');
                card.appendChild(sizes);
                const download = document.createElement('a');
                download.className = 'btn btn-primary btn-sm mt-3';
                download.href = shell.dataset.endpoint.replace(/process-[^/]+\.php$/, 'download.php') + '?token=' + encodeURIComponent(result.token);
                download.setAttribute('download', result.name);
                download.innerHTML = '<i class="fa-solid fa-download" aria-hidden="true"></i> Unduh';
                card.appendChild(download);
            } else {
                const message = document.createElement('p');
                message.className = 'result-detail text-danger';
                message.textContent = result.message || 'File tidak dapat diproses.';
                card.appendChild(message);
            }
            resultList.appendChild(card);
        });
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
        formError.textContent = message;
        formError.hidden = false;
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }
});

function showToast(message) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast show';
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 3500);
}

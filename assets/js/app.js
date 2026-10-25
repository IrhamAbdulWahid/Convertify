document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            zone.classList.add('is-dragging');
        });
        zone.addEventListener('dragleave', function (event) {
            if (!zone.contains(event.relatedTarget)) zone.classList.remove('is-dragging');
        });
        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            zone.classList.remove('is-dragging');
            const input = document.getElementById('imageFiles');
            if (!input || !event.dataTransfer.files.length) return;
            const transfer = new DataTransfer();
            Array.from(input.files).concat(Array.from(event.dataTransfer.files)).forEach(function (file) {
                transfer.items.add(file);
            });
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
});

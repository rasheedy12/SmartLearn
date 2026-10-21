(() => {
    const form = document.getElementById('profilePictureForm');
    const input = document.getElementById('profileAvatar');
    const preview = document.getElementById('profileAvatarPreview');
    const saveButton = document.getElementById('saveProfileAvatar');
    const cancelButton = document.getElementById('cancelProfileAvatar');
    const error = document.getElementById('profileAvatarError');

    if (!(form instanceof HTMLFormElement)
        || !(input instanceof HTMLInputElement)
        || !(preview instanceof HTMLImageElement)
        || !(saveButton instanceof HTMLButtonElement)
        || !(cancelButton instanceof HTMLButtonElement)
        || !(error instanceof HTMLElement)
    ) {
        return;
    }

    const currentImage = preview.src;
    let previewUrl = null;
    saveButton.disabled = true;
    cancelButton.disabled = true;

    const clearPreview = () => {
        if (previewUrl !== null) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
    };

    input.addEventListener('change', () => {
        error.textContent = '';
        const file = input.files?.[0];
        if (!file) {
            return;
        }

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            error.textContent = 'Choose a JPEG, PNG, or WebP image.';
            input.value = '';
            clearPreview();
            preview.src = currentImage;
            saveButton.disabled = true;
            cancelButton.disabled = true;
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            error.textContent = 'The profile picture must be no larger than 2 MB.';
            input.value = '';
            clearPreview();
            preview.src = currentImage;
            saveButton.disabled = true;
            cancelButton.disabled = true;
            return;
        }

        clearPreview();
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        saveButton.disabled = false;
        cancelButton.disabled = false;
    });

    cancelButton.addEventListener('click', () => {
        input.value = '';
        error.textContent = '';
        clearPreview();
        preview.src = currentImage;
        saveButton.disabled = true;
        cancelButton.disabled = true;
    });

    form.addEventListener('submit', (event) => {
        if (!input.files?.length) {
            event.preventDefault();
            error.textContent = 'Choose a profile picture before saving.';
        }
    });
})();

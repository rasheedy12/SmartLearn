(() => {
    const form = document.querySelector('[data-assignment-form]');
    if (!form) {
        return;
    }

    const response = form.querySelector('[data-assignment-response]');
    const wordCount = form.querySelector('[data-word-count]');
    const draftStatus = form.querySelector('[data-draft-status]');
    const draftVersion = form.querySelector('[data-draft-version]');
    const assignmentStatus = document.querySelector('[data-assignment-status]');
    const fileInput = form.querySelector('input[type="file"]');
    const fileList = form.querySelector('[data-selected-files]');
    const dropZone = form.querySelector('[data-assignment-drop-zone]');
    const saveButton = form.querySelector('[data-save-draft]');
    const submitButton = form.querySelector('[data-submit-assignment]');
    const resubmitConfirmation = form.querySelector('#confirmResubmission');
    const maxCharacters = Number(response.maxLength);
    let editRevision = 0;
    let dirty = false;
    let requestInFlight = null;
    let saveTimer = 0;
    let submitting = false;

    resubmitConfirmation?.addEventListener('change', () => {
        resubmitConfirmation.setCustomValidity('');
    });
    saveButton?.addEventListener('click', () => {
        resubmitConfirmation?.setCustomValidity('');
    });

    const updateWordCount = () => {
        const words = response.value.trim().match(/\S+/g);
        wordCount.textContent = `${words ? words.length : 0} words · ${response.value.length.toLocaleString()} / ${maxCharacters.toLocaleString()} characters`;
    };

    const setDraftStatus = (message, failed = false) => {
        draftStatus.textContent = message;
        draftStatus.classList.toggle('text-danger', failed);
        draftStatus.classList.toggle('text-success', !failed && message === 'Draft saved');
    };

    const sendDraft = () => {
        if (requestInFlight) {
            return requestInFlight;
        }
        if (!dirty) {
            return Promise.resolve(true);
        }

        const revision = editRevision;
        const body = new URLSearchParams({
            csrf_token: form.querySelector('input[name="csrf_token"]').value,
            action: 'save_assignment_draft',
            ajax: '1',
            draft_content: response.value,
            draft_version: draftVersion.value
        });
        dirty = false;
        setDraftStatus('Saving…');

        requestInFlight = fetch(window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        }).then(async (result) => {
            let payload;
            try {
                payload = await result.json();
            } catch {
                throw new Error('The server returned an invalid draft-saving response.');
            }
            if (!result.ok || !payload.success) {
                if (payload.conflict) {
                    dirty = false;
                }
                throw new Error(payload.message || 'The draft could not be saved.');
            }
            draftVersion.value = String(payload.draft_version);
            if (payload.display_status === 'Draft' && assignmentStatus) {
                assignmentStatus.textContent = 'Draft';
                assignmentStatus.className = 'badge bg-secondary';
            }
            if (revision === editRevision) {
                setDraftStatus('Draft saved');
            } else {
                dirty = true;
            }
            return true;
        }).catch((error) => {
            setDraftStatus(error.message || 'Could not save draft. Your text is still available.', true);
            return false;
        }).finally(() => {
            requestInFlight = null;
            if (dirty) {
                window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(() => {
                    void sendDraft();
                }, 250);
            }
        });

        return requestInFlight;
    };

    const addActionAndSubmit = (action) => {
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.append(actionInput);
        HTMLFormElement.prototype.submit.call(form);
    };

    response.addEventListener('input', () => {
        editRevision += 1;
        dirty = true;
        updateWordCount();
        setDraftStatus('Unsaved changes');
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => {
            void sendDraft();
        }, 900);
    });

    response.addEventListener('blur', () => {
        window.clearTimeout(saveTimer);
        void sendDraft();
    });

    form.querySelectorAll('[data-wrap-text]').forEach((button) => {
        button.addEventListener('click', () => {
            const marker = button.dataset.wrapText;
            const start = response.selectionStart;
            const end = response.selectionEnd;
            const selected = response.value.slice(start, end);
            const replacement = `${marker}${selected || 'text'}${marker}`;
            response.setRangeText(replacement, start, end, 'select');
            response.focus();
            response.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    if (fileInput && fileList && dropZone) {
        const maxFileBytes = Number(fileInput.dataset.maxFileBytes);
        const maxTotalBytes = Number(fileInput.dataset.maxTotalBytes);
        const validateFiles = () => {
            const files = Array.from(fileInput.files);
            const totalBytes = files.reduce((total, file) => total + file.size, 0);
            let message = '';
            if (files.length > 5) {
                message = 'Attach no more than five files at a time.';
            } else if (files.some((file) => file.size > maxFileBytes)) {
                message = `Each file must be no larger than ${(maxFileBytes / 1024 / 1024).toFixed(1)} MB.`;
            } else if (totalBytes > maxTotalBytes) {
                message = `The selected files exceed the total upload limit of ${(maxTotalBytes / 1024 / 1024).toFixed(1)} MB.`;
            }
            fileInput.setCustomValidity(message);
            return message === '';
        };

        const renderFiles = () => {
            fileList.replaceChildren();
            Array.from(fileInput.files).forEach((file) => {
                const item = document.createElement('li');
                item.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                fileList.append(item);
            });
        };

        fileInput.addEventListener('change', () => {
            renderFiles();
            validateFiles();
        });
        ['dragenter', 'dragover'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropZone.classList.add('is-dragging');
            });
        });
        ['dragleave', 'drop'].forEach((eventName) => {
            dropZone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropZone.classList.remove('is-dragging');
            });
        });
        dropZone.addEventListener('drop', (event) => {
            const transfer = new DataTransfer();
            Array.from(event.dataTransfer.files).forEach((file) => transfer.items.add(file));
            fileInput.files = transfer.files;
            renderFiles();
            validateFiles();
        });
        fileInput.dataset.validate = 'true';
    }

    form.addEventListener('submit', async (event) => {
        const action = event.submitter?.value || 'submit_assignment';
        if (!['save_assignment_draft', 'submit_assignment'].includes(action)) {
            return;
        }
        if (fileInput && !fileInput.checkValidity()) {
            event.preventDefault();
            fileInput.reportValidity();
            return;
        }

        if (submitting) {
            event.preventDefault();
            return;
        }

        if (action === 'submit_assignment') {
            if (!response.value.trim() || response.value.length > maxCharacters) {
                event.preventDefault();
                response.setCustomValidity(
                    response.value.length > maxCharacters
                        ? 'Your response must be 20,000 characters or fewer.'
                        : 'Enter an assignment response before submitting.'
                );
                response.reportValidity();
                response.addEventListener('input', () => response.setCustomValidity(''), { once: true });
                return;
            }
            if (resubmitConfirmation && !resubmitConfirmation.checked) {
                event.preventDefault();
                resubmitConfirmation.setCustomValidity(
                    'Confirm that you understand resubmitting removes the existing grade and feedback.'
                );
                resubmitConfirmation.focus();
                resubmitConfirmation.reportValidity();
                return;
            }
        }

        event.preventDefault();
        submitting = true;
        saveButton.disabled = true;
        submitButton.disabled = true;
        form.setAttribute('aria-busy', 'true');
        setDraftStatus(action === 'save_assignment_draft' ? 'Saving draft…' : 'Submitting assignment…');
        const pendingRequest = requestInFlight;
        if (pendingRequest) {
            await pendingRequest;
        }
        window.clearTimeout(saveTimer);
        dirty = false;

        addActionAndSubmit(action);
    });

    updateWordCount();

    const panel = document.querySelector('[data-assignment-deadline]');
    const countdown = panel?.querySelector('[data-deadline-countdown]');
    if (panel && countdown) {
        const deadline = new Date(panel.dataset.assignmentDeadline);
        const updateCountdown = () => {
            const seconds = Math.floor((deadline.getTime() - Date.now()) / 1000);
            if (seconds <= 0) {
                countdown.textContent = 'Deadline passed. Late submissions are accepted and will be marked Late.';
                return;
            }
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            countdown.textContent = `Time remaining: ${days ? `${days}d ` : ''}${hours}h ${minutes}m`;
        };
        updateCountdown();
        window.setInterval(updateCountdown, 60000);
    }
})();

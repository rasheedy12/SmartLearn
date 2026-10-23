(() => {
    const refreshDelay = 1500;
    const submitterSelector = 'button[type="submit"], button:not([type]), input[type="submit"]';

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
            return;
        }
        if (form.dataset.noActionFeedback === 'true') {
            return;
        }
        if (event.defaultPrevented) {
            return;
        }

        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        const status = document.createElement('span');
        status.className = 'visually-hidden';
        status.setAttribute('role', 'status');
        status.textContent = 'Processing…';
        form.append(status);

        form.querySelectorAll(submitterSelector).forEach((button) => {
            if (button === submitter) {
                button.classList.add('action-processing');
                button.dataset.actionFeedbackHadAriaLabel = button.hasAttribute('aria-label') ? 'true' : 'false';
                if (button.dataset.actionFeedbackHadAriaLabel === 'true') {
                    button.dataset.actionFeedbackOriginalAriaLabel = button.getAttribute('aria-label');
                }
                button.setAttribute('aria-label', 'Processing…');
                return;
            }
            if (!button.disabled) {
                button.dataset.actionFeedbackDisabled = 'true';
                button.disabled = true;
            }
        });

        if (submitter instanceof HTMLInputElement) {
            const visibleStatus = document.createElement('span');
            visibleStatus.className = 'action-processing-label';
            visibleStatus.textContent = 'Processing…';
            submitter.insertAdjacentElement('afterend', visibleStatus);
        }
    });

    document.querySelectorAll('[data-success-refresh="true"]').forEach((alert) => {
        if (alert.dataset.refreshScheduled === 'true') {
            return;
        }
        alert.dataset.refreshScheduled = 'true';

        const url = new URL(window.location.href);
        let cleanUrl = false;
        ['completed', 'enrolled', 'payment_success', 'success'].forEach((parameter) => {
            if (url.searchParams.has(parameter)) {
                url.searchParams.delete(parameter);
                cleanUrl = true;
            }
        });
        if (cleanUrl) {
            window.history.replaceState({}, '', url);
        }

        window.setTimeout(() => {
            window.location.reload();
        }, refreshDelay);
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            form.querySelectorAll(submitterSelector).forEach((button) => {
                if (button.dataset.actionFeedbackDisabled === 'true') {
                    button.disabled = false;
                    delete button.dataset.actionFeedbackDisabled;
                }
                button.classList.remove('action-processing');
                if (button.dataset.actionFeedbackHadAriaLabel === 'true') {
                    button.setAttribute('aria-label', button.dataset.actionFeedbackOriginalAriaLabel);
                } else {
                    button.removeAttribute('aria-label');
                }
                delete button.dataset.actionFeedbackHadAriaLabel;
                delete button.dataset.actionFeedbackOriginalAriaLabel;
            });
            form.querySelectorAll('.action-processing-label, [role="status"]').forEach((status) => status.remove());
        });
    });
})();

(() => {
    const lessonForm = document.querySelector('form input[name="action"][value="add_lesson"]')?.closest('form');
    const lessonType = lessonForm?.querySelector('select[name="content_type"]');
    const deadlineField = lessonForm?.querySelector('[data-assignment-deadline]');
    if (lessonType && deadlineField) {
        const updateDeadlineVisibility = () => {
            deadlineField.hidden = lessonType.value !== 'assignment';
        };
        lessonType.addEventListener('change', updateDeadlineVisibility);
        updateDeadlineVisibility();
    }

    document.querySelectorAll('form').forEach((form) => {
        const typeSelect = form.querySelector('select[name="question_type"]');
        if (!(typeSelect instanceof HTMLSelectElement)) {
            return;
        }

        const rows = Array.from(form.querySelectorAll('[data-quiz-option-row]'));
        const updateOptions = () => {
            const isTrueFalse = typeSelect.value === 'true_false';
            rows.forEach((row, index) => {
                const visible = !isTrueFalse || index < 2;
                const label = row.querySelector('[data-quiz-option-label]');
                const input = row.querySelector('[data-quiz-option-input]');
                const correct = row.querySelector('input[name="correct_options[]"]');
                row.classList.toggle('d-none', !visible);
                if (input instanceof HTMLInputElement) {
                    input.required = visible && !isTrueFalse;
                    if (isTrueFalse) {
                        input.value = '';
                    }
                }
                if (label instanceof HTMLLabelElement) {
                    label.textContent = isTrueFalse ? (index === 0 ? 'True' : 'False') : `Option ${index + 1}`;
                }
                if (correct instanceof HTMLInputElement) {
                    correct.disabled = !visible;
                    if (!visible) {
                        correct.checked = false;
                    }
                }
            });
        };

        typeSelect.addEventListener('change', updateOptions);
        updateOptions();
    });
})();

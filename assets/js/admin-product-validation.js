(function () {
    const validationConfig = window.wicketFinanceProductValidation || {};
    const fieldSelector = 'input[data-wicket-finance-date-role][data-wicket-finance-date-group]';
    const errorClass = 'wicket-finance-field-has-error';
    const errorMessageClass = 'wicket-finance-field-error';
    const noticeClass = 'wicket-finance-product-notice';
    const defaultMissingStartMessage = 'Deferral Start Date is required when Deferral End Date is set.';
    const defaultInvalidRangeMessage = 'Deferral End Date must be the same as or later than Deferral Start Date.';
    const defaultNoticeMessage = 'Some finance deferral dates need attention.';

    function getFieldGroups() {
        const groups = new Map();

        document.querySelectorAll(fieldSelector).forEach((field) => {
            const group = field.dataset.wicketFinanceDateGroup;
            const role = field.dataset.wicketFinanceDateRole;

            if (!groups.has(group)) {
                groups.set(group, {});
            }

            groups.get(group)[role] = field;
        });

        return Array.from(groups.values()).filter((group) => group.start && group.end);
    }

    function getFieldWrapper(field) {
        return field ? field.closest('.form-field, .form-row') : null;
    }

    function getErrorId(field) {
        return ['wicket-finance-date-error', field.id || field.name || 'field']
            .join('-')
            .replace(/[^A-Za-z0-9_-]+/g, '-');
    }

    function createOrUpdateErrorMessage(field, message) {
        const wrapper = getFieldWrapper(field);
        if (!wrapper) {
            return null;
        }

        const errorId = getErrorId(field);
        let errorElement = wrapper.querySelector('#' + errorId);

        if (!errorElement) {
            errorElement = document.createElement('p');
            errorElement.id = errorId;
            errorElement.className = errorMessageClass;
            errorElement.setAttribute('role', 'alert');
            wrapper.appendChild(errorElement);
        }

        errorElement.textContent = message;

        return errorElement;
    }

    function clearErrorMessage(field) {
        const wrapper = getFieldWrapper(field);
        if (!wrapper) {
            return;
        }

        const errorElement = wrapper.querySelector('#' + getErrorId(field));
        if (errorElement) {
            errorElement.remove();
        }
    }

    function setGroupValidity(group) {
        const startValue = group.start.value.trim();
        const endValue = group.end.value.trim();
        const startWrapper = getFieldWrapper(group.start);
        const endWrapper = getFieldWrapper(group.end);
        const missingStartMessage = validationConfig.missingStartMessage || defaultMissingStartMessage;
        const invalidRangeMessage = validationConfig.invalidRangeMessage || defaultInvalidRangeMessage;

        let invalidField = null;
        let errorMessage = '';

        if (endValue !== '' && startValue === '') {
            invalidField = group.start;
            errorMessage = missingStartMessage;
        } else if (startValue !== '' && endValue !== '' && endValue < startValue) {
            invalidField = group.end;
            errorMessage = invalidRangeMessage;
        }

        group.start.setCustomValidity('');
        group.end.setCustomValidity('');
        group.start.removeAttribute('aria-invalid');
        group.end.removeAttribute('aria-invalid');
        group.start.removeAttribute('aria-describedby');
        group.end.removeAttribute('aria-describedby');

        if (startWrapper) {
            startWrapper.classList.remove(errorClass);
        }

        if (endWrapper) {
            endWrapper.classList.remove(errorClass);
        }

        clearErrorMessage(group.start);
        clearErrorMessage(group.end);

        if (!invalidField) {
            return false;
        }

        if (startWrapper) {
            startWrapper.classList.add(errorClass);
        }

        if (endWrapper) {
            endWrapper.classList.add(errorClass);
        }

        invalidField.setAttribute('aria-invalid', 'true');
        invalidField.setCustomValidity(errorMessage);

        const errorElement = createOrUpdateErrorMessage(invalidField, errorMessage);
        if (errorElement) {
            invalidField.setAttribute('aria-describedby', errorElement.id);
        }

        return true;
    }

    function getNoticeContainer() {
        return document.querySelector('.wrap') || document.querySelector('#wpbody-content');
    }

    function renderFormNotice(message) {
        const container = getNoticeContainer();
        if (!container) {
            return null;
        }

        let notice = container.querySelector('.' + noticeClass);

        if (!notice) {
            notice = document.createElement('div');
            notice.className = 'notice notice-error ' + noticeClass;
            notice.innerHTML = '<p></p>';

            const anchor = container.querySelector('h1, .wp-heading-inline, #woocommerce-product-data');
            if (anchor && anchor.parentNode === container) {
                anchor.insertAdjacentElement('afterend', notice);
            } else {
                container.prepend(notice);
            }
        }

        notice.querySelector('p').textContent = message;

        return notice;
    }

    function clearFormNotice() {
        const container = getNoticeContainer();
        if (!container) {
            return;
        }

        const notice = container.querySelector('.' + noticeClass);
        if (notice) {
            notice.remove();
        }
    }

    function validateAllGroups() {
        const groups = getFieldGroups();
        let firstInvalidGroup = null;

        groups.forEach((group) => {
            const isInvalid = setGroupValidity(group);

            if (isInvalid && !firstInvalidGroup) {
                firstInvalidGroup = group;
            }
        });

        if (firstInvalidGroup) {
            renderFormNotice(validationConfig.noticeMessage || defaultNoticeMessage);
        } else {
            clearFormNotice();
        }

        return firstInvalidGroup;
    }

    function handleFieldInput(event) {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.matches(fieldSelector)) {
            return;
        }

        const group = getFieldGroups().find((fieldGroup) => (
            fieldGroup.start === target || fieldGroup.end === target
        ));

        if (!group) {
            return;
        }

        setGroupValidity(group);

        if (!getFieldGroups().some((fieldGroup) => fieldGroup.end.value.trim() !== '' && fieldGroup.start.value.trim() === '')) {
            clearFormNotice();
        }
    }

    function handleFormSubmit(event) {
        const firstInvalidGroup = validateAllGroups();

        if (!firstInvalidGroup) {
            return;
        }

        event.preventDefault();
        if (firstInvalidGroup.end.value.trim() !== '' && firstInvalidGroup.start.value.trim() === '') {
            firstInvalidGroup.start.focus();
            firstInvalidGroup.start.reportValidity();

            return;
        }

        firstInvalidGroup.end.focus();
        firstInvalidGroup.end.reportValidity();
    }

    function isSaveActionTrigger(target) {
        if (!(target instanceof Element)) {
            return false;
        }

        return Boolean(
            target.closest(
                '#publish, #save-post, button[type="submit"], input[type="submit"], .editor-post-publish-button, .editor-post-save-draft'
            )
        );
    }

    function handleSaveClick(event) {
        if (!isSaveActionTrigger(event.target)) {
            return;
        }

        const firstInvalidGroup = validateAllGroups();

        if (!firstInvalidGroup) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        if (firstInvalidGroup.end.value.trim() !== '' && firstInvalidGroup.start.value.trim() === '') {
            firstInvalidGroup.start.focus();
            firstInvalidGroup.start.reportValidity();

            return;
        }

        firstInvalidGroup.end.focus();
        firstInvalidGroup.end.reportValidity();
    }

    function initValidation() {
        const form = document.querySelector('form#post');
        const hasFinanceFields = getFieldGroups().length > 0;

        if (!form && !hasFinanceFields) {
            return;
        }

        validateAllGroups();

        document.addEventListener('input', handleFieldInput);
        document.addEventListener('change', handleFieldInput);

        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }

        document.addEventListener('click', handleSaveClick, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initValidation);
    } else {
        initValidation();
    }
})();

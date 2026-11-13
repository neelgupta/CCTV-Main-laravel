@once
    <style>
        .generator-field-error {
            border-color: rgb(239 68 68) !important;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.18) !important;
        }

        label.generator-error-label {
            display: block;
            margin-top: 0.375rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: rgb(220 38 38);
            font-weight: 500;
        }
    </style>

    <script>
        window.generatorValidation = window.generatorValidation || {
            ensure(form, options = {}) {
                if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.validate !== 'function') {
                    return null;
                }

                const $form = window.jQuery(form);
                let validator = $form.data('validator');

                if (validator) {
                    return validator;
                }

                validator = $form.validate({
                    ignore: options.ignore ?? ':hidden',
                    errorElement: 'label',
                    errorClass: 'generator-error-label',
                    focusInvalid: false,
                    onkeyup(element) {
                        this.element(element);
                    },
                    onfocusout(element) {
                        this.element(element);
                    },
                    highlight(element) {
                        window.jQuery(element).addClass('generator-field-error').attr('aria-invalid', 'true');
                    },
                    unhighlight(element) {
                        window.jQuery(element).removeClass('generator-field-error').removeAttr('aria-invalid');
                    },
                    errorPlacement(error, element) {
                        const $element = window.jQuery(element);
                        const $tableCell = $element.closest('td');
                        const $wrapper = $element.parent();
                        const $target = $tableCell.length ? $tableCell : $wrapper;
                        error.appendTo($target);
                    },
                    invalidHandler(event, currentValidator) {
                        window.generatorValidation.focusFirstInvalid(currentValidator);
                    }
                });

                return validator;
            },

            applyRules(form, definitions = []) {
                const validator = this.ensure(form);

                if (!validator) {
                    return null;
                }

                const $form = window.jQuery(form);

                definitions.forEach((definition) => {
                    const selector = definition?.selector;
                    const rules = definition?.rules || {};

                    if (!selector) {
                        return;
                    }

                    $form.find(selector).each(function () {
                        const $field = window.jQuery(this);

                        try {
                            $field.rules('remove');
                        } catch (error) {
                            // Ignore fields without existing rules.
                        }

                        $field.rules('add', rules);
                    });
                });

                return validator;
            },

            validate(form, definitions = [], options = {}) {
                const validator = this.ensure(form, options);

                if (!validator) {
                    return true;
                }

                this.applyRules(form, definitions);

                const isValid = validator.form();

                if (!isValid) {
                    this.focusFirstInvalid(validator);
                }

                return isValid;
            },

            focusFirstInvalid(validator) {
                const firstInvalid = validator?.errorList?.[0]?.element;

                if (!firstInvalid) {
                    return;
                }

                firstInvalid.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                if (typeof firstInvalid.focus === 'function') {
                    firstInvalid.focus({
                        preventScroll: true
                    });
                }
            }
        };
    </script>
@endonce

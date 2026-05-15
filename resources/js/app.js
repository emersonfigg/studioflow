import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('companyBranding', (config = {}) => {
        const previewUrl = config.previewUrl ?? '';
        const csrfToken = config.csrfToken ?? '';
        const defaults = config.defaults ?? {
            primary: '#d4af37',
            secondary: '#223d69',
            accent: '#132746',
        };

        const isBrokenObjectValue = (value) => /^\[object\s+HTML[\w-]*Element\]$/i.test(String(value || '').trim())
            || /^\[object\s+[\w-]+\]$/i.test(String(value || '').trim());

        return {
            brandPrimary: config.brandPrimary ?? '',
            brandSecondary: config.brandSecondary ?? '',
            brandAccent: config.brandAccent ?? '',
            brandEnabled: Boolean(config.brandEnabled ?? true),
            previewStyleVars: config.previewStyleVars ?? {},
            _previewTimer: null,

            init() {
                this.schedulePreview();
            },

            formElement() {
                return document.getElementById('company-edit-form');
            },

            fieldValue(fieldName, fallback = '') {
                void this.previewStyleVars;

                const form = this.formElement();

                if (! form) {
                    return fallback;
                }

                const field = form.elements.namedItem(fieldName);

                if (! field) {
                    return fallback;
                }

                const rawValue = field instanceof RadioNodeList ? field.value : field.value;
                const value = String(rawValue ?? '').trim();

                if (value === '' || isBrokenObjectValue(value)) {
                    return fallback;
                }

                return value;
            },

            refreshPreviewContent() {
                this.previewStyleVars = { ...this.previewStyleVars };
            },

            updateFileLabel(event, targetId) {
                const target = document.getElementById(targetId);

                if (! target) {
                    return;
                }

                const fileName = event?.target?.files?.[0]?.name ?? '';
                target.textContent = fileName ? `Arquivo selecionado: ${fileName}` : '';
                target.classList.toggle('hidden', fileName === '');
            },

            schedulePreview() {
                clearTimeout(this._previewTimer);
                this._previewTimer = setTimeout(() => this.fetchPreviewStyle(), 250);
            },

            async fetchPreviewStyle() {
                if (! previewUrl || ! csrfToken) {
                    return;
                }

                try {
                    const response = await fetch(previewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            primary_color: this.brandEnabled ? (String(this.brandPrimary || '').trim() || null) : null,
                            secondary_color: this.brandEnabled ? (String(this.brandSecondary || '').trim() || null) : null,
                            accent_color: this.brandEnabled ? (String(this.brandAccent || '').trim() || null) : null,
                            brand_enabled: this.brandEnabled,
                        }),
                    });

                    const data = await response.json();

                    if (data?.vars) {
                        this.previewStyleVars = data.vars;
                    }
                } catch (error) {
                    // Preview is best-effort only.
                }
            },

            restoreStudioflowTheme() {
                this.brandPrimary = '';
                this.brandSecondary = '';
                this.brandAccent = '';
                this.brandEnabled = false;
                this.schedulePreview();
            },

            hexOrFallback(value, fallback) {
                const normalized = String(value || '').trim();

                return /^#[0-9A-Fa-f]{6}$/.test(normalized)
                    ? normalized.toUpperCase()
                    : fallback;
            },

            pickerHex(value, fallback) {
                return this.hexOrFallback(value, fallback).toLowerCase();
            },

            onPickerInput(which, event) {
                const value = String(event?.target?.value || '').toUpperCase();

                if (! /^#[0-9A-F]{6}$/.test(value)) {
                    return;
                }

                if (which === 'primary') {
                    this.brandPrimary = value;
                } else if (which === 'secondary') {
                    this.brandSecondary = value;
                } else if (which === 'accent') {
                    this.brandAccent = value;
                }

                this.schedulePreview();
            },

            onHexInput(which, event) {
                const value = String(event?.target?.value || '').trim().toUpperCase();

                if (value !== '' && (value.length > 7 || /^#[0-9A-F]*$/.test(value) === false)) {
                    return;
                }

                if (which === 'primary') {
                    this.brandPrimary = value;
                } else if (which === 'secondary') {
                    this.brandSecondary = value;
                } else if (which === 'accent') {
                    this.brandAccent = value;
                }

                this.schedulePreview();
            },

            resetColor(which) {
                if (which === 'primary' || which === 'all') {
                    this.brandPrimary = '';
                }

                if (which === 'secondary' || which === 'all') {
                    this.brandSecondary = '';
                }

                if (which === 'accent' || which === 'all') {
                    this.brandAccent = '';
                }

                this.schedulePreview();
            },

            applyPreset(preset) {
                this.brandPrimary = preset.primary;
                this.brandSecondary = preset.secondary;
                this.brandAccent = preset.accent;
                this.brandEnabled = true;
                this.schedulePreview();
            },

            presetMatches(preset) {
                if (! this.brandEnabled) {
                    return false;
                }

                return this.hexOrFallback(this.brandPrimary, defaults.primary) === preset.primary.toUpperCase()
                    && this.hexOrFallback(this.brandSecondary, defaults.secondary) === preset.secondary.toUpperCase()
                    && this.hexOrFallback(this.brandAccent, defaults.accent) === preset.accent.toUpperCase();
            },
        };
    });
});

Alpine.start();

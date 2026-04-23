(function () {
  const serverTheme = window.__adminAppearanceTheme && typeof window.__adminAppearanceTheme === 'object'
    ? window.__adminAppearanceTheme
    : { enabled: false, mode: 'light', tokens: {} };

  const root = document.documentElement;

  function hexToRgb(hex) {
    const normalized = String(hex || '').trim();
    const short = /^#([a-f\d])([a-f\d])([a-f\d])$/i.exec(normalized);

    if (short) {
      return [
        parseInt(short[1] + short[1], 16),
        parseInt(short[2] + short[2], 16),
        parseInt(short[3] + short[3], 16)
      ].join(', ');
    }

    const full = /^#([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(normalized);

    if (!full) {
      return '';
    }

    return [
      parseInt(full[1], 16),
      parseInt(full[2], 16),
      parseInt(full[3], 16)
    ].join(', ');
  }

  function isHexColor(value) {
    return /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(value || '').trim());
  }

  function setRootVar(name, value) {
    if (value === undefined || value === null || value === '') {
      return;
    }

    root.style.setProperty(name, String(value));
  }

  function applyTheme(theme) {
    const tokens = theme && theme.tokens ? theme.tokens : {};
    const mode = tokens.mode === 'dark' || theme.mode === 'dark' ? 'dark' : 'light';
    const primaryRgb = hexToRgb(tokens.primary);
    const borderRgb = hexToRgb(tokens.border_color);
    const shadowRgb = hexToRgb(tokens.text_primary);
    const shadowAlpha = mode === 'dark' ? '0.28' : '0.08';

    root.setAttribute('data-admin-theme', mode);
    root.setAttribute('data-bs-theme', mode);

    setRootVar('--bs-body-font-family', tokens.body_font);
    setRootVar('--heading-font-family', tokens.heading_font);
    setRootVar('--bs-body-bg', tokens.bg_canvas);
    setRootVar('--bs-body-color', tokens.text_primary);
    setRootVar('--bs-emphasis-color', tokens.text_primary);
    setRootVar('--bs-secondary-color', tokens.text_secondary);
    setRootVar('--bs-tertiary-color', tokens.text_muted);
    setRootVar('--bs-secondary-bg', tokens.bg_surface_alt);
    setRootVar('--bs-tertiary-bg', tokens.bg_surface_alt);
    setRootVar('--bs-border-color', tokens.border_color);
    setRootVar('--bs-border-radius-sm', tokens.radius_sm);
    setRootVar('--bs-border-radius', tokens.radius_md);
    setRootVar('--bs-border-radius-lg', tokens.radius_lg);
    setRootVar('--bs-primary', tokens.primary);
    setRootVar('--bs-secondary', tokens.secondary);
    setRootVar('--bs-success', tokens.success);
    setRootVar('--bs-info', tokens.info);
    setRootVar('--bs-warning', tokens.warning);
    setRootVar('--bs-danger', tokens.danger);
    setRootVar('--bs-link-color', tokens.link);
    setRootVar('--bs-link-hover-color', tokens.link_hover);
    setRootVar('--bs-card-bg', tokens.card_bg);
    setRootVar('--bs-card-cap-bg', tokens.card_header_bg);
    setRootVar('--bs-card-cap-color', tokens.card_header_text);
    setRootVar('--lte-sidebar-width', tokens.sidebar_width);
    setRootVar('--lte-sidebar-bg', tokens.sidebar_bg);
    setRootVar('--lte-sidebar-color', tokens.sidebar_text);
    setRootVar('--lte-sidebar-hover-color', tokens.sidebar_hover_text);
    setRootVar('--lte-sidebar-active-color', tokens.sidebar_active_text);
    setRootVar('--lte-sidebar-active-bg', tokens.sidebar_active_bg);
    setRootVar('--admin-primary-rgb', primaryRgb);
    setRootVar('--admin-border-rgb', borderRgb);
    setRootVar('--admin-shadow-rgb', shadowRgb);
    setRootVar('--admin-shadow-alpha', shadowAlpha);
    setRootVar('--admin-bg-surface', tokens.bg_surface);
    setRootVar('--admin-bg-surface-alt', tokens.bg_surface_alt);
    setRootVar('--admin-header-bg', tokens.header_bg);
    setRootVar('--admin-header-text', tokens.header_text);
    setRootVar('--admin-header-border', tokens.header_border);
    setRootVar('--admin-sidebar-muted', tokens.sidebar_muted);
    setRootVar('--admin-sidebar-hover-bg', tokens.sidebar_hover_bg);
    setRootVar('--admin-card-border', tokens.card_border);
    setRootVar('--admin-input-bg', tokens.input_bg);
    setRootVar('--admin-input-text', tokens.input_text);
    setRootVar('--admin-input-border', tokens.input_border);
    setRootVar('--admin-login-bg', tokens.login_bg);
    setRootVar('--admin-login-card-bg', tokens.login_card_bg);
    setRootVar('--admin-login-card-border', tokens.login_card_border);
    setRootVar('--admin-btn-text-transform', String(tokens.btn_uppercase) === '1' ? 'uppercase' : 'none');
  }

  function findForm() {
    return document.querySelector('[data-admin-appearance-form]');
  }

  function readFormTokens(form) {
    const tokens = {};

    form.querySelectorAll('[data-admin-appearance-token]').forEach((element) => {
      const key = element.getAttribute('data-admin-appearance-token');

      if (!key) {
        return;
      }

      tokens[key] = element.value;
    });

    return tokens;
  }

  function syncColorPair(form, key, value) {
    const color = form.querySelector(`[data-admin-appearance-color="${key}"]`);
    const text = form.querySelector(`[data-admin-appearance-token="${key}"]`);
    const swatch = form.querySelector(`[data-admin-appearance-swatch="${key}"]`);

    if (text && text.value !== value) {
      text.value = value;
    }

    if (color && isHexColor(value) && color.value !== value) {
      color.value = value;
    }

    if (swatch) {
      swatch.style.background = value || 'transparent';
    }
  }

  function fillFormTokens(form, tokens) {
    Object.entries(tokens || {}).forEach(([key, value]) => {
      const field = form.querySelector(`[data-admin-appearance-token="${key}"]`);

      if (!field) {
        return;
      }

      field.value = value;
      syncColorPair(form, key, value);
    });
  }

  function parseJsonAttribute(form, attribute) {
    try {
      return JSON.parse(form.getAttribute(attribute) || '{}');
    } catch (error) {
      return {};
    }
  }

  function previewForm(form) {
    applyTheme({
      enabled: true,
      tokens: readFormTokens(form)
    });
  }

  function bindColorPairs(form) {
    form.querySelectorAll('[data-admin-appearance-color]').forEach((colorInput) => {
      colorInput.addEventListener('input', () => {
        const key = colorInput.getAttribute('data-admin-appearance-color');

        if (!key) {
          return;
        }

        syncColorPair(form, key, colorInput.value);
        previewForm(form);
      });
    });

    form.querySelectorAll('[data-admin-appearance-token]').forEach((field) => {
      field.addEventListener('input', () => {
        const key = field.getAttribute('data-admin-appearance-token');

        if (!key) {
          return;
        }

        syncColorPair(form, key, field.value);
        previewForm(form);
      });

      field.addEventListener('change', () => {
        previewForm(form);
      });
    });
  }

  function bindPresetActions(form) {
    const presets = parseJsonAttribute(form, 'data-admin-appearance-presets');
    const saved = parseJsonAttribute(form, 'data-admin-appearance-saved');
    const presetSelect = form.querySelector('[data-admin-appearance-preset]');
    const applyPresetButton = form.querySelector('[data-admin-appearance-action="apply-preset"]');
    const resetPreviewButton = form.querySelector('[data-admin-appearance-action="reset-preview"]');

    if (applyPresetButton && presetSelect) {
      applyPresetButton.addEventListener('click', () => {
        const presetKey = presetSelect.value;

        if (!presetKey || !presets[presetKey]) {
          return;
        }

        fillFormTokens(form, presets[presetKey]);
        previewForm(form);
      });
    }

    if (resetPreviewButton) {
      resetPreviewButton.addEventListener('click', () => {
        if (presetSelect && saved.preset) {
          presetSelect.value = saved.preset;
        }

        if (saved.tokens) {
          fillFormTokens(form, saved.tokens);
          previewForm(form);
          return;
        }

        applyTheme(serverTheme);
      });
    }
  }

  function initAdminAppearanceForm(form) {
    bindColorPairs(form);
    bindPresetActions(form);

    form.querySelectorAll('[data-admin-appearance-color]').forEach((colorInput) => {
      const key = colorInput.getAttribute('data-admin-appearance-color');
      const textInput = form.querySelector(`[data-admin-appearance-token="${key}"]`);

      if (textInput && isHexColor(textInput.value)) {
        colorInput.value = textInput.value;
      }
    });
  }

  window.applyAdminAppearancePreview = function (tokens) {
    applyTheme({ enabled: true, tokens: tokens || {} });
  };

  window.resetAdminAppearancePreview = function () {
    const form = findForm();

    if (form) {
      const saved = parseJsonAttribute(form, 'data-admin-appearance-saved');

      if (saved.tokens) {
        fillFormTokens(form, saved.tokens);
        applyTheme({ enabled: true, tokens: saved.tokens });
        return;
      }
    }

    if (serverTheme.enabled) {
      applyTheme(serverTheme);
      return;
    }

    root.removeAttribute('data-admin-theme');
    root.removeAttribute('data-bs-theme');
  };

  const form = findForm();

  if (form) {
    initAdminAppearanceForm(form);
    return;
  }

  if (serverTheme.enabled && serverTheme.tokens) {
    applyTheme(serverTheme);
  }
})();
